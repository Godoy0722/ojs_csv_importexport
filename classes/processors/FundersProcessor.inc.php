<?php

/**
 * @file plugins/importexport/csv/classes/processors/FundersProcessor.php
 *
 * Copyright (c) 2014-2026 Simon Fraser University
 * Copyright (c) 2003-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FundersProcessor
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Process funder data into the database using the Funding plugin.
 */

namespace PKP\Plugins\ImportExport\CSV\Classes\Processors;

class FundersProcessor
{
    /** @var object|null */
    private static $_funderDao;

    /** @var object|null */
    private static $_funderAwardDao;

    /**
     * @return object|null
     */
    private static function getFundingPlugin($contextId)
    {
        $plugin = \PluginRegistry::getPlugin('generic', 'FundingPlugin');
        if (!$plugin) {
            $plugin = \PluginRegistry::loadPlugin('generic', 'funding', $contextId);
        }
        return $plugin;
    }

    /**
     * @param int $contextId
     *
     * @return bool
     */
    public static function isFundingPluginEnabled($contextId)
    {
        if (!class_exists('FunderDAO')) {
            return false;
        }

        $fundingPlugin = self::getFundingPlugin($contextId);
        return (bool) ($fundingPlugin && $fundingPlugin->getEnabled($contextId));
    }

    /**
     * @param int $contextId
     *
     * @return bool
     */
    public static function isCrossrefValidationEnabled($contextId)
    {
        $fundingPlugin = self::getFundingPlugin($contextId);

        if (!$fundingPlugin || !$fundingPlugin->getEnabled($contextId)) {
            return false;
        }

        return (bool) $fundingPlugin->getSetting($contextId, 'enableGrantIdValidation');
    }

    /**
     * @param object $data
     * @param \Submission $submission
     * @param int $contextId
     * @param \Publication|null $basePublication
     *
     * @return void
     */
    public static function process($data, $submission, $contextId, $basePublication = null)
    {
        if (!self::isFundingPluginEnabled($contextId)) {
            return;
        }

        if (self::submissionHasFunders($submission->getId())) {
            return;
        }

        if (!empty($data->funders)) {
            self::createFundersFromString($data->funders, $submission->getId(), $contextId);
        } elseif ($basePublication && ($baseSubmissionId = $basePublication->getData('submissionId')) && $baseSubmissionId !== $submission->getId()) {
            self::cloneFundersFromSubmission($baseSubmissionId, $submission->getId(), $contextId);
        }
    }

    /**
     * @param object $data
     * @param \Submission $submission
     * @param int $contextId
     *
     * @return void
     */
    public static function processMultiLocale($data, $submission, $contextId)
    {
        if (!self::isFundingPluginEnabled($contextId)) {
            return;
        }

        if (empty($data->funders)) {
            return;
        }

        if (self::submissionHasFunders($submission->getId())) {
            return;
        }

        self::createFundersFromString($data->funders, $submission->getId(), $contextId);
    }

    /**
     * @param int $submissionId
     *
     * @return bool
     */
    private static function submissionHasFunders($submissionId)
    {
        self::_initializeDaos();
        if (!self::$_funderDao) {
            return false;
        }

        $existingFunders = self::$_funderDao->getBySubmissionId($submissionId);
        return $existingFunders->next() !== null;
    }

    /**
     * @param string $fundersString
     * @param int $submissionId
     * @param int $contextId
     *
     * @return void
     */
    private static function createFundersFromString($fundersString, $submissionId, $contextId)
    {
        self::_initializeDaos();
        if (!self::$_funderDao || !self::$_funderAwardDao) {
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

            $funder = self::$_funderDao->newDataObject();
            $funder->setContextId($contextId);
            $funder->setSubmissionId($submissionId);
            $funder->setFunderIdentification($funderIdentification);
            $funder->setFunderName($funderName);

            $funderId = self::$_funderDao->insertObject($funder);

            if (!empty($awardsString) && $funderId) {
                $awardsArray = array_map('trim', explode('|', $awardsString));

                foreach ($awardsArray as $awardNumber) {
                    if (empty($awardNumber)) {
                        continue;
                    }

                    $funderAward = self::$_funderAwardDao->newDataObject();
                    $funderAward->setFunderId($funderId);
                    $funderAward->setFunderAwardNumber($awardNumber);
                    self::$_funderAwardDao->insertObject($funderAward);
                }
            }
        }
    }

    /**
     * @param int $baseSubmissionId
     * @param int $newSubmissionId
     * @param int $contextId
     *
     * @return void
     */
    private static function cloneFundersFromSubmission($baseSubmissionId, $newSubmissionId, $contextId)
    {
        self::_initializeDaos();
        if (!self::$_funderDao || !self::$_funderAwardDao) {
            return;
        }

        $baseFunders = self::$_funderDao->getBySubmissionId($baseSubmissionId);

        foreach ($baseFunders->toIterator() as $baseFunder) {
            $newFunder = self::$_funderDao->newDataObject();
            $newFunder->setContextId($contextId);
            $newFunder->setSubmissionId($newSubmissionId);
            $newFunder->setFunderIdentification($baseFunder->getFunderIdentification());
            $newFunder->setFunderName($baseFunder->getFunderName());

            $newFunderId = self::$_funderDao->insertObject($newFunder);

            if ($newFunderId) {
                $baseAwards = self::$_funderAwardDao->getByFunderId($baseFunder->getId());
                foreach ($baseAwards->toIterator() as $baseAward) {
                    $newAward = self::$_funderAwardDao->newDataObject();
                    $newAward->setFunderId($newFunderId);
                    $newAward->setFunderAwardNumber($baseAward->getFunderAwardNumber());
                    self::$_funderAwardDao->insertObject($newAward);
                }
            }
        }
    }

    /**
     * @return void
     */
    private static function _initializeDaos()
    {
        if (!class_exists('FunderDAO') || !class_exists('FunderAwardDAO')) {
            self::$_funderDao = null;
            self::$_funderAwardDao = null;
            return;
        }

        self::$_funderDao ??= \DAORegistry::getDAO('FunderDAO');
        self::$_funderAwardDao ??= \DAORegistry::getDAO('FunderAwardDAO');
    }
}
