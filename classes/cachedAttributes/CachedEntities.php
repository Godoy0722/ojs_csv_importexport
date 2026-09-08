<?php

/**
 * @file plugins/importexport/csv/classes/cachedAttributes/CachedEntities.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2003-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CachedEntities
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief This class is responsible for retrieving cached entities such as
 * journals, user groups, genres, categories, sections, and issues.
 */

namespace APP\plugins\importexport\csv\classes\cachedAttributes;

use APP\facades\Repo;
use APP\issue\Issue;
use APP\journal\Journal;
use APP\section\Section;
use APP\subscription\SubscriptionType;
use PKP\category\Category;
use PKP\security\Role;
use PKP\user\User;
use PKP\userGroup\UserGroup;

class CachedEntities
{
    /** @var array<string,Journal> */
    static array $journals = [];

    /** @var array<string,int|null> */
    static array $userGroupIds = [];

    /** @var array<int,array<int,UserGroup>> */
    static array $userGroups = [];

    /** @var array<string,int|null> */
    static array $genreIds = [];

    /** @var array<string,Category|null> */
    static array $categories = [];

    /** @var array<string,Section|null> */
    static array $sections = [];

    /** @var array<int,array<int,Section>> Sections of a journal, keyed by section ID. Absent key means "not loaded yet". */
    static array $sectionsByContext = [];

    /** @var array<string,Issue|null> */
    static array $issues = [];

    /** @var array<string,User|null> */
    static array $users = [];

    /** @var array<string,SubscriptionType|null> */
    static array $subscriptionTypes = [];

    /** Resets all cached entities. Used after dry-mode rollback to clear stale IDs. */
    public static function reset(): void
    {
        static::$journals = [];
        static::$userGroupIds = [];
        static::$userGroups = [];
        static::$genreIds = [];
        static::$categories = [];
        static::$sections = [];
        static::$sectionsByContext = [];
        static::$issues = [];
        static::$users = [];
        static::$subscriptionTypes = [];
    }

    /** Retrieves a cached Journal by its path. Returns null if an error occurs. */
    static function getCachedJournal(string $journalPath): ?Journal
    {
        $journalDao = CachedDaos::getJournalDao();

        return static::$journals[$journalPath] ?? static::$journals[$journalPath] = $journalDao->getByPath($journalPath);
    }

    /** Retrieves a cached userGroup ID by journalId. Returns null if an error occurs. */
    static function getCachedUserGroupId(string $journalPath, int $journalId): ?int
    {
        if (isset(static::$userGroupIds[$journalPath])) {
            return static::$userGroupIds[$journalPath];
        }

        $userGroup = Repo::userGroup()->getCollector()
            ->filterByContextIds([$journalId])
            ->filterByRoleIds([Role::ROLE_ID_AUTHOR])
            ->filterByPermitSelfRegistration(true)
            ->limit(1)
            ->getMany()
            ->first();

        if (!$userGroup) {
            return null;
        }

        return static::$userGroupIds[$journalPath] = $userGroup->getId();
    }

	/** Retrieves a cached User by email. Returns null if an error occurs. */
    static function getCachedUserByEmail(string $email): ?User
    {
		return static::$users[$email] ??= Repo::user()->getByEmail($email);
    }

	/** Retrieves a cached User by username. Returns null if an error occurs. */
    static function getCachedUserByUsername(string $username): ?User
    {
		return static::$users[$username] ??= Repo::user()->getByUsername($username);
    }

	/**
	 * Retrieves a cached UserGroup by journalId. Returns null if an error occurs.
	 *
	 * @return UserGroup[]
	 */
    static function getCachedUserGroupsByJournalId(int $journalId): array
    {
        if (isset(static::$userGroups[$journalId])) {
            return static::$userGroups[$journalId];
        }

        $collector = Repo::userGroup()->getCollector()
            ->filterByContextIds([$journalId]);

        $userGroupsIterator = $collector->getMany();
        $userGroups = [];

        foreach ($userGroupsIterator as $userGroup) {
            $userGroups[$userGroup->getId()] = $userGroup;
        }

        return static::$userGroups[$journalId] = $userGroups;
    }

	/** Retrieves a cached UserGroup by name and journalId. Returns null if an error occurs. */
    static function getCachedUserGroupByName(string $name, int $journalId, string $locale): ?UserGroup
    {
        $userGroups = static::getCachedUserGroupsByJournalId($journalId);

        foreach ($userGroups as $userGroup) {
            if (mb_strtolower($userGroup->getName($locale)) === mb_strtolower($name)) {
                return $userGroup;
            }
        }

        return null;
    }

    /** Retrieves a cached genre ID by genreName and journalId. Returns null if an error occurs. */
    static function getCachedGenreId(string $genreName, int $journalId): ?int
    {
		$genreDao = CachedDaos::getGenreDao();
		$genre = $genreDao->getByKey($genreName, $journalId);

		return static::$genreIds[$genreName] ?? static::$genreIds[$genreName] = $genre->getId();
    }

    /** Retrieves a cached Category by categoryName and journalId. Returns null if an error occurs. */
    static function getCachedCategory(string $categoryName, int $journalId): ?Category
    {
        if (isset(static::$categories[$categoryName])) {
            return static::$categories[$categoryName];
        }

        $categories = Repo::category()->getCollector()
            ->filterByContextIds([$journalId])
            ->getMany();

        foreach ($categories as $category) {
            if ($category->getPath() === $categoryName) {
                return static::$categories[$categoryName] = $category;
            }
        }

        return null;
    }

