<?php

/**
 * @file plugins/importexport/csv/classes/handlers/DryModeReporter.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2003-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DryModeReporter
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Handles dry-mode console report output
 */

namespace APP\plugins\importexport\csv\classes\handlers;

class DryModeReporter
{
    /** Print the file header line for a dry-mode report */
    public static function printFileHeader(string $basename): void
    {
        echo __('plugins.importexport.csv.dryModeFileHeader', [
            'filename' => $basename,
        ]) . "\n";
    }

    /** Print the table header row for failed-row output */
    public static function printTableHeader(): void
    {
        echo __('plugins.importexport.csv.dryModeTableHeader') . "\n";
    }

    /** Print a single failed row with aligned row number and error reason */
    public static function printFailedRow(int $rowNumber, string $reason): void
    {
        echo __('plugins.importexport.csv.dryModeFailedRow', [
            'row' => str_pad((string) $rowNumber, 4, ' ', STR_PAD_LEFT),
            'reason' => $reason,
        ]) . "\n";
    }

    /** Print the per-file summary line with pass/fail counts */
    public static function printFileSummary(int $passed, int $failed, int $total): void
    {
        echo __('plugins.importexport.csv.dryModeFileSummary', [
            'passed' => $passed,
            'failed' => $failed,
            'total' => $total,
        ]) . "\n";
    }

    /** Print the grand total line after all files have been processed */
    public static function printGrandTotal(int $files, int $passed, int $failed): void
    {
        echo __('plugins.importexport.csv.dryModeGrandTotal', [
            'files' => $files,
            'passed' => $passed,
            'failed' => $failed,
        ]) . "\n";
    }
}
