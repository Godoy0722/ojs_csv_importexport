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

namespace PKP\Plugins\ImportExport\CSV\Classes\Commands;

import('lib.pkp.classes.submission.SubmissionFile');
import('lib.pkp.classes.file.FileManager');

use PKP\Plugins\ImportExport\CSV\Classes\CachedAttributes\CachedDaos;
use PKP\Plugins\ImportExport\CSV\Classes\CachedAttributes\CachedEntities;
use PKP\Plugins\ImportExport\CSV\Classes\Handlers\CSVFileHandler;
use PKP\Plugins\ImportExport\CSV\Classes\Processors\AuthorsProcessor;
use PKP\Plugins\ImportExport\CSV\Classes\Processors\CategoriesProcessor;
use PKP\Plugins\ImportExport\CSV\Classes\Processors\GalleyProcessor;
use PKP\Plugins\ImportExport\CSV\Classes\Processors\IssueProcessor;
use PKP\Plugins\ImportExport\CSV\Classes\Processors\KeywordsProcessor;
use PKP\Plugins\ImportExport\CSV\Classes\Processors\PublicationProcessor;
use PKP\Plugins\ImportExport\CSV\Classes\Processors\SectionsProcessor;
use PKP\Plugins\ImportExport\CSV\Classes\Processors\SubjectsProcessor;
use PKP\Plugins\ImportExport\CSV\Classes\Processors\SubmissionFileProcessor;
use PKP\Plugins\ImportExport\CSV\Classes\Processors\SubmissionProcessor;
use PKP\Plugins\ImportExport\CSV\Classes\Validations\InvalidRowValidations;
use PKP\Plugins\ImportExport\CSV\Classes\Validations\RequiredIssueHeaders;
use Illuminate\Database\Capsule\Manager as Capsule;

