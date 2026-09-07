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
use APP\publication\Publication;
use APP\section\Section;

class SectionsProcessor
{
	public static function process(object $data, int $journalId, ?Publication $basePublication = null): ?Section
    {
        if (empty($data->sectionTitle) && empty($data->sectionAbbrev) && !is_null($basePublication)) {
            $baseSectionId = $basePublication->getData('sectionId');
            $locale = $basePublication->getData('locale');

            if (!is_null($baseSectionId)) {
                $section = CachedEntities::getCachedSectionById($baseSectionId, $journalId, $locale);

                if (!is_null($section)) {
                    return $section;
                }
            }
        }

        if (empty($data->sectionTitle) && empty($data->sectionAbbrev)) {
            return null;
        }

        $section = CachedEntities::getCachedSection($data->sectionTitle, $data->sectionAbbrev, $data->locale, $journalId);

		if (!is_null($section)) {
			return $section;
		}

        return static::createSection($data, $journalId);
	}

    private static function createSection(object $data, int $journalId): Section
    {
        // A row may carry only sectionAbbrev; the abbreviation then names the section as well.
        $sectionTitle = trim($data->sectionTitle ?? '') ?: trim($data->sectionAbbrev ?? '');

        $section = Repo::section()->newDataObject();

        $section->setContextId($journalId);
        $section->setSequence(REALLY_BIG_NUMBER);
        $section->setEditorRestricted(false);
        $section->setMetaIndexed(true);
        $section->setMetaReviewed(true);
        $section->setAbstractsNotRequired(false);
        $section->setAbstractWordCount(REALLY_BIG_NUMBER);
        $section->setHideTitle(false);
        $section->setHideAuthor(false);
        $section->setIsInactive(false);
        $section->setTitle($sectionTitle, $data->locale);
        $section->setAbbrev(mb_strtoupper(trim($data->sectionAbbrev)), $data->locale);
        $section->setIdentifyType('', $data->locale);
        $section->setPolicy('', $data->locale);

        $sectionId = Repo::section()->add($section);

        $createdSection = Repo::section()->get($sectionId, $journalId);
        CachedEntities::indexSection($createdSection, $journalId);

        return $createdSection;
    }
}
