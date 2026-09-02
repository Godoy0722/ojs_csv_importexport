<?php

/**
 * @file plugins/importexport/csv/classes/processors/StatisticsProcessor.php
 *
 * Copyright (c) 2014-2026 Simon Fraser University
 * Copyright (c) 2003-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class StatisticsProcessor
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Processes usage statistics data into the metrics table.
 */

namespace PKP\Plugins\ImportExport\CSV\Classes\Processors;

import('lib.pkp.classes.statistics.PKPStatisticsHelper');

class StatisticsProcessor
{
    /**
     * @param int $submissionId
     * @param int $contextId
     * @param int $metric
     *
     * @return void
     */
    public static function insertSubmissionViews($submissionId, $contextId, $metric)
    {
        $metricsDao = \DAORegistry::getDAO('MetricsDAO');
        $date = date('Ymd');

        $metricsDao->insertRecord([
            'load_id' => "csv_import_{$submissionId}_{$date}",
            'metric_type' => METRIC_TYPE_COUNTER,
            'assoc_type' => ASSOC_TYPE_SUBMISSION,
            'assoc_id' => $submissionId,
            'day' => $date,
            'metric' => $metric,
        ]);
    }

    /**
     * @param int $submissionId
     * @param int $contextId
     * @param int $galleyId
     * @param int|null $submissionFileId
     * @param int $fileType
     * @param int $metric
     *
     * @return void
     */
    public static function insertGalleyViews(
        $submissionId,
        $contextId,
        $galleyId,
        $submissionFileId,
        $fileType,
        $metric
    ) {
        $metricsDao = \DAORegistry::getDAO('MetricsDAO');
        $date = date('Ymd');

        $record = [
            'load_id' => "csv_import_{$submissionId}_{$date}",
            'metric_type' => METRIC_TYPE_COUNTER,
            'assoc_type' => ASSOC_TYPE_SUBMISSION_FILE,
            'assoc_id' => $submissionFileId ?: $galleyId,
            'day' => $date,
            'metric' => $metric,
            'file_type' => $fileType,
        ];

        $metricsDao->insertRecord($record);
    }

    /**
     * @param string $filename
     *
     * @return int
     */
    public static function resolveFileType($filename)
    {
        $extension = mb_strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return $extension === 'pdf'
            ? STATISTICS_FILE_TYPE_PDF
            : STATISTICS_FILE_TYPE_OTHER;
    }
}
