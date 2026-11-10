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
use APP\submission\Submission;
use Illuminate\Support\Facades\DB;
use PKP\core\Core;
use PKP\core\PKPString;

class IssueProcessor
{
    /**
	 * Processes data for the Issue. If there's no issue registered, a new one will be created and attached
	 * to the submission.
	 */
	public static function process(int $journalId, object $data, bool $published = true): Issue
    {
        $issue = CachedEntities::getCachedIssue($data, $journalId);

        if (is_null($issue)) {
            $sanitizedIssueDescription = PKPString::stripUnsafeHtml($data->issueDescription ?? '');

            $issue = Repo::issue()->newDataObject();

            $issue->setJournalId($journalId);

            $issue->setShowVolume(!empty($data->issueVolume));
            $issue->setShowNumber(!empty($data->issueNumber));
            $issue->setShowYear(!empty($data->issueYear));
            $issue->setShowTitle(!empty($data->issueTitle));
            $issue->setPublished($published);
            $issue->setDatePublished(!empty($data->issuePublicationDate) ? $data->issuePublicationDate : null);
            $issue->setDescription($sanitizedIssueDescription, $data->locale);
            $issue->setAccessStatus(Issue::ISSUE_ACCESS_OPEN);
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

            $issueId = Repo::issue()->add($issue);
            $issue = Repo::issue()->get($issueId);
            $issue->setPublished($published);
        } else {
            $changed = false;
            if (!empty($data->issueTitle) && !$issue->getShowTitle()) {
                $issue->setShowTitle(true);
                $changed = true;
            }
            if ($published && !$issue->getPublished()) {
                $issue->setPublished(true);
                $changed = true;
            }
            if ($changed) {
                Repo::issue()->edit($issue, []);
            }
        }

        return $issue;
	}

    /**
     * Sets issue publication dates for issues imported without issuePublicationDate,
     * using the most recent datePublished among their published articles.
     */
    public static function fillMissingIssueDates(array $processedIssues): void
    {
        foreach ($processedIssues as $processedIssue) {
            /** @var Issue $issue */
            $issue = $processedIssue['issue'];

            if (!empty($issue->getDatePublished())) {
                continue;
            }

            $result = DB::table('publications')
                ->where('issue_id', $issue->getId())
                ->where('status', Submission::STATUS_PUBLISHED)
                ->max('date_published');

            $issue->setDatePublished($result ?: Core::getCurrentDate());
            Repo::issue()->edit($issue, []);
        }
    }

    /**
     * Process multi-locale issue data (adds new locale to existing issue)
     * This method updates an existing issue with data in a new locale
     */
    public static function processMultiLocale(Issue $issue, object $data): Issue
    {
        if (!empty($data->issueTitle)) {
            $issue->setTitle($data->issueTitle, $data->locale);
            $issue->setShowTitle(true);
        }

        if (!empty($data->issueDescription)) {
            $sanitizedIssueDescription = PKPString::stripUnsafeHtml($data->issueDescription);
            $issue->setDescription($sanitizedIssueDescription, $data->locale);
        }

        Repo::issue()->edit($issue, []);
        $issue = Repo::issue()->get($issue->getId());

        return $issue;
    }

    /**
     * Reorder all issues in each journal according to specified criteria:
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

        $importedIssueIdsByJournal = [];
        foreach ($processedIssues as $processedIssue) {
            $journalId = $processedIssue['journalId'];
            $issueId = $processedIssue['issue']->getId();
            $importedIssueIdsByJournal[$journalId][$issueId] = true;
        }

        foreach ($importedIssueIdsByJournal as $journalId => $importedIssueIds) {
            self::reorderAllIssuesForJournal($journalId, $importedIssueIds);
        }
    }

    /**
     * Reorder all issues for a specific journal (not just imported ones).
     *
     * @param array<int, true> $importedIssueIds Issue ids created or updated by this import
     */
    public static function reorderAllIssuesForJournal(int $journalId, array $importedIssueIds = []): void
    {
        $issueDao = Repo::issue()->dao;

        // Get all published issues for this journal using the Collector pattern
        $collector = Repo::issue()->getCollector()
            ->filterByContextIds([$journalId])
            ->filterByPublished(true);

        $allIssues = $collector->getMany()->toArray();

        if (empty($allIssues)) {
            return;
        }

        usort($allIssues, [self::class, 'compareIssuesForOrder']);

        // Apply custom ordering to all issues
        $sequence = 1;
        foreach ($allIssues as $issue) {
            $issueDao->moveCustomIssueOrder($journalId, $issue->getId(), $sequence);
            $sequence++;
        }

        // Same decision a dry-run reports: the first issue, unless that would
        // replace a journal issue that was published later.
        $selected = self::currentIssueFromSorted($allIssues, $importedIssueIds, null);
        if ($selected !== null) {
            Repo::issue()->updateCurrent($journalId, $selected);
        }

        // Resequence to ensure proper ordering
        $issueDao->resequenceCustomIssueOrders($journalId);
    }

    /**
     * Issue that would be current after import.
     *
     * The list must already include published journal issues and issues that
     * this import would add. When the import would not change the current
     * issue, the journal's existing current issue is returned.
     *
     * @param Issue[] $issues
     * @param array<int, true> $importedIssueIds
     */
    public static function selectCurrentIssue(array $issues, array $importedIssueIds, ?Issue $existingCurrent): ?Issue
    {
        if ($issues === []) {
            return $existingCurrent;
        }

        usort($issues, [self::class, 'compareIssuesForOrder']);

        return self::currentIssueFromSorted($issues, $importedIssueIds, $existingCurrent);
    }

