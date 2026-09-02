<?php

/**
 * @file plugins/importexport/csv/classes/processors/PublicationProcessor.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2003-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PublicationProcessor
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Processes the publication data into the database.
 */

namespace PKP\Plugins\ImportExport\CSV\Classes\Processors;

use PKP\Plugins\ImportExport\CSV\Classes\CachedAttributes\CachedDaos;

class PublicationProcessor
{
    /**
     * Processes initial data for Publication
	 *
	 * @param \Submission $submission
	 * @param object $data
	 * @param \Journal $journal
	 * @param string $sourceDir
	 *
	 * @return \Publication
	 */
    public static function process($submission, $data, $journal, $sourceDir)
    {
		$publicationDao = CachedDaos::getPublicationDao();
		$sanitizedAbstract = \PKPString::stripUnsafeHtml($data->articleAbstract);
		$locale = $data->locale;

		/** @var \Publication $publication */
		$publication = $publicationDao->newDataObject();
        $publication->stampModified();
		$publication->setData('submissionId', $submission->getId());
		$publication->setData('version', 1);
		$publication->setData('status', STATUS_PUBLISHED);
		$publication->setData('datePublished', $data->datePublished);
		$publication->setData('abstract', $sanitizedAbstract, $locale);
		$publication->setData('title', $data->articleTitle, $locale);
		$publication->setData('copyrightNotice', $journal->getLocalizedData('copyrightNotice', $locale), $locale);

        if ($data->articleSubtitle) {
            $publication->setData('subtitle', $data->articleSubtitle, $locale);
        }

        if ($data->articlePrefix) {
            $publication->setData('prefix', $data->articlePrefix, $locale);
        }

        if ($data->startPage && $data->endPage) {
            $publication->setData('pages', "{$data->startPage}-{$data->endPage}");
        }

		if (!empty($data->references)) {
            $referencesString = self::getReferencesContent($data->references, $sourceDir);

            if (!empty($referencesString)) {
                $publication->setData('citationsRaw', $referencesString);
            }
        }

        $publicationDao->insertObject($publication);

		if ($publication->getData('citationsRaw')) {
			$citationDao = CachedDaos::getCitationDAO();
			$citationDao->importCitations($publication->getId(), $publication->getData('citationsRaw'));
		}

		self::setCopyrightFromSystem($submission, $publication, $data);
		$publicationDao->updateObject($publication);

        SubmissionProcessor::updateCurrentPublicationId($submission, $publication->getId());

        return $publication;
    }

    /**
     * Updates the primary contact ID for the publication
	 *
	 * @param \Publication $publication
	 * @param int $authorId
	 *
	 * @return void
     */
    public static function updatePrimaryContactId($publication, $authorId)
    {
        self::updatePublicationAttribute($publication, 'primaryContactId', $authorId);
    }

    /**
     * Updates the coverage for the publication
	 *
	 * @param \Publication $publication
	 * @param string $coverage
	 * @param string $locale
	 *
	 * @return void
     */
    public static function updateCoverage($publication, $coverage, $locale)
    {
        self::updatePublicationAttribute($publication, 'coverage', $coverage, $locale);
    }

    /**
     * Updates the cover image for the publication
	 *
	 * @param \Publication $publication
	 * @param object $data
	 * @param string $uploadName
	 *
	 * @return void
     */
    public static function updateCoverImage($publication, $data, $uploadName)
    {
        $coverImage = [
			'uploadName' => $uploadName,
			'altText' => $data->coverImageAltText ?? '',
		];

        self::updatePublicationAttribute($publication, 'coverImage', [$data->locale => $coverImage]);
    }

    /**
     * Updates the issue ID for the publication
	 *
	 * @param \Publication $publication
	 * @param int $issueId
	 *
	 * @return void
     */
    public static function updateIssueId($publication, $issueId)
    {
        self::updatePublicationAttribute($publication, 'issueId', $issueId);
    }

    /**
     * Updates the section ID for the publication
	 *
	 * @param \Publication $publication
	 * @param int $sectionId
	 *
	 * @return void
     */
    public static function updateSectionId($publication, $sectionId)
    {
        self::updatePublicationAttribute($publication, 'sectionId', $sectionId);
    }

