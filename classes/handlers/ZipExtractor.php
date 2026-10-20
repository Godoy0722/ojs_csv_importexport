<?php

/**
 * @file plugins/importexport/csv/classes/handlers/ZipExtractor.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2003-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ZipExtractor
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Safely extracts ZIP archives uploaded via the web GUI
 */

namespace APP\plugins\importexport\csv\classes\handlers;

use APP\plugins\importexport\csv\classes\exceptions\ZipExtractionException;

class ZipExtractor
{
    /** Maximum total uncompressed size allowed (2 GB) */
    public const MAX_TOTAL_BYTES = 2147483648;

    public const MAX_RATIO = 100;

    public const MAX_ENTRIES = 10000;

    public static function isAvailable(): bool
    {
        return class_exists(\ZipArchive::class);
    }

    public function extract(string $zipPath, ?string $targetBaseDir = null): string
    {
        if (!self::isAvailable()) {
            throw ZipExtractionException::extensionMissing();
        }

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

                if (self::zipEntryNameIsUnsafe($name)) {
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

    private static function zipEntryNameIsUnsafe(string $name): bool
    {
        if (str_starts_with($name, '/') || str_contains($name, "\0")) {
            return true;
        }

        return preg_match('#(^|[\\\\/])\.\.([\\\\/]|$)#', $name) === 1;
    }

    /**
     * @throws ZipExtractionException When the layout does not match the allowed ZIP structure.
     */
    public static function resolveSourceDir(string $extractDir): string
    {
        if (self::directoryContainsCsvFile($extractDir)) {
            return $extractDir;
        }

        $contentSubdirs = [];
        foreach (array_diff(scandir($extractDir), ['.', '..']) as $entry) {
            $fullPath = $extractDir . '/' . $entry;
            if (!is_dir($fullPath) || self::shouldSkipArchiveDirectory($entry)) {
                continue;
            }
            $contentSubdirs[] = $fullPath;
        }

        if (count($contentSubdirs) !== 1) {
            throw ZipExtractionException::invalidLayout();
        }

        $wrapperDir = $contentSubdirs[0];
        if (!self::directoryContainsCsvFile($wrapperDir)) {
            throw ZipExtractionException::invalidLayout();
        }

        return $wrapperDir;
    }

    public static function isZipArchive(string $filePath, string $originalFileName = ''): bool
    {
        if (strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION)) === 'zip') {
            return true;
        }

        $handle = @fopen($filePath, 'rb');
        if ($handle === false) {
            return false;
        }

        $magic = fread($handle, 4);
        fclose($handle);

        return $magic === "PK\x03\x04";
    }

    private static function shouldSkipArchiveDirectory(string $name): bool
    {
        return in_array($name, ['__MACOSX', '.git', 'node_modules'], true)
            || str_starts_with($name, '.');
    }

    private static function directoryContainsCsvFile(string $dir): bool
    {
        foreach (new \DirectoryIterator($dir) as $fileInfo) {
            if ($fileInfo->isFile() && strtolower($fileInfo->getExtension()) === 'csv') {
                return true;
            }
        }

        return false;
    }

    public static function deleteDirectory(string $dir): void
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
