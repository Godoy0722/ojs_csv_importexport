<?php

/**
 * @file plugins/importexport/csv/classes/cachedAttributes/CachedDaos.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2003-2025 John Willinsky
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
use PKP\category\DAO as CategoryDAO;
use PKP\db\DAO;
use PKP\db\DAORegistry;
use PKP\submission\GenreDAO;
use PKP\submission\SubmissionKeywordDAO;
use PKP\submission\SubmissionSubjectDAO;
use PKP\user\InterestDAO;

class CachedDaos
{
    /** @var array<string,DAO> */
    static array $cachedDaos = [];

    /**
     * Retrieves the cached JournalDAO instance.
     */
    public static function getJournalDao(): JournalDAO
    {
        return self::$cachedDaos['JournalDAO'] ??= DAORegistry::getDAO('JournalDAO');
    }

    /** Retrieves the cached GenreDAO instance. */
    public static function getGenreDao(): GenreDAO
    {
        return self::$cachedDaos['GenreDAO'] ??= DAORegistry::getDAO('GenreDAO');
    }

    /** Retrieves the cached SubmissionKeywordDAO instance. */
    public static function getSubmissionKeywordDao(): SubmissionKeywordDAO
    {
        return self::$cachedDaos['SubmissionKeywordDAO'] ??= DAORegistry::getDAO('SubmissionKeywordDAO');
    }

    /** Retrieves the cached SubmissionSubjectDAO instance. */
    public static function getSubmissionSubjectDao(): SubmissionSubjectDAO
    {
        return self::$cachedDaos['SubmissionSubjectDAO'] ??= DAORegistry::getDAO('SubmissionSubjectDAO');
    }

    /** Retrieves the cached InterestDAO instance, which is used for user interests. */
    public static function getUserInterestDao(): InterestDAO
    {
        return self::$cachedDaos['InterestDAO'] ??= DAORegistry::getDAO('InterestDAO');
    }

    /** Retrieves the cached CategoryDAO instance. */
    public static function getCategoryDao(): CategoryDAO
	{
		return self::$cachedDaos['CategoryDAO'] ??= Repo::category()->dao;
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