    /**
     * Updates a specific attribute of the publication
	 *
	 * @param \Publication $publication
	 * @param string $attribute
	 * @param mixed $data
	 * @param string $locale
	 *
	 * @return void
     */
    static function updatePublicationAttribute($publication, $attribute, $data, $locale = null)
    {
        $publication->setData($attribute, $data, $locale);

        $publicationDao = CachedDaos::getPublicationDao();
        $publicationDao->updateObject($publication);
    }

	/**
     * Create a new publication version manually to avoid CLI context dependency
	 *
	 * @param \Publication $basePublication
	 * @param object $data
	 *
	 * @return \Publication
     */
    public static function createPublicationVersion($basePublication, $data)
    {
        $newPublication = clone $basePublication;
        $newPublication->setData('id', null);
        $newPublication->setData('datePublished', null);
        $newPublication->setData('status', STATUS_PUBLISHED);
        $newPublication->setData('version', (int)$data->version);
        $newPublication->stampModified();

		$publicationDao = CachedDaos::getPublicationDao();
        $newPublicationId = $publicationDao->insertObject($newPublication);

        $authors = $basePublication->getData('authors');

        if (empty($authors)) {
            return $newPublication;
        }

        $newPublication->setData('authors', []);
        $newPublication->setData('primaryContactId', null);
		$publicationDao->updateObject($newPublication);

		// Copy and import citations from base publication
		if (!empty($basePublication->getData('citationsRaw'))) {
			$citationDao = CachedDaos::getCitationDAO();
			$citationDao->importCitations($newPublication->getId(), $basePublication->getData('citationsRaw'));
		}

		$newPublication = $publicationDao->getById($newPublicationId);

        return $newPublication;
    }

	/**
     * Process a versioned publication with CSV data
     * This method processes a publication that was created through OJS versioning mechanism
     * OJS versioning already copied all data from base version, we only update what changed
	 *
	 * @param \Publication $publication
	 * @param object $data
	 * @param \Publication $basePublication
	 * @param string $sourceDir
	 *
	 * @return \Publication
     */
    public static function processVersionedPublication($publication, $data, $basePublication, $sourceDir)
    {
        self::updatePublicationAttribute($publication, 'version', (int)$data->version);
        self::updatePublicationAttribute($publication, 'status', STATUS_PUBLISHED);

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

		$citationsToImport = null;

		if (!empty($data->references)) {
            $referencesString = self::getReferencesContent($data->references, $sourceDir);

            if (!empty($referencesString)) {
                $publication->setData('citationsRaw', $referencesString);
				$citationsToImport = $referencesString;
            }
        } elseif (!empty($basePublication->getData('citationsRaw'))) {
            $citationsRaw = (string)$basePublication->getData('citationsRaw');
            $publication->setData('citationsRaw', $citationsRaw);
			$citationsToImport = $citationsRaw;
        }

		if ($citationsToImport) {
			$citationDao = CachedDaos::getCitationDAO();
			$citationDao->importCitations($publication->getId(), $citationsToImport);
		}

        return $publication;
    }

	/**
	 * Set copyright data for the publication
	 *
	 * @param \Submission $submission
	 * @param \Publication $publication
	 * @param object $data
	 *
	 * @return void
	 *
	 */
	private static function setCopyrightFromSystem($submission, &$publication, $data): void
    {
        $copyrightHolder = $data->copyrightHolder ?? $submission->_getContextLicenseFieldValue(
            null,
            PERMISSIONS_FIELD_COPYRIGHT_HOLDER,
            $publication
        );
        $publication->setData('copyrightHolder', $copyrightHolder, $data->locale);

        $copyrightYear = $data->copyrightYear ?? $submission->_getContextLicenseFieldValue(
            null,
            PERMISSIONS_FIELD_COPYRIGHT_YEAR,
            $publication
        );
        $publication->setData('copyrightYear', $copyrightYear);

        $licenseUrl =  $data->licenseUrl ?? $submission->_getContextLicenseFieldValue(
            null,
            PERMISSIONS_FIELD_LICENSE_URL,
            $publication
        );
        $publication->setData('licenseUrl', $licenseUrl);
    }

