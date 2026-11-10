<?php

/**
 * @file plugins/importexport/csv/classes/processors/SubmissionProcessor.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2003-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionProcessor
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Processes the submission data into the database.
 */

namespace PKP\Plugins\ImportExport\CSV\Classes\Processors;

use PKP\Plugins\ImportExport\CSV\Classes\CachedAttributes\CachedDaos;

class SubmissionProcessor
{
    /**
     * Processes initial data for the Submission
		 *
		 * @param int $journalId
		 * @param object $data
		 *
		 * @return \Submission
     */
    public static function process($journalId, $data)
    {
		$submissionDao = CachedDaos::getSubmissionDao();

		/** @var \Submission $submission */
		$submission = $submissionDao->newDataObject();
		$submission->setData('contextId', $journalId);
		$submission->stampLastActivity();
		$submission->stampModified();
		$status = isset($data->resolvedStatus) ? (int) $data->resolvedStatus : STATUS_PUBLISHED;
		$dateSubmitted = $status === STATUS_PUBLISHED
			? (string) $data->datePublished
			: (string) (isset($data->dateSubmitted) ? $data->dateSubmitted : '');
		$submission->setData('status', $status);
		$submission->setData('dateSubmitted', $dateSubmitted);
		$submission->setData('locale', $data->locale);
		$submission->setData('stageId', WORKFLOW_STAGE_ID_PRODUCTION);
		$submission->setData('submissionProgress', '0');

		if (!empty($data->articleAbstract)) {
			$submission->setData('abstract', $data->articleAbstract, $data->locale);
		}

		$submissionDao->insertObject($submission);

		return $submission;
    }

    /**
     * Updates the current publication ID for the submission
		 *
		 * @param \Submission $submission
		 * @param int $publicationId
		 *
		 * @return void
     */
    public static function updateCurrentPublicationId($submission, $publicationId)
    {
        $submission->setData('currentPublicationId', $publicationId);
        $submissionDao = CachedDaos::getSubmissionDao();
        $submissionDao->updateObject($submission);
    }
}
