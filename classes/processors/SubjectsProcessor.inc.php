<?php

/**
 * @file plugins/importexport/csv/classes/processors/SubjectsProcessor.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2003-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubjectsProcessor
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Processes the subjects data into the database.
 */

namespace PKP\Plugins\ImportExport\CSV\Classes\Processors;

use PKP\Plugins\ImportExport\CSV\Classes\CachedAttributes\CachedDaos;

class SubjectsProcessor
{
    /**
	 * Processes data for Keywords
	 *
	 * @param object $data
	 * @param int $publicationId
	 * @param \Publication $basePublication
	 *
	 * @return void
	 */
	public static function process($data, $publicationId, $basePublication)
    {
		if (empty($data->subjects) && !is_null($basePublication)) {
            $baseSubjects = $basePublication->getData('subjects');

            if (empty($baseSubjects)) {
                return;
            }

			$publicationDao = CachedDaos::getPublicationDao();
            $publication = $publicationDao->getById($publicationId);

            if ($publication) {
				$submissionSubjectDao = CachedDaos::getSubmissionSubjectDao();
				$submissionSubjectDao->insertSubjects($baseSubjects, $publicationId);
            }

            return;
        }

		$subjectsList = [$data->locale => array_map('trim', explode(';', $data->subjects))];

		if (count($subjectsList[$data->locale]) > 0) {
			$submissionSubjectDao = CachedDaos::getSubmissionSubjectDao();
			$submissionSubjectDao->insertSubjects($subjectsList, $publicationId);
		}
	}

	/**
     * Process subjects for multi-locale import (adds subjects in new locale)
	 *
	 * @param object $data
	 * @param int $publicaitonId
	 *
	 * @return void
     */
    public static function processMultiLocale($data, $publicationId)
    {
        if (empty($data->subjects)) {
            return; // No new subjects to add
        }

        if (!CachedDaos::getPublicationDao()->getById($publicationId)) {
            return;
        }

        $newSubjects = [$data->locale => array_map('trim', explode(';', $data->subjects))];

        $submissionSubjectDao = CachedDaos::getSubmissionSubjectDao();
        $submissionSubjectDao->insertSubjects($newSubjects, $publicationId, false);
    }
}
