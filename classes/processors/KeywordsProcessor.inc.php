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

namespace PKP\Plugins\ImportExport\CSV\Classes\Processors;

use PKP\Plugins\ImportExport\CSV\Classes\CachedAttributes\CachedDaos;

class KeywordsProcessor
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
		if (empty($data->keywords) && !is_null($basePublication)) {
            $baseKeywords = $basePublication->getData('keywords');

            if (empty($baseKeywords)) {
                return;
            }

			$publicationDao = CachedDaos::getPublicationDao();
            $publication = $publicationDao->getById($publicationId);

            if ($publication) {
				$submissionKeywordDao = CachedDaos::getSubmissionKeywordDao();
				$submissionKeywordDao->insertKeywords($baseKeywords, $publicationId);
            }

            return;
        }

		$keywordsList = [$data->locale => array_map('trim', explode(';', $data->keywords))];

		if (count($keywordsList[$data->locale]) > 0) {
			$submissionKeywordDao = CachedDaos::getSubmissionKeywordDao();
			$submissionKeywordDao->insertKeywords($keywordsList, $publicationId);
		}
	}
}
