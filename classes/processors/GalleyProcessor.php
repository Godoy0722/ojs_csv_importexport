<?php

/**
 * @file plugins/importexport/csv/classes/processors/GalleyProcessor.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class GalleyProcessor
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Processes the article galley data into the database.
 */

namespace APP\plugins\importexport\csv\classes\processors;

use APP\facades\Repo;
use APP\publication\Publication;
use PKP\config\Config;
use PKP\file\FileManager;
use PKP\services\PKPFileService;

class GalleyProcessor
{
    public static function process(int $submissionFileId, object $data, string $label, int $publicationId, string $extension): int
    {
        $galley = Repo::galley()->newDataObject();
        $galley->setLabel($label);
        $galley->setLocale($data->locale);
        $galley->setSequence(REALLY_BIG_NUMBER);
        $galley->setIsApproved(true);
        $galley->setData('submissionFileId', $submissionFileId);
        $galley->setData('publicationId', $publicationId);
        $galley->setName(mb_strtoupper($extension), $data->locale);

        if (!empty($data->doi)) {
            $galley->setStoredPubId('doi', $data->doi);
        }

        return Repo::galley()->add($galley);
    }

    /**
    * Copy galleys from base publication to new versioned publication
    * This replicates the behavior in OJS core's Repository::version() method
    */
   public static function copyGalleysFromBasePublication(
        Publication $basePublication,
        Publication $newPublication,
        FileManager $fileManager,
        string $format,
        PKPFileService $fileService
    ): void
    {
        $galleys = $basePublication->getData('galleys');

        if (!empty($galleys)) {
            foreach ($galleys as $galley) {
                $newGalley = clone $galley;
                $newGalley->setData('id', null);
                $newGalley->setData('publicationId', $newPublication->getId());
                $newGalley->setData('submissionFileId', null);

                $newGalleyId = Repo::galley()->add($newGalley);

                $originalSubmissionFileId = $galley->getData('submissionFileId');
                if ($originalSubmissionFileId) {
                    $originalSubmissionFile = Repo::submissionFile()->get($originalSubmissionFileId);

                    if ($originalSubmissionFile) {
                        $newSubmissionFile = clone $originalSubmissionFile;
                        $newSubmissionFile->setData('id', null);
                        $newSubmissionFile->setData('assocId', $newGalleyId);

                        $oldFileId = $originalSubmissionFile->getData('fileId');
                        $oldFile = app()->get('file')->get($oldFileId);

                        $submission = Repo::submission()->get($newPublication->getData('submissionId'));
                        $extension = $fileManager->parseFileExtension($oldFile->path);
                        $submissionDir = sprintf($format, $submission->getData('contextId'), $submission->getId());

                        $newFileId = $fileService->add(
                            Config::getVar('files', 'files_dir') . '/' . $oldFile->path,
                            $submissionDir . '/' . uniqid() . '.' . $extension
                        );

                        $newSubmissionFile->setData('fileId', $newFileId);
                        $newSubmissionFileId = Repo::submissionFile()->add($newSubmissionFile);

                        $newGalley = Repo::galley()->get($newGalleyId);
                        Repo::galley()->edit($newGalley, ['submissionFileId' => $newSubmissionFileId]);
                    }
                }
            }
        }
    }
}
