<?php

/**
 * @file plugins/importexport/csv/classes/processors/GalleyProcessor.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2003-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class GalleyProcessor
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Processes the article galley data into the database.
 */

namespace APP\plugins\importexport\csv\classes\processors;

use APP\facades\Repo;

class GalleyProcessor
{
    public static function process(int $submissionFileId, object $data, string $label, int $publicationId, string $extension): int
    {
        $galley = Repo::galley()->newDataObject([
            'submissionFileId' => $submissionFileId,
            'publicationId' => $publicationId,
            'label' => $label,
            'locale' => $data->locale,
            'isApproved' => true,
            'seq' => REALLY_BIG_NUMBER,
        ]);

        $galley->setName(mb_strtoupper($extension), $data->locale);

        if (!empty($data->doi)) {
            $galley->setStoredPubId('doi', $data->doi);
        }

        return Repo::galley()->add($galley);
    }
}
