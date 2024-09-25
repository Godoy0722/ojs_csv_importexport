<?php

/**
 * @file plugins/importexport/csv/classes/cachedAttributes/CachedEntities.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2003-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CachedEntities
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief retrieve cached Entities
 */

namespace APP\plugins\importexport\csv\classes\cachedAttributes;

use APP\facades\Repo;
use APP\issue\Issue;
use APP\journal\Journal;
use APP\section\Section;
use PKP\category\Category;
use PKP\security\Role;

class CachedEntities
{
    /** @var Journal[] */
    static array $journals = [];

    /** @var int[] */
    static array $userGroupIds = [];

    /** @var int[] */
    static array $genreIds = [];

    /** @var Category[] */
    static array $categories = [];

    /** @var Section[] */
    static array $sections = [];

    /** @var Issue[] */
    static array $issues = [];

    /**
     * Get a cached Journal by journalPath. Return null if an error occurred.
     */
    static function getCachedJournal(string $journalPath): ?Journal
    {
        $journalDao = CachedDaos::getJournalDao();

        return self::$journals[$journalPath] ??= $journalDao->getByPath($journalPath);
    }

    /**
     * Get a cached userGroup ID by journalId. Return null if an error occurred.
     */
    static function getCachedUserGroupId(string $journalPath, int $journalId): ?int
    {
        return self::$userGroupIds[$journalPath] ??= Repo::userGroup()
        ->getByRoleIds([Role::ROLE_ID_AUTHOR], $journalId)
        ->first()?->getId();
    }

    /**
     * Get a cached genre ID by journalId. Return null if an error occurred.
     */
    static function getCachedGenreId(string $genreName, int $journalId): ?int
    {
        return self::$genreIds[$genreName] ??= CachedDaos::getGenreDao()
            ->getByKey($genreName, $journalId)
            ?->getId();
    }

    static function getCachedCategory(string $categoryName, int $journalId): ?Category
    {
        $result = Repo::category()->getCollector()
        ->filterByContextIds([$journalId])
        ->filterByPaths([$categoryName])
        ->limit(1)
        ->getMany()
        ->toArray();

        return self::$categories[$categoryName] ??= (array_values($result)[0] ?? null);
    }

    static function getCachedIssue(object $data, int $journalId): ?Issue
    {
        $customIssueDescription = "{$data->issueVolume}_{$data->issueNumber}_{$data->issueYear}";
        $result =  Repo::issue()->getCollector()
            ->filterByContextIds([$journalId])
            ->filterByNumbers([$data->issueNumber])
            ->filterByVolumes([$data->issueVolume])
            ->filterByYears([$data->issueYear])
            ->limit(1)
            ->getMany()
            ->toArray();

        return self::$issues[$customIssueDescription] ??= (array_values($result)[0] ?? null);
    }

    static function getCachedSection(string $sectionTitle, string $sectionAbbrev, int $journalId): ?Section
    {
        $result = Repo::section()->getCollector()
            ->filterByContextIds([$journalId])
            ->filterByTitles([$sectionTitle])
            ->filterByAbbrevs([$sectionAbbrev])
            ->limit(1)
            ->getMany()
            ->toArray();

        return self::$sections["{$sectionTitle}_{$sectionAbbrev}"] ??= (array_values($result)[0] ?? null);
    }
}
