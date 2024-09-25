<?php

/**
 * @file plugins/importexport/csv/classes/cachedAttributes/CachedDaos.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2003-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CachedDaos
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief retrieve cached DAOs
 */

namespace APP\plugins\importexport\csv\classes\cachedAttributes;

use APP\core\Application;
use APP\journal\JournalDAO;
use PKP\db\DAO;
use PKP\db\DAORegistry;
use PKP\galley\DAO as GalleyDAO;
use PKP\submission\GenreDAO;
use PKP\submission\SubmissionKeywordDAO;
use PKP\submission\SubmissionSubjectDAO;

class CachedDaos
{
    /** @var DAO[] */
    static array $cachedDaos = [];

    public static function getJournalDao(): JournalDAO
    {
        return self::$cachedDaos['JournalDAO'] ??= DAORegistry::getDAO('JournalDAO');
    }

    public static function getGenreDao(): GenreDAO
    {
        return self::$cachedDaos['GenreDAO'] ??= DAORegistry::getDAO('GenreDAO');
    }

    public static function getSubmissionKeywordDao(): SubmissionKeywordDAO
    {
        return self::$cachedDaos['SubmissionKeywordDAO'] ??= DAORegistry::getDAO('SubmissionKeywordDAO');
    }

    public static function getSubmissionSubjectDao(): SubmissionSubjectDAO
    {
        return self::$cachedDaos['SubmissionSubjectDAO'] ??= DAORegistry::getDAO('SubmissionSubjectDAO');
    }

    public static function getRepresentationDao(): GalleyDAO
    {
        return self::$cachedDaos['RepresentationDAO'] ??= Application::getRepresentationDAO();
    }
}