    /**
     * Copy galleys from a base publication to a new publication version
     * This mimics the behavior of OJS native versioning when creating new versions
     *
     * @param \Publication $newPublication The new publication version
     * @param \Publication $basePublication The base publication to copy from
     *
     * @return void
     */
    public static function copyGalleysFromBasePublication($newPublication, $basePublication)
    {
        $galleyDao = CachedDaos::getArticleGalleyDao();

        // Load galleys directly from the database to ensure we have the latest data
        $galleysResultFactory = $galleyDao->getByPublicationId($basePublication->getId());
        $galleys = $galleysResultFactory->toArray();

        if (empty($galleys)) {
            return;
        }

        foreach ($galleys as $galley) {
            $newGalley = clone $galley;
            $newGalley->setData('id', null);
            $newGalley->setData('publicationId', $newPublication->getId());
            $galleyDao->insertObject($newGalley);
        }

        // Refresh the publication with the new galleys
        $publicationDao = CachedDaos::getPublicationDao();
        $refreshedPublication = $publicationDao->getById($newPublication->getId());
        $newPublication->setData('galleys', $refreshedPublication->getData('galleys'));
    }

	/**
     * Process references from a file and add them to the publication
	 *
	 * @param string $referencesFilename
	 * @param string $sourceDir
	 *
	 * @return string|false
     */
    public static function getReferencesContent($referencesFilename, $sourceDir)
    {
        $referencesFilePath = "{$sourceDir}/{$referencesFilename}";
        return file_get_contents($referencesFilePath);
    }

	/**
     * Process multi-locale publication data (adds new locale to existing publication)
     * This method updates an existing publication with data in a new locale
	 *
	 * @param \Publication $publication
	 * @param object $data
	 *
	 * @return \Publication
     */
    public static function processMultiLocalePublication($publication, $data)
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

		$submission = CachedDaos::getSubmissionDao()->getById($publication->getData('submissionId'));

        $server = CachedDaos::getJournalDao()->getById($submission->getData('contextId'));
        if ($server) {
            $publication->setData('copyrightNotice', $server->getLocalizedData('copyrightNotice', $data->locale));
			CachedDaos::getPublicationDao()->updateObject($publication);
        }

        return $publication;
    }

    /**
     * Set supporting agencies from CSV data, falling back to the base publication if versioning.
     *
     * @param object $data
     * @param \Publication $publication
     * @param \Publication|null $basePublication
     *
     * @return void
     */
    public static function processSupportingAgencies($data, $publication, $basePublication = null)
    {
        if (empty($data->supportingAgencies) && !is_null($basePublication)) {
            $baseSupportingAgencies = $basePublication->getData('supportingAgencies');
            if (empty($baseSupportingAgencies)) {
                return;
            }

            self::updatePublicationAttribute($publication, 'supportingAgencies', $baseSupportingAgencies);
            return;
        }

        if (empty($data->supportingAgencies)) {
            return;
        }

        $agenciesList = [$data->locale => array_map('trim', explode(';', $data->supportingAgencies))];
        if (empty($agenciesList[$data->locale])) {
            return;
        }

        self::updatePublicationAttribute($publication, 'supportingAgencies', $agenciesList);
    }

    /**
     * Merge supporting agencies for a new locale into the existing agencies array.
     *
     * @param object $data
     * @param \Publication $publication
     *
     * @return void
     */
    public static function processSupportingAgenciesMultiLocale($data, $publication)
    {
        if (empty($data->supportingAgencies)) {
            return;
        }

        $existingAgencies = $publication->getData('supportingAgencies') ?? [];
        $newAgencies = array_map('trim', explode(';', $data->supportingAgencies));
        $existingAgencies[$data->locale] = $newAgencies;

        self::updatePublicationAttribute($publication, 'supportingAgencies', $existingAgencies);
    }
}
