<?php

/**
 * @file plugins/importexport/csv/classes/handlers/CsvEncodingHandler.inc.php
 *
 * Copyright (c) 2014-2026 Simon Fraser University
 * Copyright (c) 2003-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CsvEncodingHandler
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Validates CSV file encoding and converts non-UTF-8 sources to UTF-8 when possible
 */

namespace PKP\Plugins\ImportExport\CSV\Classes\Handlers;

use PKP\Plugins\ImportExport\CSV\Classes\Exceptions\CsvEncodingException;

class CsvEncodingHandler
{
    const UTF8_BOM = "\xEF\xBB\xBF";

    /** @var string[] */
    const CONVERSION_SOURCE_ENCODINGS = ['Windows-1252', 'ISO-8859-1', 'ASCII'];

    /**
     * @return string[]
     */
    protected static function conversionSourceEncodings()
    {
        return self::CONVERSION_SOURCE_ENCODINGS;
    }

    /**
     * @param string $content
     * @param string[] $candidateEncodings
     * @return string|null
     */
    public static function detectSourceEncoding($content, $candidateEncodings)
    {
        if ($candidateEncodings === []) {
            return null;
        }

        $sourceEncoding = mb_detect_encoding($content, $candidateEncodings, true);

        return $sourceEncoding === false ? null : $sourceEncoding;
    }

    /**
     * @param string $filePath
     * @return array
     */
    public static function ensureUtf8File($filePath)
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            throw CsvEncodingException::unreadableFile($filePath);
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw CsvEncodingException::unreadableFile($filePath);
        }

        $bomRemoved = false;
        if (strpos($content, self::UTF8_BOM) === 0) {
            $content = substr($content, strlen(self::UTF8_BOM));
            $bomRemoved = true;
        }

        if (mb_check_encoding($content, 'UTF-8')) {
            if ($bomRemoved) {
                self::writeFileAtomically($filePath, $content);
            }

            return [
                'converted' => false,
                'sourceEncoding' => null,
                'bomRemoved' => $bomRemoved,
            ];
        }

        $sourceEncoding = static::detectSourceEncoding($content, static::conversionSourceEncodings());
        if ($sourceEncoding === null) {
            throw CsvEncodingException::undetectableEncoding($filePath);
        }

        $converted = mb_convert_encoding($content, 'UTF-8', $sourceEncoding);
        if (!mb_check_encoding($converted, 'UTF-8')) {
            throw CsvEncodingException::conversionFailed($filePath, $sourceEncoding);
        }

        self::writeFileAtomically($filePath, $converted);

        return [
            'converted' => true,
            'sourceEncoding' => $sourceEncoding,
            'bomRemoved' => $bomRemoved,
        ];
    }

    /**
     * @param string $filePath
     * @param string $content
     * @return void
     */
    private static function writeFileAtomically($filePath, $content)
    {
        $directory = dirname($filePath);
        $temporaryPath = $directory . '/.' . basename($filePath) . '.' . bin2hex(random_bytes(8)) . '.tmp';

        if (file_put_contents($temporaryPath, $content) === false) {
            @unlink($temporaryPath);
            throw CsvEncodingException::unreadableFile($filePath);
        }

        if (!rename($temporaryPath, $filePath)) {
            @unlink($temporaryPath);
            throw CsvEncodingException::unreadableFile($filePath);
        }
    }
}
