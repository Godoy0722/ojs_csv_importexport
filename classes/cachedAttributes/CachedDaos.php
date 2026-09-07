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
        return static::$cachedDaos['JournalDAO'] ??= DAORegistry::getDAO('JournalDAO');
    }

    /** Retrieves the cached GenreDAO instance. */
    public static function getGenreDao(): GenreDAO
    {
        return static::$cachedDaos['GenreDAO'] ??= DAORegistry::getDAO('GenreDAO');
    }

    /** Retrieves the cached SubmissionKeywordDAO instance. */
    public static function getSubmissionKeywordDao(): SubmissionKeywordDAO
    {
        return static::$cachedDaos['SubmissionKeywordDAO'] ??= DAORegistry::getDAO('SubmissionKeywordDAO');
    }

    /** Retrieves the cached SubmissionSubjectDAO instance. */
    public static function getSubmissionSubjectDao(): SubmissionSubjectDAO
    {
        return static::$cachedDaos['SubmissionSubjectDAO'] ??= DAORegistry::getDAO('SubmissionSubjectDAO');
    }

    /** Retrieves the cached InterestDAO instance, which is used for user interests. */
    public static function getUserInterestDao(): InterestDAO
    {
        return static::$cachedDaos['InterestDAO'] ??= DAORegistry::getDAO('InterestDAO');
    }

    /** Retrieves the cached CategoryDAO instance. */
    public static function getCategoryDao(): CategoryDAO
	{
		return static::$cachedDaos['CategoryDAO'] ??= Repo::category()->dao;
	}

    /** Retrieves the cached IndividualSubscriptionDAO instance. */
    public static function getIndividualSubscriptionDao(): SubscriptionIndividualSubscriptionDAO
	{
		return static::$cachedDaos['IndividualSubscriptionDAO'] ??= DAORegistry::getDAO('IndividualSubscriptionDAO');
	}

    /** Retrieves the cached SubscriptionTypeDAO instance. */
    public static function getSubscriptionTypeDao(): SubscriptionSubscriptionTypeDAO
	{
		return static::$cachedDaos['SubscriptionTypeDAO'] ??= DAORegistry::getDAO('SubscriptionTypeDAO');
	}

    /** Retrieves the cached FunderDAO instance when the Funding plugin is available. */
    public static function getFunderDao(): ?object
    {
        if (isset(static::$cachedDaos['FunderDAO'])) {
            return static::$cachedDaos['FunderDAO'];
        }

        if (!file_exists('plugins/generic/funding/classes/FunderDAO.inc.php')) {
            return null;
        }

        import('plugins.generic.funding.classes.FunderDAO');

        return static::$cachedDaos['FunderDAO'] = new \FunderDAO();
    }

    /** Retrieves the cached FunderAwardDAO instance when the Funding plugin is available. */
    public static function getFunderAwardDao(): ?object
    {
        if (isset(static::$cachedDaos['FunderAwardDAO'])) {
            return static::$cachedDaos['FunderAwardDAO'];
        }

        if (!file_exists('plugins/generic/funding/classes/FunderAwardDAO.inc.php')) {
            return null;
        }

        import('plugins.generic.funding.classes.FunderAwardDAO');

        return static::$cachedDaos['FunderAwardDAO'] = new \FunderAwardDAO();
    }
}
