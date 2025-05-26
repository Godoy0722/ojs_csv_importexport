<?php

/**
 * @file plugins/importexport/csv/classes/processors/KeywordsProcessor.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class KeywordsProcessor
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Processes the keywords data into the database.
 */

namespace APP\plugins\importexport\csv\classes\processors;

use APP\facades\Repo;

class KeywordsProcessor
{
    public static function process(object $data, int $publicationId)
    {
		$keywordsList = [$data->locale => array_map('trim', explode(';', $data->keywords))];

        if (empty($keywordsList[$data->locale])) {
            return;
        }

        $publication = Repo::publication()->get($publicationId);

        if (!$publication) {
            return;
        }

        Repo::publication()->edit($publication, ['keywords' => $keywordsList]);
	}
}
