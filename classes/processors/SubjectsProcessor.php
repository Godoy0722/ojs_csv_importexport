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

namespace APP\plugins\importexport\csv\classes\processors;

use APP\facades\Repo;
use APP\plugins\importexport\csv\classes\cachedAttributes\CachedDaos;
use APP\publication\Publication;

class SubjectsProcessor
{
	public static function process(object $data, int $publicationId, ?Publication $basePublication = null)
    {
        $submissionSubjectDao = CachedDaos::getSubmissionSubjectDao();

        if (empty($data->subjects) && !is_null($basePublication)) {
            $baseSubjects = $submissionSubjectDao->getSubjects($basePublication->getId());
            if (empty($baseSubjects)) {
                return;
            }

            $publication = Repo::publication()->get($publicationId);
            if ($publication) {
                $submissionSubjectDao->insertSubjects($baseSubjects, $publicationId);
            }

            return;
        }

		$subjectsList = [$data->locale => array_map('trim', explode(';', $data->subjects))];

		if (!empty($subjectsList[$data->locale])) {
			$submissionSubjectDao->insertSubjects($subjectsList, $publicationId);
		}
	}

    /**
     * Process subjects for multi-locale import (adds subjects in new locale)
     */
    public static function processMultiLocale(object $data, int $publicationId): void
    {
        if (empty($data->subjects)) {
            return; // No new subjects to add
        }

        $publication = Repo::publication()->get($publicationId);
        if (!$publication) {
            return;
        }

        $submissionSubjectDao = CachedDaos::getSubmissionSubjectDao();
        $existingSubjects = $submissionSubjectDao->getSubjects($publicationId) ?? [];

        $newSubjects = array_map('trim', explode(';', $data->subjects));
        if (empty($newSubjects)) {
            return;
        }

        $existingSubjects[$data->locale] = $newSubjects;
        $submissionSubjectDao->insertSubjects($existingSubjects, $publicationId);
    }
}
