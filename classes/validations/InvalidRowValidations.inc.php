<?php

/**
 * @file plugins/importexport/csv/classes/validations/InvalidRowValidations.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2003-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class InvalidRowValidations
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Class to validate all necessary requirements for a CSV row to be valid
 */

namespace PKP\Plugins\ImportExport\CSV\Classes\Validations;

use PKP\Plugins\ImportExport\CSV\Classes\CachedAttributes\CachedEntities;
use PKP\Plugins\ImportExport\CSV\Classes\Processors\FundersProcessor;

class InvalidRowValidations
{

    /** @var string[] */
    static array $coverImageAllowedTypes = ['gif', 'jpg', 'png', 'webp'];

    /**
     * Validates whether the CSV row contains all fields. Returns the reason if an error occurred,
     * or null if everything is correct.
	 *
	 * @param array $fields
	 * @param int $expectedSize
	 *
	 * @return string|null
     */
    public static function validateRowContainAllFields($fields, $expectedSize)
    {
        return count($fields) < $expectedSize
            ? __('plugins.importexport.csv.rowDoesntContainAllFields')
            : null;
    }

    /**
     * Validates whether the CSV row contains all required fields. Returns the reason if an error occurred,
     * or null if everything is correct.
	 *
	 * @param object $data
	 * @param callable $requiredFieldsValidation
	 *
	 * @return ?string
     */
    public static function validateRowHasAllRequiredFields($data, $requiredFieldsValidation)
    {
        return !$requiredFieldsValidation($data)
            ? __('plugins.importexport.csv.verifyRequiredFieldsForThisRow')
            : null;
    }


    /**
     * Validates whether the article file exists and is readable. Returns the reason if an error occurred,
     * or null if everything is correct.
	 *
	 * @param string $coverImageFilename
	 * @param string $sourceDir
	 *
	 * @return string|null
     */
    public static function validateArticleFileIsValid($coverImageFilename, $sourceDir)
    {
        $articleCoverImagePath = "{$sourceDir}/{$coverImageFilename}";

        return !is_readable($articleCoverImagePath)
            ? __('plugins.importexport.csv.invalidArticleFile')
            : null;
    }

    /**
     * Validates the article cover image. Returns the reason if an error occurred,
     * or null if everything is correct.
	 *
	 * @param string $coverImageFilename
	 * @param string $sourceDir
	 *
	 * @return string|null
     */
    public static function validateCoverImageIsValid($coverImageFilename, $sourceDir)
    {
        $articleCoverImagePath = "{$sourceDir}/{$coverImageFilename}";

        if (!is_readable($articleCoverImagePath)) {
            return __('plugins.importexport.csv.invalidBookCoverImage');
        }

        $coverImgExtension = pathinfo(mb_strtolower($coverImageFilename), PATHINFO_EXTENSION);

        if (!in_array($coverImgExtension, self::$coverImageAllowedTypes)) {
            return __('plugins.importexport.csv.invalidFileExtension');
        }

        return null;
    }

    /**
     * Perform all necessary validations for article galleys. Returns the reason if an error occurred,
     * or null if everything is correct.
	 *
	 * @param string $galleyFilenames
	 * @param string $galleyLabels
	 * @param string $sourceDir
	 *
	 * @return string|null
     */
    public static function validateArticleGalleys($galleyFilenames, $galleyLabels, $sourceDir)
    {
        $galleyFilenamesArray = explode(';', $galleyFilenames);
        $galleyLabelsArray = explode(';', $galleyLabels);

        if (count($galleyFilenamesArray) !== count($galleyLabelsArray)) {
            return __('plugins.importexport.csv.invalidNumberOfLabelsAndGalleys');
        }

        foreach($galleyFilenamesArray as $galleyFilename) {
            $galleyPath = "{$sourceDir}/{$galleyFilename}";
            if (!is_readable($galleyPath)) {
                return __('plugins.importexport.csv.invalidGalleyFile', ['filename' => $galleyFilename]);
            }
        }

        return null;
    }

    /**
     * Perform all necessary validations for supplementary files. Returns the reason if an error occurred,
     * or null if everything is correct.
	 *
	 * @param string $suppFilenames
	 * @param string $suppLabels
	 * @param string $sourceDir
	 *
	 * @return string|null
     */
    public static function validateSupplementaryFiles($suppFilenames, $suppLabels, $sourceDir)
    {
        $suppFilenamesArray = explode(';', $suppFilenames);
        $suppLabelsArray = explode(';', $suppLabels);

        if (count($suppFilenamesArray) !== count($suppLabelsArray)) {
            return __('plugins.importexport.csv.invalidNumberOfLabelsAndSupplementaryFiles');
        }

        foreach($suppFilenamesArray as $suppFilename) {
            $suppPath = "{$sourceDir}/{$suppFilename}";
            if (!is_readable($suppPath)) {
                return __('plugins.importexport.csv.invalidSupplementaryFile', ['filename' => $suppFilename]);
            }
        }

        return null;
    }

