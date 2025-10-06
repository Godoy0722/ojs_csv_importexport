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
use APP\plugins\importexport\csv\classes\processors\AuthorsProcessor;
use APP\plugins\importexport\csv\classes\processors\CategoriesProcessor;
use APP\plugins\importexport\csv\classes\processors\GalleyProcessor;
use APP\plugins\importexport\csv\classes\processors\IssueProcessor;
use APP\plugins\importexport\csv\classes\processors\KeywordsProcessor;
use APP\plugins\importexport\csv\classes\processors\PublicationProcessor;
use APP\plugins\importexport\csv\classes\processors\SectionsProcessor;
use APP\plugins\importexport\csv\classes\processors\SubjectsProcessor;
use APP\plugins\importexport\csv\classes\processors\SubmissionFileProcessor;
use APP\plugins\importexport\csv\classes\processors\SubmissionProcessor;
use APP\plugins\importexport\csv\classes\validations\InvalidRowValidations;
use APP\plugins\importexport\csv\classes\validations\RequiredIssueHeaders;
use APP\publication\Publication;
use APP\submission\Submission;
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
     * Array to track processed articles by identifier
     * Structure: [
     *     'identifier' => [
     *         'version1' => [
     *             'data' => csv_row,
     *             'publication' => Publication,
     *             'submission' => Submission
     *         ],
     *         'version2' => [
     *             'data' => csv_row,
     *             'publication' => Publication,
     *             'submission' => Submission
     *         ]
     *     ]
     * ]
     *
     * @var array
     */
    private array $processedArticles;

    public function __construct(string $sourceDir, User $user)
    {
        $this->expectedRowSize = count(RequiredIssueHeaders::$issueHeaders);
        $this->sourceDir = $sourceDir;
        $this->user = $user;
        $this->processedIssues = [];
        $this->processedArticles = [];
    }

    public function run()
    {
        foreach (new \DirectoryIterator($this->sourceDir) as $fileInfo) {
            if (!$fileInfo->isFile() || $fileInfo->getExtension() !== 'csv') {
                continue;
            }

            $filePath = $fileInfo->getPathname();
            $file = CSVFileHandler::createReadableCSVFile($filePath);

            if (is_null($file)) {
                continue;
            }

            $basename = $fileInfo->getBasename();
            $invalidCsvFile = CSVFileHandler::createCSVFileInvalidRows($this->sourceDir, "invalid_{$basename}", RequiredIssueHeaders::$issueHeaders);

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

                $data = (object) array_combine(
                    RequiredIssueHeaders::$issueHeaders,
                    array_pad(array_map('trim', $fields), $this->expectedRowSize, null)
                );

                $reason = InvalidRowValidations::validateRowHasAllRequiredFields($data, [RequiredIssueHeaders::class, 'validateRowHasAllRequiredFields']);
                if (!is_null($reason)) {
                    CSVFileHandler::processFailedRow($invalidCsvFile, $fields, $this->expectedRowSize, $reason, $this->failedRows);
                    continue;
                }

                $reason = InvalidRowValidations::validateArticleVersioningFields($data);
                if (!is_null($reason)) {
                    CSVFileHandler::processFailedRow($invalidCsvFile, $fields, $this->expectedRowSize, $reason, $this->failedRows);
                    continue;
                }

                if (!empty($data->versionIdentifier)) {
                    $reason = InvalidRowValidations::validateNoDuplicateVersion($data, $this->processedArticles);
                    if (!is_null($reason)) {
                        CSVFileHandler::processFailedRow($invalidCsvFile, $fields, $this->expectedRowSize, $reason, $this->failedRows);
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
                        CSVFileHandler::processFailedRow($invalidCsvFile, $fields, $this->expectedRowSize, $reason, $this->failedRows);
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
                        CSVFileHandler::processFailedRow($invalidCsvFile, $fields, $this->expectedRowSize, $reason, $this->failedRows);
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
                        CSVFileHandler::processFailedRow($invalidCsvFile, $fields, $this->expectedRowSize, $reason, $this->failedRows);
                        continue;
                    }
                }

                $journal = CachedEntities::getCachedJournal($data->journalPath);

                $reason = InvalidRowValidations::validateJournalIsValid($journal, $data->journalPath);
                if (!is_null($reason)) {
                    CSVFileHandler::processFailedRow($invalidCsvFile, $fields, $this->expectedRowSize, $reason, $this->failedRows);
                    continue;
                }

                $reason = InvalidRowValidations::validateJournalLocale($journal, $data->locale);
                if (!is_null($reason)) {
                    CSVFileHandler::processFailedRow($invalidCsvFile, $fields, $this->expectedRowSize, $reason, $this->failedRows);
                    continue;
                }

                // we need a Genre for the files.  Assume a key of SUBMISSION as a default.
                $genreName = 'SUBMISSION';
                $genreId = CachedEntities::getCachedGenreId($genreName, $journal->getId());

                $reason = InvalidRowValidations::validateGenreIdValid($genreId, $genreName);
                if (!is_null($reason)) {
                    CSVFileHandler::processFailedRow($invalidCsvFile, $fields, $this->expectedRowSize, $reason, $this->failedRows);
                    continue;
                }

                $userGroupId = CachedEntities::getCachedUserGroupId($data->journalPath, $journal->getId());

                $reason = InvalidRowValidations::validateUserGroupId($userGroupId, $data->journalPath);
                if (!is_null($reason)) {
                    CSVFileHandler::processFailedRow($invalidCsvFile, $fields, $this->expectedRowSize, $reason, $this->failedRows);
                    continue;
                }

                $this->initializeStaticVariables();

                if ($data->coverImageFilename) {
                    $reason = InvalidRowValidations::validateCoverImageIsValid($data->coverImageFilename, $this->sourceDir);
                    if (!is_null($reason)) {
                        CSVFileHandler::processFailedRow($invalidCsvFile, $fields, $this->expectedRowSize, $reason, $this->failedRows);
                        continue;
                    }

                    $sanitizedCoverImageName = str_replace([' ', '_', ':'], '-', mb_strtolower($data->coverImageFilename));
                    $sanitizedCoverImageName = PKPString::regexp_replace('/[^a-z0-9\.\-]+/', '', $sanitizedCoverImageName);
                    $coverImageUploadName = uniqid() . '-' . basename($sanitizedCoverImageName);

                    $destFilePath = $this->publicFileManager->getContextFilesPath($journal->getId()) . '/' . $coverImageUploadName;
                    $srcFilePath = "{$this->sourceDir}/{$data->coverImageFilename}";
                    $bookCoverImageSaved = $this->fileManager->copyFile($srcFilePath, $destFilePath);

                    if (!$bookCoverImageSaved) {
                        $reason = __('plugin.importexport.csv.erroWhileSavingBookCoverImage');
                        CSVFileHandler::processFailedRow($invalidCsvFile, $fields, $this->expectedRowSize, $reason, $this->failedRows);

                        continue;
                    }
                }

                $existingSubmission = null; /** @var null|Submission */
                $basePublication = null; /** @var null|Publication */

                if (!empty($data->versionIdentifier) && isset($this->processedArticles[$data->versionIdentifier])) {
                    $lastVersionData = end($this->processedArticles[$data->versionIdentifier]);
                    $existingSubmission = $lastVersionData['submission'];
                    $basePublication = $lastVersionData['publication'];
                }

                if ($existingSubmission && $basePublication) {
                    $submission = $existingSubmission;
                    $publication = PublicationProcessor::createPublicationVersion($basePublication, $data);

                    $publication = PublicationProcessor::processVersionedPublication($publication, $data, $basePublication);

                    // Handle cover image for versioned publication if provided in CSV
                    if ($data->coverImageFilename) {
                        $publication = PublicationProcessor::updateCoverImage($publication, $data, $coverImageUploadName);
                    }
                } else {
                    $initialPublication = PublicationProcessor::createInitialPublication($data);
                    $submission = SubmissionProcessor::process($data, $initialPublication, $journal);
                    $publication = PublicationProcessor::process($submission, $data, $journal);

                    // Handle cover image for new publication
                    if ($data->coverImageFilename) {
                        $publication = PublicationProcessor::updateCoverImage($publication, $data, $coverImageUploadName);
                    }
                }

                $publication = PublicationProcessor::process($submission, $data, $journal, $publication);
                if (!$publication) {
                    $reason = __('plugins.importexport.csv.errorWhileCreatingPublication');
                    CSVFileHandler::processFailedRow($invalidCsvFile, $fieldsList, $this->expectedRowSize, $reason, $this->failedRows);
                    continue;
                }

                // Array to store each galley ID to its respective galley file
                $galleyIds = [];
                if ($data->galleyFilenames) {
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

                        $this->handleGalley(
                            $galleyItem,
                            $data,
                            $submission->getId(),
                            $genreId,
                            $galleyLabel,
                            $publication->getId()
                        );
                    }
                } elseif ($basePublication) {
                    $this->cloneGalleysFromBasePublication($basePublication, $publication);
                }

                // Process supplementary files
                if ($data->suppFilenames) {
                    // Get supplementary genre for supplementary files
                    $genreDao = CachedDaos::getGenreDao();
                    $supplementaryGenres = $genreDao->getBySupplementaryAndContextId(true, $journal->getId())->toArray();
                    $suppGenreId = !empty($supplementaryGenres) ? $supplementaryGenres[0]->getId() : $genreId;
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

                        $this->handleGalley(
                            $suppItem,
                            $data,
                            $submission->getId(),
                            $suppGenreId,
                            $suppLabel,
                            $publication->getId()
                        );
                    }
                }

                AuthorsProcessor::process($data, $journal->getContactEmail(), $submission->getId(), $publication, $userGroupId, $basePublication);
                KeywordsProcessor::process($data, $publication->getId(), $basePublication);
                SubjectsProcessor::process($data, $publication->getId(), $basePublication);

                if ($data->coverage || ($basePublication && !$data->coverage)) {
                    if (!empty($data->coverage)) {
                        PublicationProcessor::updateCoverage($publication, $data->coverage, $data->locale);
                    } elseif ($basePublication && $basePublication->getLocalizedData('coverage', $data->locale)) {
                        PublicationProcessor::updateCoverage($publication, $basePublication->getLocalizedData('coverage', $data->locale), $data->locale);
                    }
                }

                $section = SectionsProcessor::process($data, $journal->getId(), $basePublication);
                PublicationProcessor::updateSectionId($publication, $section->getId());

                $issue = IssueProcessor::process($journal->getId(), $data, $basePublication);
                PublicationProcessor::updateIssueId($publication, $issue->getId());

                if ($data->categories || $basePublication) {
                    ($existingSubmission && $basePublication)
                        ? CategoriesProcessor::processForVersion($data->categories, $data->locale, $journal->getId(), $publication->getId(), $basePublication)
                        : CategoriesProcessor::process($data->categories, $data->locale, $journal->getId(), $publication->getId());
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

            echo __('plugins.importexpot.csv.fileProcessFinished', [
                'filename' => $fileInfo->getFilename(),
                'processedRows' => $this->processedRows,
                'failedRows' => $this->failedRows,
            ]) . "\n";

            if (!$this->failedRows) {
                unlink($this->sourceDir . '/' . "invalid_{$basename}");
            }
        }

        $this->setCurrentVersionsForProcessedArticles();

        IssueProcessor::reorderImportedIssues($this->processedIssues);
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
        array $fieldsList
    ): ?int
    {
        try {
            $extension = $this->fileManager->parseFileExtension($filePath);
            $submissionDir = sprintf($this->format, $journalId, $submission->getId());
            $completePath = "{$this->sourceDir}/{$filePath}";

            return $this->fileService->add($completePath, $submissionDir . '/' . uniqid() . '.' . $extension);
        } catch (\Exception $e) {
            CSVFileHandler::processFailedRow($invalidCsvFile, $fieldsList, $this->expectedRowSize, $reason, $this->failedRows);

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
        int $publicationId
    ): void
    {
        $galleyCompletePath = "{$this->sourceDir}/{$item['file']}";
        $galleyExtension = $this->fileManager->parseFileExtension($galleyCompletePath);

        $submissionFile = SubmissionFileProcessor::process(
            $data->locale,
            $this->user->getId(),
            $submissionId,
            $galleyCompletePath,
            $genreId,
            $item['id'],
        );

        // Now that we have the submission file ID, it's time to process the galley itself.
        $galleyId = GalleyProcessor::process($submissionFile->getId(), $data, $label, $publicationId, $galleyExtension);
        SubmissionFileProcessor::updateAssocInfo($submissionFile, $galleyId);
    }

    /**
     * Tracks a processed article for version management
     */
   private function trackProcessedArticle(object $data, Submission $submission, Publication $publication): void
   {
       $identifier = $data->versionIdentifier;
       $version = (int)$data->version;

       if (!isset($this->processedArticles[$identifier])) {
           $this->processedArticles[$identifier] = [];
       }

       $this->processedArticles[$identifier][$version] = [
           'data' => $data,
           'submission' => $submission,
           'publication' => $publication
       ];
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

           foreach ($versions as $versionKey => $versionData) {
               $versionNumber = (int)$versionData['data']->version;
               if ($versionNumber > $highestVersion) {
                   $highestVersion = $versionNumber;
                   $currentVersionData = $versionData;
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
