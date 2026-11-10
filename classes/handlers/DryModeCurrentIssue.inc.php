<?php

/**
 * @file plugins/importexport/csv/classes/handlers/DryModeCurrentIssue.inc.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DryModeCurrentIssue
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Predicts which issue would be current after a dry-run import
 */

namespace PKP\Plugins\ImportExport\CSV\Classes\Handlers;

import('classes.issue.Issue');
import('lib.pkp.classes.core.Core');
import('plugins.importexport.csv.classes.processors.IssueProcessor');

use PKP\Plugins\ImportExport\CSV\Classes\CachedAttributes\CachedDaos;
use PKP\Plugins\ImportExport\CSV\Classes\CachedAttributes\CachedEntities;
use PKP\Plugins\ImportExport\CSV\Classes\Processors\IssueProcessor;

class DryModeCurrentIssue
{
    /** @var array<int, string> Journal id => path seen while reading the CSV */
    private $_seenJournals = [];

    /**
     * Issues a real import would create or update, kept so dry-mode can name
     * the current issue after the transaction is rolled back.
     *
     * @var array<string, array>
     */
    private $_importedIssueSnapshots = [];

    /**
     * @param int $journalId
     * @param string $journalPath
     */
    public function noteJournal($journalId, $journalPath)
    {
        $this->_seenJournals[(int) $journalId] = $journalPath;
    }

    /**
     * Keep the fields needed to decide the current issue after this file's
     * dry-run transaction is rolled back.
     *
     * @param \Issue $issue
     * @param int $journalId
     * @param bool $createdByImport
     */
    public function remember($issue, $journalId, $createdByImport)
    {
        $volume = $this->_issueField($issue->getVolume());
        $number = $this->_issueField($issue->getNumber());
        $year = $this->_issueField($issue->getYear());
        $title = $this->_issueField(IssueProcessor::issueTitle($issue));
        $identity = $this->_issueIdentity($journalId, $volume, $number, $year, $title);

        $issueDate = $this->_issueField($issue->getDatePublished());
        $articleDate = '';
        if ($issueDate === '') {
            // 3.3 stores the publication's issue in publication_settings, not on publications.issue_id.
            $result = CachedDaos::getIssueDao()->retrieve(
                'SELECT MAX(p.date_published) AS max_date
                    FROM publications p
                    INNER JOIN publication_settings ps ON (ps.publication_id = p.publication_id)
                    WHERE ps.setting_name = ? AND ps.setting_value = ? AND p.status = ?',
                ['issueId', (string) $issue->getId(), STATUS_PUBLISHED]
            );
            $row = $result->current();
            $articleDate = $this->_issueField($row ? $row->max_date : null);
        }

        $snapshot = isset($this->_importedIssueSnapshots[$identity])
            ? $this->_importedIssueSnapshots[$identity]
            : [
                'journalId' => (int) $journalId,
                'issueId' => (int) $issue->getId(),
                'createdByImport' => (bool) $createdByImport,
                'volume' => $volume,
                'number' => $number,
                'year' => $year,
                'title' => $title,
                'locale' => $this->_issueField($issue->getData('locale')),
                'issueDatePublished' => '',
                'articleDatePublished' => '',
                'published' => false,
            ];

        // A later row can see an issue created earlier in this import. That
        // issue is still coming from the CSV, so keep the original flag.
        if ($createdByImport) {
            $snapshot['createdByImport'] = true;
        } elseif (!$snapshot['createdByImport']) {
            $snapshot['issueId'] = (int) $issue->getId();
        }

        if ($issue->getPublished()) {
            $snapshot['published'] = true;
        }

        if ($issueDate !== '') {
            $snapshot['issueDatePublished'] = $issueDate;
        } elseif ($articleDate !== '' && ($snapshot['articleDatePublished'] === '' || strcmp($articleDate, $snapshot['articleDatePublished']) > 0)) {
            $snapshot['articleDatePublished'] = $articleDate;
        }

        if ($snapshot['title'] === '' && $title !== '') {
            $snapshot['title'] = $title;
            $snapshot['locale'] = $this->_issueField($issue->getData('locale'));
        }

        $this->_importedIssueSnapshots[$identity] = $snapshot;
    }

