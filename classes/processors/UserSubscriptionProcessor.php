<?php

/**
 * @file plugins/importexport/csv/classes/processors/UserSubscriptionProcessor.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserSubscriptionProcessor
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Process the user subscription data into the database.
 */

namespace APP\plugins\importexport\csv\classes\processors;

use APP\plugins\importexport\csv\classes\cachedAttributes\CachedDaos;
use APP\subscription\Subscription;

class UserSubscriptionProcessor
{
	public static function process(
        int $subscriptionTypeId,
        int $userId,
        int $journalId,
        \DateTime $startDate,
        \DateTime $endDate
    )
	{
		$individualSubscriptionDao = CachedDaos::getIndividualSubscriptionDao();

		$subscription = $individualSubscriptionDao->getByUserIdForJournal($userId, $journalId);

		if (!$subscription) {
			$subscription = $individualSubscriptionDao->newDataObject();

			$subscription->setJournalId($journalId);
			$subscription->setUserId($userId);
			$subscription->setReferenceNumber(null);
			$subscription->setNotes(null);
			$subscription->setStatus(Subscription::SUBSCRIPTION_STATUS_ACTIVE);
			$subscription->setTypeId($subscriptionTypeId);
			$subscription->setMembership(null);
			$subscription->setDateStart($startDate->format('Y-m-d'));
			$subscription->setDateEnd($endDate->format('Y-m-d'));

			$individualSubscriptionDao->insertObject($subscription);
		} else {
			$subscription->setDateStart($startDate->format('Y-m-d'));
			$subscription->setDateEnd($endDate->format('Y-m-d'));
			$subscription->setStatus(Subscription::SUBSCRIPTION_STATUS_ACTIVE);

			$individualSubscriptionDao->updateObject($subscription);
		}
	}
}