	/**
     * Validates the supplementary descriptions count. Returns the reason if an error occurred,
     * or null if everything is correct.
     */
    public static function validateSupplementaryDescriptions(string $suppFilenames, string $suppLabels, ?string $suppDescriptions): ?string
    {
        if (empty($suppDescriptions)) {
            return null; // descriptions are optional
        }

        $suppFilenamesArray = array_map('trim', explode(';', $suppFilenames));
        $suppLabelsArray = array_map('trim', explode(';', $suppLabels));
        $suppDescriptionsArray = array_map('trim', explode(';', $suppDescriptions));

        if (
            count($suppDescriptionsArray) !== count($suppFilenamesArray) ||
            count($suppDescriptionsArray) !== count($suppLabelsArray)
        ) {
            return __('plugins.importexport.csv.invalidNumberOfDescriptionsAndSupplementaryFiles');
        }

        return null;
    }

    /**
     * Validates whether the journal is valid for the CSV row. Returns the reason if an error occurred,
     * or null if everything is correct.
	 *
	 * @param \Journal|null $journal
	 * @param string $journalPath
	 *
	 * @return string|null
     */
    public static function validateJournalIsValid($journal, $journalPath)
    {
        return !$journal ? __('plugins.importexport.csv.unknownJournal', ['journalPath' => $journalPath]) : null;
    }

    /**
     * Validates if the journal supports the locale provided in the CSV row. Returns the reason if an error occurred
     * or null if everything is correct.
	 *
	 * @param \Journal|null $journal
	 * @param string $locale
	 *
	 * @return string|null
     */
    public static function validateJournalLocale($journal, $locale)
    {
        $supportedLocales = $journal->getSupportedSubmissionLocales();
        if (!is_array($supportedLocales) || count($supportedLocales) < 1) {
            $supportedLocales = [$journal->getPrimaryLocale()];
        }

        return !in_array($locale, $supportedLocales)
            ? __('plugins.importexport.csv.unknownLocale', ['locale' => $locale])
            : null;
    }

    /**
     * Validates if a genre exists for the name provided in the CSV row. Returns the reason if an error occurred
     * or null if everything is correct.
	 *
	 * @param int|null $genreId
	 * @param string $genreName
	 *
	 * @return string|null
     */
    public static function validateGenreIdValid($genreId, $genreName)
    {
        return !$genreId ? __('plugins.importexport.csv.noGenre', ['genreName' => $genreName]) : null;
    }

    /**
     * Validates if the user group ID is valid. Returns the reason if an error occurred
     * or null if everything is correct.
	 *
	 * @param int|null $userGroupId
	 * @param string $journalPath
	 *
	 * @return string|null
     */
    public static function validateUserGroupId($userGroupId, $journalPath)
    {
        return !$userGroupId
            ? __('plugins.importexport.csv.noAuthorGroup', ['journal' => $journalPath])
            : null;
    }

    /**
     * Validates if all user groups are valid. Returns the reason if an error occurred
     * or null if everything is correct.
	 *
	 * @param array $roles
	 * @param int $journalId
	 * @param string $locale
	 *
	 * @return string|null
     */
    public static function validateAllUserGroupsAreValid($roles, $journalId, $locale)
    {
        $userGroups = CachedEntities::getCachedUserGroupsByJournalId($journalId);

        $allDbRoles = 0;
        foreach ($roles as $role) {
            $matchingGroups = array_filter($userGroups, function($userGroup) use ($role, $locale) {
                return mb_strtolower($userGroup->getName($locale)) === mb_strtolower($role);
            });
            $allDbRoles += count($matchingGroups);
        }

        return $allDbRoles !== count($roles)
            ? __('plugins.importexport.csv.roleDoesntExist', ['role' => $role])
            : null;
    }

	/**
	 * Validates whether version field is valid when versionIdentifier is provided
	 *
	 * @param object $row
	 *
	 * @return ?string
	 */
	public static function validateVersionFields($row)
	{
		if (!empty($row->versionIdentifier) && empty($row->version)) {
			return __('plugins.importexport.csv.versionRequiredWhenIdentifierProvided');
		}

		if (!empty($data->version)) {
			if (!empty($row->version) && ((int)$row->version < 1 || !is_numeric($row->version))) {
				return __('plugins.importexport.csv.invalidVersionFields');
			}
		}

		return null;
	}

