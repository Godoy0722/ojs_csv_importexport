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
use PKP\Plugins\ImportExport\CSV\Classes\Exceptions\RowValidationException;
use PKP\Plugins\ImportExport\CSV\Classes\Processors\FundersProcessor;
use PKP\Plugins\ImportExport\CSV\Classes\Validations\RequiredUserHeaders;



class InvalidRowValidations
{

    /** @var string[] */
    static array $coverImageAllowedTypes = ['gif', 'jpg', 'png', 'webp'];

    /**
     * Validates whether the CSV row contains all fields.
     *
     * @throws RowValidationException
     */
    public static function validateRowContainAllFields(array $fields, int $expectedSize): void
    {
        if (count($fields) < $expectedSize) {
            throw new RowValidationException(__('plugins.importexport.csv.rowDoesntContainAllFields'));
        }
    }

    /**
     * Validates whether the CSV row contains all required fields.
     *
     * @throws RowValidationException
     */
    public static function validateRowHasAllRequiredFields(object $data, callable $requiredFieldsValidation): void
    {
        if (!$requiredFieldsValidation($data)) {
            throw new RowValidationException(__('plugins.importexport.csv.verifyRequiredFieldsForThisRow'));
        }
    }

    /**
     * Validates the article cover image.
     *
     * @throws RowValidationException
     */
    public static function validateCoverImageIsValid(string $coverImageFilename, string $sourceDir): void
    {
        $articleCoverImagePath = "{$sourceDir}/{$coverImageFilename}";

        if (!is_readable($articleCoverImagePath)) {
            throw new RowValidationException(__('plugins.importexport.csv.invalidBookCoverImage'));
        }

        $coverImgExtension = pathinfo(mb_strtolower($coverImageFilename), PATHINFO_EXTENSION);

        if (!in_array($coverImgExtension, static::$coverImageAllowedTypes)) {
            throw new RowValidationException(__('plugins.importexport.csv.invalidFileExtension'));
        }
    }

    /**
     * Perform all necessary validations for article galleys.
     *
     * @throws RowValidationException
     */
    public static function validateArticleGalleys(string $galleyFilenames, string $galleyLabels, string $sourceDir): void
    {
        $galleyFilenamesArray = explode(';', $galleyFilenames);
        $galleyLabelsArray = explode(';', $galleyLabels);

        if (count($galleyFilenamesArray) !== count($galleyLabelsArray)) {
            throw new RowValidationException(__('plugins.importexport.csv.invalidNumberOfLabelsAndGalleys'));
        }

        foreach($galleyFilenamesArray as $galleyFilename) {
            $galleyPath = "{$sourceDir}/{$galleyFilename}";
            if (!is_readable($galleyPath)) {
                throw new RowValidationException(__('plugins.importexport.csv.invalidGalleyFile', ['filename' => $galleyFilename]));
            }
        }
    }

    /**
     * Perform all necessary validations for HTML galleys.
     *
     * @throws RowValidationException
     */
    public static function validateHtmlGalleys(?string $htmlGalley, string $sourceDir): void
    {
        if (empty(trim($htmlGalley ?? ''))) {
            return;
        }

        $htmlGalleyFiles = array_filter(array_map('trim', explode(';', $htmlGalley)), function ($f) {
            return $f !== '';
        });

        if (empty($htmlGalleyFiles)) {
            return;
        }

        $firstFile = $htmlGalleyFiles[0];
        $firstExtension = mb_strtolower(pathinfo($firstFile, PATHINFO_EXTENSION));

        if (!in_array($firstExtension, ['html', 'htm'])) {
            throw new RowValidationException(__('plugins.importexport.csv.invalidHtmlGalleyFirstFile', ['filename' => $firstFile]));
        }

        foreach ($htmlGalleyFiles as $file) {
            $filePath = "{$sourceDir}/{$file}";
            if (!is_readable($filePath)) {
                throw new RowValidationException(__('plugins.importexport.csv.invalidHtmlGalleyFile', ['filename' => $file]));
            }
        }
    }

