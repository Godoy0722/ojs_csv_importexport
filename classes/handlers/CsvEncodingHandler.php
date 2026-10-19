<?php

/**
 * @file plugins/importexport/csv/classes/handlers/CsvEncodingHandler.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CsvEncodingHandler
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Validates CSV file encoding and converts non-UTF-8 sources to UTF-8 when possible
 */

namespace APP\plugins\importexport\csv\classes\handlers;

use APP\plugins\importexport\csv\classes\exceptions\CsvEncodingException;

class CsvEncodingHandler
{
    private const UTF8_BOM = "\xEF\xBB\xBF";

    /** @var string[] Encodings tried when converting from a non-UTF-8 file */
    private const CONVERSION_SOURCE_ENCODINGS = ['Windows-1252', 'ISO-8859-1', 'ASCII'];

    /**
     * @return string[]
     */
    protected static function conversionSourceEncodings(): array
    {
        return self::CONVERSION_SOURCE_ENCODINGS;
    }

    /**
     * @param string[] $candidateEncodings
     */
    public static function detectSourceEncoding(string $content, array $candidateEncodings): ?string
    {
        if ($candidateEncodings === []) {
            return null;
        }

        $sourceEncoding = mb_detect_encoding($content, $candidateEncodings, true);

        return $sourceEncoding === false ? null : $sourceEncoding;
    }

    /**
     * Ensure the CSV at $filePath is valid UTF-8 (BOM stripped). Converts legacy encodings in place when needed.
     *
     * @return array{converted: bool, sourceEncoding: ?string, bomRemoved: bool}
     */
    public static function ensureUtf8File(string $filePath): array
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            throw CsvEncodingException::unreadableFile($filePath);
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw CsvEncodingException::unreadableFile($filePath);
        }

        $bomRemoved = false;
        if (str_starts_with($content, self::UTF8_BOM)) {
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

    private static function writeFileAtomically(string $filePath, string $content): void
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