	/**
     * Validates that no duplicate version exists for the same preprint identifier
     * in the current import session
	 *
	 * @param object $data
	 * @param array $processedPreprints
	 *
	 * @return ?string
     */
    public static function validateNoDuplicateVersion($data, $processedPreprints): ?string
    {
        $identifier = $data->versionIdentifier;
        $version = (int)$data->version;
        $locale = $data->locale;

        if (isset($processedPreprints[$identifier][$version][$locale])) {
            return __('plugins.importexport.csv.duplicateArticleVersionLocaleFound', [
                'identifier' => $identifier,
                'version' => $version,
                'locale' => $locale
            ]);
        }

        return null;
    }

    /**
     * Validates if the subscription dates are valid. Returns the reason if an error occurred
     * or null if everything is correct.
	 *
	 * @param string $startDate
	 * @param string $endDate
     * @param string $dateFormat
	 *
	 * @return string|null
     */
    public static function validateSubscriptionDates($startDate, $endDate, $dateFormat = 'Y-m-d')
    {
        $startDateObj = \DateTime::createFromFormat($dateFormat, $startDate);
        if (!$startDateObj) {
            return __('plugins.importexport.csv.invalidStartDate', ['date' => $startDate]);
        }

        $endDateObj = \DateTime::createFromFormat($dateFormat, $endDate);
        if (!$endDateObj) {
            return __('plugins.importexport.csv.invalidEndDate', ['date' => $endDate]);
        }

        if ($endDateObj <= $startDateObj) {
            return __('plugins.importexport.csv.endDateBeforeStartDate');
        }

        return null;
    }

    /**
     * Validates if the subscription type is valid. Returns the reason if an error occurred
     * or null if everything is correct.
	 *
	 * @param \SubscriptionType|null $subscriptionType
	 * @param int $subscriptionTypeId
	 * @param int $journalId
	 *
	 * @return string|null
     */
    public static function validateSubscriptionType($subscriptionType, $subscriptionTypeId, $journalId)
    {
		return !$subscriptionType
			? __('plugins.importexport.csv.subscriptionTypeDoesntExist', ['subscriptionTypeId' => $subscriptionTypeId])
			: null;
    }

	/**
	 * @param object $data
	 *
	 * @return ?string
	 */
	public static function validateArticleVersioningFields($data)
    {
        if (!empty($data->versionIdentifier) && empty($data->version)) {
            return __('plugins.importexport.csv.versionRequiredWhenIdentifierProvided');
        }

        if (!empty($data->version)) {
            if (!is_numeric($data->version) || (int)$data->version < 1) {
                return __('plugins.importexport.csv.versionMustBePositiveInteger');
            }
        }

        return null;
    }

    /**
     * Checks if a version exists in any locale (used for multi-locale imports)
	 *
	 * @param object $data
	 * @param array $proceedArticles
	 *
	 * @return bool
     */
    public static function versionExistsInAnyLocale($data, $processedArticles)
    {
        $identifier = $data->versionIdentifier;
        $version = (int)$data->version;

        return isset($processedArticles[$identifier][$version]) &&
               !empty($processedArticles[$identifier][$version]);
    }

	/**
     * Validates the references file. Returns the reason if an error occurred,
     * or null if everything is correct.
	 *
	 * @param ?string $referencesFilename The CSV column with the references value
	 * @param string $sourceDir The source dir to retrieve the references file if exists.
	 *
	 * @return string|null
     */
    public static function validateReferencesFile($referencesFilename, $sourceDir)
    {
        if (empty($referencesFilename)) {
            return null; // References file is optional
        }

        $referencesFilePath = "{$sourceDir}/{$referencesFilename}";

        if (!is_readable($referencesFilePath)) {
            return __('plugins.importexport.csv.invalidReferencesFile', ['filename' => $referencesFilename]);
        }

        $extension = pathinfo(mb_strtolower($referencesFilename), PATHINFO_EXTENSION);
        if ($extension !== 'txt') {
            return __('plugins.importexport.csv.invalidReferencesFileExtension');
        }

        return null;
    }

    /**
     * Perform all necessary validations for HTML galleys.
     *
     * @param string|null $htmlGalley
     * @param string $sourceDir
     *
     * @return string|null
     */
    public static function validateHtmlGalleys($htmlGalley, $sourceDir)
    {
        if (empty(trim($htmlGalley ?? ''))) {
            return null;
        }

        $htmlGalleyFiles = array_filter(array_map('trim', explode(';', $htmlGalley)), function($f) {
            return $f !== '';
        });

        if (empty($htmlGalleyFiles)) {
            return null;
        }

        $firstFile = $htmlGalleyFiles[0];
        $firstExtension = mb_strtolower(pathinfo($firstFile, PATHINFO_EXTENSION));

        if (!in_array($firstExtension, ['html', 'htm'])) {
            return __('plugins.importexport.csv.invalidHtmlGalleyFirstFile', ['filename' => $firstFile]);
        }

        foreach ($htmlGalleyFiles as $file) {
            $filePath = "{$sourceDir}/{$file}";
            if (!is_readable($filePath)) {
                return __('plugins.importexport.csv.invalidHtmlGalleyFile', ['filename' => $file]);
            }
        }

        return null;
    }

