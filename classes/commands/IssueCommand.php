<?php

/**
 * @file plugins/importexport/csv/classes/commands/IssueCommand.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class IssueCommand
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Handles the issue import when the user uses the issue command
 */

namespace APP\plugins\importexport\csv\classes\commands;

use APP\core\Application;
use APP\facades\Repo;
use APP\file\PublicFileManager;
use APP\plugins\importexport\csv\classes\cachedAttributes\CachedDaos;
use APP\plugins\importexport\csv\classes\cachedAttributes\CachedEntities;
use APP\plugins\importexport\csv\classes\processors\GalleyProcessor;
use APP\plugins\importexport\csv\classes\processors\IssueProcessor;
use APP\plugins\importexport\csv\classes\processors\PublicationProcessor;
use APP\plugins\importexport\csv\classes\processors\SectionsProcessor;
use APP\plugins\importexport\csv\classes\processors\SubmissionProcessor;
use APP\plugins\importexport\csv\classes\validations\InvalidRowValidations;
use APP\plugins\importexport\csv\classes\validations\RequiredIssueHeaders;
use APP\plugins\importexport\csv\shared\exceptions\RowValidationException;
use APP\plugins\importexport\csv\shared\handlers\CSVFileHandler;
use APP\plugins\importexport\csv\shared\handlers\DryModeReporter;
use APP\plugins\importexport\csv\shared\processors\AuthorsProcessor;
use APP\plugins\importexport\csv\shared\processors\CategoriesProcessor;
use APP\plugins\importexport\csv\shared\processors\FundersProcessor;
use APP\plugins\importexport\csv\shared\processors\KeywordsProcessor;
use APP\plugins\importexport\csv\shared\processors\StatisticsProcessor;
use APP\plugins\importexport\csv\shared\processors\SubjectsProcessor;
use APP\plugins\importexport\csv\shared\processors\SubmissionFileProcessor;
use APP\publication\Publication;
use APP\submission\Submission;
use Illuminate\Support\Facades\DB;
use PKP\file\FileManager;
use PKP\services\PKPFileService;
use PKP\user\User;

class IssueCommand
{
    /** Expected row size for a CSV based on the command passed as argument */
    private int $expectedRowSize;

    /** The folder containing all CSV files that the command must go through */
    private string $sourceDir;

    private int $processedRows;

    private int $failedRows;

    private PublicFileManager $publicFileManager;

    private FileManager $fileManager;

    private PKPFileService $fileService;

    private User $user;

    /**
     * The file directory array map used by the application.
     *
     * @var string[]
     */
    private array $dirNames;

    private string $format;

    private array $processedIssues;

    /**
     * Array to track processed articles by identifier, version, and locale
     * Structure: [
     *     'identifier' => [
     *         'version1' => [
     *             'locale1' => [
     *                 'data' => csv_row,
     *                 'publication' => Publication,
     *                 'submission' => Submission
     *             ],
     *             'locale2' => [
     *                 'data' => csv_row,
     *                 'publication' => Publication,
     *                 'submission' => Submission
     *             ]
     *         ],
     *         'version2' => [
     *             'locale1' => [
     *                 'data' => csv_row,
     *                 'publication' => Publication,
     *                 'submission' => Submission
     *             ]
     *         ]
     *     ]
     * ]
     *
     * @var array
     */
    private array $processedArticles;

    /** @var array Track failed identifiers for cascaded failure detection */
    private array $failedIdentifiers;

    private bool $dryMode;

    public function __construct(string $sourceDir, User $user, bool $dryMode = false)
    {
        $this->expectedRowSize = count(RequiredIssueHeaders::$issueHeaders);
        $this->sourceDir = $sourceDir;
        $this->user = $user;
        $this->dryMode = $dryMode;
        $this->processedIssues = [];
        $this->processedArticles = [];
        $this->failedIdentifiers = [];
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
            'failedRows' => 0,
            'perFile' => [],
        ];

