<?php

/**
 * @file plugins/importexport/csv/classes/processors/UserInterestsProcessor.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2003-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserInterestsProcessor
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Process the user interests data into the database.
 */

namespace APP\plugins\importexport\csv\classes\processors;

use APP\plugins\importexport\csv\classes\cachedAttributes\CachedDaos;

class UserInterestsProcessor
{
	public static function process(array $reviewInterests, int $userId)
    {
        if (!empty($reviewInterests)) {
            $userInterestDao = CachedDaos::getUserInterestDao();
            $userInterestDao->setUserInterests($reviewInterests, $userId);
        }
	}
}