    /**
     * Validates the galleyViews field.
     *
     * @param string|null $galleyViews
     * @param string|null $galleyLabels
     *
     * @return string|null
     */
    public static function validateGalleyViews($galleyViews, $galleyLabels)
    {
        if (empty($galleyViews)) {
            return null;
        }

        if (empty($galleyLabels)) {
            return __('plugins.importexport.csv.galleyViewsWithoutGalleys');
        }

        $galleyViewsArray = array_map('trim', explode(';', $galleyViews));
        $galleyLabelsArray = array_map('trim', explode(';', $galleyLabels));

        if (count($galleyViewsArray) !== count($galleyLabelsArray)) {
            return __('plugins.importexport.csv.invalidNumberOfGalleyViews');
        }

        foreach ($galleyViewsArray as $value) {
            $value = trim($value);
            if ($value === '') {
                continue;
            }
            if (!ctype_digit($value)) {
                return __('plugins.importexport.csv.invalidGalleyViewValue', ['value' => $value]);
            }
        }

        return null;
    }

    /**
     * Validates the articleViews field.
     *
     * @param string|null $submissionViews
     * @param string $fieldName
     *
     * @return string|null
     */
    public static function validatePublicationViews($submissionViews, $fieldName)
    {
        if (empty($submissionViews)) {
            return null;
        }

        if (!ctype_digit($submissionViews)) {
            return __('plugins.importexport.csv.invalidSubmissionViews', ['fieldName' => $fieldName]);
        }

        return null;
    }

    /**
     * Validates the funders string format.
     *
     * @param string|null $fundersString
     *
     * @return string|null
     */
    public static function validateFunders($fundersString)
    {
        if (empty($fundersString)) {
            return null;
        }

        $fundersArray = array_map('trim', explode(';', $fundersString));

        foreach ($fundersArray as $index => $funderString) {
            if (empty($funderString)) {
                continue;
            }

            $funderParts = array_map('trim', explode(',', $funderString));
            $funderName = $funderParts[0] ?? '';

            if (empty($funderName)) {
                return __('plugins.importexport.csv.invalidFunderFormat', ['index' => $index + 1]);
            }
        }

        return null;
    }

    /**
     * Validates that the Funding plugin is enabled when funders data is provided.
     *
     * @param string|null $fundersString
     * @param int $contextId
     * @param string $contextMessage
     *
     * @return string|null
     */
    public static function validateFundingPluginEnabled($fundersString, $contextId, $contextMessage)
    {
        if (empty($fundersString)) {
            return null;
        }

        if (!FundersProcessor::isFundingPluginEnabled($contextId)) {
            return __('plugins.importexport.csv.fundingPluginNotEnabled', ['context' => $contextMessage]);
        }

        return null;
    }

    /**
     * Validates Crossref registry identifications for funders when enabled.
     *
     * @param string|null $fundersString
     * @param int $contextId
     *
     * @return string|null
     */
    public static function validateFundersCrossrefRegistry($fundersString, $contextId)
    {
        if (empty($fundersString)) {
            return null;
        }

        if (!FundersProcessor::isCrossrefValidationEnabled($contextId)) {
            return null;
        }

        $fundersArray = array_map('trim', explode(';', $fundersString));

        foreach ($fundersArray as $index => $funderString) {
            if (empty($funderString)) {
                continue;
            }

            $funderParts = array_map('trim', explode(',', $funderString));
            $funderName = $funderParts[0] ?? '';
            $funderIdentification = $funderParts[1] ?? '';

            if (empty($funderName)) {
                continue;
            }

            if (!empty($funderIdentification)) {
                $hasCrossrefDoi = preg_match('/https?:\/\/(dx\.)?doi\.org\/10\.13039\//i', $funderIdentification);
                if (!$hasCrossrefDoi) {
                    return __('plugins.importexport.csv.funderNotInCrossrefRegistry', [
                        'funderName' => $funderName,
                        'index' => $index + 1,
                    ]);
                }
            } else {
                return __('plugins.importexport.csv.funderMissingCrossrefId', [
                    'funderName' => $funderName,
                    'index' => $index + 1,
                ]);
            }
        }

        return null;
    }

    /**
     * Validates that the row identifies a section.
     *
     * @param object $data
     *
     * @return string|null
     */
    public static function validateSectionFields($data)
    {
        $sectionTitle = trim($data->sectionTitle ?? '');
        $sectionAbbrev = trim($data->sectionAbbrev ?? '');

        if ($sectionTitle === '' && $sectionAbbrev === '') {
            return __('plugins.importexport.csv.incompleteSectionFields');
        }

        return null;
    }
}
