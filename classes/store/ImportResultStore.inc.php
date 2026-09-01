<?php

/**
 * @file plugins/importexport/csv/classes/store/ImportResultStore.inc.php
 *
 * Copyright (c) 2014-2026 Simon Fraser University
 * Copyright (c) 2003-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ImportResultStore
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Persists and retrieves import result data as JSON files keyed by UUID
 */

namespace PKP\Plugins\ImportExport\CSV\Classes\Store;

class ImportResultStore
{
    /** @var string */
    private $_storeDir;

    /**
     * @param string $storeDir
     */
    public function __construct($storeDir)
    {
        $this->_storeDir = $storeDir;
    }

    /**
     * @param string $uuid
     * @param array $data
     */
    public function save($uuid, $data)
    {
        if (!is_dir($this->_storeDir)) {
            mkdir($this->_storeDir, 0700, true);
        }

        file_put_contents(
            $this->_path($uuid),
            json_encode($data)
        );
    }

    /**
     * @param string $uuid
     * @return array|null
     */
    public function get($uuid)
    {
        $path = $this->_path($uuid);

        if (!file_exists($path)) {
            return null;
        }

        return json_decode(file_get_contents($path), true);
    }

    /**
     * @param string $uuid
     */
    public function delete($uuid)
    {
        $path = $this->_path($uuid);

        if (file_exists($path)) {
            unlink($path);
        }
    }

    /**
     * @param string $uuid
     * @return string
     */
    private function _path($uuid)
    {
        $sanitized = preg_replace('/[^a-f0-9\-]/', '', $uuid);
        return $this->_storeDir . '/' . $sanitized . '.json';
    }
}
