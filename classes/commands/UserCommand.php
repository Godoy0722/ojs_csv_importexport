<?php

/**
 * @file plugins/importexport/csv/classes/commands/UserCommand.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2003-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserCommand
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Handles the issue import when the user uses the issue command
 */

namespace APP\plugins\importexport\csv\classes\commands;

use APP\plugins\importexport\csv\classes\cachedAttributes\CachedEntities;
use APP\plugins\importexport\csv\classes\handlers\CSVFileHandler;
use APP\plugins\importexport\csv\classes\handlers\WelcomeEmailHandler;
use APP\plugins\importexport\csv\classes\processors\UserGroupsProcessor;
use APP\plugins\importexport\csv\classes\processors\UserInterestsProcessor;
use APP\plugins\importexport\csv\classes\processors\UsersProcessor;
use APP\plugins\importexport\csv\classes\processors\UserSubscriptionProcessor;
use APP\plugins\importexport\csv\classes\validations\InvalidRowValidations;
use APP\plugins\importexport\csv\classes\validations\RequiredUserHeaders;
use APP\plugins\importexport\csv\classes\handlers\OrcidHandler;
use PKP\security\Validation;
use PKP\user\User;

class UserCommand
{
    /** Expected row size for a CSV based on the command passed as argument */
    private int $expectedRowSize;

    /** The folder containing all CSV files that the command must go through */
    private string $sourceDir;

    private int $processedRows;

    private int $failedRows;

    private bool $sendWelcomeEmail;

    private User $senderEmailUser;

    public function __construct(string $sourceDir, User $user, bool $sendWelcomeEmail)
    {
        $this->expectedRowSize = count(RequiredUserHeaders::$userHeaders);
        $this->sourceDir = $sourceDir;
        $this->senderEmailUser = $user;
        $this->sendWelcomeEmail = $sendWelcomeEmail;
    }

