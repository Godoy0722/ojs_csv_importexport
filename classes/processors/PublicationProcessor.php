<?php

/**
 * @file plugins/importexport/csv/classes/processors/PublicationProcessor.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PublicationProcessor
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Processes the publication data into the database.
 */

namespace APP\plugins\importexport\csv\classes\processors;

use APP\facades\Repo;
use APP\journal\Journal;
use APP\plugins\importexport\csv\classes\cachedAttributes\CachedDaos;
use APP\publication\Publication;
use APP\submission\Submission;

class PublicationProcessor
{
    /**
     * Create a temporary Publication without association with Submission.
     * This Publication will be used to create the Submission and then updated.
     */
    public static function createInitialPublication(object $data): Publication
    {
        $publication = Repo::publication()->newDataObject();

        $version = !empty($data->version) ? (int)$data->version : 1;
        $publication->setData('version', $version);
        $publication->setData('status', Submission::STATUS_PUBLISHED);
        $publication->setData('datePublished', $data->datePublished);
        $publication->setData('title', $data->articleTitle, $data->locale);

        return $publication;
    }

    /** Update the Publication with all necessary data after the Submission is created. */
    public static function process(Submission $submission, object $data, Journal $journal, string $sourceDir): Publication
    {
        /** @var Publication */
        $submissionPublication = $submission->getCurrentPublication();

        $submissionPublication->setData('copyrightNotice', $journal->getLocalizedData('copyrightNotice', $data->locale));

        if (!empty($data->articleSubtitle)) {
            $submissionPublication->setData('subtitle', $data->articleSubtitle, $data->locale);
        }

        if (!empty($data->articleAbstract)) {
            $submissionPublication->setData('abstract', $data->articleAbstract, $data->locale);
        }

        if (!empty($data->articlePrefix)) {
            $submissionPublication->setData('prefix', $data->articlePrefix, $data->locale);
        }

        if (!empty($data->startPage) && !empty($data->endPage)) {
            $submissionPublication->setData('pages', "{$data->startPage}-{$data->endPage}");
        }

        if (!empty($data->references)) {
            $referencesString = self::getReferencesContent($data->references, $sourceDir);

            if (!empty($referencesString)) {
                $submissionPublication->setData('citationsRaw', $referencesString);
            }
        }

        $oldPublication = Repo::publication()->get($submissionPublication->getId());
        Repo::publication()->dao->update($submissionPublication, $oldPublication);

        self::setCopyrightFromSystem($submission, $submissionPublication, $data);

        return $submissionPublication;
    }

    public static function updatePrimaryContactId(Publication $publication, int $authorId)
    {
        self::updatePublicationAttribute($publication, 'primaryContactId', $authorId);
    }

    public static function updateCoverage(Publication $publication, string $coverage, string $locale)
    {
        self::updatePublicationAttribute($publication, 'coverage', $coverage, $locale);
    }

    public static function updateCoverImage(Publication $publication, object $data, string $uploadName)
    {
        $coverImage = [
            'dateUploaded' => date('Y-m-d H:i:s'),
            'uploadName' => $uploadName,
            'altText' => $data->coverImageAltText ?? '',
        ];

        $localizedCoverImage = [];
        $localizedCoverImage['coverImage'] = [];
        $localizedCoverImage['coverImage'][$data->locale] = $coverImage;

        $newPublication = Repo::publication()->newDataObject(array_merge($publication->_data, $localizedCoverImage));
        $newPublication->stampModified();

        Repo::publication()->dao->update($newPublication, $publication);

        $newPublication = Repo::publication()->get($newPublication->getId());
        return $newPublication;
    }

    public static function updateIssueId(Publication $publication, int $issueId)
    {
        self::updatePublicationAttribute($publication, 'issueId', $issueId);
    }

    public static function updateSectionId(Publication $publication, int $sectionId)
    {
        self::updatePublicationAttribute($publication, 'sectionId', $sectionId);
    }

    static function updatePublicationAttribute(Publication $publication, string $attribute, mixed $data, ?string $locale = null)
    {
        $publication->setData($attribute, $data, $locale);
        Repo::publication()->dao->update($publication);
    }

    private static function setCopyrightFromSystem(
        Submission $submission,
        Publication &$publication,
        object $data
    ): void
    {
        $copyrightHolder = $data->copyrightHolder ?? $submission->_getContextLicenseFieldValue(
            null,
            Submission::PERMISSIONS_FIELD_COPYRIGHT_HOLDER,
            $publication
        );

        self::updatePublicationAttribute($publication, 'copyrightHolder', $copyrightHolder, $data->locale);

        $copyrightYear = $data->copyrightYear ?? $submission->_getContextLicenseFieldValue(
            null,
            Submission::PERMISSIONS_FIELD_COPYRIGHT_YEAR,
            $publication
        );
        self::updatePublicationAttribute($publication, 'copyrightYear', $copyrightYear);

        $licenseUrl =  $data->licenseUrl ?? $submission->_getContextLicenseFieldValue(
            null,
            Submission::PERMISSIONS_FIELD_LICENSE_URL,
            $publication
        );
        self::updatePublicationAttribute($publication, 'licenseUrl', $licenseUrl);
    }

