<?php

/**
 * @file plugins/importexport/csv/classes/processors/UsersProcessor.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2003-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UsersProcessor
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Process the users data into the database.
 */

namespace APP\plugins\importexport\csv\classes\processors;

use APP\facades\Repo;
use APP\plugins\importexport\csv\classes\cachedAttributes\CachedEntities;
use PKP\core\Core;
use PKP\security\Validation;
use PKP\user\User;

class UsersProcessor
{
	public static function process(object $data, string $locale): User
    {
        $userData = [
            $locale => [
                'givenName' => $data->firstname,
                'familyName' => $data->lastname,
                'affiliation' => $data->affiliation
            ],
            'email' => $data->email,
            'country' => $data->country,
            'username' => $data->username,
            'password' => Validation::encryptCredentials($data->username, $data->tempPassword),
            'mustChangePassword' => true,
            'dateRegistered' => Core::getCurrentDate()
        ];

        $user = Repo::user()->newDataObject($userData);
        $userId = Repo::user()->add($user);

        return Repo::user()->get($userId);
	}

    public static function getValidUsername(string $firstname, string $lastname): string
    {
        $letters = range('a', 'z');

        do {
            $randomLetters = '';
            for ($i = 0; $i < 3; $i++) {
                $randomLetters .= $letters[array_rand($letters)];
            }

            $username = mb_strtolower(mb_substr($firstname, 0, 1) . $lastname . $randomLetters);
            $existingUser = CachedEntities::getCachedUserByUsername($username);

        } while (!is_null($existingUser));

        return $username;
    }
}