    /**
     * Perform all necessary validations for supplementary files.
     *
     * @throws RowValidationException
     */
    public static function validateSupplementaryFiles(string $suppFilenames, string $suppLabels, string $sourceDir): void
    {
        $suppFilenamesArray = explode(';', $suppFilenames);
        $suppLabelsArray = explode(';', $suppLabels);

        if (count($suppFilenamesArray) !== count($suppLabelsArray)) {
            throw new RowValidationException(__('plugins.importexport.csv.invalidNumberOfLabelsAndSupplementaryFiles'));
        }

        foreach($suppFilenamesArray as $suppFilename) {
            $suppPath = "{$sourceDir}/{$suppFilename}";
            if (!is_readable($suppPath)) {
                throw new RowValidationException(__('plugins.importexport.csv.invalidSupplementaryFile', ['filename' => $suppFilename]));
            }
        }
    }

    /**
     * Validates the supplementary descriptions count.
     *
     * @throws RowValidationException
     */
    public static function validateSupplementaryDescriptions(string $suppFilenames, string $suppLabels, ?string $suppDescriptions): void
    {
        if (empty($suppDescriptions)) {
            return; // descriptions are optional
        }

        $suppFilenamesArray = array_map('trim', explode(';', $suppFilenames));
        $suppLabelsArray = array_map('trim', explode(';', $suppLabels));
        $suppDescriptionsArray = array_map('trim', explode(';', $suppDescriptions));

        if (
            count($suppDescriptionsArray) !== count($suppFilenamesArray) ||
            count($suppDescriptionsArray) !== count($suppLabelsArray)
        ) {
            throw new RowValidationException(__('plugins.importexport.csv.invalidNumberOfDescriptionsAndSupplementaryFiles'));
        }
    }

    /**
     * Validates whether the journal is valid for the CSV row.
     *
     * @throws RowValidationException
     */
    public static function validateJournalIsValid($journal, string $journalPath): void
    {
        if (!$journal) {
            throw new RowValidationException(__('plugins.importexport.csv.unknownJournal', ['journalPath' => $journalPath]));
        }
    }

    /**
     * Validates if the journal supports the locale provided in the CSV row.
     *
     * @throws RowValidationException
     */
    public static function validateJournalLocale($journal, string $locale): void
    {
        $supportedLocales = $journal->getSupportedSubmissionLocales();
        if (!is_array($supportedLocales) || count($supportedLocales) < 1) {
            $supportedLocales = [$journal->getPrimaryLocale()];
        }

        if (!in_array($locale, $supportedLocales)) {
            throw new RowValidationException(__('plugins.importexport.csv.unknownLocale', ['locale' => $locale]));
        }
    }

    /**
     * Validates if a genre exists for the name provided in the CSV row.
     *
     * @throws RowValidationException
     */
    public static function validateGenreIdValid($genreId, string $genreName): void
    {
        if (!$genreId) {
            throw new RowValidationException(__('plugins.importexport.csv.noGenre', ['genreName' => $genreName]));
        }
    }

    /**
     * Validates if the user group ID is valid.
     *
     * @throws RowValidationException
     */
    public static function validateUserGroupId($userGroupId, string $journalPath): void
    {
        if (!$userGroupId) {
            throw new RowValidationException(__('plugins.importexport.csv.noAuthorGroup', ['journal' => $journalPath]));
        }
    }

    /**
     * Validates if all user groups are valid.
     *
     * @throws RowValidationException
     */
    public static function validateAllUserGroupsAreValid(array $roles, int $journalId, string $locale): void
    {
        $userGroups = CachedEntities::getCachedUserGroupsByJournalId($journalId);

        $allDbRoles = 0;
        foreach ($roles as $role) {
            $matchingGroups = array_filter($userGroups, function($userGroup) use ($role, $locale) {
                return mb_strtolower($userGroup->getName($locale)) === mb_strtolower($role);
            });
            $allDbRoles += count($matchingGroups);
        }

        if ($allDbRoles !== count($roles)) {
            throw new RowValidationException(__('plugins.importexport.csv.roleDoesntExist', ['role' => $role]));
        }
    }

    /**
     * Validates whether a user already exists with the given username.
     *
     * @throws RowValidationException
     */
    /**
     * Validates that no user exists with the given email.
     *
     * @throws RowValidationException
     */
    public static function validateUserAlreadyExistsWithThisEmail(string $email): void
    {
        $existingUser = CachedEntities::getCachedUserByEmail($email);
        if (!is_null($existingUser)) {
            throw new RowValidationException(__('plugins.importexport.csv.userAlreadyExistsWithEmail', ['email' => $email]));
        }
    }