    /**
     * Create a new publication version manually to avoid CLI context dependency
     * This is a simplified version of Repo::publication()->version() without context dependencies
     */
    public static function createPublicationVersion(Publication $basePublication, object $data): Publication
    {
        $newPublication = clone $basePublication;
        $newPublication->setData('id', null);
        $newPublication->setData('datePublished', null);
        $newPublication->setData('status', Submission::STATUS_PUBLISHED);
        $newPublication->setData('version', (int)$data->version);
        $newPublication->stampModified();

        $publicationId = Repo::publication()->dao->insert($newPublication);
        $newPublication = Repo::publication()->get($publicationId);

        // Clear authors from the cloned publication to avoid duplicates
        $newPublication->setData('authors', []);
        $newPublication->setData('primaryContactId', null);

        // Copy cover image from base publication if it exists
        $coverImage = $basePublication->getData('coverImage');
        if (!empty($coverImage)) {
            $localizedCoverImage = [];
            $localizedCoverImage['coverImage'] = $coverImage;
            $updatedPublication = Repo::publication()->newDataObject(array_merge($newPublication->_data, $localizedCoverImage));
            $updatedPublication->stampModified();
            Repo::publication()->dao->update($updatedPublication, $newPublication);
            $newPublication = Repo::publication()->get($publicationId);
        }

        $citationsRaw = $basePublication->getData('citationsRaw');
        if (!empty($citationsRaw)) {
            $newPublication->setData('citationsRaw', (string)$citationsRaw);
            $oldPublication = Repo::publication()->get($publicationId);

            Repo::publication()->dao->update($newPublication, $oldPublication);

            $newPublication = Repo::publication()->get($publicationId);
        }

        return $newPublication;
    }

    /**
     * Process a versioned publication with CSV data
     * This method processes a publication that was created through OJS versioning mechanism
     * OJS versioning already copied all data from base version, we only update what changed
     */
    public static function processVersionedPublication(
        Publication $publication,
        object $data,
        Publication $basePublication,
        string $sourceDir
    ): Publication {
        // Update version and status
        self::updatePublicationAttribute($publication, 'version', (int)$data->version);
        self::updatePublicationAttribute($publication, 'status', Submission::STATUS_PUBLISHED);

        $datePublished = !empty($data->datePublished) ? $data->datePublished : $basePublication->getData('datePublished');
        self::updatePublicationAttribute($publication, 'datePublished', $datePublished);

        $localizedFields = [
            'title' => 'articleTitle',
            'subtitle' => 'articleSubtitle',
            'abstract' => 'articleAbstract',
            'prefix' => 'articlePrefix',
            'coverage' => 'coverage',
            'copyrightHolder' => 'copyrightHolder',
        ];

        foreach ($localizedFields as $field => $csvField) {
            if (!empty($data->{$csvField})) {
                self::updatePublicationAttribute($publication, $field, $data->{$csvField}, $data->locale);
            } elseif ($basePublication->getLocalizedData($field, $data->locale)) {
                self::updatePublicationAttribute($publication, $field, $basePublication->getLocalizedData($field, $data->locale), $data->locale);
            }
        }

        $nonLocalizedFields = ['copyrightYear', 'licenseUrl'];

        foreach ($nonLocalizedFields as $nonLocaleField) {
            if (!empty($data->{$nonLocaleField})) {
                self::updatePublicationAttribute($publication, $nonLocaleField, $data->{$nonLocaleField});
            } elseif ($basePublication->getData($nonLocaleField)) {
                self::updatePublicationAttribute($publication, $nonLocaleField, $basePublication->getData($nonLocaleField));
            }
        }

        if (!empty($data->doi)) {
            self::updatePublicationAttribute($publication, 'pub-id::doi', $data->doi);
        }

        if (!empty($data->references)) {
            $referencesString = self::getReferencesContent($data->references, $sourceDir);

            if (!empty($referencesString)) {
                $publication->setData('citationsRaw', $referencesString);
            }
        } elseif (!empty($basePublication->getData('citationsRaw'))) {
            $citationsRaw = (string)$basePublication->getData('citationsRaw');
            $publication->setData('citationsRaw', $citationsRaw);
        }

        $oldPublication = Repo::publication()->get($publication->getId());
        Repo::publication()->dao->update($publication, $oldPublication);

        $publication = Repo::publication()->get($publication->getId());

        return $publication;
    }

    /**
     * Process multi-locale publication data (adds new locale to existing publication)
     * This method updates an existing publication with data in a new locale
     */
    public static function processMultiLocalePublication(Publication $publication, object $data): Publication
    {
        $localizedFields = [
            'title' => 'articleTitle',
            'subtitle' => 'articleSubtitle',
            'abstract' => 'articleAbstract',
            'prefix' => 'articlePrefix',
            'coverage' => 'coverage',
            'copyrightHolder' => 'copyrightHolder',
        ];

        foreach ($localizedFields as $field => $csvField) {
            if (!empty($data->{$csvField})) {
                self::updatePublicationAttribute($publication, $field, $data->{$csvField}, $data->locale);
            }
        }

        // Handle non-localized fields (only update if provided in CSV)
        $nonLocalizedFields = ['copyrightYear', 'licenseUrl'];

        foreach ($nonLocalizedFields as $nonLocaleField) {
            if (!empty($data->{$nonLocaleField})) {
                self::updatePublicationAttribute($publication, $nonLocaleField, $data->{$nonLocaleField});
            }
        }

        $submission = Repo::submission()->get($publication->getData('submissionId'));
        $journal = CachedDaos::getJournalDao()->getById($submission->getData('contextId'));
        if ($journal) {
            $publication->setData('copyrightNotice', $journal->getLocalizedData('copyrightNotice', $data->locale));
            Repo::publication()->dao->update($publication);
        }

        return $publication;
    }

    /**
     * Process references from a file and add them to the publication
     */
    public static function getReferencesContent(string $referencesFilename, string $sourceDir): string|false
    {
        $referencesFilePath = "{$sourceDir}/{$referencesFilename}";
        return file_get_contents($referencesFilePath);
    }
}
