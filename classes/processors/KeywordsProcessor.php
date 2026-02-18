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
use APP\publication\Publication;

class KeywordsProcessor
{
    public static function process(object $data, int $publicationId, ?Publication $basePublication = null)
    {
        if (empty($data->keywords) && is_null($basePublication)) {
            return;
        }

        if (empty($data->keywords) && !is_null($basePublication)) {
            $baseKeywords = $basePublication->getData('keywords');
            if (empty($baseKeywords)) {
                return;
            }

            $publication = Repo::publication()->get($publicationId);
            if ($publication) {
                Repo::publication()->edit($publication, ['keywords' => $baseKeywords]);
            }

            return;
        }

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

    /**
     * Process keywords for multi-locale import (adds keywords in new locale)
     */
    public static function processMultiLocale(object $data, int $publicationId): void
    {
        if (empty($data->keywords)) {
            return; // No new keywords to add
        }

        $publication = Repo::publication()->get($publicationId);
        if (!$publication) {
            return;
        }

        $existingKeywords = $publication->getData('keywords') ?? [];

        $newKeywords = array_map('trim', explode(';', $data->keywords));
        $existingKeywords[$data->locale] = $newKeywords;

        Repo::publication()->edit($publication, ['keywords' => $existingKeywords]);
    }
}
