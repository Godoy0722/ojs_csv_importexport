<?php

/**
 * @file plugins/importexport/csv/classes/validations/RequiredIssueHeaders.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
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
        return count($row) === count(self::$issueHeaders);
    }

    public static function validateRowHasAllRequiredFields(object $row, array $processedArticles = []): bool
    {
        $isMultiVersionOrLocale = !empty($row->versionIdentifier)
            && !empty($row->version)
            && isset($processedArticles[$row->versionIdentifier]);

        if ($isMultiVersionOrLocale) {
            return true;
        }

        foreach (self::$issueRequiredHeaders as $requiredHeader) {
            if (!$row->{$requiredHeader}) {
                return false;
            }
        }

        return true;
    }
}
