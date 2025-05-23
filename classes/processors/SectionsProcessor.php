<?php

/**
 * @file plugins/importexport/csv/classes/processors/SectionsProcessor.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2003-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SectionsProcessor
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Processes the section data into the database.
 */

namespace APP\plugins\importexport\csv\classes\processors;

use APP\facades\Repo;
use APP\plugins\importexport\csv\classes\cachedAttributes\CachedEntities;
use APP\section\Section;

class SectionsProcessor
{
	public static function process(object $data, int $journalId): Section
    {
        $section = CachedEntities::getCachedSection($data->sectionTitle, $data->sectionAbbrev, $data->locale, $journalId);

		if (!is_null($section)) {
			return $section;
		}

        $sectionData = [
            'contextId' => $journalId,
            'sequence' => REALLY_BIG_NUMBER,
            'editorRestricted' => false,
            'metaIndexed' => true,
            'metaReviewed' => true,
            'abstractsNotRequired' => false,
            'hideTitle' => false,
            'hideAuthor' => false,
            'isInactive' => false,
            $data->locale => [
                'title' => $data->sectionTitle,
                'abbrev' => mb_strtoupper(trim($data->sectionAbbrev)),
                'identifyType' => '',
                'policy' => '',
            ]
        ];

        $section = Repo::section()->newDataObject($sectionData);
        $sectionId = Repo::section()->add($section);

        return Repo::section()->get($sectionId, $journalId);
	}
}
