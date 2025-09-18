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

namespace APP\plugins\importexport\csv\classes\processors;

use APP\facades\Repo;
use APP\journal\Journal;
use APP\publication\Publication;
use APP\submission\Submission;
use PKP\core\PKPString;

class PublicationProcessor
{
    /**
     * Create a temporary Publication without association with Submission.
     * This Publication will be used to create the Submission and then updated.
     */
    public static function createInitialPublication(object $data): Publication
    {
        $publication = Repo::publication()->newDataObject();

        $publication->setData('version', 1);
        $publication->setData('status', Submission::STATUS_PUBLISHED);
        $publication->setData('datePublished', $data->datePublished);
        $publication->setData('title', $data->articleTitle, $data->locale);

        return $publication;
    }

    /** Update the Publication with all necessary data after the Submission is created. */
    public static function process(Submission $submission, object $data, Journal $journal): Publication
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

        Repo::publication()->dao->update($submissionPublication);

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
}
