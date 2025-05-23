<?php

/**
 * @file plugins/importexport/csv/classes/processors/SubmissionFileProcessor.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2003-2025 John Willinsky
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
        $submissionFileData = [
            'submissionId' => $submissionId,
            'uploaderUserId' => $userId,
            'fileId' => $fileId,
            'genreId' => $genreId,
            'fileStage' => SubmissionFile::SUBMISSION_FILE_PROOF,
            'createdAt' => Core::getCurrentDate(),
            'updatedAt' => Core::getCurrentDate(),
            'mimetype' => PKPString::mime_content_type($filePath),
            'locale' => $locale,
            $locale => [
                'name' => pathinfo($filePath, PATHINFO_FILENAME)
            ],
            'directSalesPrice' => 0,
            'salesType' => 'openAccess'
        ];

        $submissionFile = Repo::submissionFile()->newDataObject($submissionFileData);
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
