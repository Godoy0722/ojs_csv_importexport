<?php

/**
 * @file plugins/importexport/csv/classes/validations/RequiredIssueHeaders.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2003-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RequiredIssueHeaders
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Class to validate headers in the issue CSV files
 */

namespace APP\plugins\importexport\csv\classes\validations;

class RequiredIssueHeaders
{
    static $issueHeaders = [
        'journalPath',
        'locale',
		'versionIdentifier',
		'version',
        'articlePrefix',
        'articleTitle',
        'articleSubtitle',
        'articleAbstract',
        'authors',
        'keywords',
        'subjects',
        'coverage',
        'categories',
        'doi',
        'coverImageFilename',
        'coverImageAltText',
        'galleyFilenames',
        'galleyLabels',
        'galleyViews',
        'htmlGalley',
        'suppFilenames',
        'suppLabels',
        'suppDescriptions',
        'sectionTitle',
        'sectionAbbrev',
        'issueTitle',
        'issueVolume',
        'issueNumber',
        'issueYear',
        'issueDescription',
        'issuePublicationDate',
        'datePublished',
        'startPage',
        'endPage',
		'copyrightYear',
		'copyrightHolder',
		'licenseUrl',
        'references',
        'username',
        'funders',
        'supportingAgencies',
        'articleViews',
    ];

    static $issueRequiredHeaders = [
        'journalPath',
        'locale',
        'articleTitle',
        'authors',
        'datePublished',
    ];

    public static function validateRowHasAllFields(array $row): bool
    {
        return count($row) === count(static::$issueHeaders);
    }

    public static function isMultiVersionOrLocale(object $row, array $processedArticles = []): bool
    {
        if (!empty($row->version) && !empty($row->versionIdentifier) && (int)$row->version > 1) {
            return true;
        }

        if (!empty($row->versionIdentifier) && !empty($row->version)) {
            $identifier = $row->versionIdentifier;
            $version = (int)$row->version;

            if (isset($processedArticles[$identifier][$version]) && !empty($processedArticles[$identifier][$version])) {
                return !isset($processedArticles[$identifier][$version][$row->locale]);
            }
        }

        return false;
    }

    public static function validateRowHasAllRequiredFields(object $row, array $processedArticles = []): bool
    {
        if (static::isMultiVersionOrLocale($row, $processedArticles)) {
            return true;
        }

        foreach (static::$issueRequiredHeaders as $requiredHeader) {
            if (!$row->{$requiredHeader}) {
                return false;
            }
        }

        return true;
    }
}
