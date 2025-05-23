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
        $publicationData = [
            'version' => 1,
            'status' => Submission::STATUS_PUBLISHED,
            'datePublished' => $data->datePublished,
            $data->locale => [
                'title' => $data->articleTitle
            ]
        ];

        $publication = Repo::publication()->newDataObject($publicationData);
        return $publication;
    }

    /** Update the Publication with all necessary data after the Submission is created. */
    public static function process(Submission $submission, object $data, Journal $journal): Publication
    {
        $publicationData = [
            'submissionId' => $submission->getId(),
            'version' => 1,
            'status' => Submission::STATUS_PUBLISHED,
            'datePublished' => $data->datePublished,
            $data->locale => [
                'abstract' => PKPString::stripUnsafeHtml($data->articleAbstract),
                'title' => $data->articleTitle,
                'copyrightNotice' => $journal->getLocalizedData('copyrightNotice', $data->locale)
            ]
        ];

        if (!empty($data->articleSubtitle)) {
            $publicationData[$data->locale]['subtitle'] = $data->articleSubtitle;
        }

        if (!empty($data->articlePrefix)) {
            $publicationData[$data->locale]['prefix'] = $data->articlePrefix;
        }

        if (!empty($data->startPage) && !empty($data->endPage)) {
            $publicationData['pages'] = "{$data->startPage}-{$data->endPage}";
        }

        $publication = Repo::publication()->newDataObject($publicationData);
        $publication->stampModified();

        $publicationId = Repo::publication()->add($publication);
        $publication = Repo::publication()->get($publicationId);

        SubmissionProcessor::updateCurrentPublicationId($submission, $publicationId);

        return $publication;
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

        $localeData = [$data->locale => [
            'coverImage' => $coverImage
        ]];

        Repo::publication()->edit($publication, $localeData);
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
        $updateData = is_null($locale)
            ? [$attribute => $data]
            : [$locale => [$attribute => $data]];

        Repo::publication()->edit($publication, $updateData);
    }
}
