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

namespace PKP\Plugins\ImportExport\CSV\Classes\Validations;

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
    ];

    static $issueRequiredHeaders = [
        'journalPath',
        'locale',
        'articleTitle',
        'authors',
        'datePublished',
    ];

    /**
     * Validates whether the row contains all headers.
     *
     * @param array $row
     *
     * @return bool
     */
    public static function validateRowHasAllFields($row)
    {
        return count($row) === count(self::$issueHeaders);
    }

    /**
     * Validates whether the row contains all required headers.
	 *
	 * @param object $row
	 *
	 * @return bool
     */
    public static function validateRowHasAllRequiredFields($row)
    {
		if (!empty($row->version) && !empty($row->versionIdentifier) && (int)$row->version > 1) {
			return true;
		}

        foreach(self::$issueRequiredHeaders as $requiredHeader) {
            if (!$row->{$requiredHeader}) {
                return false;
            }
        }

        return true;
    }
}
