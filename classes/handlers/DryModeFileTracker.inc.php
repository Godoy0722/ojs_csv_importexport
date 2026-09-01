<?php

/**
 * @file plugins/importexport/csv/classes/handlers/DryModeFileTracker.inc.php
 *
 * Copyright (c) 2014-2026 Simon Fraser University
 * Copyright (c) 2003-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DryModeFileTracker
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Tracks filesystem artifacts created during dry-mode imports for rollback cleanup
 */

namespace PKP\Plugins\ImportExport\CSV\Classes\Handlers;

class DryModeFileTracker
{
    /** @var int[] */
    private $_fileServiceIds = [];

    /** @var string[] */
    private $_publicFilePaths = [];

    /** @var string[] */
    private $_submissionDirs = [];

    /**
     * @param int $fileId
     */
    public function trackFileServiceId($fileId)
    {
        $this->_fileServiceIds[] = (int) $fileId;
    }

    /**
     * @param string $absolutePath
     */
    public function trackPublicFile($absolutePath)
    {
        if ($absolutePath !== '' && $absolutePath[0] !== '/' && !(strlen($absolutePath) > 1 && $absolutePath[1] === ':')) {
            $baseDir = defined('BASE_SYS_DIR') ? BASE_SYS_DIR : getcwd();
            $absolutePath = rtrim($baseDir, '/') . '/' . ltrim($absolutePath, '/');
        }
        $this->_publicFilePaths[] = $absolutePath;
    }

    /**
     * @param string $relativeDir Path relative to files_dir (e.g. journals/1/articles/42)
     */
    public function trackSubmissionDir($relativeDir)
    {
        $this->_submissionDirs[$relativeDir] = true;
    }

    /**
     * Remove tracked files from disk before the database transaction is rolled back.
     *
     * @param \PKPFileService $fileService
     */
    public function cleanup($fileService)
    {
        foreach ($this->_fileServiceIds as $fileId) {
            try {
                $fileService->delete($fileId);
            } catch (\Exception $e) {
                // File may already have been removed during row-level error handling.
            }
        }

        foreach ($this->_publicFilePaths as $path) {
            if (file_exists($path)) {
                @unlink($path);
            }
        }

        $filesDir = rtrim(\Config::getVar('files', 'files_dir'), '/');
        foreach (array_keys($this->_submissionDirs) as $relativeDir) {
            $absoluteDir = $filesDir . '/' . ltrim($relativeDir, '/');
            if (!is_dir($absoluteDir)) {
                continue;
            }

            $entries = @scandir($absoluteDir);
            if ($entries !== false && count(array_diff($entries, ['.', '..'])) === 0) {
                @rmdir($absoluteDir);
            }
        }

        $this->reset();
    }

    public function reset()
    {
        $this->_fileServiceIds = [];
        $this->_publicFilePaths = [];
        $this->_submissionDirs = [];
    }
}
