<?php

/**
 * @file plugins/importexport/csv/classes/handlers/OrcidHandler.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2003-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class OrcidHandler
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Handles ORCID normalization and validation operations.
 *
 * This class provides centralized ORCID handling including:
 * - Normalizing ORCID values to full URL format (https://orcid.org/XXXX-XXXX-XXXX-XXXX)
 * - Validating ORCID format and checksum
 *
 * Accepted input formats:
 * - Full URL: https://orcid.org/0000-0002-1825-0097 or https://sandbox.orcid.org/0000-0002-1825-0097
 * - Dashed format: 0000-0002-1825-0097
 * - Numeric format: 0000000218250097
 * - Can end with X (checksum character)
 */

namespace PKP\Plugins\ImportExport\CSV\Classes\Handlers;

use PKP\Plugins\ImportExport\CSV\Classes\Exceptions\RowValidationException;

class OrcidHandler
{
    /**
     * Normalizes an ORCID value to the full URL format.
     *
     * @param string|null $orcid Raw ORCID value
     *
     * @return string|null Normalized ORCID URL or null if invalid/empty
     */
    public static function normalize($orcid)
    {
        if (empty($orcid)) {
            return null;
        }

        $orcid = trim($orcid);

        if (empty($orcid)) {
            return null;
        }

        // Already a valid full URL (including sandbox)
        if (preg_match('/^https:\/\/(sandbox\.)?orcid\.org\/(\d{4})-(\d{4})-(\d{4})-(\d{3}[0-9X])$/', $orcid)) {
            return $orcid;
        }

        // Dashed format without URL prefix
        if (preg_match('/^(\d{4})-(\d{4})-(\d{4})-(\d{3}[0-9X])$/', $orcid)) {
            return 'https://orcid.org/' . $orcid;
        }

        // 16-digit numeric format (no dashes)
        if (preg_match('/^(\d{15}[0-9X])$/', $orcid)) {
            $formatted = mb_substr($orcid, 0, 4) . '-' .
                         mb_substr($orcid, 4, 4) . '-' .
                         mb_substr($orcid, 8, 4) . '-' .
                         mb_substr($orcid, 12, 4);
            return 'https://orcid.org/' . $formatted;
        }

        return null;
    }

    /**
     * Validates the ORCID value including format and checksum.
     *
     * @param string|null $orcid ORCID value to validate
     *
     * @return void
     *
     * @throws RowValidationException
     */
    public static function validate($orcid)
    {
        if (empty($orcid)) {
            return;
        }

        $normalizedOrcid = self::normalize($orcid);

        if ($normalizedOrcid === null) {
            throw new RowValidationException(__('plugins.importexport.csv.invalidOrcidFormat', ['orcid' => $orcid]));
        }

        $digits = preg_replace('/[^0-9X]/', '', $normalizedOrcid);

        if (strlen($digits) !== 16) {
            throw new RowValidationException(__('plugins.importexport.csv.invalidOrcidFormat', ['orcid' => $orcid]));
        }

        if (!self::validateChecksum($digits)) {
            throw new RowValidationException(__('plugins.importexport.csv.invalidOrcidChecksum', ['orcid' => $orcid]));
        }
    }

    /**
     * Validates the ORCID checksum using ISNI algorithm.
     *
     * The ISNI algorithm works by:
     * 1. Taking the first 15 digits
     * 2. For each digit, add it to a running total and multiply by 2
     * 3. The 16th character is the check digit that makes the total valid
     *
     * @param string $digits 16-character string of digits (last may be X)
     *
     * @return bool True if checksum is valid
     */
    private static function validateChecksum($digits)
    {
        $total = 0;
        for ($i = 0; $i < 15; $i++) {
            $total = ($total + (int) $digits[$i]) * 2;
        }

        $remainder = $total % 11;
        $result = (12 - $remainder) % 11;
        $expectedCheckDigit = ($result === 10) ? 'X' : (string) $result;

        return $digits[15] === $expectedCheckDigit;
    }
}