    /**
     * @param string|null $currentJournalPath
     *
     * @return array
     */
    public function reports($currentJournalPath)
    {
        $journals = $this->_seenJournals;
        if ($journals === [] && $currentJournalPath !== null) {
            $journal = CachedEntities::getCachedJournal($currentJournalPath);
            if ($journal !== null) {
                $journals[$journal->getId()] = $journal->getPath();
            }
        }

        $snapshotsByJournal = [];
        foreach ($this->_importedIssueSnapshots as $snapshot) {
            $snapshotsByJournal[$snapshot['journalId']][] = $snapshot;
        }

        $reports = [];
        $issueDao = CachedDaos::getIssueDao();
        foreach ($journals as $journalId => $journalPath) {
            $snapshots = isset($snapshotsByJournal[$journalId]) ? $snapshotsByJournal[$journalId] : [];
            $existingCurrent = $issueDao->getCurrent((int) $journalId);
            $selected = $snapshots === []
                ? $existingCurrent
                : $this->_selectCurrentIssueForJournal((int) $journalId, $snapshots, $existingCurrent);

            $source = null;
            $identification = null;
            if ($selected !== null) {
                $identification = IssueProcessor::describeIssue($selected);
                $source = $selected->getId() < 0 ? 'csv' : 'journal';
                $message = __('plugins.importexport.csv.dryModeCurrentIssue', [
                    'journalPath' => $journalPath,
                    'identification' => $identification,
                    'source' => $source === 'csv'
                        ? __('plugins.importexport.csv.dryModeCurrentIssueSource.csv')
                        : __('plugins.importexport.csv.dryModeCurrentIssueSource.journal'),
                ]);
            } else {
                $message = __('plugins.importexport.csv.dryModeCurrentIssueNone', [
                    'journalPath' => $journalPath,
                ]);
            }

            $reports[] = [
                'journalPath' => $journalPath,
                'identification' => $identification,
                'source' => $source,
                'message' => $message,
            ];
        }

        usort($reports, function ($a, $b) {
            return strcmp($a['journalPath'], $b['journalPath']);
        });

        return $reports;
    }

    /**
     * @param int $journalId
     * @param array $snapshots
     * @param \Issue|null $existingCurrent
     *
     * @return \Issue|null
     */
    private function _selectCurrentIssueForJournal($journalId, array $snapshots, $existingCurrent)
    {
        $publishedIssues = CachedDaos::getIssueDao()->getPublishedIssues($journalId);
        $existing = $publishedIssues->toArray();

        $existingById = [];
        $existingByIdentity = [];
        foreach ($existing as $issue) {
            $existingById[$issue->getId()] = $issue;
            $existingByIdentity[$this->_issueIdentity(
                $journalId,
                $this->_issueField($issue->getVolume()),
                $this->_issueField($issue->getNumber()),
                $this->_issueField($issue->getYear()),
                $this->_issueField(IssueProcessor::issueTitle($issue))
            )] = $issue;
        }

        $importedIds = [];
        $issues = array_values($existingById);
        $nextVirtualId = -1;

        foreach ($snapshots as $snapshot) {
            if (empty($snapshot['published'])) {
                continue;
            }

            $identity = $this->_issueIdentity(
                $journalId,
                $snapshot['volume'],
                $snapshot['number'],
                $snapshot['year'],
                $snapshot['title']
            );
            $match = null;
            if (isset($existingById[$snapshot['issueId']])) {
                $match = $existingById[$snapshot['issueId']];
            } elseif (isset($existingByIdentity[$identity])) {
                $match = $existingByIdentity[$identity];
            }

            if ($match !== null) {
                $importedIds[$match->getId()] = true;
                if (empty($match->getDatePublished())) {
                    $match->setDatePublished($this->_filledIssueDate($snapshot));
                }
                continue;
            }

            $virtual = $this->_buildImportedIssue($snapshot, $nextVirtualId);
            $nextVirtualId--;
            $issues[] = $virtual;
            $importedIds[$virtual->getId()] = true;
        }

        return IssueProcessor::selectCurrentIssue($issues, $importedIds, $existingCurrent);
    }

    /**
     * @param array $snapshot
     * @param int $virtualId
     *
     * @return \Issue
     */
    private function _buildImportedIssue(array $snapshot, $virtualId)
    {
        $issue = new \Issue();
        $issue->setId($virtualId);
        $issue->setJournalId($snapshot['journalId']);
        $issue->setPublished(!empty($snapshot['published']));
        if ($snapshot['volume'] !== '') {
            $issue->setVolume($snapshot['volume']);
            $issue->setShowVolume(true);
        }
        if ($snapshot['number'] !== '') {
            $issue->setNumber($snapshot['number']);
            $issue->setShowNumber(true);
        }
        if ($snapshot['year'] !== '') {
            $issue->setYear($snapshot['year']);
            $issue->setShowYear(true);
        }
        if ($snapshot['title'] !== '') {
            $locale = $snapshot['locale'] !== '' ? $snapshot['locale'] : 'en';
            $issue->setTitle($snapshot['title'], $locale);
            $issue->setData('locale', $locale);
            $issue->setShowTitle(true);
        }
        $issue->setDatePublished($this->_filledIssueDate($snapshot));

        return $issue;
    }

    /**
     * @param array $snapshot
     *
     * @return string
     */
    private function _filledIssueDate(array $snapshot)
    {
        if ($snapshot['issueDatePublished'] !== '') {
            return $snapshot['issueDatePublished'];
        }
        if ($snapshot['articleDatePublished'] !== '') {
            return $snapshot['articleDatePublished'];
        }

        return \Core::getCurrentDate();
    }

    /**
     * @param int $journalId
     * @param string $volume
     * @param string $number
     * @param string $year
     * @param string $title
     *
     * @return string
     */
    private function _issueIdentity($journalId, $volume, $number, $year, $title)
    {
        if ($volume === '' && $number === '' && $year === '') {
            return $journalId . '|title:' . mb_strtolower($title);
        }

        return $journalId . '|v:' . $volume . '|n:' . $number . '|y:' . $year;
    }

    /**
     * @param mixed $value
     *
     * @return string
     */
    private function _issueField($value)
    {
        if ($value === null) {
            return '';
        }

        return trim((string) $value);
    }
}
