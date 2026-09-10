<?php

/**
 * @file plugins/importexport/csv/classes/handlers/CSVFileHandler.php
 *
 * Copyright (c) 2014-2026 Simon Fraser University
 * Copyright (c) 2003-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CSVFileHandler
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Handles CSV file read/write operations for invalid row output
 */

namespace PKP\Plugins\ImportExport\CSV\Classes\Handlers;

class CSVFileHandler
{
    /**
     * Create a new readable SplFileObject.
     *
     * @param string $filePath
     *
     * @return \SplFileObject
     *
     * @throws \Exception
     */
    public static function createReadableCSVFile($filePath)
    {
        try {
            $file = new \SplFileObject($filePath, 'r');
            $file->setFlags(\SplFileObject::READ_CSV);

            return $file;
        } catch (\Exception $e) {
            throw new \Exception(__('plugins.importexport.csv.couldNotOpenFile', [
                'filePath' => $filePath,
                'errorMessage' => $e->getMessage(),
            ]));
        }
    }

    /**
     * Create a new writable SplFileObject for invalid rows from a unique CSV file.
     *
     * @param string $sourceDir
     * @param string $filename
     * @param array $requiredHeaders
     *
     * @return \SplFileObject
     *
     * @throws \Exception
     */
    public static function createCSVFileInvalidRows($sourceDir, $filename, $requiredHeaders)
    {
        try {
            $invalidRowsFile = new \SplFileObject($sourceDir . '/' . $filename, 'a+');
            $invalidRowsFile->fputcsv(array_merge($requiredHeaders, ['error']));

            return $invalidRowsFile;
        } catch (\Exception $e) {
            throw new \Exception(__('plugins.importexport.csv.couldNotCreateFile', ['filename' => $sourceDir . '/' . $filename]));
        }
    }

    /**
     * Add a new row on the invalid csv file
     *
     * @param \SplFileObject &$invalidRowsCsvFile
     * @param array $fields
     * @param int $rowSize
     * @param string $reason
     * @param int &$failedRows
     *
     * @return void
     *
     * @throws \Exception
     */
    public static function processFailedRow(&$invalidRowsCsvFile, $fields, $rowSize, $reason, &$failedRows)
    {
        if (!$invalidRowsCsvFile->fputcsv(array_merge(array_pad($fields, $rowSize, null), [$reason]))) {
            throw new \Exception(__('plugins.importexport.csv.couldNotWriteFile', ['filename' => $invalidRowsCsvFile->getFilename()]));
        }
        ++$failedRows;
    }
}
