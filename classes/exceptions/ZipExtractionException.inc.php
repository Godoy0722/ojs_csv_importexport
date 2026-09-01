<?php

/**
 * @file plugins/importexport/csv/classes/exceptions/ZipExtractionException.inc.php
 *
 * Copyright (c) 2014-2026 Simon Fraser University
 * Copyright (c) 2003-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ZipExtractionException
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Exception thrown when ZIP extraction fails
 */

namespace PKP\Plugins\ImportExport\CSV\Classes\Exceptions;

class ZipExtractionException extends \Exception
{
    /**
     * @param string $path
     * @return ZipExtractionException
     */
    public static function corruptArchive($path)
    {
        return new self(__('plugins.importexport.csv.zipCorruptArchive', ['path' => $path]));
    }

    /**
     * @param int $actual
     * @param int $limit
     * @return ZipExtractionException
     */
    public static function bombDetected($actual, $limit)
    {
        return new self(__('plugins.importexport.csv.zipBombDetected', ['actual' => $actual, 'limit' => $limit]));
    }

    /**
     * @param string $entryName
     * @return ZipExtractionException
     */
    public static function pathTraversal($entryName)
    {
        return new self(__('plugins.importexport.csv.zipPathTraversal', ['entry' => $entryName]));
    }
}
