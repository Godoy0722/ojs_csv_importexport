<?php

/**
 * @file plugins/importexport/csv/classes/commands/UserCommand.php
 *
 * Copyright (c) 2014-2026 Simon Fraser University
 * Copyright (c) 2003-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserCommand
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Handles the issue import when the user uses the issue command
 */

namespace PKP\Plugins\ImportExport\CSV\Classes\Commands;

import('plugins.importexport.csv.classes.validations.RequiredUserHeaders');

use PKP\Plugins\ImportExport\CSV\Classes\CachedAttributes\CachedEntities;
use PKP\Plugins\ImportExport\CSV\Classes\Handlers\CSVFileHandler;
use PKP\Plugins\ImportExport\CSV\Classes\Handlers\DryModeReporter;
use PKP\Plugins\ImportExport\CSV\Classes\Handlers\OrcidHandler;
use PKP\Plugins\ImportExport\CSV\Classes\Handlers\WelcomeEmailHandler;
use PKP\Plugins\ImportExport\CSV\Classes\Processors\UserGroupsProcessor;
use PKP\Plugins\ImportExport\CSV\Classes\Processors\UserInterestsProcessor;
use PKP\Plugins\ImportExport\CSV\Classes\Processors\UsersProcessor;
use PKP\Plugins\ImportExport\CSV\Classes\Processors\UserSubscriptionProcessor;
use PKP\Plugins\ImportExport\CSV\Classes\Validations\InvalidRowValidations;
use PKP\Plugins\ImportExport\CSV\Classes\Validations\RequiredUserHeaders;
use Illuminate\Database\Capsule\Manager as Capsule;

class UserCommand
{
    /**
     * Expected row size for a CSV based on the command passed as argument
     *
     * @var int
     */
    private $_expectedRowSize;

    /**
     * The folder containing all CSV files that the command must go through
     *
     * @var string
     */
    private $_sourceDir;

    /** @var int */
    private $_processedRows;

    /** @var int */
    private $_failedRows;

    /** @var bool */
    private $_sendWelcomeEmail;

    /** @var \User */
    private $_senderEmailUser;

    /** @var bool */
    private $_dryMode;

    /** @var string|null */
    private $_currentJournalPath;

    /** @var string */
    private $_currentFileBasename = '';

    /**
     * @param string $sourceDir The folder containing all CSV files that the command must go through
     * @param \User $user The user that is importing the CSV file
     * @param bool $sendWelcomeEmail Whether to send welcome email to the user
     * @param bool $dryMode Whether to validate without persisting
     * @param string|null $currentJournalPath Current journal path from GUI context
     */
    public function __construct($sourceDir, $user, $sendWelcomeEmail, $dryMode = false, $currentJournalPath = null)
    {
        $this->_expectedRowSize = count(RequiredUserHeaders::$userHeaders);
        $this->_sourceDir = $sourceDir;
        $this->_senderEmailUser = $user;
        $this->_sendWelcomeEmail = $sendWelcomeEmail;
        $this->_dryMode = $dryMode;
        $this->_currentJournalPath = $currentJournalPath;
    }