    public static function validateUserAlreadyExistsWithThisUsername(string $username): void
    {
        $existingUserByUsername = CachedEntities::getCachedUserByUsername($username);
        if (!is_null($existingUserByUsername)) {
            throw new RowValidationException(__('plugins.importexport.csv.userAlreadyExistsWithUsername', ['username' => $username]));
        }
    }

    /**
     * Validates that subscription fields are all provided when any one is present.
     *
     * @throws RowValidationException
     */
    public static function validateSubscriptionFields(object $data): void
    {
        if (!RequiredUserHeaders::validateSubscriptionFields($data)) {
            throw new RowValidationException(__('plugins.importexport.csv.missingSubscriptionFields', ['email' => $data->email]));
        }
    }

    /**
     * Validates if the subscription dates are valid.
     *
     * @throws RowValidationException
     */
    public static function validateSubscriptionDates(string $startDate, string $endDate, ?string $dateFormat = 'Y-m-d'): void
    {
        $startDateObj = \DateTime::createFromFormat($dateFormat, $startDate);
        if (!$startDateObj) {
            throw new RowValidationException(__('plugins.importexport.csv.invalidStartDate', ['date' => $startDate]));
        }

        $endDateObj = \DateTime::createFromFormat($dateFormat, $endDate);
        if (!$endDateObj) {
            throw new RowValidationException(__('plugins.importexport.csv.invalidEndDate', ['date' => $endDate]));
        }

        if ($endDateObj <= $startDateObj) {
            throw new RowValidationException(__('plugins.importexport.csv.endDateBeforeStartDate'));
        }
    }

    /**
     * Validates if the subscription type is valid.
     *
     * @throws RowValidationException
     */
    public static function validateSubscriptionType($subscriptionType, int $subscriptionTypeId): void
    {
        if (!$subscriptionType) {
            throw new RowValidationException(__('plugins.importexport.csv.subscriptionTypeDoesntExist', ['subscriptionTypeId' => $subscriptionTypeId]));
        }
    }

    /**
     * Validates article versioning fields.
     *
     * @throws RowValidationException
     */
    public static function validateArticleVersioningFields(object $data): void
    {
        if (!empty($data->versionIdentifier) && empty($data->version)) {
            throw new RowValidationException(__('plugins.importexport.csv.versionRequiredWhenIdentifierProvided'));
        }

        if (!empty($data->version)) {
            if (!is_numeric($data->version) || (int)$data->version < 1) {
                throw new RowValidationException(__('plugins.importexport.csv.versionMustBePositiveInteger'));
            }
        }
    }

