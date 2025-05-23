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

            $issueData = [
                'journalId' => $journalId,
                'volume' => $data->issueVolume ?? null,
                'number' => $data->issueNumber ?? null,
                'year' => $data->issueYear ?? null,
                'showVolume' => !empty($data->issueVolume),
                'showNumber' => !empty($data->issueNumber),
                'showYear' => !empty($data->issueYear),
                'showTitle' => 1,
                'published' => true,
                'datePublished' => Core::getCurrentDate(),
                'title' => $data->issueTitle ?? '',
                'description' => $sanitizedIssueDescription,
                'accessStatus' => Issue::ISSUE_ACCESS_OPEN,
                'locale' => $data->locale,
                'lastModified' => Core::getCurrentDate(),
            ];

            $issue = Repo::issue()->newDataObject($issueData);
            $issueId = Repo::issue()->add($issue);
            $issue = Repo::issue()->get($issueId);
        }

        return $issue;
	}
}
