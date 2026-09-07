<?php

/**
 * @file plugins/importexport/csv/classes/processors/FundersProcessor.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2003-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FundersProcessor
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Process funder data into the database using the Funding plugin.
 */

namespace APP\plugins\importexport\csv\classes\processors;

use APP\plugins\importexport\csv\classes\cachedAttributes\CachedDaos;
use APP\publication\Publication;
use APP\submission\Submission;
use PKP\plugins\PluginRegistry;

class FundersProcessor
{
    private static function getFundingPlugin(int $contextId): ?object
    {
        return PluginRegistry::getPlugin('generic', 'FundingPlugin')
            ?? PluginRegistry::loadPlugin('generic', 'funding', $contextId);
    }

    public static function isFundingPluginEnabled(int $contextId): bool
    {
        $fundingPlugin = static::getFundingPlugin($contextId);

        return (bool) ($fundingPlugin && $fundingPlugin->getEnabled($contextId));
    }

    public static function isCrossrefValidationEnabled(int $contextId): bool
    {
        $fundingPlugin = static::getFundingPlugin($contextId);

        if (!$fundingPlugin || !$fundingPlugin->getEnabled($contextId)) {
            return false;
        }

        return (bool) $fundingPlugin->getSetting($contextId, 'enableGrantIdValidation');
    }

    public static function process(object $data, Submission $submission, int $contextId, ?Publication $basePublication = null): void
    {
        if (!static::isFundingPluginEnabled($contextId)) {
            return;
        }

        if (static::submissionHasFunders($submission->getId())) {
            return;
        }

        if (!empty($data->funders)) {
            static::createFundersFromString($data->funders, $submission->getId(), $contextId);
        } elseif ($basePublication && ($baseSubmissionId = $basePublication->getData('submissionId')) && $baseSubmissionId !== $submission->getId()) {
            static::cloneFundersFromSubmission($baseSubmissionId, $submission->getId(), $contextId);
        }
    }

    public static function processMultiLocale(object $data, Submission $submission, int $contextId): void
    {
        if (!static::isFundingPluginEnabled($contextId)) {
            return;
        }

        if (empty($data->funders)) {
            return;
        }

        if (static::submissionHasFunders($submission->getId())) {
            return;
        }

        static::createFundersFromString($data->funders, $submission->getId(), $contextId);
    }

    private static function submissionHasFunders(int $submissionId): bool
    {
        $funderDao = CachedDaos::getFunderDao();
        if (!$funderDao) {
            return false;
        }

        $existingFunders = $funderDao->getBySubmissionId($submissionId);

        return $existingFunders->next() !== null;
    }

    private static function createFundersFromString(string $fundersString, int $submissionId, int $contextId): void
    {
        $funderDao = CachedDaos::getFunderDao();
        $funderAwardDao = CachedDaos::getFunderAwardDao();
        if (!$funderDao || !$funderAwardDao) {
            return;
        }

        $fundersArray = array_map('trim', explode(';', $fundersString));

        foreach ($fundersArray as $funderString) {
            if (empty($funderString)) {
                continue;
            }

            $funderParts = array_map('trim', explode(',', $funderString));
            $funderName = $funderParts[0] ?? '';
            $funderIdentification = $funderParts[1] ?? '';
            $awardsString = $funderParts[2] ?? '';

            if (empty($funderName)) {
                continue;
            }

            $funder = $funderDao->newDataObject();
            $funder->setContextId($contextId);
            $funder->setSubmissionId($submissionId);
            $funder->setFunderIdentification($funderIdentification);
            $funder->setFunderName($funderName);

            $funderId = $funderDao->insertObject($funder);

            if (!empty($awardsString) && $funderId) {
                $awardsArray = array_map('trim', explode('|', $awardsString));

                foreach ($awardsArray as $awardNumber) {
                    if (empty($awardNumber)) {
                        continue;
                    }

                    $funderAward = $funderAwardDao->newDataObject();
                    $funderAward->setFunderId($funderId);
                    $funderAward->setFunderAwardNumber($awardNumber);
                    $funderAwardDao->insertObject($funderAward);
                }
            }
        }
    }

    private static function cloneFundersFromSubmission(int $baseSubmissionId, int $newSubmissionId, int $contextId): void
    {
        $funderDao = CachedDaos::getFunderDao();
        $funderAwardDao = CachedDaos::getFunderAwardDao();
        if (!$funderDao || !$funderAwardDao) {
            return;
        }

        $baseFunders = $funderDao->getBySubmissionId($baseSubmissionId);

        foreach ($baseFunders->toIterator() as $baseFunder) {
            $newFunder = $funderDao->newDataObject();
            $newFunder->setContextId($contextId);
            $newFunder->setSubmissionId($newSubmissionId);
            $newFunder->setFunderIdentification($baseFunder->getFunderIdentification());
            $newFunder->setFunderName($baseFunder->getFunderName());

            $newFunderId = $funderDao->insertObject($newFunder);

            if ($newFunderId) {
                $baseAwards = $funderAwardDao->getByFunderId($baseFunder->getId());
                foreach ($baseAwards->toIterator() as $baseAward) {
                    $newAward = $funderAwardDao->newDataObject();
                    $newAward->setFunderId($newFunderId);
                    $newAward->setFunderAwardNumber($baseAward->getFunderAwardNumber());
                    $funderAwardDao->insertObject($newAward);
                }
            }
        }
    }
}
