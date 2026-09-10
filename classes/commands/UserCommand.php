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
use APP\plugins\importexport\csv\classes\exceptions\RowValidationException;
use APP\plugins\importexport\csv\classes\handlers\CSVFileHandler;
use APP\plugins\importexport\csv\classes\handlers\DryModeReporter;
use APP\plugins\importexport\csv\classes\handlers\WelcomeEmailHandler;
use APP\plugins\importexport\csv\classes\processors\UserGroupsProcessor;
use APP\plugins\importexport\csv\classes\processors\UserInterestsProcessor;
use APP\plugins\importexport\csv\classes\processors\UsersProcessor;
use APP\plugins\importexport\csv\classes\processors\UserSubscriptionProcessor;
use APP\plugins\importexport\csv\classes\validations\InvalidRowValidations;
use APP\plugins\importexport\csv\classes\validations\RequiredUserHeaders;
use APP\plugins\importexport\csv\classes\handlers\OrcidHandler;
use Illuminate\Support\Facades\DB;
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

    /** Validates the CSV files without persisting anything when true. */
    private bool $dryMode;

    /** @var string|null Current journal path from GUI context. When set, rows with a different journalPath are rejected. */
    private ?string $currentJournalPath;

    public function __construct(string $sourceDir, User $user, bool $sendWelcomeEmail, bool $dryMode = false, ?string $currentJournalPath = null)
    {
        $this->expectedRowSize = count(RequiredUserHeaders::$userHeaders);
        $this->sourceDir = $sourceDir;
        $this->senderEmailUser = $user;
        $this->sendWelcomeEmail = $sendWelcomeEmail;
        $this->dryMode = $dryMode;
        $this->currentJournalPath = $currentJournalPath;
    }

    public function run(): array
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
            $invalidCsvFile = null;

            $this->processedRows = 0;
            $this->failedRows = 0;
            $fileUpdatedRows = 0;
            $fileUpdatedUsers = [];
            $fileFailedRows = [];

            if ($this->dryMode) {
                DB::statement('SET FOREIGN_KEY_CHECKS=0');
                DB::beginTransaction();
            }

            foreach ($file as $index => $fields) {
                if (!$index || empty(array_filter($fields))) {
                    continue; // Skip headers or end of file
                }

                ++$this->processedRows;

                try {
                    InvalidRowValidations::validateRowContainAllFields($fields, $this->expectedRowSize);

                    $fieldsList = array_pad(array_map('trim', $fields), $this->expectedRowSize, null);
                    $data = (object) array_combine(RequiredUserHeaders::$userHeaders, $fieldsList);

                    InvalidRowValidations::validateRowHasAllRequiredFields($data, [RequiredUserHeaders::class, 'validateRowHasAllRequiredFields']);

                    $journal = CachedEntities::getCachedJournal($data->journalPath);

                    InvalidRowValidations::validateJournalIsValid($journal, $data->journalPath);

                    if ($this->currentJournalPath !== null && $data->journalPath !== $this->currentJournalPath) {
                        throw new RowValidationException(__('plugins.importexport.csv.contextPathMismatch', [
                            'contextType' => 'journal',
                            'csvContextPath' => $data->journalPath,
                            'currentContextPath' => $this->currentJournalPath,
                        ]));
                    }

                    $existingUserByEmail = CachedEntities::getCachedUserByEmail($data->email);
                    $isNewUser = is_null($existingUserByEmail);

                    if ($isNewUser) {
                        if ($data->username) {
                            InvalidRowValidations::validateUserAlreadyExistsWithThisUsername($data->username);
                        }

                        if (empty($data->username)) {
                            $data->username = UsersProcessor::getValidUsername($data->firstname, $data->lastname);
                        }
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

                    if ($isNewUser && is_null($data->tempPassword)) {
                        $data->tempPassword = Validation::generatePassword();
                    }

                    $user = UsersProcessor::process($data, $journal->getPrimaryLocale());
                    $userId = $user->getId();
                    $userInterests = array_map('trim', explode(';', $data->reviewInterests));
                    UserInterestsProcessor::process($userInterests, $userId);

                    if ($isNewUser) {
                        UserGroupsProcessor::process($roles, $userId, $journal->getId(), $journal->getPrimaryLocale());
                    } else {
                        $fileUpdatedRows++;
                        $fileUpdatedUsers[] = $data->email;
                    }

                    if (!empty($data->subscriptionType) && !empty($data->startDate) && !empty($data->endDate)) {
                        $dateFormat = 'Y-m-d';
                        $startDate = \DateTime::createFromFormat($dateFormat, $data->startDate);
                        $endDate = \DateTime::createFromFormat($dateFormat, $data->endDate);

                        UserSubscriptionProcessor::process((int) $data->subscriptionType, $user->getId(), $journal->getId(), $startDate, $endDate);
                    }

                    if ($this->sendWelcomeEmail && !$this->dryMode && $isNewUser) {
                        WelcomeEmailHandler::sendWelcomeEmail($journal, $user, $this->senderEmailUser, $data->tempPassword);
                    }
                } catch (RowValidationException $e) {
                    if (is_null($invalidCsvFile)) {
                        $invalidCsvFile = CSVFileHandler::createCSVFileInvalidRows(
                            $this->sourceDir,
                            "invalid_{$basename}",
                            RequiredUserHeaders::$userHeaders
                        );
                    }

                    CSVFileHandler::processFailedRow(
                        $invalidCsvFile,
                        $fields,
                        $this->expectedRowSize,
                        $e->getMessage(),
                        $this->failedRows
                    );
                    $fileFailedRows[] = ['row' => $this->processedRows + 1, 'reason' => $e->getMessage()];

                    continue;
                } catch (\Throwable $e) {
                    if (is_null($invalidCsvFile)) {
                        $invalidCsvFile = CSVFileHandler::createCSVFileInvalidRows(
                            $this->sourceDir,
                            "invalid_{$basename}",
                            RequiredUserHeaders::$userHeaders
                        );
                    }

                    $message = __('plugins.importexport.csv.rowImportFailed', ['message' => $e->getMessage()]);

                    CSVFileHandler::processFailedRow(
                        $invalidCsvFile,
                        $fields,
                        $this->expectedRowSize,
                        $message,
                        $this->failedRows
                    );
                    $fileFailedRows[] = ['row' => $this->processedRows + 1, 'reason' => $message];

                    continue;
                }
            }

            if ($this->dryMode) {
                $passed = $this->processedRows - $this->failedRows;
                DryModeReporter::printFileHeader($basename);
                if (!empty($fileFailedRows)) {
                    DryModeReporter::printTableHeader();
                    foreach ($fileFailedRows as $failedRow) {
                        DryModeReporter::printFailedRow($failedRow['row'], $failedRow['reason']);
                    }
                }
                DryModeReporter::printFileSummary($passed, $this->failedRows, $this->processedRows);
                $totalFiles++;
                $totalPassed += $passed;
                $totalFailed += $this->failedRows;

                DB::rollBack();
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
                CachedEntities::reset();
            }

            $createdRows = $this->processedRows - $this->failedRows - $fileUpdatedRows;
            echo __('plugins.importexport.csv.fileProcessFinished', [
                'filename' => $fileInfo->getFilename(),
                'processedRows' => $this->processedRows,
                'createdRows' => $createdRows,
                'updatedRows' => $fileUpdatedRows,
                'failedRows' => $this->failedRows,
            ]) . "\n";

            $invalidFilename = "invalid_{$basename}";
            $results['perFile'][] = [
                'filename' => $basename,
                'rows' => $this->processedRows,
                'successful' => $this->processedRows - $this->failedRows,
                'created' => $createdRows,
                'updated' => $fileUpdatedRows,
                'updatedUsers' => $fileUpdatedUsers,
                'failed' => $this->failedRows,
                'errors' => $fileFailedRows,
                'invalidFile' => ($this->failedRows > 0 && is_file($this->sourceDir . '/' . $invalidFilename))
                    ? $invalidFilename
                    : null,
            ];
            $results['filesProcessed']++;
            $results['totalRows'] += $this->processedRows;
            $results['successfulRows'] += $this->processedRows - $this->failedRows;
            $results['createdRows'] += $createdRows;
            $results['updatedRows'] += $fileUpdatedRows;
            $results['failedRows'] += $this->failedRows;
        }

        if ($this->dryMode) {
            DryModeReporter::printGrandTotal($totalFiles, $totalPassed, $totalFailed);
            $results['exitCode'] = $totalFailed > 0 ? 1 : 0;
            return $results;
        }

        $results['exitCode'] = $results['failedRows'] > 0 ? 1 : 0;
        return $results;
    }
}
