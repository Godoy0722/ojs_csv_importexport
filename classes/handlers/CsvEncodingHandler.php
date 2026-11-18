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

    private const MACINTOSH_ENCODING = 'Macintosh';

    /** @var string[] Encodings tried when converting from a non-UTF-8 file */
    private const CONVERSION_SOURCE_ENCODINGS = ['Windows-1252', 'ISO-8859-1', 'ASCII'];

    /**
     * Windows-1252 has no characters at these bytes. Mac OS Roman uses them for Å, ç, è, ê, and ù.
     *
     * @var array<int, true>
     */
    private const MACROMAN_BYTES_UNDEFINED_IN_WINDOWS_1252 = [
        0x81 => true,
        0x8D => true,
        0x8F => true,
        0x90 => true,
        0x9D => true,
    ];

    /**
     * Mac OS Roman letters whose Windows-1252 byte is a symbol that does not occur between letters.
     * ñ (0x96) and ó (0x97) are omitted: those bytes are the Windows-1252 en dash and em dash.
     *
     * @var array<int, true>
     */
    private const MACROMAN_LETTERS_INSIDE_WORD = [
        0x87 => true, // á
        0x88 => true, // à
        0x89 => true, // â
        0x8B => true, // ã
        0x91 => true, // ë
        0x95 => true, // ï
        0x98 => true, // ò
        0x9B => true, // õ
    ];

    /**
     * Mac OS Roman letters that share a byte with a Windows-1252 letter used at the start of a word
     * (Š, Ž, Ÿ). A preceding ASCII letter means the byte is inside the word, so it is ä, é, or ü.
     *
     * @var array<int, true>
     */
    private const MACROMAN_LETTERS_AFTER_ASCII_LETTER = [
        0x8A => true, // ä, not word-initial Š
        0x8C => true, // å, not word-initial Œ
        0x8E => true, // é, not word-initial Ž
        0x9F => true, // ü, not Ÿ
    ];

    /**
     * Mac OS Roman capitals whose Windows-1252 byte is a symbol (€, ƒ, …, †) and is not followed by a letter.
     *
     * @var array<int, true>
     */
    private const MACROMAN_CAPITALS_BEFORE_ASCII_LETTER = [
        0x80 => true, // Ä
        0x83 => true, // É
        0x85 => true, // Ö
        0x86 => true, // Ü
    ];

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

        $sourceEncoding = self::isMacintosh($content)
            ? self::MACINTOSH_ENCODING
            : static::detectSourceEncoding($content, static::conversionSourceEncodings());
        if ($sourceEncoding === null) {
            throw CsvEncodingException::undetectableEncoding($filePath);
        }

        $converted = self::convertToUtf8($content, $sourceEncoding);
        if ($converted === null && $sourceEncoding === self::MACINTOSH_ENCODING) {
            $sourceEncoding = static::detectSourceEncoding($content, static::conversionSourceEncodings());
            $converted = $sourceEncoding === null ? null : self::convertToUtf8($content, $sourceEncoding);
        }
        if ($sourceEncoding === null) {
            throw CsvEncodingException::undetectableEncoding($filePath);
        }
        if ($converted === null || !mb_check_encoding($converted, 'UTF-8')) {
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
     * Mac OS Roman and Windows-1252 are both single-byte encodings, so mb_detect_encoding
     * accepts either and returns whichever candidate is listed first. Reading Mac OS Roman
     * as Windows-1252 turns Müller into MŸller (0x9F) and García into Garc’a (0x92).
     */
    private static function isMacintosh(string $content): bool
    {
        $length = strlen($content);
        for ($index = 0; $index < $length; $index++) {
            $byte = ord($content[$index]);
            if ($byte < 0x80 || $byte > 0x9F) {
                continue;
            }

            if (isset(self::MACROMAN_BYTES_UNDEFINED_IN_WINDOWS_1252[$byte])) {
                return true;
            }

            $previousIsLetter = $index > 0 && self::isAsciiLetter(ord($content[$index - 1]));
            $nextIsLetter = $index + 1 < $length && self::isAsciiLetter(ord($content[$index + 1]));

            if ($previousIsLetter && $nextIsLetter && isset(self::MACROMAN_LETTERS_INSIDE_WORD[$byte])) {
                return true;
            }

            if ($previousIsLetter && isset(self::MACROMAN_LETTERS_AFTER_ASCII_LETTER[$byte])) {
                return true;
            }

            if (!$previousIsLetter && $nextIsLetter && isset(self::MACROMAN_CAPITALS_BEFORE_ASCII_LETTER[$byte])) {
                return true;
            }

            // ú shares 0x9C with the French ligature œ, which is followed by u (cœur, œuvre).
            if ($byte === 0x9C && $previousIsLetter && $nextIsLetter) {
                $next = ord($content[$index + 1]);
                if ($next !== ord('u') && $next !== ord('U')) {
                    return true;
                }
            }

            // ö shares 0x9A with š. š in Windows-1252 is usually followed by a, e, i, or šp/št/šk.
            if ($byte === 0x9A && $previousIsLetter && $nextIsLetter) {
                $next = ord($content[$index + 1]);
                if (!in_array($next, [ord('a'), ord('e'), ord('i'), ord('p'), ord('t'), ord('k')], true)) {
                    return true;
                }
            }

            if ($byte === 0x92 && self::isMacRomanIAcute($content, $index)) {
                return true;
            }
        }

        return false;
    }

    /**
     * í shares 0x92 with the Windows-1252 apostrophe. García has the byte inside a word
     * before a vowel. One-letter French elisions (l’amour, c’est) and it’s / O’Brien do not.
     */
    private static function isMacRomanIAcute(string $content, int $index): bool
    {
        $nextIndex = $index + 1;
        if ($nextIndex >= strlen($content) || !self::isAsciiLowerVowel(ord($content[$nextIndex]))) {
            return false;
        }

        $lettersBefore = 0;
        for ($cursor = $index - 1; $cursor >= 0; $cursor--) {
            if (!self::isAsciiLetter(ord($content[$cursor]))) {
                break;
            }
            $lettersBefore++;
        }
        if ($lettersBefore < 2) {
            return false;
        }

        $word = substr($content, $index - $lettersBefore, $lettersBefore);
        $followedByAnotherLetter = $nextIndex + 1 < strlen($content)
            && self::isAsciiLetter(ord($content[$nextIndex + 1]));
        if ($followedByAnotherLetter && preg_match('/qu$/i', $word) === 1) {
            return false;
        }

        return true;
    }

    private static function isAsciiLetter(int $byte): bool
    {
        return ($byte >= ord('A') && $byte <= ord('Z')) || ($byte >= ord('a') && $byte <= ord('z'));
    }

    private static function isAsciiLowerVowel(int $byte): bool
    {
        return $byte === ord('a') || $byte === ord('e') || $byte === ord('i') || $byte === ord('o') || $byte === ord('u');
    }

    private static function convertToUtf8(string $content, string $sourceEncoding): ?string
    {
        if ($sourceEncoding === self::MACINTOSH_ENCODING) {
            if (!function_exists('iconv')) {
                return null;
            }

            $converted = iconv('MACINTOSH', 'UTF-8', $content);

            return $converted === false ? null : $converted;
        }

        return mb_convert_encoding($content, 'UTF-8', $sourceEncoding);
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