class IssueCommand
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

    /** @var \PublicFileManager */
    private $_publicFileManager;

    /** @var \FileManager */
    private $_fileManager;

    /** @var \PKPFileService */
    private $_fileService;

    /** @var \User */
    private $_user;

    /**
     * The file directory array map used by the application.
     *
     * @var string[]
     */
    private $_dirNames;

    /** @var string */
    private $_format;

    /** @var array */
    private $_processedIssues;

    /**
     * Array to track processed preprints by identifier, version, and locale
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
    private $_processedPublications;

    /**
     * @param string $sourceDir
     * @param \User $user
     */
    public function __construct($sourceDir, $user)
    {
        import('plugins.importexport.csv.classes.validations.RequiredIssueHeaders');
        $this->_expectedRowSize = count(RequiredIssueHeaders::$issueHeaders);
        $this->_sourceDir = $sourceDir;
        $this->_user = $user;
        $this->_processedIssues = [];
        $this->_processedPublications = [];
    }

    public function run()
    {
        foreach (new \DirectoryIterator($this->_sourceDir) as $fileInfo) {
            if (!$fileInfo->isFile() || $fileInfo->getExtension() !== 'csv') {
                continue;
            }

            $filePath = $fileInfo->getPathname();

            $file = CSVFileHandler::createReadableCSVFile($filePath);

            if (is_null($file)) {
                continue;
            }

            $basename = $fileInfo->getBasename();
            $invalidCsvFile = CSVFileHandler::createCSVFileInvalidRows($this->_sourceDir, "invalid_{$basename}", RequiredIssueHeaders::$issueHeaders);

            if (is_null($invalidCsvFile)) {
                continue;
            }

            $this->_processedRows = 0;
            $this->_failedRows = 0;

            foreach ($file as $index => $fields) {
                if (!$index || empty(array_filter($fields))) {
                    continue; // Skip headers or end of file
                }

                ++$this->_processedRows;

                $reason = InvalidRowValidations::validateRowContainAllFields($fields, $this->_expectedRowSize);

                if (!is_null($reason)) {
                    CSVFileHandler::processFailedRow($invalidCsvFile, $fields, $this->_expectedRowSize, $reason, $this->_failedRows);
                    continue;
                }

                $data = (object) array_combine(
                    RequiredIssueHeaders::$issueHeaders,
                    array_pad(array_map('trim', $fields), $this->_expectedRowSize, null)
                );

                $reason = InvalidRowValidations::validateRowHasAllRequiredFields($data, function($row) {
                    return RequiredIssueHeaders::validateRowHasAllRequiredFields($row, $this->_processedPublications);
                });
                if (!is_null($reason)) {
                    CSVFileHandler::processFailedRow($invalidCsvFile, $fields, $this->_expectedRowSize, $reason, $this->_failedRows);
                    continue;
                }

                $reason = InvalidRowValidations::validateVersionFields($data);
                if ($reason) {
                    CSVFileHandler::processFailedRow($invalidCsvFile, $fields, $this->_expectedRowSize, $reason, $this->_failedRows);
                    continue;
                }

                if (!empty($data->versionIdentifier)) {
                    $reason = InvalidRowValidations::validateNoDuplicateVersion($data, $this->_processedPublications);
                    if (!is_null($reason)) {
                        CSVFileHandler::processFailedRow($invalidCsvFile, $fields, $this->_expectedRowSize, $reason, $this->_failedRows);
                        continue;
                    }
                }

                $fieldsList = array_pad($fields, $this->_expectedRowSize, null);

                $hasIssueData = !empty(trim($data->issueTitle))
                                || !empty(trim($data->issueVolume))
                                || !empty(trim($data->issueNumber))
                                || !empty(trim($data->issueYear));

				if (empty($data->version) || (!empty($data->version) && (int)$data->version === 1)) {
					if (!$hasIssueData) {
						$reason = __('plugins.importexport.csv.atLeastOneIssueFieldRequired');
						CSVFileHandler::processFailedRow($invalidCsvFile, $fields, $this->_expectedRowSize, $reason, $this->_failedRows);
						continue;
					}
				}

                if ($data->galleyFilenames) {
                    $reason = InvalidRowValidations::validateArticleGalleys(
                        $data->galleyFilenames,
                        $data->galleyLabels,
                        $this->_sourceDir
                    );

                    if (!is_null($reason)) {
                        CSVFileHandler::processFailedRow($invalidCsvFile, $fields, $this->_expectedRowSize, $reason, $this->_failedRows);
                        continue;
                    }
                }

                if ($data->suppFilenames) {
                    $reason = InvalidRowValidations::validateSupplementaryFiles(
                        $data->suppFilenames,
                        $data->suppLabels,
                        $this->_sourceDir
                    );

                    if (!is_null($reason)) {
                        CSVFileHandler::processFailedRow($invalidCsvFile, $fields, $this->_expectedRowSize, $reason, $this->_failedRows);
                        continue;
                    }
                }

				if ($data->references) {
                    $reason = InvalidRowValidations::validateReferencesFile($data->references, $this->_sourceDir);
                    if (!is_null($reason)) {
                        CSVFileHandler::processFailedRow($invalidCsvFile, $fields, $this->_expectedRowSize, $reason, $this->_failedRows);
                        continue;
                    }
                }

                $journal = CachedEntities::getCachedJournal($data->journalPath);

                $reason = InvalidRowValidations::validateJournalIsValid($journal, $data->journalPath);
                if (!is_null($reason)) {
                    CSVFileHandler::processFailedRow($invalidCsvFile, $fields, $this->_expectedRowSize, $reason, $this->_failedRows);
                    continue;
                }

                $reason = InvalidRowValidations::validateJournalLocale($journal, $data->locale);

                if (!is_null($reason)) {
                    CSVFileHandler::processFailedRow($invalidCsvFile, $fields, $this->_expectedRowSize, $reason, $this->_failedRows);
                    continue;
                }

                // we need a Genre for the files.  Assume a key of SUBMISSION as a default.
                $genreName = 'SUBMISSION';
                $genreId = CachedEntities::getCachedGenreId($genreName, $journal->getId());

                $reason = InvalidRowValidations::validateGenreIdValid($genreId, $genreName);
                if (!is_null($reason)) {
                    CSVFileHandler::processFailedRow($invalidCsvFile, $fields, $this->_expectedRowSize, $reason, $this->_failedRows);
                    continue;
                }

                $userGroupId = CachedEntities::getCachedUserGroupId($data->journalPath, $journal->getId());

                $reason = InvalidRowValidations::validateUserGroupId($userGroupId, $data->journalPath);
                if (!is_null($reason)) {
                    CSVFileHandler::processFailedRow($invalidCsvFile, $fields, $this->_expectedRowSize, $reason, $this->_failedRows);
                    continue;
                }

                $this->_initializeStaticVariables();

                if ($data->coverImageFilename) {
                    $reason = InvalidRowValidations::validateCoverImageIsValid($data->coverImageFilename, $this->_sourceDir);

                    if (!is_null($reason)) {
                        CSVFileHandler::processFailedRow($invalidCsvFile, $fields, $this->_expectedRowSize, $reason, $this->_failedRows);
                        continue;
                    }

                    $sanitizedCoverImageName = str_replace([' ', '_', ':'], '-', mb_strtolower($data->coverImageFilename));
                    $sanitizedCoverImageName = \PKPString::regexp_replace('/[^a-z0-9\.\-]+/', '', $sanitizedCoverImageName);
                    $coverImageUploadName = uniqid() . '-' . basename($sanitizedCoverImageName);

                    $destFilePath = $this->_publicFileManager->getContextFilesPath($journal->getId()) . '/' . $coverImageUploadName;
                    $srcFilePath = "{$this->_sourceDir}/{$data->coverImageFilename}";
                    $bookCoverImageSaved = $this->_fileManager->copyFile($srcFilePath, $destFilePath);

                    if (!$bookCoverImageSaved) {
                        $reason = __('plugin.importexport.csv.erroWhileSavingBookCoverImage');
                        CSVFileHandler::processFailedRow($invalidCsvFile, $fields, $this->_expectedRowSize, $reason, $this->_failedRows);

                        continue;
                    }
                }

				import('plugins.importexport.csv.classes.processors.SubmissionProcessor');
				import('plugins.importexport.csv.classes.processors.PublicationProcessor');

				$existingSubmission = null; /** @var null|\Submission */
                $basePublication = null; /** @var null|\Publication */
                $isMultiLocaleImport = false;

                if (
                    !empty($data->versionIdentifier)
                    && InvalidRowValidations::versionExistsInAnyLocale($data, $this->_processedPublications)
                ) {
                    $version = (int)$data->version;
                    $versionData = $this->_processedPublications[$data->versionIdentifier][$version];

                    $firstLocaleData = reset($versionData);
                    $existingSubmission = $firstLocaleData['submission'];
                    $basePublication = $firstLocaleData['publication'];

                    if (!isset($versionData[$data->locale])) {
                        $isMultiLocaleImport = true;
                    }
                } elseif (!empty($data->versionIdentifier) && isset($this->_processedPublications[$data->versionIdentifier])) {
                    // Handle new version (not multi-locale)
                    $versions = $this->_processedPublications[$data->versionIdentifier];
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

                    $publication = PublicationProcessor::processVersionedPublication($publication, $data, $basePublication, $this->_sourceDir);
                    if (empty($data->galleyFilenames)) {
                        PublicationProcessor::copyGalleysFromBasePublication($publication, $basePublication);
                    }
                } else {
                    // New submission import
                    $submission = SubmissionProcessor::process($journal->getId(), $data);
                    $publication = PublicationProcessor::process($submission, $data, $journal, $this->_sourceDir);
                }

                // Array to store each galley ID to its respective galley file
                $galleyIds = [];
                if ($data->galleyFilenames) {
                    foreach (array_map('trim', explode(';', $data->galleyFilenames)) as $galleyFile) {
                        $galleyFileId = $this->_saveSubmissionFile(
                            $galleyFile,
                            $journal->getId(),
                            $submission->getId(),
                            $invalidCsvFile,
                            __('plugins.importexport.csv.errorWhileSavingSubmissionGalley', ['galley' => $galleyFile]),
                            $fieldsList
                        );

                        if (is_null($galleyFileId)) {
                            foreach($galleyIds as $galleyItem) {
                                $this->_fileService->delete($galleyItem['id']);
                            }

                            continue;
                        }

                        $galleyIds[] = ['file' => $galleyFile, 'id' => $galleyFileId];
                    }

                    // Now, process the submission file for all article galleys
                    $galleyLabelsArray = array_map('trim', explode(';', $data->galleyLabels));
                    $count = min(count($galleyIds), count($galleyLabelsArray));
                    for($i = 0; $i < $count; $i++) {
                        $galleyItem = $galleyIds[$i];
                        $galleyLabel = $galleyLabelsArray[$i];

                        $this->_handleGalley(
                            $galleyItem,
                            $data,
                            $submission->getId(),
                            $genreId,
                            $galleyLabel,
                            $publication->getId()
                        );
                    }
                }

                // Process supplementary files
                if ($data->suppFilenames) {
                    $suppIds = [];

                    foreach (array_map('trim', explode(';', $data->suppFilenames)) as $suppFile) {
                        $suppFileId = $this->_saveSubmissionFile(
                            $suppFile,
                            $journal->getId(),
                            $submission->getId(),
                            $invalidCsvFile,
                            __('plugins.importexport.csv.errorWhileSavingSupplementaryFile', ['file' => $suppFile]),
                            $fieldsList
                        );

                        if (is_null($suppFileId)) {
                            foreach($galleyIds as $galleyItem) {
                                $this->_fileService->delete($galleyItem['id']);
                            }

                            foreach($suppIds as $suppItem) {
                                $this->_fileService->delete($suppItem['id']);
                            }

                            continue;
                        }

                        $suppIds[] = ['file' => $suppFile, 'id' => $suppFileId];
                    }

                    // Get supplementary genre for supplementary files
                    $genreDao = CachedDaos::getGenreDao();
                    $supplementaryGenres = $genreDao->getBySupplementaryAndContextId(true, $journal->getId())->toArray();
                    $suppGenreId = !empty($supplementaryGenres) ? $supplementaryGenres[0]->getId() : $genreId;

                    $suppLabelsArray = array_map('trim', explode(';', $data->suppLabels));
                    for($i = 0; $i < count($suppLabelsArray); $i++) {
                        $suppItem = $suppIds[$i];
                        $suppLabel = $suppLabelsArray[$i];

                        $this->_handleGalley(
                            $suppItem,
                            $data,
                            $submission->getId(),
                            $suppGenreId,
                            $suppLabel,
                            $publication->getId()
                        );
                    }
                }

                import('plugins.importexport.csv.classes.processors.AuthorsProcessor');
				import('plugins.importexport.csv.classes.processors.KeywordsProcessor');
				import('plugins.importexport.csv.classes.processors.SubjectsProcessor');
				if ($isMultiLocaleImport) {
                    // For multi-locale imports, update existing publication with new locale data
                    AuthorsProcessor::processMultiLocale($data, $journal->getContactEmail(), $submission->getId(), $publication, $userGroupId);
                    KeywordsProcessor::processMultiLocale($data, $publication->getId());
                    SubjectsProcessor::processMultiLocale($data, $publication->getId());
                } else {
                    // For new submissions or versions, use the regular process
                    AuthorsProcessor::process($data, $journal->getContactEmail(), $submission->getId(), $publication, $userGroupId, $basePublication);
                    KeywordsProcessor::process($data, $publication->getId(), $basePublication);
                    SubjectsProcessor::process($data, $publication->getId(), $basePublication);
                }

                if ($data->coverage || ($basePublication && !$data->coverage)) {
                    if (!empty($data->coverage)) {
                        PublicationProcessor::updateCoverage($publication, $data->coverage, $data->locale);
                    } elseif ($basePublication && $basePublication->getLocalizedData('coverage', $data->locale)) {
                        PublicationProcessor::updateCoverage($publication, $basePublication->getLocalizedData('coverage', $data->locale), $data->locale);
                    }
                }

                import('plugins.importexport.csv.classes.processors.SectionsProcessor');
                $section = SectionsProcessor::process($data, $journal->getId(), $basePublication);
                PublicationProcessor::updateSectionId($publication, $section->getId());

                import('plugins.importexport.csv.classes.processors.IssueProcessor');
                $issue = IssueProcessor::process($journal->getId(), $data, $basePublication);
				if ($isMultiLocaleImport) {
                    $issue = IssueProcessor::processMultiLocale($issue, $data);
                }

                PublicationProcessor::updateIssueId($publication, $issue->getId());

                if ($data->coverImageFilename) {
                    PublicationProcessor::updateCoverImage($publication, $data, $coverImageUploadName);
                } elseif ($basePublication && $basePublication->getLocalizedData('coverImage', $data->locale)) {
                    PublicationProcessor::updatePublicationAttribute($publication, 'coverImage', $basePublication->getData('coverImage'));
                }

                import('plugins.importexport.csv.classes.processors.CategoriesProcessor');
                if ($data->categories || $basePublication) {
                    if ($isMultiLocaleImport) {
                        CategoriesProcessor::processMultiLocale($data->categories, $data->locale, $journal->getId(), $publication->getId());
                    } elseif ($existingSubmission && $basePublication) {
                        CategoriesProcessor::processForVersion($data->categories, $data->locale, $journal->getId(), $publication->getId(), $basePublication);
                    } else {
                        CategoriesProcessor::process($data->categories, $data->locale, $journal->getId(), $publication->getId());
                    }
                }

				if (!empty($data->versionIdentifier)) {
                    $this->_trackProcessedArticle($data, $submission, $publication);
                }

                $issueKey = $journal->getId() . '_' . $issue->getId();
                if (!isset($this->_processedIssues[$issueKey])) {
                    $this->_processedIssues[$issueKey] = [
                        'issue' => $issue,
                        'journalId' => $journal->getId(),
                        'data' => $data
                    ];
                }
            }

            echo __('plugins.importexpot.csv.fileProcessFinished', [
                'filename' => $fileInfo->getFilename(),
                'processedRows' => $this->_processedRows,
                'failedRows' => $this->_failedRows,
            ]) . "\n";

            if (!$this->_failedRows) {
                unlink($this->_sourceDir . '/' . "invalid_{$basename}");
            }
        }

		$this->_syncCoverImagesForProcessedArticles();
        $this->_setCurrentVersionsForProcessedArticles();

        import('plugins.importexport.csv.classes.processors.IssueProcessor');
        IssueProcessor::reorderImportedIssues($this->_processedIssues);

    }

    /**
     * Insert static data that will be used for the submission processing
     */
    private function _initializeStaticVariables(): void
    {
        $this->_dirNames ??= \Application::getFileDirectories();
        $this->_format ??= trim($this->_dirNames['context'], '/') . '/%d/' . trim($this->_dirNames['submission'], '/') . '/%d';
        $this->_fileManager ??= new \FileManager();
        $this->_publicFileManager ??= new \PublicFileManager();
        $this->_fileService ??= \Services::get('file');
    }

    /**
     * Save a submission file. If an error occurred, the method will delete the submission already saved
     * and return null.
     *
     * @param string $filePath
     * @param int $journalId
     * @param int $submissionId
     * @param \SplFileObject $invalidCsvFile
     * @param string $reason
     * @param array $fieldsList
     *
     * @return int|null
     */
    private function _saveSubmissionFile($filePath, $journalId, $submissionId, &$invalidCsvFile, $reason, $fieldsList)
    {
        try {
            $extension = $this->_fileManager->parseFileExtension($filePath);
            $submissionDir = sprintf($this->_format, $journalId, $submissionId);
            $completePath = "{$this->_sourceDir}/{$filePath}";
            return $this->_fileService->add($completePath, $submissionDir . '/' . uniqid() . '.' . $extension);
        } catch (\Exception $e) {
            CSVFileHandler::processFailedRow($invalidCsvFile, $fieldsList, $this->_expectedRowSize, $reason, $this->_failedRows);

            $submissionDao = CachedDaos::getSubmissionDao();
            $submissionDao->deleteById($submissionId);

            return null;
        }
    }

    /**
     * Process data for primary and supplementary galleys.
     *
     * @param array $item
     * @param object $data
     * @param int $submissionId
     * @param int $genreId
     * @param string $label
     * @param int $publicationId
     *
     * @return void
     */
    private function _handleGalley($item, $data, $submissionId, $genreId, $label, $publicationId)
    {
        $galleyCompletePath = "{$this->_sourceDir}/{$item['file']}";
        $galleyExtension = $this->_fileManager->parseFileExtension($galleyCompletePath);

        import('plugins.importexport.csv.classes.processors.SubmissionFileProcessor');
        $file = SubmissionFileProcessor::process(
            $data->locale,
            $this->_user->getId(),
            $submissionId,
            $galleyCompletePath,
            $genreId,
            $item['id'],
        );

        // Now that we have the submission file ID, it's time to process the galley itself.
        import('plugins.importexport.csv.classes.processors.GalleyProcessor');
        $galleyId = GalleyProcessor::process($file->getId(), $data, $label, $publicationId, $galleyExtension);
        SubmissionFileProcessor::updateAssocInfo($file, $galleyId);
    }

	/**
     * Tracks a processed preprint for version management
	 *
	 * @param object $data - The CSV row
	 * @param \Submission $submission
	 * @param \Publication $publication
	 *
	 * @return void
     */
    private function _trackProcessedArticle($data, $submission, $publication)
    {
        $identifier = $data->versionIdentifier;
        $version = (int)$data->version;
        $locale = $data->locale;

        if (!isset($this->_processedPublications[$identifier])) {
            $this->_processedPublications[$identifier] = [];
        }

        if (!isset($this->_processedPublications[$identifier][$version])) {
            $this->_processedPublications[$identifier][$version] = [];
        }

        $this->_processedPublications[$identifier][$version][$locale] = [
            'data' => $data,
            'submission' => $submission,
            'publication' => $publication
        ];
    }

	private function _syncCoverImagesForProcessedArticles()
    {
        foreach ($this->_processedPublications as $identifier => $versions) {
            foreach ($versions as $versionNumber => $localeData) {
                $firstLocaleData = reset($localeData);
                $publication = $firstLocaleData['publication'];
                $publicationId = $publication->getId();

                $submissionDao = CachedDaos::getSubmissionDao();
                $submission = $submissionDao->getById($publication->getData('submissionId'));
                if (!$submission) {
                    continue;
                }
                $serverId = $submission->getData('contextId');
                $serverDao = CachedDaos::getJournalDao();
                $server = $serverDao->getById($serverId);
                if (!$server) {
                    continue;
                }
                $defaultLocale = $server->getData('primaryLocale');

                $coverImageSettings = Capsule::table('publication_settings')
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

                $allPublicationLocales = Capsule::table('publication_settings')
                    ->where('publication_id', $publicationId)
                    ->whereNotNull('locale')
                    ->where('locale', '!=', '')
                    ->distinct()
                    ->pluck('locale')
                    ->toArray();

                foreach ($allPublicationLocales as $locale) {
                    if (isset($coverImagesByLocale[$locale])) {
                        continue;
                    }

                    $reloadedPublication = CachedDaos::getPublicationDao()->getById($publicationId);
                    if ($reloadedPublication) {
                        PublicationProcessor::updatePublicationAttribute($reloadedPublication, 'coverImage', $sourceCoverImage, $locale);
                    }
                }
            }
        }
    }

	/**
     * Set the highest version as current for each processed preprint identifier
	 *
	 * @return void
     */
    private function _setCurrentVersionsForProcessedArticles()
    {
        foreach ($this->_processedPublications as $identifier => $versions) {
            if (count($versions) <= 1) {
                continue; // Skip if only one version exists
            }

            $highestVersion = 0;
            $currentVersionData = null;

            foreach ($versions as $versionKey => $versionData) {
                $firstLocaleData = reset($versionData);
                if (!$firstLocaleData || !isset($firstLocaleData['data'])) {
                    continue;
                }
                $versionNumber = (int)$firstLocaleData['data']->version;
                if ($versionNumber > $highestVersion) {
                    $highestVersion = $versionNumber;
                    $currentVersionData = $firstLocaleData;
                }
            }

            if ($currentVersionData) {
                $submission = $currentVersionData['submission'];
                $publication = $currentVersionData['publication'];

                SubmissionProcessor::updateCurrentPublicationId($submission, $publication->getId());
            }
        }
    }
}
