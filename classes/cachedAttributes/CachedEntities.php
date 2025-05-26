<?php

/**
 * @file plugins/importexport/csv/classes/cachedAttributes/CachedEntities.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
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

    /** @var array<string,Issue|null> */
    static array $issues = [];

    /** @var array<string,User|null> */
    static array $users = [];

    /** @var array<string,SubscriptionType|null> */
    static array $subscriptionTypes = [];

    /** Retrieves a cached Journal by its path. Returns null if an error occurs. */
    static function getCachedJournal(string $journalPath): ?Journal
    {
        $journalDao = CachedDaos::getJournalDao();

        return self::$journals[$journalPath] ?? self::$journals[$journalPath] = $journalDao->getByPath($journalPath);
    }

    /** Retrieves a cached userGroup ID by journalId. Returns null if an error occurs. */
    static function getCachedUserGroupId(string $journalPath, int $journalId): ?int
    {
        if (isset(self::$userGroupIds[$journalPath])) {
            return self::$userGroupIds[$journalPath];
        }

        $userGroups = Repo::userGroup()->getByRoleIds([Role::ROLE_ID_AUTHOR], $journalId);

        if (empty($userGroups)) {
            return null;
        }

        $userGroup = $userGroups->first();
        return self::$userGroupIds[$journalPath] = $userGroup->getId();
    }

	/** Retrieves a cached User by email. Returns null if an error occurs. */
    static function getCachedUserByEmail(string $email): ?User
    {
		return self::$users[$email] ??= Repo::user()->getByEmail($email);
    }

	/** Retrieves a cached User by username. Returns null if an error occurs. */
    static function getCachedUserByUsername(string $username): ?User
    {
		return self::$users[$username] ??= Repo::user()->getByUsername($username);
    }

	/**
	 * Retrieves a cached UserGroup by journalId. Returns null if an error occurs.
	 *
	 * @return UserGroup[]
	 */
    static function getCachedUserGroupsByJournalId(int $journalId): array
    {
        if (isset(self::$userGroups[$journalId])) {
            return self::$userGroups[$journalId];
        }

        $userGroups = [];
        $userGroupsCollection = UserGroup::withContextIds([$journalId])->get();

        foreach ($userGroupsCollection as $userGroup) {
            $userGroups[$userGroup->getId()] = $userGroup;
        }

        return self::$userGroups[$journalId] = $userGroups;
    }

	/** Retrieves a cached UserGroup by name and journalId. Returns null if an error occurs. */
    static function getCachedUserGroupByName(string $name, int $journalId, string $locale): ?UserGroup
    {
        $userGroups = self::getCachedUserGroupsByJournalId($journalId);

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
		return self::$genreIds[$genreName] ??= CachedDaos::getGenreDao()->getByKey($genreName, $journalId)->getId();
    }

    /** Retrieves a cached Category by categoryName and journalId. Returns null if an error occurs. */
    static function getCachedCategory(string $categoryName, int $journalId): ?Category
    {
        if (isset(self::$categories[$categoryName])) {
            return self::$categories[$categoryName];
        }

        $categories = Repo::category()->getCollector()
            ->filterByContextIds([$journalId])
            ->getMany();

        foreach ($categories as $category) {
            if ($category->getPath() === $categoryName) {
                return self::$categories[$categoryName] = $category;
            }
        }

        return null;
    }

    /** Retrieves a cached Issue by issue data and journalId. Returns null if an error occurs. */
    static function getCachedIssue(object $data, int $journalId): ?Issue
    {
        $customIssueDescription = "{$data->issueVolume}_{$data->issueNumber}_{$data->issueYear}";

        if (isset(self::$issues[$customIssueDescription])) {
            return self::$issues[$customIssueDescription];
        }

        $issues = Repo::issue()->getCollector()
            ->filterByContextIds([$journalId])
            ->filterByVolumes([$data->issueVolume])
            ->filterByNumbers([$data->issueNumber])
            ->filterByYears([$data->issueYear])
            ->getMany();

        self::$issues[$customIssueDescription] = $issues->first();

        return self::$issues[$customIssueDescription];
    }

    /** Retrieves a cached Section by sectionTitle, sectionAbbrev, and journalId. Returns null if an error occurs. */
    static function getCachedSection(string $sectionTitle, string $sectionAbbrev, string $locale, int $journalId): ?Section
    {
        $customSectionKey = "{$sectionTitle}_{$sectionAbbrev}";

        if (isset(self::$sections[$customSectionKey])) {
            return self::$sections[$customSectionKey];
        }

        $sections = Repo::section()->getCollector()
            ->filterByContextIds([$journalId])
            ->getMany();

        foreach ($sections as $section) {
            if ($section->getAbbrev($locale) === $sectionAbbrev && $section->getTitle($locale) === $sectionTitle) {
                return self::$sections[$customSectionKey] = $section;
            }
        }

        return null;
    }

	/** Retrieves a cached SubscriptionType by subscriptionType and journalId. Returns null if an error occurs. */
	static function getCachedSubscriptionType(string $subscriptionType, int $journalId): ?SubscriptionType
    {
        if (isset(self::$subscriptionTypes[$subscriptionType])) {
            return self::$subscriptionTypes[$subscriptionType];
        }

        return self::$subscriptionTypes[$subscriptionType] ??= CachedDaos::getSubscriptionTypeDao()->getById((int) $subscriptionType, $journalId);
    }
}