        foreach (new \DirectoryIterator($this->sourceDir) as $fileInfo) {
            if (!$fileInfo->isFile() || $fileInfo->getExtension() !== 'csv') {
                continue;
            }

            $basename = $fileInfo->getBasename();

            // Skip invalid_*.csv files created by previous failed imports
            if (str_starts_with($basename, 'invalid_')) {
                continue;
            }

            $filePath = $fileInfo->getPathname();
            $file = CSVFileHandler::createReadableCSVFile($filePath);

            if (is_null($file)) {
                continue;
            }
            $invalidCsvFile = null;

            $this->processedRows = 0;
            $this->failedRows = 0;
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

                if (!$this->dryMode) {
                    DB::beginTransaction();
                }
                try {
                    InvalidRowValidations::validateRowContainAllFields($fields, $this->expectedRowSize);

                    $data = (object) array_combine(
                        RequiredIssueHeaders::$issueHeaders,
                        array_pad(array_map('trim', $fields), $this->expectedRowSize, null)
                    );

                    if (
                        !empty($data->versionIdentifier)
                        && !empty($data->version)
                        && !isset($this->processedArticles[$data->versionIdentifier])
                        && isset($this->failedIdentifiers[$data->versionIdentifier])
                    ) {
                        throw new RowValidationException(
                            __('plugins.importexport.csv.baseRowFailedForIdentifier', [
                                'identifier' => $data->versionIdentifier,
                            ])
                        );
                    }

                    InvalidRowValidations::validateRowHasAllRequiredFieldsCommons($data, function($row) {
                        return RequiredIssueHeaders::validateRowHasAllRequiredFields($row, $this->processedArticles);
                    });

                    InvalidRowValidations::validateContextVersioningFields($data);

                    if (!empty($data->versionIdentifier)) {
                        InvalidRowValidations::validateNoDuplicateVersion($data, $this->processedArticles);
                    }

                    $hasIssueData = !empty(trim($data->issueTitle))
                                    || !empty(trim($data->issueVolume))
                                    || !empty(trim($data->issueNumber))
                                    || !empty(trim($data->issueYear));

                    if (empty($data->version) || (!empty($data->version) && (int)$data->version === 1)) {
                        if (!$hasIssueData) {
                            throw new RowValidationException(__('plugins.importexport.csv.atLeastOneIssueFieldRequired'));
                        }
                    }

                    if ($data->galleyFilenames) {
                        InvalidRowValidations::validatePublicationGalleys(
                            $data->galleyFilenames,
                            $data->galleyLabels,
                            $this->sourceDir
                        );
                    }

                    InvalidRowValidations::validateGalleyViews($data->galleyViews ?? null, $data->galleyLabels ?? null);
                    InvalidRowValidations::validatePublicationViews($data->articleViews ?? null, 'articleViews');

                    if ($data->suppFilenames) {
                        InvalidRowValidations::validateSupplementaryFiles(
                            $data->suppFilenames,
                            $data->suppLabels,
                            $this->sourceDir
                        );
                    }

                    if ($data->suppFilenames && !empty($data->suppDescriptions)) {
                        InvalidRowValidations::validateSupplementaryDescriptions(
                            $data->suppFilenames,
                            $data->suppLabels,
                            $data->suppDescriptions
                        );
                    }

                    if ($data->references) {
                        InvalidRowValidations::validateReferencesFile($data->references, $this->sourceDir);
                    }

                    if ($data->funders) {
                        InvalidRowValidations::validateFunders($data->funders);
                    }

                    $fileUploadUser = $this->user;
                    $csvUser = null;
                    $usedDefaultUser = false;
                    if (!empty($data->username)) {
                        $csvUser = CachedEntities::getCachedUserByUsername($data->username, true);
                        $csvUser ? $fileUploadUser = $csvUser : $usedDefaultUser = true;
                    }
                    $hasValidCsvUser = !empty($data->username) && !$usedDefaultUser && isset($csvUser);

                    $journal = CachedEntities::getCachedJournal($data->journalPath);

                    InvalidRowValidations::validateContextIsValid($journal, $data->journalPath, 'Journal');
                    InvalidRowValidations::validateContextLocale($journal, $data->locale, 'Journal');

                    $genreName = 'SUBMISSION';
                    $genreId = CachedEntities::getCachedGenreId($genreName, $journal->getId());

                    InvalidRowValidations::validateGenreIdValid($genreId, $genreName);

                    $userGroupId = CachedEntities::getCachedAuthorUserGroupId($data->journalPath, $journal->getId());

                    InvalidRowValidations::validateUserGroupId($userGroupId, $data->journalPath, 'Journal');

                    if ($data->funders) {
                        InvalidRowValidations::validateFundingPluginEnabled($data->funders, $journal->getId(), 'Journal');
                        InvalidRowValidations::validateFundersCrossrefRegistry($data->funders, $journal->getId());
                    }

                    $this->initializeStaticVariables();

                    $coverImageUploadName = null;
                    if (!$this->dryMode && $data->coverImageFilename) {
                        InvalidRowValidations::validateCoverImageIsValid($data->coverImageFilename, $this->sourceDir);

                        $sanitizedCoverImageName = str_replace([' ', '_', ':'], '-', mb_strtolower($data->coverImageFilename));
                        $sanitizedCoverImageName = preg_replace('/[^a-z0-9\.\-]+/', '', $sanitizedCoverImageName);
                        $coverImageUploadName = uniqid() . '-' . basename($sanitizedCoverImageName);

                        $destFilePath = $this->publicFileManager->getContextFilesPath($journal->getId()) . '/' . $coverImageUploadName;
                        $srcFilePath = "{$this->sourceDir}/{$data->coverImageFilename}";
                        $bookCoverImageSaved = $this->fileManager->copyFile($srcFilePath, $destFilePath);

                        if (!$bookCoverImageSaved) {
                            throw new RowValidationException(__('plugin.importexport.csv.erroWhileSavingBookCoverImage'));
                        }
                    }

                    $existingSubmission = null; /** @var null|Submission */
                    $basePublication = null; /** @var null|Publication */
                    $isMultiLocaleImport = false;

                    if (!empty($data->versionIdentifier) &&
                        InvalidRowValidations::versionExistsInAnyLocale($data, $this->processedArticles)) {
                        $version = (int)$data->version;
                        $versionData = $this->processedArticles[$data->versionIdentifier][$version];

                        $firstLocaleData = reset($versionData);
                        $existingSubmission = $firstLocaleData['submission'];
                        $basePublication = $firstLocaleData['publication'];

                        if (!isset($versionData[$data->locale])) {
                            $isMultiLocaleImport = true;
                        }
                    } elseif (!empty($data->versionIdentifier) && isset($this->processedArticles[$data->versionIdentifier])) {
                        // Handle new version (not multi-locale)
                        $versions = $this->processedArticles[$data->versionIdentifier];
                        $lastVersion = end($versions);
                        $lastVersionData = reset($lastVersion);
                        $existingSubmission = $lastVersionData['submission'];
                        $basePublication = $lastVersionData['publication'];
                    }

                    if ($isMultiLocaleImport) {
                        $submission = $existingSubmission;
                        $publication = $basePublication;

                        $publication = PublicationProcessor::processMultiLocalePublication($publication, $data, $journal);
                    } elseif ($existingSubmission && $basePublication) {
                        // New version import
                        $submission = $existingSubmission;
                        $publication = PublicationProcessor::createPublicationVersion($basePublication, $data, $journal);

                        $publication = PublicationProcessor::processVersionedPublication($publication, $data, $basePublication, $this->sourceDir);
                    } else {
                        // New submission import
                        $initialPublication = PublicationProcessor::createInitialPublication($data);
                        $submission = SubmissionProcessor::process($data, $initialPublication, $journal);
                        $publication = PublicationProcessor::process($submission, $data, $journal, $this->sourceDir);
                    }

                    if (!$publication) {
                        throw new RowValidationException(__('plugins.importexport.csv.errorWhileCreatingPublication'));
                    }

                    if (!$this->dryMode && $data->coverImageFilename) {
                        PublicationProcessor::updateCoverImage($publication, $data, $coverImageUploadName);
                    }

                    if (!$this->dryMode && $existingSubmission && $basePublication && !$data->galleyFilenames) {
                        GalleyProcessor::copyGalleysFromBasePublication(
                            $basePublication,
                            $publication,
                            $this->fileManager,
                            $this->format,
                            $this->fileService
                        );
                    }

                    // Array to store each galley ID to its respective galley file
                    $galleyIds = [];
                    $galleyMetadata = [];
                    if (!$this->dryMode && $data->galleyFilenames) {
                        foreach (array_map('trim', explode(';', $data->galleyFilenames)) as $galleyFile) {
                            $galleyFileId = $this->saveSubmissionFile(
                                $galleyFile,
                                $journal->getId(),
                                $submission,
                                __('plugins.importexport.csv.errorWhileSavingSubmissionGalley', ['galley' => $galleyFile]),
                            );

                            if (is_null($galleyFileId)) {
                                foreach($galleyIds as $galleyItem) {
                                    $this->fileService->delete($galleyItem['id']);
                                }

                                continue;
                            }

                            $galleyIds[] = ['file' => $galleyFile, 'id' => $galleyFileId];
                        }

                        $galleyLabelsArray = array_map('trim', explode(';', $data->galleyLabels));
                        for($i = 0; $i < count($galleyLabelsArray); $i++) {
                            $galleyItem = $galleyIds[$i];
                            $galleyLabel = $galleyLabelsArray[$i];

                            $galleyMetadata[] = $this->handleGalley(
                                $galleyItem,
                                $data,
                                $submission->getId(),
                                $genreId,
                                $galleyLabel,
                                $publication->getId(),
                                $fileUploadUser
                            );
                        }
                    }

                    if (!$this->dryMode && $data->suppFilenames) {
                        $genreDao = CachedDaos::getGenreDao();
                        $supplementaryGenres = $genreDao->getBySupplementaryAndContextId(true, $journal->getId())->toArray();
                        $suppGenreId = !empty($supplementaryGenres) ? $supplementaryGenres[0]->getId() : $genreId;
                        $suppIds = [];

                        foreach (array_map('trim', explode(';', $data->suppFilenames)) as $suppFile) {
                            $suppFileId = $this->saveSubmissionFile(
                                $suppFile,
                                $journal->getId(),
                                $submission,
                                __('plugins.importexport.csv.errorWhileSavingSupplementaryFile', ['file' => $suppFile]),
                            );

                            if (is_null($suppFileId)) {
                                foreach($galleyIds as $galleyItem) {
                                    $this->fileService->delete($galleyItem['id']);
                                }

                                foreach($suppIds as $suppItem) {
                                    $this->fileService->delete($suppItem['id']);
                                }

                                continue;
                            }

                            $suppIds[] = ['file' => $suppFile, 'id' => $suppFileId];
                        }

                        $suppDescriptionsArray = !empty($data->suppDescriptions)
                            ? array_map('trim', explode(';', $data->suppDescriptions))
                            : [];
                        $suppLabelsArray = array_map('trim', explode(';', $data->suppLabels));
                        for($i = 0; $i < count($suppLabelsArray); $i++) {
                            $suppItem = $suppIds[$i];
                            $suppLabel = $suppLabelsArray[$i];
                            $suppDescription = $suppDescriptionsArray[$i] ?? null;

                            $this->handleGalley(
                                $suppItem,
                                $data,
                                $submission->getId(),
                                $suppGenreId,
                                $suppLabel,
                                $publication->getId(),
                                $fileUploadUser,
                                $suppDescription
                            );
                        }
                    }

                    if (!empty($data->articleViews) && (int)$data->articleViews > 0) {
                        StatisticsProcessor::insertSubmissionViews(
                            $submission->getId(),
                            $journal->getId(),
                            (int)$data->articleViews
                        );
                    }

                    if (!$this->dryMode && !empty($data->galleyViews) && !empty($galleyMetadata)) {
                        $galleyViewsArray = explode(';', $data->galleyViews);
                        foreach ($galleyViewsArray as $idx => $views) {
                            $views = trim($views);
                            if ($views === '' || (int)$views === 0) {
                                continue;
                            }
                            if (isset($galleyMetadata[$idx])) {
                                $meta = $galleyMetadata[$idx];
                                StatisticsProcessor::insertGalleyViews(
                                    $submission->getId(),
                                    $journal->getId(),
                                    $meta['galleyId'],
                                    $meta['submissionFileId'],
                                    StatisticsProcessor::resolveFileType($meta['filename']),
                                    (int)$views
                                );
                            }
                        }
                    }

                    if ($isMultiLocaleImport) {
                        // For multi-locale imports, update existing publication with new locale data
                        if ($hasValidCsvUser) {
                            AuthorsProcessor::updateUsernameAuthorLocale($csvUser, $publication, $data->locale);
                        }
                        AuthorsProcessor::processMultiLocale($data, $journal->getContactEmail(), $submission->getId(), $publication, $userGroupId);
                        KeywordsProcessor::processMultiLocale($data, $publication);
                        SubjectsProcessor::processMultiLocale($data, $publication);
                        FundersProcessor::processMultiLocale($data, $submission, $journal->getId());
                        PublicationProcessor::processSupportingAgenciesMultiLocale($data, $publication);
                    } else {
                        $usernameAuthorAdded = false;
                        if ($hasValidCsvUser && (!empty($data->authors) || is_null($basePublication))) {
                            AuthorsProcessor::addAuthorFromUser($csvUser, $submission, $publication, $journal, $userGroupId);
                            $usernameAuthorAdded = true;
                        }

                        AuthorsProcessor::process($data, $journal->getContactEmail(), $submission->getId(), $publication, $userGroupId, $basePublication, $usernameAuthorAdded ? $csvUser : null);
                        KeywordsProcessor::process($data, $publication, $basePublication);
                        SubjectsProcessor::process($data, $publication, $basePublication);
                        FundersProcessor::process($data, $submission, $journal->getId(), $basePublication);
                        PublicationProcessor::processSupportingAgencies($data, $publication, $basePublication);
                    }

                    if (
                        ((!empty($data->version) && (int) $data->version === 1) || empty($data->version))
                        && $data->coverage
                    ) {
                        PublicationProcessor::updateCoverage($publication, $data->coverage, $data->locale);
                    }

                    SectionsProcessor::process($data, $journal->getId(), $publication, $basePublication);

                    $issue = IssueProcessor::process($journal->getId(), $data, $basePublication);
                    if ($isMultiLocaleImport) {
                        $issue = IssueProcessor::processMultiLocale($issue, $data);
                    }

                    PublicationProcessor::updateIssueId($publication, $issue->getId());

                    if ($data->categories || $basePublication) {
                        if ($isMultiLocaleImport) {
                            CategoriesProcessor::processMultiLocale($data->categories, $data->locale, $journal->getId(), $publication->getId());
                        } elseif ($existingSubmission && $basePublication) {
                            CategoriesProcessor::processForVersion($data->categories, $data->locale, $journal->getId(), $publication->getId(), $basePublication);
                        } else {
                            CategoriesProcessor::process($data->categories, $data->locale, $journal->getId(), $publication->getId());
                        }
                    }

                    $issueKey = $journal->getId() . '_' . $issue->getId();
                    if (!isset($this->processedIssues[$issueKey])) {
                        $this->processedIssues[$issueKey] = [
                            'issue' => $issue,
                            'journalId' => $journal->getId(),
                            'data' => $data
                        ];
                    }

                    // Refresh publication to retrieve all its data correctly
                    $publication = Repo::publication()->get($publication->getId());

                    if (!empty($data->versionIdentifier)) {
                        $this->trackProcessedArticle($data, $submission, $publication);
                    }

                    if (!$this->dryMode) {
                        DB::commit();
                    }
                } catch (RowValidationException $e) {
                    if (!$this->dryMode) {
                        DB::rollBack();
                    }
                    $failedIdentifier = $fields[2] ?? null;
                    if (!empty($failedIdentifier)) {
                        $this->failedIdentifiers[$failedIdentifier] = true;
                    }

                    if (is_null($invalidCsvFile)) {
                        $invalidCsvFile = CSVFileHandler::createCSVFileInvalidRows(
                            $this->sourceDir,
                            "invalid_{$basename}",
                            RequiredIssueHeaders::$issueHeaders
                        );
                        if (is_null($invalidCsvFile)) {
                            continue 2;
                        }
                    }

                    CSVFileHandler::processFailedRow(
                        $invalidCsvFile,
                        $fields,
                        $this->expectedRowSize,
                        $e->getMessage(),
                        $this->failedRows
                    );
                    if ($this->dryMode) {
                        $fileFailedRows[] = ['row' => $this->processedRows + 1, 'reason' => $e->getMessage()];
                    }

                    continue;
                } catch (\Throwable $e) {
                    if (!$this->dryMode) {
                        DB::rollBack();
                    }

                    $failedIdentifier = $fields[2] ?? null;
                    if (!empty($failedIdentifier)) {
                        $this->failedIdentifiers[$failedIdentifier] = true;
                    }

                    if (is_null($invalidCsvFile)) {
                        $invalidCsvFile = CSVFileHandler::createCSVFileInvalidRows(
                            $this->sourceDir,
                            "invalid_{$basename}",
                            RequiredIssueHeaders::$issueHeaders
                        );
                        if (is_null($invalidCsvFile)) {
                            continue 2;
                        }
                    }

                    $message = __('plugins.importexport.csv.rowImportFailed', ['message' => $e->getMessage()]);

                    CSVFileHandler::processFailedRow(
                        $invalidCsvFile,
                        $fields,
                        $this->expectedRowSize,
                        $message,
                        $this->failedRows
                    );
                    if ($this->dryMode) {
                        $fileFailedRows[] = ['row' => $this->processedRows + 1, 'reason' => $message];
                    }

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
                $this->processedArticles = [];
                $this->failedIdentifiers = [];
            }

            echo __('plugins.importexpot.csv.fileProcessFinished', [
                'filename' => $fileInfo->getFilename(),
                'processedRows' => $this->processedRows,
                'failedRows' => $this->failedRows,
            ]) . "\n";

            $fileResult = [
                'filename' => $basename,
                'rows' => $this->processedRows,
                'successful' => $this->processedRows - $this->failedRows,
                'failed' => $this->failedRows,
                'errors' => $fileFailedRows,
                'invalidFile' => $this->failedRows > 0 ? "invalid_{$basename}" : null,
            ];
            $results['perFile'][] = $fileResult;
            $results['filesProcessed']++;
            $results['totalRows'] += $this->processedRows;
            $results['successfulRows'] += $this->processedRows - $this->failedRows;
            $results['failedRows'] += $this->failedRows;
        }

