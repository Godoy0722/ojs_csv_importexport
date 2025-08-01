<?php

/**
 * @file plugins/importexport/csv/classes/processors/SubmissionFileProcessor.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionFileProcessor
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Processes the submission files data into the database.
 */

namespace APP\plugins\importexport\csv\classes\processors;

use APP\core\Application;
use APP\facades\Repo;
use PKP\core\Core;
use PKP\core\PKPString;
use PKP\submissionFile\SubmissionFile;

class SubmissionFileProcessor
{
    public static function process(
        string $locale,
        int $userId,
        int $submissionId,
        string $filePath,
        int $genreId,
        int $fileId
    ): SubmissionFile
    {
        $submissionFile = Repo::submissionFile()->newDataObject();

        $submissionFile->setData('submissionId', $submissionId);
        $submissionFile->setData('uploaderUserId', $userId);
        $submissionFile->setData('fileId', $fileId);
        $submissionFile->setData('genreId', $genreId);
        $submissionFile->setData('fileStage', SubmissionFile::SUBMISSION_FILE_PROOF);
        $submissionFile->setData('createdAt', Core::getCurrentDate());
        $submissionFile->setData('updatedAt', Core::getCurrentDate());
        $submissionFile->setData('mimetype', PKPString::mime_content_type($filePath));
        $submissionFile->setData('locale', $locale);
        $submissionFile->setData('name', pathinfo($filePath, PATHINFO_FILENAME), $locale);
        $submissionFile->setData('directSalesPrice', 0);
        $submissionFile->setData('salesType', 'openAccess');

        $submissionFileId = Repo::submissionFile()->add($submissionFile);

        return Repo::submissionFile()->get($submissionFileId);
	}

    public static function updateAssocInfo(SubmissionFile $submissionFile, int $galleyId)
    {
        Repo::submissionFile()->edit($submissionFile,
            [
                'assocType' => Application::ASSOC_TYPE_REPRESENTATION,
                'assocId' => $galleyId
            ]
        );
    }
}
