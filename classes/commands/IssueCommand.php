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

    public function __construct(string $sourceDir, User $user)
    {
        $this->expectedRowSize = count(RequiredIssueHeaders::$issueHeaders);
        $this->sourceDir = $sourceDir;
        $this->user = $user;
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

                $fieldsList = array_pad($fields, $this->expectedRowSize, null);

                $hasIssueData = !empty(trim($data->issueTitle))
                                || !empty(trim($data->issueVolume))
                                || !empty(trim($data->issueNumber))
                                || !empty(trim($data->issueYear));

                if (!$hasIssueData) {
                    $reason = __('plugins.importexport.csv.atLeastOneIssueFieldRequired');
                    CSVFileHandler::processFailedRow($invalidCsvFile, $fields, $this->expectedRowSize, $reason, $this->failedRows);
                    continue;
                }

                $reason = InvalidRowValidations::validateArticleFileIsValid($data->articleFilepath, $this->sourceDir);

                $reason = InvalidRowValidations::validateArticleFileIsValid($data->articleFilepath, $this->sourceDir);
                if (!is_null($reason)) {
                    CSVFileHandler::processFailedRow($invalidCsvFile, $fields, $this->expectedRowSize, $reason, $this->failedRows);
                    continue;
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

                $section = CachedEntities::getCachedSection($data->sectionTitle, $data->sectionAbbrev, $data->locale, $journal->getId());
                $abstractsRequired = true;

                if ($section) {
                    $abstractsRequired = !$section->getData('abstractsNotRequired');
                }

                if ($abstractsRequired && empty(trim($data->articleAbstract))) {
                    $reason = __('plugins.importexport.csv.abstractRequiredBySection');
                    CSVFileHandler::processFailedRow($invalidCsvFile, $fields, $this->expectedRowSize, $reason, $this->failedRows);
                    continue;
                }

                // we need a Genre for the files.  Assume a key of SUBMISSION as a default.
			    $genreName = mb_strtoupper($data->genreName ?? 'SUBMISSION');
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

                $initialPublication = PublicationProcessor::createInitialPublication($data, $journal);
                $submission = SubmissionProcessor::process($data, $initialPublication, $journal);

                // Copy Submission file. If an error occured, save this row as invalid,
                // delete the saved submission and continue the loop.
                $articleFilePathId = $this->saveSubmissionFile(
                    $data->articleFilepath,
                    $journal->getId(),
                    $submission,
                    $invalidCsvFile,
                    __('plugins.importexport.csv.errorWhileSavingSubmissionFile'),
                    $fieldsList
                );

                if (is_null($articleFilePathId)) {
                    continue;
                }

                $publication = PublicationProcessor::process($submission, $data, $journal);
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
                            $this->fileService->delete($articleFilePathId);

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

                        $this->handleArticleGalley(
                            $galleyItem,
                            $data,
                            $submission->getId(),
                            $genreId,
                            $galleyLabel,
                            $publication->getId()
                        );
                    }
                }

                AuthorsProcessor::process($data, $journal->getContactEmail(), $submission->getId(), $publication, $userGroupId);

                $articleFileCompletePath = "{$this->sourceDir}/{$data->articleFilepath}";
                SubmissionFileProcessor::process(
                    $data->locale,
                    $this->user->getId(),
                    $submission->getId(),
                    $articleFileCompletePath,
                    $genreId,
                    $articleFilePathId
                );

                KeywordsProcessor::process($data, $publication->getId());
                SubjectsProcessor::process($data, $publication->getId());

                if ($data->coverage) {
                    PublicationProcessor::updateCoverage($publication, $data->coverage, $data->locale);
                }

                if ($data->coverImageFilename) {
                    PublicationProcessor::updateCoverImage($publication, $data, $coverImageUploadName);
                }

                if ($data->categories) {
                    CategoriesProcessor::process($data->categories, $data->locale, $journal->getId(), $publication->getId());
                }

                $issue = IssueProcessor::process($journal->getId(), $data);
                PublicationProcessor::updateIssueId($publication, $issue->getId());
                $section = SectionsProcessor::process($data, $journal->getId());
                PublicationProcessor::updateSectionId($publication, $section->getId());
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
    private function handleArticleGalley(
        array $galleyItem,
        object $data,
        int $submissionId,
        int $genreId,
        string $galleyLabel,
        int $publicationId
    ): void
    {
        $galleyCompletePath = "{$this->sourceDir}/{$galleyItem['file']}";
        $galleyExtension = $this->fileManager->parseFileExtension($galleyCompletePath);

        $submissionFile = SubmissionFileProcessor::process(
            $data->locale,
            $this->user->getId(),
            $submissionId,
            $galleyCompletePath,
            $genreId,
            $galleyItem['id'],
        );

        // Now that we have the submission file ID, it's time to process the galley itself.
        $galleyId = GalleyProcessor::process($submissionFile->getId(), $data, $galleyLabel, $publicationId, $galleyExtension);
        SubmissionFileProcessor::updateAssocInfo($submissionFile, $galleyId);
    }
}
