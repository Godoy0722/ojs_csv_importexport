<?php

/**
 * @file plugins/importexport/csv/classes/processors/IssueProcessor.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class IssueProcessor
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Processes the issue data into the database.
 */

namespace APP\plugins\importexport\csv\classes\processors;

use APP\facades\Repo;
use APP\issue\Issue;
use APP\plugins\importexport\csv\classes\cachedAttributes\CachedEntities;
use PKP\core\Core;
use PKP\core\PKPString;

class IssueProcessor
{
    /**
	 * Processes data for the Issue. If there's no issue registered, a new one will be created and attached
	 * to the submission.
	 */
	public static function process(int $journalId, object $data): Issue
    {
        $issue = CachedEntities::getCachedIssue($data, $journalId);

        if (is_null($issue)) {
            $sanitizedIssueDescription = PKPString::stripUnsafeHtml($data->issueDescription ?? '');

            $issue = Repo::issue()->newDataObject();

            $issue->setJournalId($journalId);
            $issue->setVolume($data->issueVolume ?? null);
            $issue->setNumber($data->issueNumber ?? null);
            $issue->setYear($data->issueYear ?? null);
            $issue->setShowVolume(!empty($data->issueVolume));
            $issue->setShowNumber(!empty($data->issueNumber));
            $issue->setShowYear(!empty($data->issueYear));
            $issue->setShowTitle(true);
            $issue->setPublished(true);
            $issue->setDatePublished(Core::getCurrentDate());
            $issue->setTitle($data->issueTitle ?? '', $data->locale);
            $issue->setDescription($sanitizedIssueDescription, $data->locale);
            $issue->setAccessStatus(Issue::ISSUE_ACCESS_OPEN);
            $issue->setData('locale', $data->locale);
            $issue->stampModified();

            $issueId = Repo::issue()->add($issue);
            $issue = Repo::issue()->get($issueId);
        }

        return $issue;
	}

    /**
     * Reorder all imported issues according to the specified criteria:
     * 1. Year (most recent to oldest)
     * 2. Volume (biggest to lowest)
     * 3. Number (biggest to lowest)
     * 4. Fallback to datePublished (most recent to oldest) if issue info is missing
     */
    public static function reorderImportedIssues(array $processedIssues): void
    {
        if (empty($processedIssues)) {
            return;
        }

        $issuesByJournal = [];
        foreach ($processedIssues as $processedIssue) {
            $journalId = $processedIssue['journalId'];
            if (!isset($issuesByJournal[$journalId])) {
                $issuesByJournal[$journalId] = [];
            }
            $issuesByJournal[$journalId][] = $processedIssue;
        }

        foreach ($issuesByJournal as $journalId => $issues) {
            self::reorderIssuesForJournal($journalId, $issues);
        }
    }

    /** Reorder issues for a specific journal */
    public static function reorderIssuesForJournal(int $journalId, array $issues): void
    {
        $issueDao = Repo::issue()->dao;

        usort($issues, function($a, $b) {
            $dataA = $a['data'];
            $dataB = $b['data'];
            $issueA = $a['issue'];
            $issueB = $b['issue'];

            // Extract sorting criteria
            $yearA = self::extractNumericValue($dataA->issueYear);
            $yearB = self::extractNumericValue($dataB->issueYear);
            $volumeA = self::extractNumericValue($dataA->issueVolume);
            $volumeB = self::extractNumericValue($dataB->issueVolume);
            $numberA = self::extractNumericValue($dataA->issueNumber);
            $numberB = self::extractNumericValue($dataB->issueNumber);

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
            $dateA = self::extractDateValue($dataA->datePublished, $issueA->getDatePublished());
            $dateB = self::extractDateValue($dataB->datePublished, $issueB->getDatePublished());

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

        // Apply custom ordering
        $sequence = 1;
        foreach ($issues as $issueData) {
            $issue = $issueData['issue'];
            $issueDao->moveCustomIssueOrder($journalId, $issue->getId(), $sequence);
            $sequence++;
        }

        // Resequence to ensure proper ordering
        $issueDao->resequenceCustomIssueOrders($journalId);
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
}
