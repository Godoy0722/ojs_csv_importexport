<?php

/**
 * @file plugins/importexport/csv/classes/exceptions/CsvEncodingException.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CsvEncodingException
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Exception thrown when a CSV file cannot be validated or converted to UTF-8
 */

namespace APP\plugins\importexport\csv\classes\exceptions;

use Exception;

class CsvEncodingException extends Exception
{
    public static function unreadableFile(string $filePath): self
    {
        return new self(__('plugins.importexport.csv.encodingUnreadableFile', ['filePath' => $filePath]));
    }

    public static function undetectableEncoding(string $filePath): self
    {
        return new self(__('plugins.importexport.csv.encodingUndetectable', ['filePath' => $filePath]));
    }

    public static function conversionFailed(string $filePath, string $sourceEncoding): self
    {
        return new self(__('plugins.importexport.csv.encodingConversionFailed', [
            'filePath' => $filePath,
            'sourceEncoding' => $sourceEncoding,
        ]));
    }
}
