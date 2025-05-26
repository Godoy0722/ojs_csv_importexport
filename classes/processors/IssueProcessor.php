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
}