        if ($this->dryMode) {
            DryModeReporter::printGrandTotal($totalFiles, $totalPassed, $totalFailed);
            $results['exitCode'] = $totalFailed > 0 ? 1 : 0;
            return $results;
        }

        $this->syncCoverImagesForProcessedArticles();
        $this->setCurrentVersionsForProcessedArticles();

        IssueProcessor::reorderImportedIssues($this->processedIssues);

        $results['exitCode'] = $results['failedRows'] > 0 ? 1 : 0;
        return $results;
    }

    /** Insert static data that will be used for the submission processing */
    private function initializeStaticVariables(): void
    {
        $this->dirNames ??= Application::getFileDirectories();
        $this->format ??= trim($this->dirNames['context'], '/') . '/%d/' . trim($this->dirNames['submission'], '/') . '/%d';
        $this->fileManager ??= new FileManager();
        $this->publicFileManager ??= new PublicFileManager();
        $this->fileService ??= app()->get('file');
    }

    /**
     * Save a submission file. If an error occurred, the method will delete the submission already saved
     * and return null.
     */
    private function saveSubmissionFile(
        string $filePath,
        int $journalId,
        Submission $submission,
        string $reason,
    ): ?int
    {
        try {
            $extension = $this->fileManager->parseFileExtension($filePath);
            $submissionDir = sprintf($this->format, $journalId, $submission->getId());
            $completePath = "{$this->sourceDir}/{$filePath}";

            return $this->fileService->add($completePath, $submissionDir . '/' . uniqid() . '.' . $extension);
        } catch (\Exception $e) {
            Repo::submission()->delete($submission);

            throw new RowValidationException($reason);
        }
    }

    /** Process data for the galley submission file and galley into the database. */
    private function handleGalley(
        array $item,
        object $data,
        int $submissionId,
        int $genreId,
        string $label,
        int $publicationId,
        User $fileUploadUser,
        ?string $description = null
    ): array
    {
        $galleyCompletePath = "{$this->sourceDir}/{$item['file']}";
        $galleyExtension = $this->fileManager->parseFileExtension($galleyCompletePath);

        $submissionFile = SubmissionFileProcessor::process(
            $data->locale,
            $fileUploadUser->getId(),
            $submissionId,
            $galleyCompletePath,
            $genreId,
            $item['id'],
            $description
        );

        $galleyId = GalleyProcessor::process($submissionFile->getId(), $data, $label, $publicationId, $galleyExtension);
        SubmissionFileProcessor::updateAssocInfo($submissionFile, $galleyId);

        return [
            'galleyId' => $galleyId,
            'submissionFileId' => $submissionFile->getId(),
            'filename' => $item['file'],
        ];
    }

   /**
    * Tracks a processed article for version management
    */
   private function trackProcessedArticle(object $data, Submission $submission, Publication $publication): void
   {
        $identifier = $data->versionIdentifier;
        $version = (int)$data->version;
        $locale = $data->locale;

        if (!isset($this->processedArticles[$identifier])) {
            $this->processedArticles[$identifier] = [];
        }

        if (!isset($this->processedArticles[$identifier][$version])) {
            $this->processedArticles[$identifier][$version] = [];
        }

        $this->processedArticles[$identifier][$version][$locale] = [
            'data' => $data,
            'submission' => $submission,
            'publication' => $publication
        ];
   }

   private function syncCoverImagesForProcessedArticles(): void
    {
        foreach ($this->processedArticles as $identifier => $versions) {
            foreach ($versions as $versionNumber => $localeData) {
                $firstLocaleData = reset($localeData);
                $publication = $firstLocaleData['publication'];
                $publicationId = $publication->getId();

                $serverId = Repo::submission()->get($publication->getData('submissionId'))->getData('contextId');
                $serverDao = CachedDaos::getJournalDao();
                $server = $serverDao->getById($serverId);
                if (!$server) {
                    continue;
                }
                $defaultLocale = $server->getPrimaryLocale();

                $coverImageSettings = DB::table('publication_settings')
                    ->where('publication_id', $publicationId)
                    ->where('setting_name', 'coverImage')
                    ->get();

                if ($coverImageSettings->isEmpty()) {
                    continue;
                }

                $coverImagesByLocale = [];
                foreach ($coverImageSettings as $setting) {
                    if (empty($setting->setting_value)) {
                        continue;
                    }

                    $coverImageData = json_decode($setting->setting_value, true);
                    if (!empty($coverImageData)) {
                        $coverImagesByLocale[$setting->locale] = $coverImageData;
                    }
                }

                if (empty($coverImagesByLocale)) {
                    continue;
                }

                $sourceCoverImage = null;

                if (isset($coverImagesByLocale[$defaultLocale])) {
                    $sourceCoverImage = $coverImagesByLocale[$defaultLocale];
                } else {
                    foreach ($coverImagesByLocale as $locale => $coverImageData) {
                        if (!empty($coverImageData) && isset($coverImageData['uploadName'])) {
                            $sourceCoverImage = $coverImageData;
                            break;
                        }
                    }
                }

                if (!$sourceCoverImage) {
                    continue;
                }

                $allPublicationLocales = DB::table('publication_settings')
                    ->where('publication_id', $publicationId)
                    ->whereNotNull('locale')
                    ->whereNot('locale', '')
                    ->distinct()
                    ->pluck('locale')
                    ->toArray();

                foreach ($allPublicationLocales as $locale) {
                    if (isset($coverImagesByLocale[$locale])) {
                        continue;
                    }

                    $reloadedPublication = Repo::publication()->get($publicationId);
                    if ($reloadedPublication) {
                        PublicationProcessor::updatePublicationAttribute($reloadedPublication, 'coverImage', $sourceCoverImage, $locale);
                    }
                }
            }
        }
    }

   /**
    * Set the highest version as current for each processed article identifier
    */
   private function setCurrentVersionsForProcessedArticles(): void
   {
        foreach ($this->processedArticles as $identifier => $versions) {
            if (count($versions) <= 1) {
                continue; // Skip if only one version exists
            }

            $highestVersion = 0;
            $currentVersionData = null;

            foreach ($versions as $versionKey => $locales) {
                foreach($locales as $locale => $localeData) {
                    $versionNumber = (int)$localeData['data']->version;
                    if ($versionNumber > $highestVersion) {
                        $highestVersion = $versionNumber;
                        $currentVersionData = $localeData;
                    }
                }
            }

           if ($currentVersionData) {
                $submission = $currentVersionData['submission']; /** @var Submission */
                $publication = $currentVersionData['publication']; /** @var Publication */
                SubmissionProcessor::setCurrentPublicationId($submission, $publication->getId());
            }
        }
    }
}