    /**
     * Validates that no duplicate version exists for the same article identifier,
     * version, and locale combination in the current import session.
     *
     * @throws RowValidationException
     */
    public static function validateNoDuplicateVersion(object $data, array $processedArticles): void
    {
        $identifier = $data->versionIdentifier;
        $version = (int)$data->version;
        $locale = $data->locale;

        if (isset($processedArticles[$identifier][$version][$locale])) {
            throw new RowValidationException(__('plugins.importexport.csv.duplicateArticleVersionLocaleFound', [
                'identifier' => $identifier,
                'version' => $version,
                'locale' => $locale
            ]));
        }
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
     * Validates the references file.
     *
     * @throws RowValidationException
     */
    public static function validateReferencesFile(?string $referencesFilename, string $sourceDir): void
    {
        if (empty($referencesFilename)) {
            return; // References file is optional
        }

        $referencesFilePath = "{$sourceDir}/{$referencesFilename}";

        if (!is_readable($referencesFilePath)) {
            throw new RowValidationException(__('plugins.importexport.csv.invalidReferencesFile', ['filename' => $referencesFilename]));
        }

        $extension = pathinfo(mb_strtolower($referencesFilename), PATHINFO_EXTENSION);
        if ($extension !== 'txt') {
            throw new RowValidationException(__('plugins.importexport.csv.invalidReferencesFileExtension'));
        }
    }

    /**
     * Validates the galleyViews field.
     *
     * @throws RowValidationException
     */
    public static function validateGalleyViews(?string $galleyViews, ?string $galleyLabels): void
    {
        if (empty($galleyViews)) {
            return;
        }

        if (empty($galleyLabels)) {
            throw new RowValidationException(__('plugins.importexport.csv.galleyViewsWithoutGalleys'));
        }

        $galleyViewsArray = array_map('trim', explode(';', $galleyViews));
        $galleyLabelsArray = array_map('trim', explode(';', $galleyLabels));

        if (count($galleyViewsArray) !== count($galleyLabelsArray)) {
            throw new RowValidationException(__('plugins.importexport.csv.invalidNumberOfGalleyViews'));
        }

        foreach ($galleyViewsArray as $value) {
            $value = trim($value);
            if ($value === '') {
                continue;
            }
            if (!ctype_digit($value)) {
                throw new RowValidationException(__('plugins.importexport.csv.invalidGalleyViewValue', ['value' => $value]));
            }
        }
    }

    /**
     * Validates the publicationViews field. Must be empty or a non-negative integer.
     *
     * @throws RowValidationException
     */
    public static function validatePublicationViews(?string $submissionViews, string $fieldName): void
    {
        if (empty($submissionViews)) {
            return;
        }

        if (!ctype_digit($submissionViews)) {
            throw new RowValidationException(__('plugins.importexport.csv.invalidSubmissionViews', ['fieldName' => $fieldName]));
        }
    }

    /**
     * Validates the funders string format.
     *
     * @throws RowValidationException
     */
    public static function validateFunders(?string $fundersString): void
    {
        if (empty($fundersString)) {
            return;
        }

        $fundersArray = array_map('trim', explode(';', $fundersString));

        foreach ($fundersArray as $index => $funderString) {
            if (empty($funderString)) {
                continue;
            }

            $funderParts = array_map('trim', explode(',', $funderString));
            $funderName = $funderParts[0] ?? '';

            if (empty($funderName)) {
                throw new RowValidationException(__('plugins.importexport.csv.invalidFunderFormat', ['index' => $index + 1]));
            }
        }
    }

    /**
     * Validates that the Funding plugin is enabled when funders data is provided.
     *
     * @throws RowValidationException
     */
    public static function validateFundingPluginEnabled(?string $fundersString, int $contextId, string $contextMessage): void
    {
        if (empty($fundersString)) {
            return;
        }

        if (!FundersProcessor::isFundingPluginEnabled($contextId)) {
            throw new RowValidationException(__('plugins.importexport.csv.fundingPluginNotEnabled', ['context' => $contextMessage]));
        }
    }

    /**
     * Validates that all funders have valid Crossref registry identifications.
     *
     * @throws RowValidationException
     */
    public static function validateFundersCrossrefRegistry(?string $fundersString, int $contextId): void
    {
        if (empty($fundersString)) {
            return;
        }

        if (!FundersProcessor::isCrossrefValidationEnabled($contextId)) {
            return;
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
                    throw new RowValidationException(__('plugins.importexport.csv.funderNotInCrossrefRegistry', [
                        'funderName' => $funderName,
                        'index' => $index + 1,
                    ]));
                }
            } else {
                throw new RowValidationException(__('plugins.importexport.csv.funderMissingCrossrefId', [
                    'funderName' => $funderName,
                    'index' => $index + 1,
                ]));
            }
        }
    }

    /**
     * Validates that the row identifies a section.
     * sectionTitle and sectionAbbrev form a group: at least one of them must be filled.
     *
     * @throws RowValidationException
     */
    public static function validateSectionFields(object $data): void
    {
        $sectionTitle = trim($data->sectionTitle ?? '');
        $sectionAbbrev = trim($data->sectionAbbrev ?? '');

        if ($sectionTitle === '' && $sectionAbbrev === '') {
            throw new RowValidationException(__('plugins.importexport.csv.incompleteSectionFields'));
        }
    }

    /**
     * Validates if the publication was successfully retrieved or created.
     *
     * @throws RowValidationException
     */
    public static function validatePublicationWasSuccessfullyCreated($publication): void
    {
        if (!$publication) {
            throw new RowValidationException(__('plugins.importexport.csv.errorWhileCreatingPublication'));
        }
    }
}
