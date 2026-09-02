<?php

/**
 * @file plugins/importexport/csv/classes/processors/HtmlGalleyProcessor.php
 *
 * Copyright (c) 2014-2026 Simon Fraser University
 * Copyright (c) 2003-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class HtmlGalleyProcessor
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Processes HTML galley data with dependent files into the database.
 */

namespace PKP\Plugins\ImportExport\CSV\Classes\Processors;

import('lib.pkp.classes.submission.SubmissionFile');

use Illuminate\Database\Capsule\Manager as Capsule;
use PKP\Plugins\ImportExport\CSV\Classes\CachedAttributes\CachedDaos;

class HtmlGalleyProcessor
{
    /**
     * Prepare an HTML galley file for import: sanitize content and rebuild a
     * complete HTML document so OJS detects text/html and the viewer plugin
     * can render it.
     *
     * @param string $filePath
     * @param array $dependentFiles
     *
     * @return string
     */
    public static function prepareHtmlGalleyFile($filePath, array $dependentFiles = [])
    {
        $htmlContent = file_get_contents($filePath);

        if ($htmlContent === false) {
            throw new \Exception(__('plugins.importexport.csv.errorWhileSanitizingHtmlGalley', ['filename' => basename($filePath)]));
        }

        $title = self::extractTitle($htmlContent, $filePath);
        $bodyContent = self::extractBodyContent($htmlContent);
        $purifiedBody = self::purifyHtmlGalleyContent($bodyContent);

        return self::buildHtmlDocument($title, $purifiedBody, $dependentFiles);
    }

    /**
     * @deprecated Use prepareHtmlGalleyFile()
     *
     * @param string $filePath
     *
     * @return string
     */
    public static function sanitizeHtmlFile($filePath)
    {
        return self::prepareHtmlGalleyFile($filePath);
    }

    /**
     * @param string $fileId
     * @param string $mimetype
     *
     * @return void
     */
    public static function setFileMimetype($fileId, $mimetype)
    {
        Capsule::table('files')
            ->where('file_id', (int) $fileId)
            ->update(['mimetype' => $mimetype]);
    }

    /**
     * Create dependent submission files linked to an HTML galley's primary submission file.
     *
     * @param array $dependentFiles
     * @param int $parentSubmissionFileId
     * @param string $sourceDir
     * @param string $destinationDir
     * @param object $data
     * @param int $submissionId
     * @param int $genreId
     * @param \User $fileUploadUser
     * @param \PKPFileService $fileService
     *
     * @return int[]
     */
    public static function createDependentFiles(
        $dependentFiles,
        $parentSubmissionFileId,
        $sourceDir,
        $destinationDir,
        $data,
        $submissionId,
        $genreId,
        $fileUploadUser,
        $fileService
    ) {
        $dependentFileIds = [];
        $submissionFileDao = CachedDaos::getSubmissionFileDao();

        foreach ($dependentFiles as $dependentFile) {
            $sourcePath = "{$sourceDir}/{$dependentFile}";
            $extension = pathinfo($dependentFile, PATHINFO_EXTENSION);
            $destPath = $destinationDir . '/' . uniqid() . '.' . $extension;

            try {
                $fileId = $fileService->add($sourcePath, $destPath);
                $mimetype = self::getMimetypeForDependentFile($sourcePath, $dependentFile);
                self::setFileMimetype($fileId, $mimetype);
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

            /** @var \SubmissionFile $submissionFile */
            $submissionFile = $submissionFileDao->newDataObject();
            $submissionFile->setData('submissionId', $submissionId);
            $submissionFile->setData('uploaderUserId', $fileUploadUser->getId());
            $submissionFile->setData('fileId', $fileId);
            $submissionFile->setData('genreId', $genreId);
            $submissionFile->setData('fileStage', SUBMISSION_FILE_DEPENDENT);
            $submissionFile->setData('assocType', ASSOC_TYPE_SUBMISSION_FILE);
            $submissionFile->setData('assocId', $parentSubmissionFileId);
            $submissionFile->setData('createdAt', \Core::getCurrentDate());
            $submissionFile->setData('updatedAt', \Core::getCurrentDate());
            $submissionFile->setData('mimetype', $mimetype);
            $submissionFile->setData('locale', $data->locale);
            $submissionFile->setData('name', pathinfo($dependentFile, PATHINFO_BASENAME), $data->locale);
            $submissionFile->setDirectSalesPrice(0);
            $submissionFile->setSalesType('openAccess');

            $dependentFileId = $submissionFileDao->insertObject($submissionFile);
            $dependentFileIds[] = $dependentFileId;
        }

        return $dependentFileIds;
    }

    /**
     * @param string $htmlContent
     * @param string $filePath
     *
     * @return string
     */
    protected static function extractTitle($htmlContent, $filePath)
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $htmlContent, $matches)) {
            $title = trim(strip_tags($matches[1]));
            if ($title !== '') {
                return $title;
            }
        }

        return pathinfo($filePath, PATHINFO_FILENAME);
    }

    /**
     * @param string $htmlContent
     *
     * @return string
     */
    protected static function extractBodyContent($htmlContent)
    {
        if (preg_match('/<body[^>]*>(.*)<\/body>/is', $htmlContent, $matches)) {
            return $matches[1];
        }

        return $htmlContent;
    }

    /**
     * @param string $input
     *
     * @return string
     */
    protected static function purifyHtmlGalleyContent($input)
    {
        return \PKPString::stripUnsafeHtml($input);
    }

    /**
     * @param string $title
     * @param string $bodyContent
     * @param array $dependentFiles
     *
     * @return string
     */
    protected static function buildHtmlDocument($title, $bodyContent, array $dependentFiles)
    {
        $headLinks = '';

        foreach ($dependentFiles as $dependentFile) {
            $basename = basename($dependentFile);
            $extension = strtolower(pathinfo($basename, PATHINFO_EXTENSION));

            if ($extension === 'css') {
                $headLinks .= '<link rel="stylesheet" href="' . htmlspecialchars($basename, ENT_QUOTES, 'UTF-8') . '" type="text/css">' . "\n";
            }
        }

        $escapedTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');

        return "<!DOCTYPE html>\n"
            . "<html lang=\"en\">\n"
            . "<head>\n"
            . "<meta charset=\"utf-8\">\n"
            . "<title>{$escapedTitle}</title>\n"
            . $headLinks
            . "</head>\n"
            . "<body>\n"
            . trim($bodyContent) . "\n"
            . "</body>\n"
            . "</html>\n";
    }

    /**
     * @param string $sourcePath
     * @param string $filename
     *
     * @return string
     */
    protected static function getMimetypeForDependentFile($sourcePath, $filename)
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $map = [
            'css' => 'text/css',
            'html' => 'text/html',
            'htm' => 'text/html',
            'js' => 'application/javascript',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
        ];

        if (isset($map[$extension])) {
            return $map[$extension];
        }

        $detected = \PKPString::mime_content_type($sourcePath);
        return $detected ?: 'application/octet-stream';
    }
}
