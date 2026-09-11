<?php

/**
 * @file plugins/importexport/csv/classes/processors/UsersProcessor.php
 *
 * Copyright (c) 2014-2026 Simon Fraser University
 * Copyright (c) 2003-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UsersProcessor
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Process the users data into the database.
 */

namespace PKP\Plugins\ImportExport\CSV\Classes\Processors;

import('plugins.importexport.csv.classes.handlers.OrcidHandler');

use PKP\Plugins\ImportExport\CSV\Classes\CachedAttributes\CachedDaos;
use PKP\Plugins\ImportExport\CSV\Classes\CachedAttributes\CachedEntities;
use PKP\Plugins\ImportExport\CSV\Classes\Handlers\OrcidHandler;

class UsersProcessor
{
    /**
     * Create or update a user from CSV row data.
     *
     * @param object $data
     * @param string $locale
     *
     * @return \User
     */
    public static function process($data, $locale)
    {
        $existing = CachedEntities::getCachedUserByEmail($data->email);
        if ($existing) {
            return static::update($existing, $data, $locale);
        }

        return static::create($data, $locale);
    }

    /**
     * @param object $data
     * @param string $locale
     *
     * @return \User
     */
    public static function create($data, $locale)
    {
        $userDao = CachedDaos::getUserDao();

        $user = $userDao->newDataObject();
        $user->setGivenName($data->firstname, $locale);
        $user->setFamilyName($data->lastname, $locale);
        $user->setEmail($data->email);
        $user->setAffiliation($data->affiliation, $locale);
        $user->setCountry($data->country);
        $user->setUsername($data->username);
        $user->setMustChangePassword(true);
        $user->setDateRegistered(\Core::getCurrentDate());
        $user->setPassword(\Validation::encryptCredentials($data->username, $data->tempPassword));

        if (!empty($data->orcid)) {
            $normalizedOrcid = OrcidHandler::normalize($data->orcid);
            if ($normalizedOrcid !== null) {
                $user->setOrcid($normalizedOrcid);
            }
        }

        $userDao->insertObject($user);

        return $user;
    }

    /**
     * @param \User $user
     * @param object $data
     * @param string $locale
     *
     * @return \User
     */
    public static function update($user, $data, $locale)
    {
        $userDao = CachedDaos::getUserDao();

        $user->setGivenName($data->firstname, $locale);
        $user->setFamilyName($data->lastname, $locale);
        $user->setAffiliation($data->affiliation, $locale);
        $user->setEmail($data->email);
        $user->setCountry($data->country);

        if (!empty($data->orcid)) {
            $normalizedOrcid = OrcidHandler::normalize($data->orcid);
            if ($normalizedOrcid !== null) {
                $user->setOrcid($normalizedOrcid);
            }
        }

        $userDao->updateObject($user);

        return $userDao->getById($user->getId());
    }

    /**
     * Get a valid username for a user.
     *
     * @param string $firstname
     * @param string $lastname
     *
     * @return string
     */
    public static function getValidUsername($firstname, $lastname)
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
