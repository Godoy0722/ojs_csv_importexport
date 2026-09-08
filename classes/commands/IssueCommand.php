<?php

/**
 * @file plugins/importexport/csv/classes/commands/IssueCommand.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2003-2025 John Willinsky
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
use APP\core\Services;
use APP\facades\Repo;
use APP\file\PublicFileManager;
use APP\plugins\importexport\csv\classes\cachedAttributes\CachedDaos;
use APP\plugins\importexport\csv\classes\cachedAttributes\CachedEntities;
use APP\plugins\importexport\csv\classes\handlers\CSVFileHandler;
use APP\plugins\importexport\csv\classes\handlers\DryModeReporter;
use APP\plugins\importexport\csv\classes\processors\AuthorsProcessor;
use APP\plugins\importexport\csv\classes\processors\CategoriesProcessor;
use APP\plugins\importexport\csv\classes\processors\FundersProcessor;
use APP\plugins\importexport\csv\classes\processors\GalleyProcessor;
use APP\plugins\importexport\csv\classes\processors\HtmlGalleyProcessor;
use APP\plugins\importexport\csv\classes\processors\IssueProcessor;
use APP\plugins\importexport\csv\classes\processors\KeywordsProcessor;
use APP\plugins\importexport\csv\classes\processors\PublicationProcessor;
use APP\plugins\importexport\csv\classes\processors\SectionsProcessor;
use APP\plugins\importexport\csv\classes\processors\StatisticsProcessor;
use APP\plugins\importexport\csv\classes\processors\SubjectsProcessor;
use APP\plugins\importexport\csv\classes\processors\SubmissionFileProcessor;
use APP\plugins\importexport\csv\classes\processors\SubmissionProcessor;
use APP\plugins\importexport\csv\classes\validations\InvalidRowValidations;
use APP\plugins\importexport\csv\classes\validations\RequiredIssueHeaders;
use APP\publication\Publication;
use APP\submission\Submission;
use Illuminate\Support\Facades\DB;
use PKP\core\PKPString;
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

    /** Validates the CSV files without persisting anything when true. */
    private bool $dryMode;

    /** @var array<int,array{row:int,reason:string}> Failures of the file being processed, for the dry-mode report */
    private array $fileFailedRows;

    public function __construct(string $sourceDir, User $user, bool $dryMode = false)
    {
        $this->expectedRowSize = count(RequiredIssueHeaders::$issueHeaders);
        $this->sourceDir = $sourceDir;
        $this->user = $user;
        $this->dryMode = $dryMode;
        $this->processedIssues = [];
        $this->processedArticles = [];
        $this->fileFailedRows = [];
    }

    /** @return int The exit code: 1 when at least one row failed, 0 otherwise. */
    public function run(): int
    {
        $totalFiles = 0;
        $totalPassed = 0;
        $totalFailed = 0;

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

            $invalidCsvFile = CSVFileHandler::createCSVFileInvalidRows($this->sourceDir, "invalid_{$basename}", RequiredIssueHeaders::$issueHeaders);

            if (is_null($invalidCsvFile)) {
                continue;
            }

            $this->processedRows = 0;
            $this->failedRows = 0;
            $this->fileFailedRows = [];

            if ($this->dryMode) {
                DB::statement('SET FOREIGN_KEY_CHECKS=0');
                DB::beginTransaction();
            }

            foreach ($file as $index => $fields) {
                if (!$index || empty(array_filter($fields))) {
                    continue; // Skip headers or end of file
                }

                ++$this->processedRows;

                $reason = InvalidRowValidations::validateRowContainAllFields($fields, $this->expectedRowSize);
                if (!is_null($reason)) {
                    $this->registerFailedRow($invalidCsvFile, $fields, $reason);
                    continue;
                }

                $data = (object) array_combine(
                    RequiredIssueHeaders::$issueHeaders,
                    array_pad(array_map('trim', $fields), $this->expectedRowSize, null)
                );

                $reason = InvalidRowValidations::validateRowHasAllRequiredFields($data, function($row) {
                    return RequiredIssueHeaders::validateRowHasAllRequiredFields($row, $this->processedArticles);
                });
                if (!is_null($reason)) {
                    $this->registerFailedRow($invalidCsvFile, $fields, $reason);
                    continue;
                }

                $reason = InvalidRowValidations::validateArticleVersioningFields($data);
                if (!is_null($reason)) {
                    $this->registerFailedRow($invalidCsvFile, $fields, $reason);
                    continue;
                }

                if (!empty($data->versionIdentifier)) {
                    $reason = InvalidRowValidations::validateNoDuplicateVersion($data, $this->processedArticles);
                    if (!is_null($reason)) {
                        $this->registerFailedRow($invalidCsvFile, $fields, $reason);
                        continue;
                    }
                }

                $fieldsList = array_pad($fields, $this->expectedRowSize, null);

                $hasIssueData = !empty(trim($data->issueTitle))
                                || !empty(trim($data->issueVolume))
                                || !empty(trim($data->issueNumber))
                                || !empty(trim($data->issueYear));

                if (empty($data->version) || (!empty($data->version) && (int)$data->version === 1)) {
                    if (!$hasIssueData) {
                        $reason = __('plugins.importexport.csv.atLeastOneIssueFieldRequired');
                        $this->registerFailedRow($invalidCsvFile, $fields, $reason);
                        continue;
                    }
                }

                if ($data->galleyFilenames) {
                    $reason = InvalidRowValidations::validateArticleGalleys(
                        $data->galleyFilenames,
                        $data->galleyLabels,
                        $this->sourceDir
                    );

                    if (!is_null($reason)) {
                        $this->registerFailedRow($invalidCsvFile, $fields, $reason);
                        continue;
                    }
                }

                if ($data->htmlGalley) {
                    $reason = InvalidRowValidations::validateHtmlGalleys($data->htmlGalley, $this->sourceDir);
                    if (!is_null($reason)) {
                        $this->registerFailedRow($invalidCsvFile, $fields, $reason);
                        continue;
                    }
                }

                $reason = InvalidRowValidations::validateGalleyViews($data->galleyViews ?? null, $data->galleyLabels ?? null);
                if (!is_null($reason)) {
                    $this->registerFailedRow($invalidCsvFile, $fields, $reason);
                    continue;
                }

                $reason = InvalidRowValidations::validatePublicationViews($data->articleViews ?? null, 'articleViews');
                if (!is_null($reason)) {
                    $this->registerFailedRow($invalidCsvFile, $fields, $reason);
                    continue;
                }

                if ($data->funders) {
                    $reason = InvalidRowValidations::validateFunders($data->funders);
                    if (!is_null($reason)) {
                        $this->registerFailedRow($invalidCsvFile, $fields, $reason);
                        continue;
                    }
                }

                if ($data->suppFilenames) {
                    $reason = InvalidRowValidations::validateSupplementaryFiles(
                        $data->suppFilenames,
                        $data->suppLabels,
                        $this->sourceDir
                    );

                    if (!is_null($reason)) {
                        $this->registerFailedRow($invalidCsvFile, $fields, $reason);
                        continue;
                    }
                }

                if ($data->suppFilenames && !empty($data->suppDescriptions)) {
                    $reason = InvalidRowValidations::validateSupplementaryDescriptions(
                        $data->suppFilenames,
                        $data->suppLabels,
                        $data->suppDescriptions
                    );
                    if (!is_null($reason)) {
                        $this->registerFailedRow($invalidCsvFile, $fields, $reason);
                        continue;
                    }
                }

                if ($data->references) {
                    $reason = InvalidRowValidations::validateReferencesFile($data->references, $this->sourceDir);
                    if (!is_null($reason)) {
                        $this->registerFailedRow($invalidCsvFile, $fields, $reason);
                        continue;
                    }
                }

                if (!RequiredIssueHeaders::isMultiVersionOrLocale($data, $this->processedArticles)) {
                    $reason = InvalidRowValidations::validateSectionFields($data);
                    if (!is_null($reason)) {
                        $this->registerFailedRow($invalidCsvFile, $fields, $reason);
                        continue;
                    }
                }

                $fileUploadUser = $this->user;
                $csvUser = null;
                $usedDefaultUser = false;
                if (!empty($data->username)) {
                    $csvUser = CachedEntities::getCachedUserByUsername($data->username);
                    if ($csvUser) {
                        $fileUploadUser = $csvUser;
                    } else {
                        $usedDefaultUser = true;
                    }
                }
                $hasValidCsvUser = !empty($data->username) && !$usedDefaultUser && isset($csvUser);

                $journal = CachedEntities::getCachedJournal($data->journalPath);

                $reason = InvalidRowValidations::validateJournalIsValid($journal, $data->journalPath);
                if (!is_null($reason)) {
                    $this->registerFailedRow($invalidCsvFile, $fields, $reason);
                    continue;
                }

                $reason = InvalidRowValidations::validateJournalLocale($journal, $data->locale);
                if (!is_null($reason)) {
                    $this->registerFailedRow($invalidCsvFile, $fields, $reason);
                    continue;
                }

                // we need a Genre for the files.  Assume a key of SUBMISSION as a default.
                $genreName = 'SUBMISSION';
                $genreId = CachedEntities::getCachedGenreId($genreName, $journal->getId());

                $reason = InvalidRowValidations::validateGenreIdValid($genreId, $genreName);
                if (!is_null($reason)) {
                    $this->registerFailedRow($invalidCsvFile, $fields, $reason);
                    continue;
                }

                $userGroupId = CachedEntities::getCachedUserGroupId($data->journalPath, $journal->getId());

                $reason = InvalidRowValidations::validateUserGroupId($userGroupId, $data->journalPath);
                if (!is_null($reason)) {
                    $this->registerFailedRow($invalidCsvFile, $fields, $reason);
                    continue;
                }

                if ($data->funders) {
                    $reason = InvalidRowValidations::validateFundingPluginEnabled($data->funders, $journal->getId(), 'Journal');
                    if (!is_null($reason)) {
                        $this->registerFailedRow($invalidCsvFile, $fields, $reason);
                        continue;
                    }

                    $reason = InvalidRowValidations::validateFundersCrossrefRegistry($data->funders, $journal->getId());
                    if (!is_null($reason)) {
                        $this->registerFailedRow($invalidCsvFile, $fields, $reason);
                        continue;
                    }
                }

                $this->initializeStaticVariables();

                $coverImageUploadName = null;
                if ($data->coverImageFilename) {
                    $reason = InvalidRowValidations::validateCoverImageIsValid($data->coverImageFilename, $this->sourceDir);
                    if (!is_null($reason)) {
                        $this->registerFailedRow($invalidCsvFile, $fields, $reason);
                        continue;
                    }

                    if (!$this->dryMode) {
                        $sanitizedCoverImageName = str_replace([' ', '_', ':'], '-', mb_strtolower($data->coverImageFilename));
                        $sanitizedCoverImageName = PKPString::regexp_replace('/[^a-z0-9\.\-]+/', '', $sanitizedCoverImageName);
                        $coverImageUploadName = uniqid() . '-' . basename($sanitizedCoverImageName);

                        $destFilePath = $this->publicFileManager->getContextFilesPath($journal->getId()) . '/' . $coverImageUploadName;
                        $srcFilePath = "{$this->sourceDir}/{$data->coverImageFilename}";
                        $bookCoverImageSaved = $this->fileManager->copyFile($srcFilePath, $destFilePath);

                        if (!$bookCoverImageSaved) {
                            $reason = __('plugin.importexport.csv.erroWhileSavingBookCoverImage');
                            $this->registerFailedRow($invalidCsvFile, $fields, $reason);

                            continue;
                        }
                    }
                }

                $existingSubmission = null; /** @var null|Submission */
                $basePublication = null; /** @var null|Publication */
                $isMultiLocaleImport = false;

                if (
                    !empty($data->versionIdentifier)
                    && InvalidRowValidations::versionExistsInAnyLocale($data, $this->processedArticles)
                ) {
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

                    $publication = PublicationProcessor::processMultiLocalePublication($publication, $data);
                } elseif ($existingSubmission && $basePublication) {
                    // New version import
                    $submission = $existingSubmission;
                    $publication = PublicationProcessor::createPublicationVersion($basePublication, $data);

                    $publication = PublicationProcessor::processVersionedPublication($publication, $data, $basePublication, $this->sourceDir);
                } else {
                    // New submission import
                    $initialPublication = PublicationProcessor::createInitialPublication($data);
                    $submission = SubmissionProcessor::process($data, $initialPublication, $journal);
                    $publication = PublicationProcessor::process($submission, $data, $journal, $this->sourceDir);
                }

                if (!$publication) {
                    $reason = __('plugins.importexport.csv.errorWhileCreatingPublication');
                    $this->registerFailedRow($invalidCsvFile, $fieldsList, $reason);
                    continue;
                }

                if (!$this->dryMode && $data->coverImageFilename) {
                    $publication = PublicationProcessor::updateCoverImage($publication, $data, $coverImageUploadName);
                }

                $galleyIds = [];
                $galleyMetadata = [];
                if (!$this->dryMode && $data->galleyFilenames) {
                        foreach (array_map('trim', explode(';', $data->galleyFilenames)) as $galleyFile) {
                            $galleyFileId = $this->saveSubmissionFile(
                                $galleyFile,
                                $journal->getId(),
                                $submission,
                                $invalidCsvFile,
                                __('plugins.importexport.csv.errorWhileSavingSubmissionGalley', ['galley' => $galleyFile]),
                                $fieldsList
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
                    } elseif (!$this->dryMode && $basePublication && !$isMultiLocaleImport) {
                        $this->cloneGalleysFromBasePublication($basePublication, $publication);
                    }

                if (!$this->dryMode && $data->htmlGalley) {
                    $htmlGalleyFiles = array_values(array_filter(
                        array_map('trim', explode(';', $data->htmlGalley)),
                        fn(string $f) => $f !== ''
                    ));

                    $htmlFile = $htmlGalleyFiles[0];
                    $dependentFiles = array_slice($htmlGalleyFiles, 1);
                    $htmlSourcePath = "{$this->sourceDir}/{$htmlFile}";

                    try {
                        $preparedHtml = HtmlGalleyProcessor::sanitizeHtmlFile($htmlSourcePath);
                        $tempFile = tempnam(sys_get_temp_dir(), 'csv_html_galley_');
                        file_put_contents($tempFile, $preparedHtml);

                        $htmlFileId = $this->saveSubmissionFile(
                            $htmlFile,
                            $journal->getId(),
                            $submission,
                            $invalidCsvFile,
                            __('plugins.importexport.csv.errorWhileSavingHtmlGalley', ['filename' => $htmlFile]),
                            $fieldsList,
                            $tempFile
                        );

                        if (is_null($htmlFileId)) {
                            foreach ($galleyIds as $galleyItem) {
                                $this->fileService->delete($galleyItem['id']);
                            }
                        } else {
                            $htmlGalleyMeta = $this->handleGalley(
                                ['file' => $htmlFile, 'id' => $htmlFileId],
                                $data,
                                $submission->getId(),
                                $genreId,
                                'HTML',
                                $publication->getId(),
                                $fileUploadUser
                            );

                            $galleyMetadata[] = $htmlGalleyMeta;

                            $submissionDir = sprintf($this->format, $journal->getId(), $submission->getId());

                            if (!empty($dependentFiles)) {
                                HtmlGalleyProcessor::createDependentFiles(
                                    $dependentFiles,
                                    $htmlGalleyMeta['submissionFileId'],
                                    $this->sourceDir,
                                    $submissionDir,
                                    $data,
                                    $submission->getId(),
                                    $genreId,
                                    $fileUploadUser,
                                    $this->fileService
                                );
                            }
                        }
                    } catch (\Exception $e) {
                        $this->registerFailedRow($invalidCsvFile, $fieldsList, $e->getMessage());
                        continue;
                    } finally {
                        if (isset($tempFile) && file_exists($tempFile)) {
                            unlink($tempFile);
                        }
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

                // Process supplementary files
                if (!$this->dryMode && $data->suppFilenames) {
                    // Get supplementary genre for supplementary files
                    $genreDao = CachedDaos::getGenreDao();
                    $supplementaryGenres = $genreDao->getBySupplementaryAndContextId(true, $journal->getId())->toArray();
                    $suppGenreId = !empty($supplementaryGenres) ? $supplementaryGenres[0]->getId() : $genreId;
                    $suppDescriptionsArray = !empty($data->suppDescriptions)
                        ? array_map('trim', explode(';', $data->suppDescriptions))
                        : [];
                    $suppIds = [];

                    foreach (array_map('trim', explode(';', $data->suppFilenames)) as $suppFile) {
                        $suppFileId = $this->saveSubmissionFile(
                            $suppFile,
                            $journal->getId(),
                            $submission,
                            $invalidCsvFile,
                            __('plugins.importexport.csv.errorWhileSavingSupplementaryFile', ['file' => $suppFile]),
                            $fieldsList
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

                if ($isMultiLocaleImport) {
                    if ($hasValidCsvUser) {
                        AuthorsProcessor::updateUsernameAuthorLocale($csvUser, $publication, $data->locale);
                    }
                    AuthorsProcessor::processMultiLocale($data, $journal->getContactEmail(), $submission->getId(), $publication, $userGroupId);
                    KeywordsProcessor::processMultiLocale($data, $publication->getId());
                    SubjectsProcessor::processMultiLocale($data, $publication->getId());
                    FundersProcessor::processMultiLocale($data, $submission, $journal->getId());
                    PublicationProcessor::processSupportingAgenciesMultiLocale($data, $publication);
                } else {
                    $usernameAuthorAdded = false;
                    if ($hasValidCsvUser && (!empty($data->authors) || is_null($basePublication))) {
                        AuthorsProcessor::addAuthorFromUser($csvUser, $submission, $publication, $journal, $userGroupId);
                        $usernameAuthorAdded = true;
                    }

                    AuthorsProcessor::process($data, $journal->getContactEmail(), $submission->getId(), $publication, $userGroupId, $basePublication, $usernameAuthorAdded ? $csvUser : null);
                    KeywordsProcessor::process($data, $publication->getId(), $basePublication);
                    SubjectsProcessor::process($data, $publication->getId(), $basePublication);
                    FundersProcessor::process($data, $submission, $journal->getId(), $basePublication);
                    PublicationProcessor::processSupportingAgencies($data, $publication, $basePublication);
                }

                if (
                    ((!empty($data->version) && (int) $data->version === 1) || empty($data->version))
                    && $data->coverage
                ) {
                    PublicationProcessor::updateCoverage($publication, $data->coverage, $data->locale);
                }

                if ($isMultiLocaleImport && $data->coverage) {
                    PublicationProcessor::updateCoverage($publication, $data->coverage, $data->locale);
                }

                $section = SectionsProcessor::process($data, $journal->getId(), $basePublication);
                if ($section) {
                    PublicationProcessor::updateSectionId($publication, $section->getId());
                }

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

                if (!empty($data->versionIdentifier)) {
                    $this->trackProcessedArticle($data, $submission, $publication);
                }
            }

            $totalFailed += $this->failedRows;

            if ($this->dryMode) {
                $passed = $this->processedRows - $this->failedRows;
                DryModeReporter::printFileHeader($basename);
                if (!empty($this->fileFailedRows)) {
                    DryModeReporter::printTableHeader();
                    foreach ($this->fileFailedRows as $failedRow) {
                        DryModeReporter::printFailedRow($failedRow['row'], $failedRow['reason']);
                    }
                }
                DryModeReporter::printFileSummary($passed, $this->failedRows, $this->processedRows);
                $totalFiles++;
                $totalPassed += $passed;

                DB::rollBack();
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
                CachedEntities::reset();
                $this->processedIssues = [];
                $this->processedArticles = [];
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

        if ($this->dryMode) {
            DryModeReporter::printGrandTotal($totalFiles, $totalPassed, $totalFailed);

            return $totalFailed > 0 ? 1 : 0;
        }

        $this->syncCoverImagesForProcessedArticles();
        $this->setCurrentVersionsForProcessedArticles();

        IssueProcessor::fillMissingIssueDates($this->processedIssues);
        IssueProcessor::reorderImportedIssues($this->processedIssues);

        return $totalFailed > 0 ? 1 : 0;
    }

    /** Writes a failed row to the invalid CSV file and, in dry mode, keeps it for the console report. */
    private function registerFailedRow(\SplFileObject $invalidCsvFile, array $fields, string $reason): void
    {
        CSVFileHandler::processFailedRow($invalidCsvFile, $fields, $this->expectedRowSize, $reason, $this->failedRows);

        if ($this->dryMode) {
            $this->fileFailedRows[] = ['row' => $this->processedRows + 1, 'reason' => $reason];
        }
    }

    /** Insert static data that will be used for the submission processing */
    private function initializeStaticVariables(): void
    {
        $this->dirNames ??= Application::getFileDirectories();
        $this->format ??= trim($this->dirNames['context'], '/') . '/%d/' . trim($this->dirNames['submission'], '/') . '/%d';
        $this->fileManager ??= new FileManager();
        $this->publicFileManager ??= new PublicFileManager();
        $this->fileService ??= Services::get('file');
    }

    /**
     * Save a submission file. If an error occurred, the method will delete the submission already saved
     * and return null.
     */
    private function saveSubmissionFile(
        string $filePath,
        int $journalId,
        Submission $submission,
        \SplFileObject $invalidCsvFile,
        string $reason,
        array $fieldsList,
        ?string $sourcePathOverride = null
    ): ?int
    {
        try {
            $extension = $this->fileManager->parseFileExtension($filePath);
            $submissionDir = sprintf($this->format, $journalId, $submission->getId());
            $completePath = $sourcePathOverride ?? "{$this->sourceDir}/{$filePath}";

            return $this->fileService->add($completePath, $submissionDir . '/' . uniqid() . '.' . $extension);
        } catch (\Exception $e) {
            $this->registerFailedRow($invalidCsvFile, $fieldsList, $reason);

            Repo::submission()->delete($submission);

            return null;
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

    /**
     * Clone galleys from base publication to versioned publication
     */
    private function cloneGalleysFromBasePublication(Publication $basePublication, Publication $newPublication): void
    {
        $baseGalleys = Repo::galley()->getCollector()
            ->filterByPublicationIds([$basePublication->getId()])
            ->getMany()
            ->toArray();

        if (empty($baseGalleys)) {
            return;
        }

        foreach ($baseGalleys as $baseGalley) {
            $newGalley = clone $baseGalley;
            $newGalley->setData('id', null);
            $newGalley->setData('publicationId', $newPublication->getId());

            // Clone the submission file associated with the galley
            $baseSubmissionFileId = $baseGalley->getData('submissionFileId');
            if ($baseSubmissionFileId) {
                $baseSubmissionFile = Repo::submissionFile()->get($baseSubmissionFileId);
                if ($baseSubmissionFile) {
                    $newSubmissionFile = clone $baseSubmissionFile;
                    $newSubmissionFile->setData('id', null);
                    $newSubmissionFileId = Repo::submissionFile()->add($newSubmissionFile);
                    $newGalley->setData('submissionFileId', $newSubmissionFileId);
                }
            }

            $newGalleyId = Repo::galley()->add($newGalley);

            // Update the submission file's assoc info to point to the new galley
            if (isset($newSubmissionFileId)) {
                $newSubmissionFile = Repo::submissionFile()->get($newSubmissionFileId);
                if ($newSubmissionFile) {
                    SubmissionFileProcessor::updateAssocInfo($newSubmissionFile, $newGalleyId);
                }
            }
        }
    }
}
