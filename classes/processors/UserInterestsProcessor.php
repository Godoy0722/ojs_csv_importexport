<?php

/**
 * @file plugins/importexport/csv/classes/processors/UserInterestsProcessor.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserInterestsProcessor
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Process the user interests data into the database.
 */

namespace APP\plugins\importexport\csv\classes\processors;

use APP\facades\Repo;

class UserInterestsProcessor
{
	public static function process(array $reviewInterests, int $userId)
    {
        if (!empty($reviewInterests)) {
            $user = Repo::user()->get($userId);
            if ($user) {
                Repo::userInterest()->setInterestsForUser($user, $reviewInterests);
            }
        }
	}
}
