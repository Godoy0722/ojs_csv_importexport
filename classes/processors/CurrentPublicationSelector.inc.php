<?php

/**
 * @file plugins/importexport/csv/classes/processors/CurrentPublicationSelector.inc.php
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

namespace PKP\Plugins\ImportExport\CSV\Classes\Processors;

use PKP\Plugins\ImportExport\CSV\Classes\CachedAttributes\CachedDaos;

class CurrentPublicationSelector
{
    /**
     * A single version already has its status. For two or more versions, the
     * submission is Published when any version is published, otherwise Scheduled
     * when any version is scheduled, otherwise Queued. Readers see the latest
     * published version. When none are published, the workflow opens the latest version.
     *
     * @param array $versions
     */
    public static function apply($versions)
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

                if ($status === STATUS_PUBLISHED && $version > $latestPublishedVersion) {
                    $latestPublishedVersion = $version;
                    $latestPublished = $localeData;
                }

                if ($status === STATUS_SCHEDULED) {
                    $hasScheduled = true;
                }
            }
        }

        $current = $latestPublished !== null ? $latestPublished : $latest;
        if ($current === null) {
            return;
        }

        if ($latestPublished !== null) {
            $submissionStatus = STATUS_PUBLISHED;
        } elseif ($hasScheduled) {
            $submissionStatus = STATUS_SCHEDULED;
        } else {
            $submissionStatus = STATUS_QUEUED;
        }

        $submission = $current['submission'];
        $submission->setData('status', $submissionStatus);
        $submission->setData('currentPublicationId', $current['publication']->getId());
        CachedDaos::getSubmissionDao()->updateObject($submission);
    }
}