    public function run(): void
    {
        foreach (new \DirectoryIterator($this->sourceDir) as $fileInfo) {
            if (!$fileInfo->isFile() || $fileInfo->getExtension() !== 'csv') {
                continue;
            }

            $basename = $fileInfo->getBasename();
            if (str_starts_with($basename, 'invalid_')) {
                continue;
            }

            $filePath = $fileInfo->getPathname();
            $file = CSVFileHandler::createReadableCSVFile($filePath);
            if (is_null($file)) {
                continue;
            }

            $invalidCsvFile = CSVFileHandler::createCSVFileInvalidRows($this->sourceDir, "invalid_{$basename}", RequiredUserHeaders::$userHeaders);
            if (is_null($invalidCsvFile)) {
                continue;
            }

            $this->processedRows = 0;
            $this->failedRows = 0;

            foreach ($file as $index => $fields) {
                if (!$index || empty(array_filter($fields))) {
                    continue; // Skip headers or end of file
                }

                ++$this->processedRows;

                $reason = InvalidRowValidations::validateRowContainAllFields($fields, $this->expectedRowSize);
                if (!is_null($reason)) {
                    CSVFileHandler::processFailedRow($invalidCsvFile, $fields, $this->expectedRowSize, $reason, $this->failedRows);
                    continue;
                }

                $fieldsList = array_pad(array_map('trim', $fields), $this->expectedRowSize, null);
                $data = (object) array_combine(RequiredUserHeaders::$userHeaders, $fieldsList);

                $reason = InvalidRowValidations::validateRowHasAllRequiredFields($data, [RequiredUserHeaders::class, 'validateRowHasAllRequiredFields']);
                if (!is_null($reason)) {
                    CSVFileHandler::processFailedRow($invalidCsvFile, $fields, $this->expectedRowSize, $reason, $this->failedRows);
                    continue;
                }

                $journal = CachedEntities::getCachedJournal($data->journalPath);

                $reason = InvalidRowValidations::validateJournalIsValid($journal, $data->journalPath);
                if (!is_null($reason)) {
                    CSVFileHandler::processFailedRow($invalidCsvFile, $fields, $this->expectedRowSize, $reason, $this->failedRows);
                    continue;
                }

                $existingUserByEmail = CachedEntities::getCachedUserByEmail($data->email);
                $isNewUser = is_null($existingUserByEmail);

                if ($isNewUser) {
                    if ($data->username) {
                        $existingUserByUsername = CachedEntities::getCachedUserByUsername($data->username);
                        if (!is_null($existingUserByUsername)) {
                            CSVFileHandler::processFailedRow($invalidCsvFile, $fields, $this->expectedRowSize, __('plugins.importexport.csv.userAlreadyExistsWithUsername', ['username' => $data->username]), $this->failedRows);
                            continue;
                        }
                    }

                    if (empty($data->username)) {
                        $data->username = UsersProcessor::getValidUsername($data->firstname, $data->lastname);
                    }
                }

                $roles = array_map('trim', explode(';', $data->roles));

                if ($isNewUser) {
                    $reason = InvalidRowValidations::validateAllUserGroupsAreValid($roles, $journal->getId(), $journal->getPrimaryLocale());
                    if (!is_null($reason)) {
                        CSVFileHandler::processFailedRow($invalidCsvFile, $fields, $this->expectedRowSize, $reason, $this->failedRows);
                        continue;
                    }
                }

                if (!empty($data->subscriptionType) || !empty($data->startDate) || !empty($data->endDate)) {
					if (!RequiredUserHeaders::validateSubscriptionFields($data)) {
						$reason = __('plugins.importexport.csv.missingSubscriptionFields', ['email' => $data->email]);
						CSVFileHandler::processFailedRow($invalidCsvFile, $fields, $this->expectedRowSize, $reason, $this->failedRows);
						continue;
					}

					$subscriptionType = CachedEntities::getCachedSubscriptionType($data->subscriptionType, $journal->getId());

                    $reason = InvalidRowValidations::validateSubscriptionType($subscriptionType, $data->subscriptionType, $journal->getId());
                    if (!is_null($reason)) {
                        CSVFileHandler::processFailedRow($invalidCsvFile, $fields, $this->expectedRowSize, $reason, $this->failedRows);
                        continue;
                    }

					$reason = InvalidRowValidations::validateSubscriptionDates($data->startDate, $data->endDate);
					if ($reason) {
						CSVFileHandler::processFailedRow($invalidCsvFile, $fields, $this->expectedRowSize, $reason, $this->failedRows);
						continue;
					}
                }

                if (!empty($data->orcid)) {
                    $reason = OrcidHandler::validate($data->orcid);
                    if (!is_null($reason)) {
                        CSVFileHandler::processFailedRow($invalidCsvFile, $fields, $this->expectedRowSize, $reason, $this->failedRows);
                        continue;
                    }
                }

                if ($isNewUser && is_null($data->tempPassword)) {
                    $data->tempPassword = Validation::generatePassword();
                }

                $user = UsersProcessor::process($data, $journal->getPrimaryLocale());
                $userId = $user->getId();
                $userInterests = array_map('trim', explode(';', $data->reviewInterests));
                UserInterestsProcessor::process($userInterests, $userId);

                if ($isNewUser) {
                    UserGroupsProcessor::process($roles, $userId, $journal->getId(), $journal->getPrimaryLocale());
                }

                if (!empty($data->subscriptionType) && !empty($data->startDate) && !empty($data->endDate)) {
                    $dateFormat = 'Y-m-d';
                    $startDate = \DateTime::createFromFormat($dateFormat, $data->startDate);
                    $endDate = \DateTime::createFromFormat($dateFormat, $data->endDate);

                    UserSubscriptionProcessor::process((int) $data->subscriptionType, $user->getId(), $journal->getId(), $startDate, $endDate);
                }

                if ($this->sendWelcomeEmail && $isNewUser) {
                    WelcomeEmailHandler::sendWelcomeEmail($journal, $user, $this->senderEmailUser, $data->tempPassword);
                }
            }

            echo __('plugins.importexpot.csv.fileProcessFinished', [
                'filename' => $fileInfo->getFilename(),
                'processedRows' => $this->processedRows,
                'failedRows' => $this->failedRows,
            ]) . "\n";

            if (!$this->failedRows) {
                unlink($this->sourceDir . '/' . "invalid_{$basename}");
            }
        }
    }
}