    /** Retrieves a cached Issue by issue data and journalId. Returns null if an error occurs. */
    static function getCachedIssue(object $data, int $journalId): ?Issue
    {
        $cacheKeyParts = [];
        if (!empty($data->issueTitle)) $cacheKeyParts[] = "title:" . $data->issueTitle;
        if (!empty($data->issueVolume)) $cacheKeyParts[] = "vol:" . $data->issueVolume;
        if (!empty($data->issueNumber)) $cacheKeyParts[] = "num:" . $data->issueNumber;
        if (!empty($data->issueYear)) $cacheKeyParts[] = "year:" . $data->issueYear;

        $customIssueDescription = implode('_', $cacheKeyParts);

        $collector = Repo::issue()->getCollector()->filterByContextIds([$journalId]);

        if (!empty($data->issueVolume)) {
            $collector = $collector->filterByVolumes([(int)$data->issueVolume]);
        }
        if (!empty($data->issueNumber)) {
            $collector = $collector->filterByNumbers([$data->issueNumber]);
        }
        if (!empty($data->issueYear)) {
            $collector = $collector->filterByYears([(int)$data->issueYear]);
        }
        if (!empty($data->issueTitle)) {
            $collector = $collector->filterByTitles([$data->issueTitle]);
        }

        $issues = $collector->limit(1)->getMany();
        $issue = $issues->first();

		static::$issues[$customIssueDescription] = $issue;

		return static::$issues[$customIssueDescription];
    }

    /**
     * Retrieves a Section of the journal matching the fields the CSV row provides.
     * A row may carry the title, the abbreviation or both; whichever it carries has to match.
     * When more than one section matches, the one with the lowest ID wins.
     */
    static function getCachedSection(string $sectionTitle, string $sectionAbbrev, string $locale, int $journalId): ?Section
    {
        $sectionTitle = static::normalizeSectionTitle($sectionTitle);
        $sectionAbbrev = static::normalizeSectionAbbrev($sectionAbbrev);

        if ($sectionTitle === '' && $sectionAbbrev === '') {
            return null;
        }

        foreach (static::getSectionsForContext($journalId) as $section) {
            $titleMatches = $sectionTitle === '' || static::normalizeSectionTitle($section->getTitle($locale)) === $sectionTitle;
            $abbrevMatches = $sectionAbbrev === '' || static::normalizeSectionAbbrev($section->getAbbrev($locale)) === $sectionAbbrev;

            if ($titleMatches && $abbrevMatches) {
                return $section;
            }
        }

        return null;
    }

    static function getCachedSectionById(int $baseSectionId, int $journalId, string $locale): ?Section
    {
        if (isset(static::$sections["sectionId_{$baseSectionId}"])) {
            return static::$sections["sectionId_{$baseSectionId}"];
        }

        $section = Repo::section()->get($baseSectionId, $journalId);
        if (!$section) {
            return null;
        }

        static::indexSection($section, $journalId);

        return $section;
    }

    /** Makes a Section reachable by the lookups without hitting the database again. */
    public static function indexSection(Section $section, int $journalId): void
    {
        static::$sections["sectionId_{$section->getId()}"] = $section;

        if (isset(static::$sectionsByContext[$journalId])) {
            static::$sectionsByContext[$journalId][$section->getId()] = $section;
            ksort(static::$sectionsByContext[$journalId]);
        }
    }

    /**
     * Every section of the journal, keyed and ordered by ID. Read from the database once per journal.
     *
     * @return array<int,Section>
     */
    private static function getSectionsForContext(int $journalId): array
    {
        if (isset(static::$sectionsByContext[$journalId])) {
            return static::$sectionsByContext[$journalId];
        }

        $sections = Repo::section()->getCollector()
            ->filterByContextIds([$journalId])
            ->getMany();

        static::$sectionsByContext[$journalId] = [];

        foreach ($sections as $section) {
            static::$sections["sectionId_{$section->getId()}"] = $section;
            static::$sectionsByContext[$journalId][$section->getId()] = $section;
        }

        ksort(static::$sectionsByContext[$journalId]);

        return static::$sectionsByContext[$journalId];
    }

    private static function normalizeSectionTitle(string|array|null $sectionTitle): string
    {
        return is_string($sectionTitle) ? trim($sectionTitle) : '';
    }

    private static function normalizeSectionAbbrev(string|array|null $sectionAbbrev): string
    {
        return is_string($sectionAbbrev) ? mb_strtoupper(trim($sectionAbbrev)) : '';
    }

	/** Retrieves a cached SubscriptionType by subscriptionType and journalId. Returns null if an error occurs. */
	static function getCachedSubscriptionType(string $subscriptionType, int $journalId): ?SubscriptionType
    {
        if (isset(static::$subscriptionTypes[$subscriptionType])) {
            return static::$subscriptionTypes[$subscriptionType];
        }

        $subscriptionTypeDao = CachedDaos::getSubscriptionTypeDao();
        $retrievedType = $subscriptionTypeDao->getById((int) $subscriptionType, $journalId);

        return static::$subscriptionTypes[$subscriptionType] = $retrievedType;
    }
}
