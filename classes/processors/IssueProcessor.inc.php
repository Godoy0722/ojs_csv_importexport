<?php

/**
 * @file plugins/importexport/csv/classes/processors/IssueProcessor.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2003-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class IssueProcessor
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Processes the issue data into the database.
 */

namespace PKP\Plugins\ImportExport\CSV\Classes\Processors;

use PKP\Plugins\ImportExport\CSV\Classes\CachedAttributes\CachedDaos;
use PKP\Plugins\ImportExport\CSV\Classes\CachedAttributes\CachedEntities;

class IssueProcessor
{
	/**
	 * Processes data for the Issue. If there's no issue registered, a new one will be created and attached
	 * to the submission.
	 *
	 * @param int $journalId
	 * @param object $data
	 * @param ?\Publication $basePublication
	 *
	 * @return \Issue
	 */
	public static function process($journalId, $data, $basePublication = null)
    {
        if (!is_null($basePublication)) {
            $hasIssueData = !empty($data->issueVolume)
                || !empty($data->issueNumber)
                || !empty($data->issueYear)
                || !empty($data->issueTitle);

            if (!$hasIssueData) {
                $issueId = $basePublication->getData('issueId');
                if (!empty($issueId)) {
                    $issueDao = CachedDaos::getIssueDao();
                    $issue = $issueDao->getById($issueId, $journalId);
                    if ($issue) {
                        return $issue;
                    }
                }
            }
        }

        $issue = CachedEntities::getCachedIssue($data, $journalId);

        if(is_null($issue)) {
            $issueDao = CachedDaos::getIssueDao();
            $sanitizedIssueDescription = \PKPString::stripUnsafeHtml($data->issueDescription);

			/** @var \Issue $issue */
            $issue = $issueDao->newDataObject();
            $issue->setJournalId($journalId);

            $issue->setShowVolume(!empty($data->issueVolume));
            $issue->setShowNumber(!empty($data->issueNumber));
            $issue->setShowYear(!empty($data->issueYear));
            $issue->setShowTitle(!empty($data->issueTitles));
            $issue->setPublished(true);
            $issue->setDatePublished(\Core::getCurrentDate());
            $issue->setDescription($sanitizedIssueDescription, $data->locale);
            $issue->setAccessStatus(ISSUE_ACCESS_OPEN);
            $issue->setData('locale', $data->locale);
            $issue->stampModified();

            if (!empty($data->issueVolume)) {
                $issue->setVolume($data->issueVolume);
            }

            if (!empty($data->issueNumber)) {
                $issue->setNumber($data->issueNumber);
            }

            if (!empty($data->issueYear)) {
                $issue->setYear($data->issueYear);
            }

            if (!empty($data->issueTitle)) {
                $issue->setTitle($data->issueTitle, $data->locale);
            }

            $issueDao->insertObject($issue);
        }

        return $issue;
	}

	/**
     * Process multi-locale issue data (adds new locale to existing issue)
     * This method updates an existing issue with data in a new locale
	 *
	 * @param \Issue $issue
	 * @param object $data
	 *
	 * @return \Issue
     */
    public static function processMultiLocale($issue, $data)
    {
        if (!empty($data->issueTitle)) {
            $issue->setTitle($data->issueTitle, $data->locale);
        }

        if (!empty($data->issueDescription)) {
            $sanitizedIssueDescription = \PKPString::stripUnsafeHtml($data->issueDescription);
            $issue->setDescription($sanitizedIssueDescription, $data->locale);
        }

		CachedDaos::getIssueDao()->updateObject($issue);
        return CachedDaos::getIssueDao()->getById($issue->getId());
    }

	/**
     * Reorder all issues in each journal according to specified criteria:
     * 1. Year (most recent to oldest)
     * 2. Volume (biggest to lowest)
     * 3. Number (biggest to lowest)
     * 4. Fallback to datePublished (most recent to oldest) if issue info is missing
	 *
	 * @param array $processedIssues
	 *
	 * @return void
     */
    public static function reorderImportedIssues($processedIssues)
    {
        if (empty($processedIssues)) {
            return;
        }

        // Get all unique journal IDs from the processed issues
        $journalIds = [];
        foreach ($processedIssues as $processedIssue) {
            $journalId = $processedIssue['journalId'];
            $journalIds[$journalId] = $journalId;
        }

        // Reorder all issues for each journal that had imported issues
        foreach ($journalIds as $journalId) {
            self::reorderAllIssuesForJournal($journalId);
        }
    }

