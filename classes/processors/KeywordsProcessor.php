<?php

/**
 * @file plugins/importexport/csv/classes/processors/KeywordsProcessor.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2003-2025 John Willinsky
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
use APP\plugins\importexport\csv\classes\cachedAttributes\CachedDaos;
use APP\publication\Publication;

class KeywordsProcessor
{
    public static function process(object $data, int $publicationId, ?Publication $basePublication = null)
    {
        $submissionKeywordDao = CachedDaos::getSubmissionKeywordDao();

        if (empty($data->keywords) && !is_null($basePublication)) {
            $baseKeywords = $submissionKeywordDao->getKeywords($basePublication->getId());
            if (empty($baseKeywords)) {
                return;
            }

            $publication = Repo::publication()->get($publicationId);
            if ($publication) {
                $submissionKeywordDao->insertKeywords($baseKeywords, $publicationId);
            }

            return;
        }

		$keywordsList = [$data->locale => array_map('trim', explode(';', $data->keywords))];
        if (empty($keywordsList[$data->locale])) {
            return;
        }

        $submissionKeywordDao->insertKeywords($keywordsList, $publicationId);
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

        $submissionKeywordDao = CachedDaos::getSubmissionKeywordDao();
        $existingKeywords = $submissionKeywordDao->getKeywords($publicationId) ?? [];

        $existingKeywords[$data->locale] = array_map('trim', explode(';', $data->keywords));

        if (!empty($existingKeywords[$data->locale])) {
            $submissionKeywordDao->insertKeywords($existingKeywords, $publicationId);
        }
    }
}
