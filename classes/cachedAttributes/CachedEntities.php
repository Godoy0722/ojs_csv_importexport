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
use APP\subscription\SubscriptionType;
use APP\plugins\importexport\csv\shared\cachedAttributes\CachedEntities as SharedCachedEntities;

class CachedEntities extends SharedCachedEntities
{
    /** @var array<string,Journal> */
    static array $journals = [];

    /** @var array<string,Issue|null> */
    static array $issues = [];

    /** @var array<string,SubscriptionType|null> */
    static array $subscriptionTypes = [];

    /** @var array<int,array<string,bool>> Journal-scoped cache of existing DOIs from the database */
    static array $existingDoisByJournal = [];

    /** Resets all cached entities. Used after dry-mode rollback to clear stale IDs. */
    public static function reset(): void
    {
        parent::reset();

        static::$existingDoisByJournal = [];
        static::$journals = [];
        static::$issues = [];
        static::$subscriptionTypes = [];
    }

    /** Retrieves a cached Journal by its path. Returns null if an error occurs. */
    static function getCachedJournal(string $journalPath): ?Journal
    {
        $journalDao = CachedDaos::getJournalDao();
        return self::$journals[$journalPath] ?? self::$journals[$journalPath] = $journalDao->getByPath($journalPath);
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

        if (isset(self::$issues[$customIssueDescription])) {
            return self::$issues[$customIssueDescription];
        }

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

        self::$issues[$customIssueDescription] = $issue;

        return self::$issues[$customIssueDescription];
    }

	/** Retrieves a cached SubscriptionType by subscriptionType and journalId. Returns null if an error occurs. */
	static function getCachedSubscriptionType(string $subscriptionType, int $journalId): ?SubscriptionType
    {
        if (isset(self::$subscriptionTypes[$subscriptionType])) {
            return self::$subscriptionTypes[$subscriptionType];
        }

        return self::$subscriptionTypes[$subscriptionType] ??= CachedDaos::getSubscriptionTypeDao()->getById((int) $subscriptionType, $journalId);
    }

    /** Retrieves all existing DOIs for a journal, cached statically. Returns assoc array [doi => true]. */
    static function getExistingDois(int $journalId): array
    {
        if (isset(static::$existingDoisByJournal[$journalId])) {
            return static::$existingDoisByJournal[$journalId];
        }

        $dois = \Illuminate\Support\Facades\DB::table('dois')
            ->where('context_id', $journalId)
            ->whereNotNull('doi')
            ->distinct()
            ->pluck('doi');

        $map = [];
        foreach ($dois as $doi) {
            $map[$doi] = true;
        }

        return static::$existingDoisByJournal[$journalId] = $map;
    }
}
