<?php

/**
 * @file plugins/importexport/csv/CSVImportExportPlugin.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2003-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CSVImportExportPlugin
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief CSV import/export plugin
 */

namespace APP\plugins\importexport\csv;

use APP\core\Application;
use APP\facades\Repo;
use APP\plugins\importexport\csv\classes\commands\IssueCommand;
use APP\plugins\importexport\csv\classes\commands\UserCommand;
use APP\plugins\importexport\csv\classes\exceptions\ZipExtractionException;
use APP\plugins\importexport\csv\classes\forms\CsvImportForm;
use APP\plugins\importexport\csv\classes\handlers\ZipExtractor;
use APP\plugins\importexport\csv\classes\store\ImportResultStore;
use APP\template\TemplateManager;
use PKP\config\Config;
use PKP\core\PKPApplication;
use PKP\core\PKPRequest;
use PKP\file\TemporaryFileManager;
use PKP\plugins\ImportExportPlugin;
use PKP\user\User;

class CSVImportExportPlugin extends ImportExportPlugin
{

    /** Which command is the tool using from CLI. Currently supports "issues" or "users" */
    private string $command;

    private string $username;

    private User $user;

    private string $sourceDir;

    private bool $sendWelcomeEmail = false;

    /**
     * @copydoc Plugin::register()
     *
     * @param null|mixed $mainContextId
     */
    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);
		$isInstalled = !!Config::getVar('general', 'installed');
		$isUpgrading = defined('RUNNING_UPGRADE');

        if (!$isInstalled || $isUpgrading) {
            return $success;
        }

        if ($success && $this->getEnabled()) {
            $this->addLocaleData();
        }

        return $success;
    }

    /**
     * @copydoc Plugin::getDisplayName()
     */
    public function getDisplayName()
    {
        return __('plugins.importexport.csv.displayName');
    }

    /**
     * @copydoc Plugin::getDescription()
     */
    public function getDescription()
    {
        return __('plugins.importexport.csv.description');
    }

    /**
     * @copydoc Plugin::getName()
     */
    public function getName()
    {
        return 'CSVImportExportPlugin';
    }

    /**
     * @copydoc ImportExportPlugin::display()
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function display($args, $request)
    {
        parent::display($args, $request);

        $op = array_shift($args);
        if ($op === null) {
            $op = '';
        }

        switch ($op) {
            case 'index':
            case '':
                return $this->displayImportForm($request);
            case 'import':
                return $this->handleImport($request);
            case 'downloadInvalidCsv':
                return $this->handleDownloadInvalidCsv($request);
            case 'cleanup':
                return $this->handleCleanup($request);
            default:
                fatalError('Invalid operation.');
        }
    }

    /**
     * @param PKPRequest $request
     */
    private function displayImportForm(PKPRequest $request): void
    {
        $templateMgr = TemplateManager::getManager($request);
        $context = $request->getContext();
        if (!$context) {
            fatalError('Context required.');
        }

        $form = new CsvImportForm(
            $request->getDispatcher()->url(
                $request,
                PKPApplication::ROUTE_PAGE,
                null,
                'management',
                'importexport',
                ['plugin', $this->getName(), 'import']
            ),
            $request->getDispatcher()->url($request, Application::ROUTE_API, $context->getPath(), 'temporaryFiles')
        );

        $templateMgr->setState([
            'components' => [
                'csvImport' => $form->getConfig(),
            ],
        ]);

        $templateMgr->assign('pageComponent', 'ImportExportPage');

        $downloadBaseUrl = $request->getDispatcher()->url(
            $request,
            PKPApplication::ROUTE_PAGE,
            null,
            'management',
            'importexport',
            ['plugin', $this->getName(), 'downloadInvalidCsv']
        );
        $cleanupUrl = $request->getDispatcher()->url(
            $request,
            PKPApplication::ROUTE_PAGE,
            null,
            'management',
            'importexport',
            ['plugin', $this->getName(), 'cleanup']
        );

        $scriptUrl = $request->getBaseUrl() . '/' . $this->getPluginPath() . '/scripts/csvImportResults.js';
        $templateMgr->addJavaScript('csvImportResults', $scriptUrl, [
            'contexts' => ['backend'],
            'priority' => TemplateManager::STYLE_SEQUENCE_LAST,
        ]);

        $templateMgr->assign('csvImportPluginConfig', json_encode([
            'formId' => FORM_CSV_IMPORT,
            'downloadBaseUrl' => $downloadBaseUrl,
            'cleanupUrl' => $cleanupUrl,
            'labels' => [
                'dryModeTitle' => __('plugins.importexport.csv.results.dryModeTitle'),
                'importCompleteTitle' => __('plugins.importexport.csv.results.importCompleteTitle'),
                'importType' => __('plugins.importexport.csv.results.importType'),
                'filesProcessed' => __('plugins.importexport.csv.results.filesProcessed'),
                'totalRows' => __('plugins.importexport.csv.results.totalRows'),
                'successfulRows' => __('plugins.importexport.csv.results.successfulRows'),
                'createdRows' => __('plugins.importexport.csv.results.createdRows'),
                'updatedRows' => __('plugins.importexport.csv.results.updatedRows'),
                'failedRows' => __('plugins.importexport.csv.results.failedRows'),
                'updatedUsersSection' => __('plugins.importexport.csv.results.updatedUsersSection'),
                'updatedUsersExplanation' => __('plugins.importexport.csv.results.updatedUsersExplanation'),
                'invalidFiles' => __('plugins.importexport.csv.results.invalidFiles'),
                'introIssues' => __('plugins.importexport.csv.results.intro.issues'),
                'introIssuesDryMode' => __('plugins.importexport.csv.results.intro.issues.dryMode'),
                'introUsers' => __('plugins.importexport.csv.results.intro.users'),
                'introUsersDryMode' => __('plugins.importexport.csv.results.intro.users.dryMode'),
                'legend' => __('plugins.importexport.csv.results.legend'),
                'invalidFilesHint' => __('plugins.importexport.csv.results.invalidFilesHint'),
                'importing' => __('plugins.importexport.csv.form.importing'),
                'imported' => __('plugins.importexport.csv.form.imported'),
            ],
        ]));

        $templateMgr->display($this->getTemplateResource('settingsForm.tpl'));
    }

    /**
     * @param array $data
     * @param int $statusCode
     */
    private function sendJsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
    }

    /**
     * @param PKPRequest $request
     */
    private function handleImport(PKPRequest $request): void
    {
        $user = $request->getUser();
        $importType = $request->getUserVar('importType');
        $dryMode = $this->getCheckboxValue($request, 'dryMode');
        $sendWelcomeEmail = $this->getCheckboxValue($request, 'sendWelcomeEmail');

        if (!in_array($importType, ['issues', 'users'])) {
            $this->sendJsonResponse(['errorMessage' => __('plugins.importexport.csv.invalidImportType', ['importType' => $importType])], 400);
            return;
        }

        $importFile = $request->getUserVar('importFile');
        $temporaryFileId = is_array($importFile) ? ($importFile['temporaryFileId'] ?? null) : $request->getUserVar('temporaryFileId');

        $temporaryFileManager = new TemporaryFileManager();
        $temporaryFile = $temporaryFileManager->getFile($temporaryFileId, $user->getId());

        if (!$temporaryFile) {
            $this->sendJsonResponse(['errorMessage' => __('plugins.importexport.csv.uploadFailed')], 400);
            return;
        }

        try {
            set_time_limit(1200);

            $filePath = $temporaryFile->getFilePath();
            $extension = strtolower(pathinfo($temporaryFile->getOriginalFileName(), PATHINFO_EXTENSION));

            if ($extension === 'zip') {
                $extractor = new ZipExtractor();
                $extractDir = $extractor->extract($filePath);
                $sourceDir = ZipExtractor::resolveSourceDir($extractDir);
            } else {
                $sourceDir = sys_get_temp_dir() . '/csv_import_' . bin2hex(random_bytes(16));
                mkdir($sourceDir, 0700, true);
                copy($filePath, $sourceDir . '/' . $temporaryFile->getOriginalFileName());
            }

            ob_start();
            $context = $request->getContext();
            $currentJournalPath = $context ? $context->getPath() : null;

            if ($importType === 'issues') {
                $result = (new IssueCommand($sourceDir, $user, $dryMode, $currentJournalPath))->run();
            } else {
                $result = (new UserCommand($sourceDir, $user, $sendWelcomeEmail, $dryMode, $currentJournalPath))->run();
            }
            ob_get_clean();

            $uuid = bin2hex(random_bytes(16));
            $storeDir = sys_get_temp_dir() . '/csv_import_results';
            $store = new ImportResultStore($storeDir);

            $invalidFiles = [];
            foreach ($result['perFile'] as $fileResult) {
                if (
                    !empty($fileResult['invalidFile'])
                    && is_file($sourceDir . '/' . $fileResult['invalidFile'])
                ) {
                    $invalidFiles[] = $fileResult['invalidFile'];
                }
            }

            $store->save($uuid, [
                'status' => $result['failedRows'] > 0 ? 'partial' : 'success',
                'importType' => $importType,
                'rowsProcessed' => $result['totalRows'],
                'rowsFailed' => $result['failedRows'],
                'perFileResults' => $invalidFiles,
                'sourceDir' => $sourceDir,
            ]);

            $this->sendJsonResponse([
                'uuid' => $uuid,
                'resultImportType' => $importType,
                'resultDryMode' => $dryMode,
                'resultFilesProcessed' => $result['filesProcessed'],
                'resultTotalRows' => $result['totalRows'],
                'resultSuccessfulRows' => $result['successfulRows'],
                'resultCreatedRows' => $result['createdRows'] ?? 0,
                'resultUpdatedRows' => $result['updatedRows'] ?? 0,
                'resultFailedRows' => $result['failedRows'],
                'resultInvalidFiles' => $invalidFiles,
                'resultPerFile' => $result['perFile'],
            ]);
        } catch (ZipExtractionException $e) {
            $this->sendJsonResponse(['errorMessage' => __('plugins.importexport.csv.zipExtractionFailed', ['reason' => $e->getMessage()])], 400);
        }
    }

    /**
     * @param PKPRequest $request
     */
    private function handleDownloadInvalidCsv(PKPRequest $request): void
    {
        $uuid = $request->getUserVar('uuid');
        $filename = basename($request->getUserVar('filename') ?: '');

        if (empty($filename) || empty($uuid)) {
            $request->getDispatcher()->handle404();
            return;
        }

        $store = new ImportResultStore(sys_get_temp_dir() . '/csv_import_results');
        $result = $store->get($uuid);

        if ($result === null) {
            $request->getDispatcher()->handle404();
            return;
        }

        $invalidFiles = $result['perFileResults'] ?? [];
        if (!in_array($filename, $invalidFiles)) {
            $request->getDispatcher()->handle404();
            return;
        }

        $sourceDir = $result['sourceDir'] ?? '';
        $sourceDirReal = $sourceDir ? realpath($sourceDir) : false;
        $tempRealPath = realpath(sys_get_temp_dir());

        if (!$sourceDirReal || !$tempRealPath || !str_starts_with($sourceDirReal, $tempRealPath . DIRECTORY_SEPARATOR)) {
            $request->getDispatcher()->handle404();
            return;
        }

        $filePath = $sourceDirReal . DIRECTORY_SEPARATOR . $filename;
        if (!is_file($filePath)) {
            $request->getDispatcher()->handle404();
            return;
        }

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        readfile($filePath);
    }

    /**
     * @param PKPRequest $request
     */
    private function handleCleanup(PKPRequest $request): void
    {
        $uuid = $request->getUserVar('uuid');
        if (empty($uuid)) {
            $this->sendJsonResponse(['cleaned' => false], 400);
            return;
        }

        $store = new ImportResultStore(sys_get_temp_dir() . '/csv_import_results');
        $result = $store->get($uuid);

        if ($result !== null) {
            $sourceDir = $result['sourceDir'] ?? '';
            $tempRealPath = realpath(sys_get_temp_dir());
            $sourceRealPath = $sourceDir ? realpath($sourceDir) : false;
            if ($sourceRealPath && $tempRealPath && str_starts_with($sourceRealPath, $tempRealPath . DIRECTORY_SEPARATOR)) {
                ZipExtractor::deleteDirectory($sourceDir);
            }

            $store->delete($uuid);
        }

        $this->sendJsonResponse(['cleaned' => true]);
    }

    /**
     * @param PKPRequest $request
     * @param string $name
     */
    private function getCheckboxValue(PKPRequest $request, string $name): bool
    {
        $value = $request->getUserVar($name);
        if (is_array($value)) {
            return !empty($value);
        }
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @copydoc PKPImportExportPlugin::usage
     */
    public function usage($scriptName)
    {
        echo __('plugins.importexport.csv.cliUsage', [
            'scriptName' => $scriptName,
            'pluginName' => $this->getName()
        ]) . "\n\n";
        echo __('plugins.importexport.csv.cliUsage.examples', [
            'scriptName' => $scriptName,
            'pluginName' => $this->getName()
        ]) . "\n\n";
    }

    /**
     * @see PKPImportExportPlugin::executeCLI()
     */
    public function executeCLI($scriptName, &$args)
    {
        $startTime = microtime(true);

        $dryModeKey = array_search('--dry-mode', $args);
        $dryMode = $dryModeKey !== false;
        if ($dryMode) {
            unset($args[$dryModeKey]);
            $args = array_values($args);
        }

        $this->command = array_shift($args);
		$this->username = array_shift($args);
        $this->sourceDir = array_shift($args);
        $this->sendWelcomeEmail = array_shift($args) ?? false;

        if (! in_array($this->command, ['issues', 'users']) || !$this->sourceDir || !$this->username) {
			$this->usage($scriptName);
			exit(1);
		}

        if (! is_dir($this->sourceDir)) {
            echo __('plugins.importexport.csv.unknownSourceDir', ['sourceDir' => $this->sourceDir]) . "\n";
            exit(1);
        }

		$this->validateUser();

        switch ($this->command) {
            case 'issues':
				$result = (new IssueCommand($this->sourceDir, $this->user, $dryMode))->run();
                break;
            case 'users':
                $result = (new UserCommand($this->sourceDir, $this->user, $this->sendWelcomeEmail, $dryMode))->run();
                break;
            default:
                throw new \InvalidArgumentException("Comando inválido: {$this->command}");
        }

        $exitCode = $result['exitCode'] ?? ($result['failedRows'] > 0 ? 1 : 0);

		$endTime = microtime(true);
		$executionTime = $endTime - $startTime;
		echo "Executed in: " . number_format($executionTime, 2) . " seconds\n";

        exit($exitCode);
    }

	private function validateUser()
    {
		$this->user = Repo::user()->getByUsername($this->username);
		if (!$this->user) {
			echo __('plugins.importexport.csv.unknownUser', ['username' => $this->username]) . "\n";
			exit(1);
		}
	}
}
