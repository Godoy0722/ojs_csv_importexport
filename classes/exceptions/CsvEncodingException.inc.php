<?php

/**
 * @file plugins/importexport/csv/classes/exceptions/CsvEncodingException.inc.php
 *
 * Copyright (c) 2014-2026 Simon Fraser University
 * Copyright (c) 2003-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CsvEncodingException
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Exception thrown when a CSV file cannot be validated or converted to UTF-8
 */

namespace PKP\Plugins\ImportExport\CSV\Classes\Exceptions;

class CsvEncodingException extends \Exception
{
    /**
     * @param string $filePath
     * @return CsvEncodingException
     */
    public static function unreadableFile($filePath)
    {
        return new self(__('plugins.importexport.csv.encodingUnreadableFile', ['filePath' => $filePath]));
    }

    /**
     * @param string $filePath
     * @return CsvEncodingException
     */
    public static function undetectableEncoding($filePath)
    {
        return new self(__('plugins.importexport.csv.encodingUndetectable', ['filePath' => $filePath]));
    }

    /**
     * @param string $filePath
     * @param string $sourceEncoding
     * @return CsvEncodingException
     */
    public static function conversionFailed($filePath, $sourceEncoding)
    {
        return new self(__('plugins.importexport.csv.encodingConversionFailed', [
            'filePath' => $filePath,
            'sourceEncoding' => $sourceEncoding,
        ]));
    }
}
