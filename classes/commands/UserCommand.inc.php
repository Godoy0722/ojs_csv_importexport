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
import('plugins.importexport.csv.classes.exceptions.RowValidationException');

use PKP\Plugins\ImportExport\CSV\Classes\CachedAttributes\CachedEntities;
use PKP\Plugins\ImportExport\CSV\Classes\Handlers\CSVFileHandler;
use PKP\Plugins\ImportExport\CSV\Classes\Handlers\DryModeReporter;
use PKP\Plugins\ImportExport\CSV\Classes\Handlers\OrcidHandler;
use PKP\Plugins\ImportExport\CSV\Classes\Handlers\WelcomeEmailHandler;
use PKP\Plugins\ImportExport\CSV\Classes\Processors\UserGroupsProcessor;
use PKP\Plugins\ImportExport\CSV\Classes\Processors\UserInterestsProcessor;
use PKP\Plugins\ImportExport\CSV\Classes\Processors\UsersProcessor;
use PKP\Plugins\ImportExport\CSV\Classes\Processors\UserSubscriptionProcessor;
use PKP\Plugins\ImportExport\CSV\Classes\Exceptions\RowValidationException;
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

                try {
                    InvalidRowValidations::validateRowContainAllFields($fields, $this->_expectedRowSize);

                    $fieldsList = array_pad(array_map('trim', $fields), $this->_expectedRowSize, null);
                    $data = (object) array_combine(RequiredUserHeaders::$userHeaders, $fieldsList);

                    InvalidRowValidations::validateRowHasAllRequiredFields($data, [RequiredUserHeaders::class, 'validateRowHasAllRequiredFields']);

                    $journal = CachedEntities::getCachedJournal($data->journalPath);

                    InvalidRowValidations::validateJournalIsValid($journal, $data->journalPath);

                    if ($this->_currentJournalPath !== null && $data->journalPath !== $this->_currentJournalPath) {
                        throw new RowValidationException(__('plugins.importexport.csv.contextPathMismatch', [
                            'contextType' => 'journal',
                            'csvContextPath' => $data->journalPath,
                            'currentContextPath' => $this->_currentJournalPath,
                        ]));
                    }

                    InvalidRowValidations::validateUserAlreadyExistsWithEmail($data->email);

                    if ($data->username) {
                        InvalidRowValidations::validateUserAlreadyExistsWithThisUsername($data->username);
                    }

                    import('plugins.importexport.csv.classes.processors.UsersProcessor');
                    if (empty($data->username)) {
                        $data->username = UsersProcessor::getValidUsername($data->firstname, $data->lastname);
                    }

                    $roles = array_map('trim', explode(';', $data->roles));

                    InvalidRowValidations::validateAllUserGroupsAreValid($roles, $journal->getId(), $journal->getPrimaryLocale());

                    if (!empty($data->subscriptionType) || !empty($data->startDate) || !empty($data->endDate)) {
                        InvalidRowValidations::validateSubscriptionFields($data);

                        $subscriptionType = CachedEntities::getCachedSubscriptionType($data->subscriptionType, $journal->getId());

                        InvalidRowValidations::validateSubscriptionType($subscriptionType, $data->subscriptionType);
                        InvalidRowValidations::validateSubscriptionDates($data->startDate, $data->endDate);
                    }

                    if (!empty($data->orcid)) {
                        OrcidHandler::validate($data->orcid);
                    }

                    if (empty($data->tempPassword)) {
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
                        $startDate = \DateTime::createFromFormat($dateFormat, $data->startDate);
                        $endDate = \DateTime::createFromFormat($dateFormat, $data->endDate);

                        import('plugins.importexport.csv.classes.processors.UserSubscriptionProcessor');
                        UserSubscriptionProcessor::process($data, $user->getId(), $journal->getId(), $startDate, $endDate);
                    }

                    if ($this->_sendWelcomeEmail && !$this->_dryMode) {
                        import('plugins.importexport.csv.classes.handlers.WelcomeEmailHandler');
                        WelcomeEmailHandler::sendWelcomeEmail($journal, $user, $this->_senderEmailUser, $data->tempPassword);
                    }
                } catch (RowValidationException $e) {
                    $this->_recordFailedRow($invalidCsvFile, $fields, $e->getMessage(), $fileFailedRows);
                    continue;
                } catch (\Throwable $e) {
                    $message = __('plugins.importexport.csv.rowImportFailed', ['message' => $e->getMessage()]);
                    $this->_recordFailedRow($invalidCsvFile, $fields, $message, $fileFailedRows);
                    continue;
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
    private function _recordFailedRow(&$invalidCsvFile, $fields, $reason, &$fileFailedRows)
    {
        if ($invalidCsvFile === null) {
            $invalidCsvFile = CSVFileHandler::createCSVFileInvalidRows(
                $this->_sourceDir,
                'invalid_' . $this->_currentFileBasename,
                RequiredUserHeaders::$userHeaders
            );
        }

        CSVFileHandler::processFailedRow($invalidCsvFile, $fields, $this->_expectedRowSize, $reason, $this->_failedRows);
        $fileFailedRows[] = ['row' => $this->_processedRows + 1, 'reason' => $reason];
    }
}
