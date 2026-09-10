<?php

/**
 * @file plugins/importexport/csv/classes/exceptions/ZipExtractionException.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2003-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ZipExtractionException
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Exception thrown when ZIP extraction fails
 */

namespace APP\plugins\importexport\csv\classes\exceptions;

use Exception;

class ZipExtractionException extends Exception
{
    public static function corruptArchive(string $path): self
    {
        return new self(__('plugins.importexport.csv.zipCorruptArchive', ['path' => $path]));
    }

    public static function bombDetected(int $actual, int $limit): self
    {
        return new self(__('plugins.importexport.csv.zipBombDetected', ['actual' => $actual, 'limit' => $limit]));
    }

    public static function pathTraversal(string $entryName): self
    {
        return new self(__('plugins.importexport.csv.zipPathTraversal', ['entry' => $entryName]));
    }

    public static function extensionMissing(): self
    {
        return new self(__('plugins.importexport.csv.zipExtensionMissing'));
    }
}