    /**
     * @return array
     */
    public function run()
    {
        $totalFiles = 0;
        $totalPassed = 0;
        $totalFailed = 0;
        $results = [
            'filesProcessed' => 0,
            'totalRows' => 0,
            'successfulRows' => 0,
            'createdRows' => 0,
            'updatedRows' => 0,
            'failedRows' => 0,
            'perFile' => [],
        ];

        foreach (new \DirectoryIterator($this->_sourceDir) as $fileInfo) {
            if (!$fileInfo->isFile() || $fileInfo->getExtension() !== 'csv') {
                continue;
            }

            $basename = $fileInfo->getBasename();
            if (strpos($basename, 'invalid_') === 0) {
                continue;
            }

            $filePath = $fileInfo->getPathname();

            $file = CSVFileHandler::createReadableCSVFile($filePath);

            if (is_null($file)) {
                continue;
            }

            $this->_currentFileBasename = $basename;
            $invalidCsvFile = null;

            $this->_processedRows = 0;
            $this->_failedRows = 0;
            $fileFailedRows = [];
            $fileUpdatedRows = 0;
            $fileUpdatedUsers = [];

            if ($this->_dryMode) {
                Capsule::connection()->statement('SET FOREIGN_KEY_CHECKS=0');
                Capsule::connection()->beginTransaction();
            }

            foreach ($file as $index => $fields) {
                if (!$index || empty(array_filter($fields))) {
                    continue; // Skip headers or end of file
                }

                ++$this->_processedRows;

                $reason = InvalidRowValidations::validateRowContainAllFields($fields, $this->_expectedRowSize);

                if (!is_null($reason)) {
                    $this->_processFailedRow($invalidCsvFile, $fields, $reason, $fileFailedRows);
                    continue;
                }

                $fieldsList = array_pad(array_map('trim', $fields), $this->_expectedRowSize, null);
                $data = (object) array_combine(RequiredUserHeaders::$userHeaders, $fieldsList);

                $reason = InvalidRowValidations::validateRowHasAllRequiredFields($data, [RequiredUserHeaders::class, 'validateRowHasAllRequiredFields']);

                if (!is_null($reason)) {
                    $this->_processFailedRow($invalidCsvFile, $fields, $reason, $fileFailedRows);
                    continue;
                }

                $journal = CachedEntities::getCachedJournal($data->journalPath);

                $reason = InvalidRowValidations::validateJournalIsValid($journal, $data->journalPath);
                if (!is_null($reason)) {
                    $this->_processFailedRow($invalidCsvFile, $fields, $reason, $fileFailedRows);
                    continue;
                }

                if ($this->_currentJournalPath !== null && $data->journalPath !== $this->_currentJournalPath) {
                    $reason = __('plugins.importexport.csv.contextPathMismatch', [
                        'contextType' => 'journal',
                        'csvContextPath' => $data->journalPath,
                        'currentContextPath' => $this->_currentJournalPath,
                    ]);
                    $this->_processFailedRow($invalidCsvFile, $fields, $reason, $fileFailedRows);
                    continue;
                }

                $existingUserByEmail = CachedEntities::getCachedUserByEmail($data->email);
                if (!is_null($existingUserByEmail)) {
                    $this->_processFailedRow($invalidCsvFile, $fields, __('plugins.importexport.csv.userAlreadyExistsWithEmail', ['email' => $data->email]), $fileFailedRows);
                    continue;
                }

                if ($data->username) {
                    $existingUserByUsername = CachedEntities::getCachedUserByUsername($data->username);
                    if (!is_null($existingUserByUsername)) {
                        $this->_processFailedRow($invalidCsvFile, $fields, __('plugins.importexport.csv.userAlreadyExistsWithUsername', ['username' => $data->username]), $fileFailedRows);
                        continue;
                    }
                }

				import('plugins.importexport.csv.classes.processors.UsersProcessor');
				if (empty($data->username)) {
					$data->username = UsersProcessor::getValidUsername($data->firstname, $data->lastname);
				}

                $roles = array_map('trim', explode(';', $data->roles));

                $reason = InvalidRowValidations::validateAllUserGroupsAreValid($roles, $journal->getId(), $journal->getPrimaryLocale());

                if (!is_null($reason)) {
                    $this->_processFailedRow($invalidCsvFile, $fields, $reason, $fileFailedRows);
                    continue;
                }

				$subscriptionType = null;

                if (!empty($data->subscriptionType) || !empty($data->startDate) || !empty($data->endDate)) {
					if (!RequiredUserHeaders::validateSubscriptionFields($data)) {
						$reason = __('plugins.importexport.csv.missingSubscriptionFields', ['email' => $data->email]);
						$this->_processFailedRow($invalidCsvFile, $fields, $reason, $fileFailedRows);
						continue;
					}

					$subscriptionType = CachedEntities::getCachedSubscriptionType($data->subscriptionType, $journal->getId());

                    $reason = InvalidRowValidations::validateSubscriptionType($subscriptionType, $data->subscriptionType, $journal->getId());
                    if (!is_null($reason)) {
                        $this->_processFailedRow($invalidCsvFile, $fields, $reason, $fileFailedRows);
                        continue;
                    }

					$reason = InvalidRowValidations::validateSubscriptionDates($data->start_date, $data->end_date);
					if ($reason) {
						$this->_processFailedRow($invalidCsvFile, $fields, $reason, $fileFailedRows);
						continue;
					}
                }

				if (!empty($data->orcid)) {
					import('plugins.importexport.csv.classes.handlers.OrcidHandler');
                    $reason = OrcidHandler::validate($data->orcid);
                    if (!is_null($reason)) {
                        $this->_processFailedRow($invalidCsvFile, $fields, $reason, $fileFailedRows);
                        continue;
                    }
                }

                if (is_null($data->tempPassword)) {
                    $data->tempPassword = \Validation::generatePassword();
                }

                $user = UsersProcessor::process($data, $journal->getPrimaryLocale());
                $userId = $user->getId();

				import('plugins.importexport.csv.classes.processors.UserInterestsProcessor');
                $userInterests = array_map('trim', explode(';', $data->reviewInterests));
                UserInterestsProcessor::process($userInterests, $userId);

				import('plugins.importexport.csv.classes.processors.UserGroupsProcessor');
                UserGroupsProcessor::process($roles, $userId, $journal->getId(), $journal->getPrimaryLocale());

				if (!empty($data->subscriptionType) && !empty($data->startDate) && !empty($data->endDate)) {
					$dateFormat = 'Y-m-d';
					$startDate = \DateTime::createFromFormat($dateFormat, $data->start_date);
					$endDate = \DateTime::createFromFormat($dateFormat, $data->end_date);

					import('plugins.importexport.csv.classes.processors.UserSubscriptionProcessor');
					UserSubscriptionProcessor::process($data, $user->getId(), $journal->getId(), $startDate, $endDate);
				}

                if ($this->_sendWelcomeEmail && !$this->_dryMode) {
					import('plugins.importexport.csv.classes.handlers.WelcomeEmailHandler');
                    WelcomeEmailHandler::sendWelcomeEmail($journal, $user, $this->_senderEmailUser, $data->tempPassword);
                }
            }

            if ($this->_dryMode) {
                $passed = $this->_processedRows - $this->_failedRows;
                import('plugins.importexport.csv.classes.handlers.DryModeReporter');
                DryModeReporter::printFileHeader($basename);
                if (!empty($fileFailedRows)) {
                    DryModeReporter::printTableHeader();
                    foreach ($fileFailedRows as $failedRow) {
                        DryModeReporter::printFailedRow($failedRow['row'], $failedRow['reason']);
                    }
                }
                DryModeReporter::printFileSummary($passed, $this->_failedRows, $this->_processedRows);
                $totalFiles++;
                $totalPassed += $passed;
                $totalFailed += $this->_failedRows;

                Capsule::connection()->rollBack();
                Capsule::connection()->statement('SET FOREIGN_KEY_CHECKS=1');
                CachedEntities::reset();
            }

            echo __('plugins.importexport.csv.submissionFileProcessFinished', [
                'filename' => $fileInfo->getFilename(),
                'processedRows' => $this->_processedRows,
                'failedRows' => $this->_failedRows,
            ]) . "\n";

            if (!$this->_failedRows) {
                @unlink($this->_sourceDir . '/' . "invalid_{$basename}");
            }

            $successful = $this->_processedRows - $this->_failedRows;
            $createdRows = $successful - $fileUpdatedRows;
            $invalidFilename = "invalid_{$basename}";
            $results['perFile'][] = [
                'filename' => $basename,
                'rows' => $this->_processedRows,
                'successful' => $successful,
                'created' => $createdRows,
                'updated' => $fileUpdatedRows,
                'updatedUsers' => $fileUpdatedUsers,
                'failed' => $this->_failedRows,
                'errors' => $fileFailedRows,
                'invalidFile' => ($this->_failedRows > 0 && is_file($this->_sourceDir . '/' . $invalidFilename))
                    ? $invalidFilename
                    : null,
            ];
            $results['filesProcessed']++;
            $results['totalRows'] += $this->_processedRows;
            $results['successfulRows'] += $successful;
            $results['createdRows'] += $createdRows;
            $results['updatedRows'] += $fileUpdatedRows;
            $results['failedRows'] += $this->_failedRows;
        }

        if ($this->_dryMode) {
            import('plugins.importexport.csv.classes.handlers.DryModeReporter');
            DryModeReporter::printGrandTotal($totalFiles, $totalPassed, $totalFailed);
        }

        $results['exitCode'] = $results['failedRows'] > 0 ? 1 : 0;
        return $results;
    }

    /**
     * @param \SplFileObject|null $invalidCsvFile
     * @param array $fields
     * @param string $reason
     * @param array $fileFailedRows
     */
    private function _processFailedRow(&$invalidCsvFile, $fields, $reason, &$fileFailedRows)
    {
        if ($invalidCsvFile === null) {
            $invalidCsvFile = CSVFileHandler::createCSVFileInvalidRows(
                $this->_sourceDir,
                'invalid_' . $this->_currentFileBasename,
                RequiredUserHeaders::$userHeaders
            );
            if ($invalidCsvFile === null) {
                ++$this->_failedRows;
                $fileFailedRows[] = ['row' => $this->_processedRows + 1, 'reason' => $reason];
                return;
            }
        }

        CSVFileHandler::processFailedRow($invalidCsvFile, $fields, $this->_expectedRowSize, $reason, $this->_failedRows);
        $fileFailedRows[] = ['row' => $this->_processedRows + 1, 'reason' => $reason];
    }
}
