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

use APP\plugins\importexport\csv\classes\cachedAttributes\CachedDaos;

class SubjectsProcessor
{
	public static function process(object $data, int $publicationId)
    {
		$subjectsList = [$data->locale => array_map('trim', explode(';', $data->subjects))];

		if (!empty($subjectsList[$data->locale])) {
			$submissionSubjectDao = CachedDaos::getSubmissionSubjectDao();
			$submissionSubjectDao->insertSubjects($subjectsList, $publicationId);
		}
	}
}
