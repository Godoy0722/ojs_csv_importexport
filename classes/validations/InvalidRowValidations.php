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

namespace APP\plugins\importexport\csv\classes\validations;

use APP\journal\Journal;
use APP\plugins\importexport\csv\classes\cachedAttributes\CachedEntities;
use APP\plugins\importexport\csv\classes\processors\FundersProcessor;
use APP\subscription\SubscriptionType;

class InvalidRowValidations
{

    /** @var string[] */
    static array $coverImageAllowedTypes = ['gif', 'jpg', 'png', 'webp'];

    /**
     * Validates whether the CSV row contains all fields. Returns the reason if an error occurred,
     * or null if everything is correct.
     */
    public static function validateRowContainAllFields(array $fields, int $expectedSize): ?string
    {
        return count($fields) < $expectedSize
            ? __('plugins.importexport.csv.rowDoesntContainAllFields')
            : null;
    }

    /**
     * Validates whether the CSV row contains all required fields. Returns the reason if an error occurred,
     * or null if everything is correct.
     */
    public static function validateRowHasAllRequiredFields(object $data, callable $requiredFieldsValidation): ?string
    {
        return !$requiredFieldsValidation($data)
            ? __('plugins.importexport.csv.verifyRequiredFieldsForThisRow')
            : null;
    }


    /**
     * Validates whether the article file exists and is readable. Returns the reason if an error occurred,
     * or null if everything is correct.
     */
    public static function validateArticleFileIsValid(string $coverImageFilename, string $sourceDir): ?string
    {
        $articleCoverImagePath = "{$sourceDir}/{$coverImageFilename}";

        return !is_readable($articleCoverImagePath)
            ? __('plugins.importexport.csv.invalidArticleFile')
            : null;
    }

    /**
     * Validates the article cover image. Returns the reason if an error occurred,
     * or null if everything is correct.
     */
    public static function validateCoverImageIsValid(string $coverImageFilename, string $sourceDir): ?string
    {
        $articleCoverImagePath = "{$sourceDir}/{$coverImageFilename}";

        if (!is_readable($articleCoverImagePath)) {
            return __('plugins.importexport.csv.invalidBookCoverImage');
        }

        $coverImgExtension = pathinfo(mb_strtolower($coverImageFilename), PATHINFO_EXTENSION);

        if (!in_array($coverImgExtension, static::$coverImageAllowedTypes)) {
            return __('plugins.importexport.csv.invalidFileExtension');
        }

        return null;
    }

    /**
     * Perform all necessary validations for article galleys. Returns the reason if an error occurred,
     * or null if everything is correct.
     */
    public static function validateArticleGalleys(string $galleyFilenames, string $galleyLabels, string $sourceDir): ?string
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
     */
    public static function validateSupplementaryFiles(string $suppFilenames, string $suppLabels, string $sourceDir): ?string
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
     * Validates whether the journal is valid for the CSV row. Returns the reason if an error occurred,
     * or null if everything is correct.
     */
    public static function validateJournalIsValid(Journal $journal, string $journalPath): ?string
    {
        return !$journal ? __('plugins.importexport.csv.unknownJournal', ['journalPath' => $journalPath]) : null;
    }

    /**
     * Validates if the journal supports the locale provided in the CSV row. Returns the reason if an error occurred
     * or null if everything is correct.
     */
    public static function validateJournalLocale(Journal $journal, string $locale): ?string
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
     */
    public static function validateGenreIdValid(?int $genreId, string $genreName): ?string
    {
        return !$genreId ? __('plugins.importexport.csv.noGenre', ['genreName' => $genreName]) : null;
    }

    /**
     * Validates if the user group ID is valid. Returns the reason if an error occurred
     * or null if everything is correct.
     */
    public static function validateUserGroupId(?int $userGroupId, string $journalPath): ?string
    {
        return !$userGroupId
            ? __('plugins.importexport.csv.noAuthorGroup', ['journal' => $journalPath])
            : null;
    }

    /**
     * Validates if all user groups are valid. Returns the reason if an error occurred
     * or null if everything is correct.
     */
    public static function validateAllUserGroupsAreValid(array $roles, int $journalId, string $locale): ?string
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
     * Validates if the subscription dates are valid. Returns the reason if an error occurred
     * or null if everything is correct.
     */
    public static function validateSubscriptionDates(string $startDate, string $endDate, ?string $dateFormat = 'Y-m-d'): ?string
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
     */
    public static function validateSubscriptionType(?SubscriptionType $subscriptionType, int $subscriptionTypeId): ?string
    {
        return !$subscriptionType
            ? __('plugins.importexport.csv.subscriptionTypeDoesntExist', ['subscriptionTypeId' => $subscriptionTypeId])
            : null;
    }

    public static function validateArticleVersioningFields(object $data): ?string
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
     * Validates that no duplicate version exists for the same article identifier,
     * version, and locale combination in the current import session
     */
    public static function validateNoDuplicateVersion(object $data, array $processedArticles): ?string
    {
        $identifier = $data->versionIdentifier;
        $version = (int)$data->version;
        $locale = $data->locale;

        if (isset($processedArticles[$identifier][$version][$locale])) {
            return __('plugins.importexport.csv.duplicateArticleVersionLocaleFound', [
                'identifier' => $identifier,
                'version' => $version,
                'locale' => $locale
            ]);
        }

        return null;
    }

    /**
     * Checks if a version exists in any locale (used for multi-locale imports)
     */
    public static function versionExistsInAnyLocale(object $data, array $processedArticles): bool
    {
        $identifier = $data->versionIdentifier;
        $version = (int)$data->version;

        return isset($processedArticles[$identifier][$version]) &&
               !empty($processedArticles[$identifier][$version]);
    }

    /**
     * Validates the references file. Returns the reason if an error occurred,
     * or null if everything is correct.
     */
    public static function validateReferencesFile(?string $referencesFilename, string $sourceDir): ?string
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

    public static function validateHtmlGalleys(?string $htmlGalley, string $sourceDir): ?string
    {
        if (empty(trim($htmlGalley ?? ''))) {
            return null;
        }

        $htmlGalleyFiles = array_filter(array_map('trim', explode(';', $htmlGalley)), fn($f) => $f !== '');

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

    public static function validateGalleyViews(?string $galleyViews, ?string $galleyLabels): ?string
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

    public static function validatePublicationViews(?string $submissionViews, string $fieldName): ?string
    {
        if (empty($submissionViews)) {
            return null;
        }

        if (!ctype_digit($submissionViews)) {
            return __('plugins.importexport.csv.invalidSubmissionViews', ['fieldName' => $fieldName]);
        }

        return null;
    }

    public static function validateFunders(?string $fundersString): ?string
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

    public static function validateFundingPluginEnabled(?string $fundersString, int $contextId, string $contextMessage): ?string
    {
        if (empty($fundersString)) {
            return null;
        }

        if (!FundersProcessor::isFundingPluginEnabled($contextId)) {
            return __('plugins.importexport.csv.fundingPluginNotEnabled', ['context' => $contextMessage]);
        }

        return null;
    }

    public static function validateFundersCrossrefRegistry(?string $fundersString, int $contextId): ?string
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

    public static function validateSectionFields(object $data): ?string
    {
        $sectionTitle = trim($data->sectionTitle ?? '');
        $sectionAbbrev = trim($data->sectionAbbrev ?? '');

        if ($sectionTitle === '' && $sectionAbbrev === '') {
            return __('plugins.importexport.csv.incompleteSectionFields');
        }

        return null;
    }
}
