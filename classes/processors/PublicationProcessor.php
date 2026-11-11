<?php

/**
 * @file plugins/importexport/csv/classes/processors/PublicationProcessor.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
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
use APP\plugins\importexport\csv\shared\processors\PublicationProcessor as SharedPublicationProcessor;
use APP\publication\Publication;
use APP\submission\Submission;
use Illuminate\Support\Facades\DB;

class PublicationProcessor extends SharedPublicationProcessor
{

    private const LOCALIZED_FIELDS = [
            'title' => 'articleTitle',
            'subtitle' => 'articleSubtitle',
            'abstract' => 'articleAbstract',
            'coverage' => 'coverage',
            'copyrightHolder' => 'copyrightHolder',
        ];

    private const NON_LOCALIZED_FIELDS = ['copyrightYear', 'licenseUrl'];
    /**
     * Create a temporary Publication without association with Submission.
     * This Publication will be used to create the Submission and then updated.
     */
    public static function createInitialPublication(object $data, int $status = Submission::STATUS_PUBLISHED): Publication
    {
        $status = (int) ($data->resolvedStatus ?? $status);
        $publication = parent::createInitialPublication($data, $status);

        if ($status === Submission::STATUS_PUBLISHED) {
            $publication->setData('datePublished', $data->datePublished);
        }
        $publication->setData('title', $data->articleTitle, $data->locale);

        return $publication;
    }

    /** Update the Publication with all necessary data after the Submission is created. */
    public static function process(Submission $submission, object $data, Journal $journal, string $sourceDir): Publication
    {
        $submissionPublication = parent::processCommons($submission, $data, $journal, $sourceDir);

        if (!empty($data->articleSubtitle)) {
            $submissionPublication->setData('subtitle', $data->articleSubtitle, $data->locale);
        }

        if (!empty($data->articleAbstract)) {
            $submissionPublication->setData('abstract', $data->articleAbstract, $data->locale);
        }

        if (!empty($data->startPage) && !empty($data->endPage)) {
            $submissionPublication->setData('pages', "{$data->startPage}-{$data->endPage}");
        } elseif (!empty($data->startPage)) {
            $submissionPublication->setData('pages', $data->startPage);
        }

        $oldPublication = Repo::publication()->get($submissionPublication->getId());
        Repo::publication()->dao->update($submissionPublication, $oldPublication);

        return $submissionPublication;
    }

    public static function updateIssueId(Publication $publication, int $issueId)
    {
        $publication->setData('issueId', $issueId);
        Repo::publication()->dao->update($publication);
    }

    static function updatePublicationAttribute(Publication $publication, string $attribute, mixed $data, ?string $locale = null)
    {
        $publication->setData($attribute, $data, $locale);
        Repo::publication()->dao->update($publication);
    }

    /**
     * Create a new publication version manually to avoid CLI context dependency
     * This is a simplified version of Repo::publication()->version() without context dependencies
     */
    public static function createPublicationVersion(Publication $basePublication, object $data): Publication
    {
        $status = (int) ($data->resolvedStatus ?? Submission::STATUS_PUBLISHED);
        $newPublication = parent::createPublicationVersionCommons($basePublication, $data, $status);

        $coverImage = $basePublication->getData('coverImage');
        if (!empty($coverImage)) {
            $localizedCoverImage = [];
            $localizedCoverImage['coverImage'] = $coverImage;
            $updatedPublication = Repo::publication()->newDataObject(array_merge($newPublication->_data, $localizedCoverImage));
            $updatedPublication->stampModified();
            Repo::publication()->dao->update($updatedPublication, $newPublication);
            $newPublication = Repo::publication()->get($newPublication->getId());
        }

        Repo::publication()->dao->update($newPublication);

        $newPublication = Repo::publication()->get($newPublication->getId());

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
        parent::processVersionedPublicationCommons(
            $publication,
            $data,
            $basePublication,
            $sourceDir,
            static::LOCALIZED_FIELDS,
            static::NON_LOCALIZED_FIELDS
        );

        $publication->setData('version', (int)$data->version);
        $status = (int) ($data->resolvedStatus ?? Submission::STATUS_PUBLISHED);
        $publication->setData('status', $status);

        if ($status === Submission::STATUS_PUBLISHED) {
            $datePublished = !empty($data->datePublished) ? $data->datePublished : $basePublication->getData('datePublished');
            $publication->setData('datePublished', $datePublished);
        } else {
            $publication->setData('datePublished', null);
        }

        $oldPublication = Repo::publication()->get($publication->getId());
        Repo::publication()->dao->update($publication, $oldPublication);

        return $publication;
    }

    /**
     * Process multi-locale publication data (adds new locale to existing publication)
     * This method updates an existing publication with data in a new locale
     */
    public static function processMultiLocalePublication(Publication $publication, object $data): Publication
    {
        return parent::processMultiLocalePublicationCommons($publication, $data, static::LOCALIZED_FIELDS, static::NON_LOCALIZED_FIELDS);
    }

    /**
     * Copy a publication cover image onto every locale that does not have one.
     */
    public static function syncCoverImagesForProcessedArticles(array $processedArticles): void
    {
        foreach ($processedArticles as $versions) {
            foreach ($versions as $localeData) {
                $firstLocaleData = reset($localeData);
                $publication = $firstLocaleData['publication'];
                $publicationId = $publication->getId();

                $serverId = Repo::submission()->get($publication->getData('submissionId'))->getData('contextId');
                $serverDao = CachedDaos::getJournalDao();
                $server = $serverDao->getById($serverId);
                if (!$server) {
                    continue;
                }
                $defaultLocale = $server->getPrimaryLocale();

                $coverImageSettings = DB::table('publication_settings')
                    ->where('publication_id', $publicationId)
                    ->where('setting_name', 'coverImage')
                    ->get();

                if ($coverImageSettings->isEmpty()) {
                    continue;
                }

                $coverImagesByLocale = [];
                foreach ($coverImageSettings as $setting) {
                    if (empty($setting->setting_value)) {
                        continue;
                    }

                    $coverImageData = json_decode($setting->setting_value, true);
                    if (!empty($coverImageData)) {
                        $coverImagesByLocale[$setting->locale] = $coverImageData;
                    }
                }

                if (empty($coverImagesByLocale)) {
                    continue;
                }

                $sourceCoverImage = null;

                if (isset($coverImagesByLocale[$defaultLocale])) {
                    $sourceCoverImage = $coverImagesByLocale[$defaultLocale];
                } else {
                    foreach ($coverImagesByLocale as $coverImageData) {
                        if (!empty($coverImageData) && isset($coverImageData['uploadName'])) {
                            $sourceCoverImage = $coverImageData;
                            break;
                        }
                    }
                }

                if (!$sourceCoverImage) {
                    continue;
                }

                $allPublicationLocales = DB::table('publication_settings')
                    ->where('publication_id', $publicationId)
                    ->whereNotNull('locale')
                    ->whereNot('locale', '')
                    ->distinct()
                    ->pluck('locale')
                    ->toArray();

                foreach ($allPublicationLocales as $locale) {
                    if (isset($coverImagesByLocale[$locale])) {
                        continue;
                    }

                    $reloadedPublication = Repo::publication()->get($publicationId);
                    if ($reloadedPublication) {
                        static::updatePublicationAttribute($reloadedPublication, 'coverImage', $sourceCoverImage, $locale);
                    }
                }
            }
        }
    }
}
