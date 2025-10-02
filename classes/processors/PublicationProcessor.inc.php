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
	 *
	 * @return \Publication
	 */
    public static function process($submission, $data, $journal)
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

        $publicationDao->insertObject($publication);

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
	 *
	 * @return \Publication
     */
    public static function processVersionedPublication($publication, $data, $basePublication)
    {
        self::updatePublicationAttribute($publication, 'version', (int)$data->version);
        self::updatePublicationAttribute($publication, 'status', STATUS_PUBLISHED);

        $datePublished = !empty($data->datePublished) ? $data->datePublished : $basePublication->getData('datePublished');
        self::updatePublicationAttribute($publication, 'datePublished', $datePublished);

        $title = !empty($data->articleTitle) ? $data->articleTitle : $basePublication->getLocalizedData('title', $data->locale);
        self::updatePublicationAttribute($publication, 'title', $title, $data->locale);

        if (!empty($data->articleSubtitle)) {
            self::updatePublicationAttribute($publication, 'subtitle', $data->articleSubtitle, $data->locale);
        } elseif ($basePublication->getLocalizedData('subtitle', $data->locale)) {
            self::updatePublicationAttribute($publication, 'subtitle', $basePublication->getLocalizedData('subtitle', $data->locale), $data->locale);
        }

        if (!empty($data->articleAbstract)) {
            self::updatePublicationAttribute($publication, 'abstract', $data->articleAbstract, $data->locale);
        } elseif ($basePublication->getLocalizedData('abstract', $data->locale)) {
            self::updatePublicationAttribute($publication, 'abstract', $basePublication->getLocalizedData('abstract', $data->locale), $data->locale);
        }

        if (!empty($data->articlePrefix)) {
            self::updatePublicationAttribute($publication, 'prefix', $data->articlePrefix, $data->locale);
        } elseif ($basePublication->getLocalizedData('prefix', $data->locale)) {
            self::updatePublicationAttribute($publication, 'prefix', $basePublication->getLocalizedData('prefix', $data->locale), $data->locale);
        }

        if (!empty($data->doi)) {
            self::updatePublicationAttribute($publication, 'pub-id::doi', $data->doi);
        }

        if (!empty($data->coverage)) {
            self::updatePublicationAttribute($publication, 'coverage', $data->coverage, $data->locale);
        } elseif ($basePublication->getLocalizedData('coverage', $data->locale)) {
            self::updatePublicationAttribute($publication, 'coverage', $basePublication->getLocalizedData('coverage', $data->locale), $data->locale);
        }

        self::setCopyrightFromSystemForVersion($publication, $data, $basePublication);

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
     * Set copyright information for versioned publications
     * Clone from base version if CSV fields are empty
	 *
	 * @param \Publication $publication
	 * @param object $data
	 * @param \Publication $basePublication
	 *
	 * @return void
     */
    private static function setCopyrightFromSystemForVersion(&$publication, $data, $basePublication)
    {
        if (!empty($data->copyrightHolder)) {
            self::updatePublicationAttribute($publication, 'copyrightHolder', $data->copyrightHolder, $data->locale);
        } elseif ($basePublication->getLocalizedData('copyrightHolder', $data->locale)) {
            self::updatePublicationAttribute($publication, 'copyrightHolder', $basePublication->getLocalizedData('copyrightHolder', $data->locale), $data->locale);
        }

        if (!empty($data->copyrightYear)) {
            self::updatePublicationAttribute($publication, 'copyrightYear', $data->copyrightYear);
        } elseif ($basePublication->getData('copyrightYear')) {
            self::updatePublicationAttribute($publication, 'copyrightYear', $basePublication->getData('copyrightYear'));
        }

        if (!empty($data->licenseUrl)) {
            self::updatePublicationAttribute($publication, 'licenseUrl', $data->licenseUrl);
        } elseif ($basePublication->getData('licenseUrl')) {
            self::updatePublicationAttribute($publication, 'licenseUrl', $basePublication->getData('licenseUrl'));
        }
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
}
