<?php

/**
 * @file plugins/importexport/csv/classes/processors/SubmissionProcessor.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionProcessor
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Processes the submission data into the database.
 */

namespace APP\plugins\importexport\csv\classes\processors;

use APP\facades\Repo;
use APP\publication\Publication;
use APP\submission\Submission;
use PKP\context\Context;

class SubmissionProcessor
{
    public static function process(object $data, Publication $publication, Context $journal): Submission
    {
        $submission = Repo::submission()->newDataObject();

        $submission->setData('contextId', $journal->getId());
        $submission->setData('status', Submission::STATUS_PUBLISHED);
        $submission->setData('locale', $data->locale);
        $submission->setData('stageId', WORKFLOW_STAGE_ID_PRODUCTION);
        $submission->setData('submissionProgress', '0');
        $submission->setData('abstract', $data->articleAbstract, $data->locale);

        $submission->stampLastActivity();
        $submission->stampModified();

        $submissionId = Repo::submission()->add($submission, $publication, $journal);
        return Repo::submission()->get($submissionId);
    }

    public static function updateCurrentPublicationId(Submission $submission, int $publicationId)
    {
        Repo::submission()->edit($submission, ['currentPublicationId' => $publicationId]);
    }
}
