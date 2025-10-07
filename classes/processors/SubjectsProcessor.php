<?php

/**
 * @file plugins/importexport/csv/classes/processors/SubjectsProcessor.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
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
use APP\publication\Publication;

class SubjectsProcessor
{
	public static function process(object $data, int $publicationId, ?Publication $basePublication = null)
    {
        if (empty($data->subjects) && !is_null($basePublication)) {
            $baseSubjects = $basePublication->getData('subjects');
            if (empty($baseSubjects)) {
                return;
            }

            $publication = Repo::publication()->get($publicationId);
            if ($publication) {
                Repo::publication()->edit($publication, ['subjects' => $baseSubjects]);
            }

            return;
        }

		$subjectsList = [$data->locale => array_map('trim', explode(';', $data->subjects))];

		if (empty($subjectsList[$data->locale])) {
            return;
        }

        $publication = Repo::publication()->get($publicationId);

        if (!$publication) {
            return;
        }

        Repo::publication()->edit($publication, ['subjects' => $subjectsList]);
	}
}
