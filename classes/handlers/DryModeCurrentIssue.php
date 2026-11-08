<?php

/**
 * @file plugins/importexport/csv/classes/handlers/DryModeCurrentIssue.php
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

namespace APP\plugins\importexport\csv\classes\handlers;

use APP\facades\Repo;
use APP\issue\Issue;
use APP\plugins\importexport\csv\classes\cachedAttributes\CachedEntities;
use APP\plugins\importexport\csv\classes\processors\IssueProcessor;
use Illuminate\Support\Facades\DB;
use PKP\core\Core;

class DryModeCurrentIssue
{
    /** @var array<int, string> Journal id => path seen while reading the CSV */
    private array $seenJournals = [];

    /**
     * Issues a real import would create or update, kept so dry-mode can name
     * the current issue after the transaction is rolled back.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $importedIssueSnapshots = [];

    public function noteJournal(int $journalId, string $journalPath): void
    {
        $this->seenJournals[$journalId] = $journalPath;
    }

    /**
     * Keep the fields needed to decide the current issue after this file's
     * dry-run transaction is rolled back.
     */
    public function remember(Issue $issue, int $journalId, bool $createdByImport): void
    {
        $volume = $this->issueField($issue->getVolume());
        $number = $this->issueField($issue->getNumber());
        $year = $this->issueField($issue->getYear());
        $title = $this->issueField(IssueProcessor::issueTitle($issue));
        $identity = $this->issueIdentity($journalId, $volume, $number, $year, $title);

        $issueDate = $this->issueField($issue->getDatePublished());
        $articleDate = null;
        if ($issueDate === '') {
            $articleDate = DB::table('publications')
                ->where('issue_id', $issue->getId())
                ->max('date_published');
            $articleDate = $this->issueField($articleDate);
        }

        $snapshot = $this->importedIssueSnapshots[$identity] ?? [
            'journalId' => $journalId,
            'issueId' => (int) $issue->getId(),
            'createdByImport' => $createdByImport,
            'volume' => $volume,
            'number' => $number,
            'year' => $year,
            'title' => $title,
            'locale' => $this->issueField($issue->getData('locale')),
            'issueDatePublished' => '',
            'articleDatePublished' => '',
        ];

        // A later row can see an issue created earlier in this import. That
        // issue is still coming from the CSV, so keep the original flag.
        if ($createdByImport) {
            $snapshot['createdByImport'] = true;
        } elseif (!$snapshot['createdByImport']) {
            $snapshot['issueId'] = (int) $issue->getId();
        }

        if ($issueDate !== '') {
            $snapshot['issueDatePublished'] = $issueDate;
        } elseif ($articleDate !== '' && ($snapshot['articleDatePublished'] === '' || strcmp($articleDate, $snapshot['articleDatePublished']) > 0)) {
            $snapshot['articleDatePublished'] = $articleDate;
        }

        if ($snapshot['title'] === '' && $title !== '') {
            $snapshot['title'] = $title;
            $snapshot['locale'] = $this->issueField($issue->getData('locale'));
        }

        $this->importedIssueSnapshots[$identity] = $snapshot;
    }

    /**
     * @return list<array{journalPath: string, identification: ?string, source: ?string, message: string}>
     */
    public function reports(?string $currentJournalPath): array
    {
        $journals = $this->seenJournals;
        if ($journals === [] && $currentJournalPath !== null) {
            $journal = CachedEntities::getCachedJournal($currentJournalPath);
            if ($journal !== null) {
                $journals[$journal->getId()] = $journal->getPath();
            }
        }

        $snapshotsByJournal = [];
        foreach ($this->importedIssueSnapshots as $snapshot) {
            $snapshotsByJournal[$snapshot['journalId']][] = $snapshot;
        }

        $reports = [];
        foreach ($journals as $journalId => $journalPath) {
            $snapshots = $snapshotsByJournal[$journalId] ?? [];
            $existingCurrent = Repo::issue()->getCurrent((int) $journalId);
            $selected = $snapshots === []
                ? $existingCurrent
                : $this->selectCurrentIssueForJournal((int) $journalId, $snapshots, $existingCurrent);

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

        usort($reports, function (array $a, array $b): int {
            return strcmp($a['journalPath'], $b['journalPath']);
        });

        return $reports;
    }

    /**
     * @param list<array<string, mixed>> $snapshots
     */
    private function selectCurrentIssueForJournal(int $journalId, array $snapshots, ?Issue $existingCurrent): ?Issue
    {
        $existing = Repo::issue()->getCollector()
            ->filterByContextIds([$journalId])
            ->filterByPublished(true)
            ->getMany()
            ->toArray();

        $existingById = [];
        $existingByIdentity = [];
        foreach ($existing as $issue) {
            $existingById[$issue->getId()] = $issue;
            $existingByIdentity[$this->issueIdentity(
                $journalId,
                $this->issueField($issue->getVolume()),
                $this->issueField($issue->getNumber()),
                $this->issueField($issue->getYear()),
                $this->issueField(IssueProcessor::issueTitle($issue))
            )] = $issue;
        }

        $importedIds = [];
        $issues = array_values($existingById);
        $nextVirtualId = -1;

        foreach ($snapshots as $snapshot) {
            $match = $existingById[$snapshot['issueId']] ?? $existingByIdentity[$this->issueIdentity(
                $journalId,
                $snapshot['volume'],
                $snapshot['number'],
                $snapshot['year'],
                $snapshot['title']
            )] ?? null;

            if ($match !== null) {
                $importedIds[$match->getId()] = true;
                if (empty($match->getDatePublished())) {
                    $match->setDatePublished($this->filledIssueDate($snapshot));
                }
                continue;
            }

            $virtual = $this->buildImportedIssue($snapshot, $nextVirtualId);
            $nextVirtualId--;
            $issues[] = $virtual;
            $importedIds[$virtual->getId()] = true;
        }

        return IssueProcessor::selectCurrentIssue($issues, $importedIds, $existingCurrent);
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    private function buildImportedIssue(array $snapshot, int $virtualId): Issue
    {
        $issue = new Issue();
        $issue->setId($virtualId);
        $issue->setJournalId($snapshot['journalId']);
        $issue->setPublished(true);
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
        $issue->setDatePublished($this->filledIssueDate($snapshot));

        return $issue;
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    private function filledIssueDate(array $snapshot): string
    {
        if ($snapshot['issueDatePublished'] !== '') {
            return $snapshot['issueDatePublished'];
        }
        if ($snapshot['articleDatePublished'] !== '') {
            return $snapshot['articleDatePublished'];
        }

        return Core::getCurrentDate();
    }

    private function issueIdentity(int $journalId, string $volume, string $number, string $year, string $title): string
    {
        if ($volume === '' && $number === '' && $year === '') {
            return $journalId . '|title:' . mb_strtolower($title);
        }

        return $journalId . '|v:' . $volume . '|n:' . $number . '|y:' . $year;
    }

    private function issueField($value): string
    {
        if ($value === null) {
            return '';
        }

        return trim((string) $value);
    }
}
