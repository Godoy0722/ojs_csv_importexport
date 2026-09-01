<?php

/**
 * @file plugins/importexport/csv/classes/handlers/DryModeReporter.inc.php
 *
 * Copyright (c) 2014-2026 Simon Fraser University
 * Copyright (c) 2003-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DryModeReporter
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Handles dry-mode console report output
 */

namespace PKP\Plugins\ImportExport\CSV\Classes\Handlers;

class DryModeReporter
{
    /**
     * @param string $basename
     */
    public static function printFileHeader($basename)
    {
        echo __('plugins.importexport.csv.dryModeFileHeader', [
            'filename' => $basename,
        ]) . "\n";
    }

    public static function printTableHeader()
    {
        echo __('plugins.importexport.csv.dryModeTableHeader') . "\n";
    }

    /**
     * @param int $rowNumber
     * @param string $reason
     */
    public static function printFailedRow($rowNumber, $reason)
    {
        echo __('plugins.importexport.csv.dryModeFailedRow', [
            'row' => str_pad((string) $rowNumber, 4, ' ', STR_PAD_LEFT),
            'reason' => $reason,
        ]) . "\n";
    }

    /**
     * @param int $passed
     * @param int $failed
     * @param int $total
     */
    public static function printFileSummary($passed, $failed, $total)
    {
        echo __('plugins.importexport.csv.dryModeFileSummary', [
            'passed' => $passed,
            'failed' => $failed,
            'total' => $total,
        ]) . "\n";
    }

    /**
     * @param int $files
     * @param int $passed
     * @param int $failed
     */
    public static function printGrandTotal($files, $passed, $failed)
    {
        echo __('plugins.importexport.csv.dryModeGrandTotal', [
            'files' => $files,
            'passed' => $passed,
            'failed' => $failed,
        ]) . "\n";
    }
}