    /**
     * @param Issue[] $sortedIssues Most recent first
     * @param array<int, true> $importedIssueIds
     */
    public static function currentIssueFromSorted(array $sortedIssues, array $importedIssueIds, ?Issue $existingCurrent): ?Issue
    {
        if ($sortedIssues === []) {
            return $existingCurrent;
        }

        $candidate = $sortedIssues[0];
        if (self::importedIssueWouldReplaceNewerExisting($candidate, $sortedIssues, $importedIssueIds)) {
            return $existingCurrent;
        }

        return $candidate;
    }

    /**
     * Year, then volume, then number, then publication date. Most recent first.
     */
    public static function compareIssuesForOrder(Issue $a, Issue $b): int
    {
        $yearA = self::extractNumericValue($a->getYear());
        $yearB = self::extractNumericValue($b->getYear());
        $volumeA = self::extractNumericValue($a->getVolume());
        $volumeB = self::extractNumericValue($b->getVolume());
        $numberA = self::extractNumericValue($a->getNumber());
        $numberB = self::extractNumericValue($b->getNumber());

        if ($yearA !== null && $yearB !== null) {
            if ($yearA !== $yearB) {
                return $yearB <=> $yearA;
            }
        } elseif ($yearA !== null) {
            return -1;
        } elseif ($yearB !== null) {
            return 1;
        }

        if ($volumeA !== null && $volumeB !== null) {
            if ($volumeA !== $volumeB) {
                return $volumeB <=> $volumeA;
            }
        } elseif ($volumeA !== null) {
            return -1;
        } elseif ($volumeB !== null) {
            return 1;
        }

        if ($numberA !== null && $numberB !== null) {
            if ($numberA !== $numberB) {
                return $numberB <=> $numberA;
            }
        } elseif ($numberA !== null) {
            return -1;
        } elseif ($numberB !== null) {
            return 1;
        }

        $dateA = $a->getDatePublished();
        $dateB = $b->getDatePublished();

        if ($dateA && $dateB) {
            return strcmp($dateB, $dateA);
        } elseif ($dateA) {
            return -1;
        } elseif ($dateB) {
            return 1;
        }

        return 0;
    }

    /**
     * Label used in the dry-run report. Shows whichever of volume, number,
     * year, and title the issue actually has.
     */
    public static function describeIssue(Issue $issue): string
    {
        $parts = [];
        if (!empty($issue->getVolume())) {
            $parts[] = __('issue.vol') . ' ' . $issue->getVolume();
        }
        if (!empty($issue->getNumber())) {
            $parts[] = __('issue.no') . ' ' . $issue->getNumber();
        }
        if (!empty($issue->getYear())) {
            $year = (string) $issue->getYear();
            $parts[] = $parts === [] ? $year : '(' . $year . ')';
        }

        $title = self::issueTitle($issue);
        if ($title !== '') {
            if ($parts !== []) {
                $parts[array_key_last($parts)] .= ':';
            }
            $parts[] = $title;
        }

        return $parts === [] ? '' : implode(' ', $parts);
    }

    /** Title stored on the issue, without asking the request for a locale. */
    public static function issueTitle(Issue $issue): string
    {
        $locale = $issue->getData('locale');
        $title = is_string($locale) ? $issue->getData('title', $locale) : null;
        if (is_string($title) && $title !== '') {
            return $title;
        }

        $titles = $issue->getData('title');
        $first = is_array($titles) ? reset($titles) : $titles;

        return is_string($first) ? $first : '';
    }

    /**
     * True when the issue that would become current came from this import,
     * and an issue already in the journal was published later.
     *
     * @param Issue[] $allIssues
     * @param array<int, true> $importedIssueIds
     */
    private static function importedIssueWouldReplaceNewerExisting(Issue $candidate, array $allIssues, array $importedIssueIds): bool
    {
        if ($importedIssueIds === [] || !isset($importedIssueIds[$candidate->getId()])) {
            return false;
        }

        $latestImported = null;
        $latestExisting = null;

        foreach ($allIssues as $issue) {
            $date = self::normalizePublishedDate($issue->getDatePublished());
            if ($date === null) {
                continue;
            }

            if (isset($importedIssueIds[$issue->getId()])) {
                if ($latestImported === null || strcmp($date, $latestImported) > 0) {
                    $latestImported = $date;
                }
                continue;
            }

            if ($latestExisting === null || strcmp($date, $latestExisting) > 0) {
                $latestExisting = $date;
            }
        }

        if ($latestExisting === null) {
            return false;
        }

        if ($latestImported === null) {
            return true;
        }

        return strcmp($latestExisting, $latestImported) > 0;
    }

    /**
     * Compare publication dates on their YYYY-MM-DD[ HH:MM:SS] prefix.
     */
    private static function normalizePublishedDate($date): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }

        $date = substr((string) $date, 0, 19);
        return $date === '' ? null : $date;
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
        $maxSequence = DB::table('custom_issue_orders')
            ->where('journal_id', '=', $journalId)
            ->max('seq');

        return $maxSequence ? (int)$maxSequence : 0;
    }
}
