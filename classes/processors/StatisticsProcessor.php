<?php

/**
 * @file plugins/importexport/csv/classes/processors/StatisticsProcessor.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2003-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class StatisticsProcessor
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Processes usage statistics data into the metrics_submission table.
 */

namespace APP\plugins\importexport\csv\classes\processors;

use APP\core\Application;
use Illuminate\Support\Facades\DB;
use PKP\core\Core;
use PKP\statistics\PKPStatisticsHelper;

class StatisticsProcessor
{
    public static function insertSubmissionViews(int $submissionId, int $contextId, int $metric): void
    {
        $date = Core::getCurrentDate();

        DB::table('metrics_submission')->insert([
            'load_id' => "csv_import_{$submissionId}_{$date}",
            'context_id' => $contextId,
            'submission_id' => $submissionId,
            'assoc_type' => Application::ASSOC_TYPE_SUBMISSION,
            'date' => $date,
            'metric' => $metric,
        ]);
    }

    public static function insertGalleyViews(
        int $submissionId,
        int $contextId,
        int $galleyId,
        ?int $submissionFileId,
        int $fileType,
        int $metric
    ): void {
        $date = Core::getCurrentDate();

        DB::table('metrics_submission')->insert([
            'load_id' => "csv_import_{$submissionId}_{$date}",
            'context_id' => $contextId,
            'submission_id' => $submissionId,
            'assoc_type' => Application::ASSOC_TYPE_SUBMISSION_FILE,
            'representation_id' => $galleyId,
            'submission_file_id' => $submissionFileId,
            'file_type' => $fileType,
            'date' => $date,
            'metric' => $metric,
        ]);
    }

    public static function resolveFileType(string $filename): int
    {
        $extension = mb_strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (in_array($extension, ['html', 'htm'])) {
            return PKPStatisticsHelper::STATISTICS_FILE_TYPE_HTML;
        }

        return $extension === 'pdf'
            ? PKPStatisticsHelper::STATISTICS_FILE_TYPE_PDF
            : PKPStatisticsHelper::STATISTICS_FILE_TYPE_OTHER;
    }
}
