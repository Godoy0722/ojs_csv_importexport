<?php

/**
 * @file plugins/importexport/csv/classes/validations/InvalidRowValidations.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
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
        if (!empty($data->version) && !empty($data->versionIdentifier) && (int)$data->version > 1) {
			return null;
		}

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

        if (!in_array($coverImgExtension, self::$coverImageAllowedTypes)) {
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
                return mb_strtolower($userGroup->name[$locale]) === mb_strtolower($role);
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
     * Validates the ORCID value. Returns the reason if an error occurred,
     * or null if everything is correct.
     *
     * Accepts the following formats:
     * - Full URL: https://orcid.org/0000-0002-1825-0097 or https://sandbox.orcid.org/0000-0002-1825-0097
     * - Dashed format: 0000-0002-1825-0097
     * - Numeric format: 0000000218250097
     * - Can end with X (checksum character)
     */
    public static function validateOrcid(?string $orcid): ?string
    {
        if (empty($orcid)) {
            return null;
        }

        $normalizedOrcid = self::normalizeOrcid($orcid);

        if ($normalizedOrcid === null) {
            return __('plugins.importexport.csv.invalidOrcidFormat', ['orcid' => $orcid]);
        }

        $digits = preg_replace('/[^0-9X]/', '', $normalizedOrcid);

        if (strlen($digits) !== 16) {
            return __('plugins.importexport.csv.invalidOrcidFormat', ['orcid' => $orcid]);
        }

        if (!self::validateOrcidChecksum($digits)) {
            return __('plugins.importexport.csv.invalidOrcidChecksum', ['orcid' => $orcid]);
        }

        return null;
    }

    /**
     * Normalizes an ORCID value to the full URL format.
     */
    public static function normalizeOrcid(string $orcid): ?string
    {
        $orcid = trim($orcid);

        if (empty($orcid)) {
            return null;
        }

        if (preg_match('/^https:\/\/(sandbox\.)?orcid\.org\/(\d{4})-(\d{4})-(\d{4})-(\d{3}[0-9X])$/', $orcid)) {
            return $orcid;
        }

        if (preg_match('/^(\d{4})-(\d{4})-(\d{4})-(\d{3}[0-9X])$/', $orcid)) {
            return 'https://orcid.org/' . $orcid;
        }

        if (preg_match('/^(\d{15}[0-9X])$/', $orcid)) {
            $formatted = substr($orcid, 0, 4) . '-' .
                         substr($orcid, 4, 4) . '-' .
                         substr($orcid, 8, 4) . '-' .
                         substr($orcid, 12, 4);
            return 'https://orcid.org/' . $formatted;
        }

        return null;
    }

    /**
     * Validates the ORCID checksum using ISNI algorithm.
     */
    private static function validateOrcidChecksum(string $digits): bool
    {
        $total = 0;
        for ($i = 0; $i < 15; $i++) {
            $total = ($total + (int) $digits[$i]) * 2;
        }

        $remainder = $total % 11;
        $result = (12 - $remainder) % 11;
        $expectedCheckDigit = ($result === 10) ? 'X' : (string) $result;

        return $digits[15] === $expectedCheckDigit;
    }
}
