<?php

/**
 * @file plugins/importexport/csv/classes/processors/HtmlGalleyProcessor.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2003-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class HtmlGalleyProcessor
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Processes HTML galley data with dependent files into the database.
 */

namespace APP\plugins\importexport\csv\classes\processors;

use APP\core\Application;
use APP\facades\Repo;
use PKP\core\Core;
use PKP\core\PKPString;
use PKP\services\PKPFileService;
use PKP\submissionFile\SubmissionFile;
use PKP\user\User;

class HtmlGalleyProcessor
{
    /**
     * Sanitize an HTML file's content using PKP's HTMLPurifier configuration.
     * The original document structure is preserved so HtmlArticleGalleyPlugin
     * can resolve dependent file references at view time.
     */
    public static function sanitizeHtmlFile(string $filePath): string
    {
        $htmlContent = file_get_contents($filePath);

        if ($htmlContent === false) {
            throw new \Exception(__('plugins.importexport.csv.errorWhileSanitizingHtmlGalley', ['filename' => basename($filePath)]));
        }

        return PKPString::stripUnsafeHtml($htmlContent);
    }

    /**
     * Create dependent submission files linked to an HTML galley's primary submission file.
     *
     * Dependent files (CSS, images, JS, fonts, etc.) are stored with:
     *  - fileStage = SubmissionFile::SUBMISSION_FILE_DEPENDENT
     *  - assocType = Application::ASSOC_TYPE_SUBMISSION_FILE
     *  - assocId  = $parentSubmissionFileId
     *
     * This matches the association pattern used by OJS core and HtmlArticleGalleyPlugin.
     */
    public static function createDependentFiles(
        array $dependentFiles,
        int $parentSubmissionFileId,
        string $sourceDir,
        string $destinationDir,
        object $data,
        int $submissionId,
        int $genreId,
        User $fileUploadUser,
        PKPFileService $fileService
    ): array {
        $dependentFileIds = [];

        foreach ($dependentFiles as $dependentFile) {
            $sourcePath = "{$sourceDir}/{$dependentFile}";
            $extension = pathinfo($dependentFile, PATHINFO_EXTENSION);
            $destPath = $destinationDir . '/' . uniqid() . '.' . $extension;

            try {
                $fileId = $fileService->add($sourcePath, $destPath);
            } catch (\Exception $e) {
                foreach ($dependentFileIds as $createdFileId) {
                    try {
                        $fileService->delete($createdFileId);
                    } catch (\Exception $cleanupError) {
                        error_log('Failed to cleanup dependent file ' . $createdFileId . ': ' . $cleanupError->getMessage());
                    }
                }
                throw new \Exception(__('plugins.importexport.csv.errorWhileSavingHtmlDependentFile', ['filename' => $dependentFile]));
            }

            $submissionFile = Repo::submissionFile()->newDataObject();
            $submissionFile->setData('submissionId', $submissionId);
            $submissionFile->setData('uploaderUserId', $fileUploadUser->getId());
            $submissionFile->setData('fileId', $fileId);
            $submissionFile->setData('genreId', $genreId);
            $submissionFile->setData('fileStage', SubmissionFile::SUBMISSION_FILE_DEPENDENT);
            $submissionFile->setData('assocType', Application::ASSOC_TYPE_SUBMISSION_FILE);
            $submissionFile->setData('assocId', $parentSubmissionFileId);
            $submissionFile->setData('createdAt', Core::getCurrentDate());
            $submissionFile->setData('updatedAt', Core::getCurrentDate());
            $submissionFile->setData('mimetype', PKPString::mime_content_type($sourcePath));
            $submissionFile->setData('locale', $data->locale);
            $submissionFile->setData('name', pathinfo($dependentFile, PATHINFO_BASENAME), $data->locale);
            $submissionFile->setDirectSalesPrice(0);
            $submissionFile->setSalesType('openAccess');

            $dependentFileIds[] = Repo::submissionFile()->add($submissionFile);
        }

        return $dependentFileIds;
    }
}