    /**
     * Reorder all issues for a specific journal (not just imported ones)
	 *
	 * @param int $journalId
	 *
	 * @return void
     */
    public static function reorderAllIssuesForJournal($journalId)
    {
        $issueDao = CachedDaos::getIssueDao();

        $publishedIssues = $issueDao->getPublishedIssues($journalId);
        $allIssues = $publishedIssues->toArray();

        if (empty($allIssues)) {
            return;
        }

        // Sort all issues according to the same criteria as imported issues
        usort($allIssues, function($a, $b) {
            // Extract sorting criteria from issue objects
            $yearA = self::extractNumericValue($a->getYear());
            $yearB = self::extractNumericValue($b->getYear());
            $volumeA = self::extractNumericValue($a->getVolume());
            $volumeB = self::extractNumericValue($b->getVolume());
            $numberA = self::extractNumericValue($a->getNumber());
            $numberB = self::extractNumericValue($b->getNumber());

            // Primary sort: Year (most recent to oldest - descending)
            if ($yearA !== null && $yearB !== null) {
                if ($yearA !== $yearB) {
                    return $yearB <=> $yearA; // Descending order
                }
            } else if ($yearA !== null) {
                return -1; // A has year, B doesn't - A comes first
            } else if ($yearB !== null) {
                return 1; // B has year, A doesn't - B comes first
            }

            // Secondary sort: Volume (biggest to lowest - descending)
            if ($volumeA !== null && $volumeB !== null) {
                if ($volumeA !== $volumeB) {
                    return $volumeB <=> $volumeA; // Descending order
                }
            } else if ($volumeA !== null) {
                return -1; // A has volume, B doesn't - A comes first
            } else if ($volumeB !== null) {
                return 1; // B has volume, A doesn't - B comes first
            }

            // Tertiary sort: Number (biggest to lowest - descending)
            if ($numberA !== null && $numberB !== null) {
                if ($numberA !== $numberB) {
                    return $numberB <=> $numberA; // Descending order
                }
            } else if ($numberA !== null) {
                return -1; // A has number, B doesn't - A comes first
            } else if ($numberB !== null) {
                return 1; // B has number, A doesn't - B comes first
            }

            // Fallback: datePublished (most recent to oldest - descending)
            $dateA = $a->getDatePublished();
            $dateB = $b->getDatePublished();

            if ($dateA && $dateB) {
                return strcmp($dateB, $dateA); // Descending order (string comparison)
            } else if ($dateA) {
                return -1; // A has date, B doesn't - A comes first
            } else if ($dateB) {
                return 1; // B has date, A doesn't - B comes first
            }

            // If all else is equal, maintain original order
            return 0;
        });

        $issueDao->update(
            'DELETE FROM custom_issue_orders WHERE journal_id = ?',
            [(int) $journalId]
        );

        $sequence = 1;
        foreach ($allIssues as $issue) {
            $issueDao->insertCustomIssueOrder($journalId, $issue->getId(), $sequence);
            $sequence++;
        }

        $mostRecentIssue = $allIssues[0];
        $mostRecentIssue->setCurrent(1);
        $issueDao->updateCurrent($journalId, $mostRecentIssue);
    }

    /**
     * Extract numeric value from a field, handling various input types
     *
     * @param mixed $value
     * @return int|null
     */
    public static function extractNumericValue($value)
    {
        if (empty($value)) {
            return null;
        }

        $numValue = is_numeric($value) ? (int)$value : (int)trim($value);
        return ($numValue > 0) ? $numValue : null;
    }

    /**
     * Extract date value for comparison, preferring CSV data over issue data
     *
     * @param string|null $csvDate
     * @param string|null $issueDate
     * @return string|null
     */
    public static function extractDateValue($csvDate, $issueDate)
    {
        if (!empty($csvDate)) {
            $date = trim($csvDate);
            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $date)) {
                return $date;
            }
        }

        // Fallback to issue date
        if (!empty($issueDate)) {
            return $issueDate;
        }

        return null;
    }

	/**
     * Get the current maximum sequence value for a journal's custom issue orders
     */
    public static function getCurrentMaxSequence(int $journalId): int
    {
        $issueDao = CachedDaos::getIssueDao();
        $result = $issueDao->retrieve(
            'SELECT MAX(seq) AS max_seq FROM custom_issue_orders WHERE journal_id = ?',
            [(int) $journalId]
        );

        $row = $result->current();
        return ($row && $row->max_seq) ? (int) $row->max_seq : 0;
    }
}
