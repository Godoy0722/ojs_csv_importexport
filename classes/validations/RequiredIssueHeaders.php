<?php

/**
 * @file plugins/importexport/csv/classes/validations/RequiredIssueHeders.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2003-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RequiredIssueHeders
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief class to validate headers on the issue CSV files
 */

namespace APP\plugins\importexport\csv\classes\validations;

class RequiredIssueHeaders
{
    static $issueHeaders = [
        'journalPath',
        'locale',
        'articleTitle',
        'articlePrefix',
        'articleSubtitle',
        'articleAbstract',
        'articleFilepath',
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
        'sectionTitle',
        'sectionAbbrev',
        'issueTitle',
        'issueVolume',
        'issueNumber',
        'issueYear',
        'issueDescription',
        'datePublished',
        'startPage',
        'endPage',
    ];

    static $issueRequiredHeaders = [
        'journalPath',
        'locale',
        'articleTitle',
        'articleAbstract',
        'articleFilepath',
        'authors',
        'issueTitle',
        'issueVolume',
        'issueNumber',
        'issueYear',
        'datePublished',
    ];

    public static function validateRowHasAllFields(array $row): bool
    {
        return count($row) === count(self::$issueHeaders);
    }

    public static function validateRowHasAllRequiredFields(object $row): bool
    {
        foreach(self::$issueRequiredHeaders as $requiredHeader) {
            if (!$row->{$requiredHeader}) {
                return false;
            }
        }

        return true;
    }
}
