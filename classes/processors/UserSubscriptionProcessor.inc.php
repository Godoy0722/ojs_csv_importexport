<?php

/**
 * @file plugins/importexport/csv/classes/processors/UserSubscriptionProcessor.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2003-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserSubscriptionProcessor
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Process the user subscription data into the database.
 */

namespace PKP\Plugins\ImportExport\CSV\Classes\Processors;

use PKP\Plugins\ImportExport\CSV\Classes\CachedAttributes\CachedDaos;

class UserSubscriptionProcessor
{
	/**
	 * Process the user subscription data into the database.
	 *
	 * @param int $subscriptionTypeId The subscription id to process.
	 * @param int $userId The user id to process.
	 * @param int $journalId The journal id to process.
	 * @param \DateTime $startDate The start date to process.
	 * @param \DateTime $endDate The end date to process.
	 */
	public static function process($subscriptionTypeId, $userId, $journalId, $startDate, $endDate)
	{
		$individualSubscriptionDao = CachedDaos::getIndividualSubscriptionDao();

		$subscription = $individualSubscriptionDao->getByUserIdForJournal($userId, $journalId);

		if (!$subscription) {
			$subscription = $individualSubscriptionDao->newDataObject();

			$subscription->setJournalId($journalId);
			$subscription->setUserId($userId);
			$subscription->setReferenceNumber(null);
			$subscription->setNotes(null);
			$subscription->setStatus(SUBSCRIPTION_STATUS_ACTIVE);
			$subscription->setTypeId($subscriptionTypeId);
			$subscription->setMembership(null);
			$subscription->setDateStart($startDate->format('Y-m-d'));
			$subscription->setDateEnd($endDate->format('Y-m-d'));

			$individualSubscriptionDao->insertObject($subscription);
		} else {
			$subscription->setDateStart($startDate->format('Y-m-d'));
			$subscription->setDateEnd($endDate->format('Y-m-d'));
			$subscription->setStatus(SUBSCRIPTION_STATUS_ACTIVE);

			$individualSubscriptionDao->updateObject($subscription);
		}
	}
}