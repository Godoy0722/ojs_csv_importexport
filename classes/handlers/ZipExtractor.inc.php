<?php

/**
 * @file plugins/importexport/csv/classes/handlers/ZipExtractor.inc.php
 *
 * Copyright (c) 2014-2026 Simon Fraser University
 * Copyright (c) 2003-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ZipExtractor
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Safely extracts ZIP archives uploaded via the web GUI
 */

namespace PKP\Plugins\ImportExport\CSV\Classes\Handlers;

use PKP\Plugins\ImportExport\CSV\Classes\Exceptions\ZipExtractionException;

class ZipExtractor
{
    const MAX_TOTAL_BYTES = 524288000;
    const MAX_RATIO = 100;
    const MAX_ENTRIES = 10000;

    /**
     * @param string $zipPath
     * @param string|null $targetBaseDir
     * @return string
     */
    public function extract($zipPath, $targetBaseDir = null)
    {
        $baseDir = $targetBaseDir ?: sys_get_temp_dir();

        $zip = new \ZipArchive();
        $result = $zip->open($zipPath);

        if ($result !== true) {
            throw ZipExtractionException::corruptArchive($zipPath);
        }

        try {
            $entryCount = $zip->numFiles;

            if ($entryCount > self::MAX_ENTRIES) {
                throw ZipExtractionException::bombDetected($entryCount, self::MAX_ENTRIES);
            }

            $totalUncompressed = 0;

            for ($i = 0; $i < $entryCount; $i++) {
                $stat = $zip->statIndex($i);

                if ($stat === false) {
                    continue;
                }

                $name = $stat['name'];

                if (strpos($name, '..') !== false || (isset($name[0]) && $name[0] === '/')) {
                    throw ZipExtractionException::pathTraversal($name);
                }

                $compressedSize = (int) $stat['comp_size'];
                $uncompressedSize = (int) $stat['size'];

                if ($compressedSize > 0) {
                    $ratio = $uncompressedSize / $compressedSize;
                    if ($ratio > self::MAX_RATIO) {
                        throw ZipExtractionException::bombDetected((int) $ratio, self::MAX_RATIO);
                    }
                }

                $totalUncompressed += $uncompressedSize;

                if ($totalUncompressed > self::MAX_TOTAL_BYTES) {
                    throw ZipExtractionException::bombDetected($totalUncompressed, self::MAX_TOTAL_BYTES);
                }
            }

            $extractDir = $baseDir . '/csv_import_' . bin2hex(random_bytes(8));
            mkdir($extractDir, 0700, true);

            if (!$zip->extractTo($extractDir)) {
                throw ZipExtractionException::corruptArchive($zipPath);
            }
        } finally {
            $zip->close();
        }

        return $extractDir;
    }

    /**
     * @param string $extractDir
     * @return string
     */
    public static function resolveSourceDir($extractDir)
    {
        $entries = array_diff(scandir($extractDir), ['.', '..']);

        $hasCsvAtRoot = false;
        $subdirectories = [];

        foreach ($entries as $entry) {
            $fullPath = $extractDir . '/' . $entry;
            if (is_dir($fullPath)) {
                $subdirectories[] = $fullPath;
            } elseif (preg_match('/\.csv$/i', $entry)) {
                $hasCsvAtRoot = true;
            }
        }

        if ($hasCsvAtRoot) {
            return $extractDir;
        }

        if (count($subdirectories) === 1) {
            return $subdirectories[0];
        }

        return $extractDir;
    }

    /**
     * @param string $dir
     */
    public static function deleteDirectory($dir)
    {
        if (!is_dir($dir)) {
            return;
        }

        $entries = array_diff(scandir($dir), ['.', '..']);
        foreach ($entries as $entry) {
            $path = $dir . '/' . $entry;
            if (is_dir($path)) {
                static::deleteDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
