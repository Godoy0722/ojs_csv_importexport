<?php

/**
 * @file plugins/importexport/csv/classes/processors/CurrentPublicationSelector.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CurrentPublicationSelector
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Chooses the current publication and submission status for a multi-version article
 */

namespace APP\plugins\importexport\csv\classes\processors;

use APP\facades\Repo;
use APP\submission\Submission;

class CurrentPublicationSelector
{
    /**
     * A single version already has its status. For two or more versions, the
     * submission is Published when any version is published, otherwise Scheduled
     * when any version is scheduled, otherwise Queued. Readers see the latest
     * published version. When none are published, the workflow opens the latest version.
     *
     * @param array<int|string, array<string, array{publication: \APP\publication\Publication, submission: Submission}>> $versions
     */
    public static function apply(array $versions): void
    {
        if (count($versions) <= 1) {
            return;
        }

        $latestPublished = null;
        $latestPublishedVersion = -1;
        $latest = null;
        $latestVersion = -1;
        $hasScheduled = false;

        foreach ($versions as $locales) {
            foreach ($locales as $localeData) {
                $publication = $localeData['publication'];
                $version = (int) $publication->getData('version');
                $status = (int) $publication->getData('status');

                if ($version > $latestVersion) {
                    $latestVersion = $version;
                    $latest = $localeData;
                }

                if ($status === Submission::STATUS_PUBLISHED && $version > $latestPublishedVersion) {
                    $latestPublishedVersion = $version;
                    $latestPublished = $localeData;
                }

                if ($status === Submission::STATUS_SCHEDULED) {
                    $hasScheduled = true;
                }
            }
        }

        $current = $latestPublished ?? $latest;
        if ($current === null) {
            return;
        }

        $submissionStatus = $latestPublished !== null
            ? Submission::STATUS_PUBLISHED
            : ($hasScheduled ? Submission::STATUS_SCHEDULED : Submission::STATUS_QUEUED);

        Repo::submission()->edit($current['submission'], [
            'status' => $submissionStatus,
            'currentPublicationId' => $current['publication']->getId(),
        ]);
    }
}
