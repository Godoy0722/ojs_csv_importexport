<?php

/**
 * @file plugins/importexport/csv/classes/cachedAttributes/CachedDaos.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CachedDaos
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief This class is responsible for retrieving cached DAOs.
 */

namespace APP\plugins\importexport\csv\classes\cachedAttributes;

use APP\facades\Repo;
use APP\journal\JournalDAO;
use APP\subscription\IndividualSubscriptionDAO as SubscriptionIndividualSubscriptionDAO;
use APP\subscription\SubscriptionTypeDAO as SubscriptionSubscriptionTypeDAO;
use PKP\db\DAO;
use PKP\db\DAORegistry;
use PKP\submission\GenreDAO;

class CachedDaos
{
    /** @var array<string,DAO> */
    static array $cachedDaos = [];

    /** Retrieves the cached JournalDAO instance. */
    public static function getJournalDao(): JournalDAO
    {
        return self::$cachedDaos['JournalDAO'] ??= DAORegistry::getDAO('JournalDAO');
    }

    /** Retrieves the cached GenreDAO instance. */
    public static function getGenreDao(): GenreDAO
    {
        return self::$cachedDaos['GenreDAO'] ??= DAORegistry::getDAO('GenreDAO');
    }

    /** Retrieves the cached CategoryDAO instance. */
    public static function getCategoryDao()
	{
		return Repo::category()->dao;
	}

    /** Retrieves the cached IndividualSubscriptionDAO instance. */
    public static function getIndividualSubscriptionDao(): SubscriptionIndividualSubscriptionDAO
	{
		return self::$cachedDaos['IndividualSubscriptionDAO'] ??= DAORegistry::getDAO('IndividualSubscriptionDAO');
	}

    /** Retrieves the cached SubscriptionTypeDAO instance. */
    public static function getSubscriptionTypeDao(): SubscriptionSubscriptionTypeDAO
	{
		return self::$cachedDaos['SubscriptionTypeDAO'] ??= DAORegistry::getDAO('SubscriptionTypeDAO');
	}
}
