<?php

/**
 * @file plugins/importexport/csv/CSVImportExportPlugin.inc.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CSVImportExportPlugin
 * @ingroup plugins_importexport_csv
 *
 * @brief CSV import/export plugin
 */

namespace APP\plugins\importexport\csv;

use APP\facades\Repo;
use APP\plugins\importexport\csv\classes\commands\IssueCommand;
use APP\plugins\importexport\csv\classes\commands\UserCommand;
use APP\template\TemplateManager;
use Exception;
use PKP\config\Config;
use PKP\core\JSONMessage;
use PKP\file\TemporaryFileManager;
use PKP\plugins\Hook;
use PKP\plugins\ImportExportPlugin;
use PKP\user\User;

class CSVImportExportPlugin extends ImportExportPlugin
{
    /** @var string Command being used from CLI (supports "issues" or "users") */
    private string $command = '';

    /** @var string Username for authentication */
    private string $username = '';

    /** @var User|null Authenticated user instance */
    private ?User $user = null;

    /** @var string Source directory for import/export */
    private string $sourceDir = '';

    /** @var bool Whether to send welcome email */
    private bool $sendWelcomeEmail = false;

    /** @copydoc Plugin::register() */
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

            // Register the hook to add website settings tab
            Hook::add('Template::Settings::website', $this->callbackShowWebsiteSettingsTabs(...));
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
     * Extend the website settings tabs to include CSV import/export
     *
     * @param string $hookName The name of the invoked hook
     * @param array $args Hook parameters
     *
     * @return bool Hook handling status
     */
    public function callbackShowWebsiteSettingsTabs($hookName, $args)
    {
        $templateMgr = $args[1];
        $output = &$args[2];

        $output .= $templateMgr->fetch($this->getTemplateResource('csvImportExportTab.tpl'));

        // Permit other plugins to continue interacting with this hook
        return false;
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
				(new IssueCommand($this->sourceDir, $this->user))->run();
                break;
            case 'users':
                (new UserCommand($this->sourceDir, $this->user, $this->sendWelcomeEmail))->run();
                break;
            default:
                throw new \InvalidArgumentException("Comando inválido: {$this->command}");
        }

		$endTime = microtime(true);
		$executionTime = $endTime - $startTime;
		echo "Executed in: " . number_format($executionTime, 2) . " seconds\n";
    }

	private function validateUser()
    {
		$this->user = Repo::user()->getByUsername($this->username);
		if (!$this->user) {
			echo __('plugins.importexport.csv.unknownUser', ['username' => $this->username]) . "\n";
			exit(1);
		}
	}

    /**
     * @copydoc ImportExportPlugin::display()
     */
    public function display($args, $request)
    {
        parent::display($args, $request);

        $templateMgr = TemplateManager::getManager($request);
        $context = $request->getContext();

        switch (array_shift($args)) {
            case 'index':
            case '':
                // This will be handled by the tab in website settings
                break;
            case 'uploadImportCSV':
                return $this->uploadImportCSV($request);
            case 'importBounce':
                return $this->importBounce($args, $request);
            case 'downloadExample':
                return $this->downloadExample($args, $request);
            default:
                break;
        }
    }

    /**
     * Handle file upload for CSV import
     */
    public function uploadImportCSV($request)
    {
        $user = $request->getUser();
        $temporaryFileManager = new TemporaryFileManager();
        $temporaryFile = $temporaryFileManager->handleUpload('uploadedFile', $user->getId());

        if ($temporaryFile) {
            // Validate that it's a CSV file
            $fileName = $temporaryFile->getOriginalFileName();
            $extension = pathinfo($fileName, PATHINFO_EXTENSION);

            if (strtolower($extension) !== 'csv') {
                $json = new JSONMessage(false, __('plugins.importexport.csv.invalidFileType'));
            } else {
                $json = new JSONMessage(true);
                $json->setAdditionalAttributes([
                    'temporaryFileId' => $temporaryFile->getId()
                ]);
            }
        } else {
            $json = new JSONMessage(false, __('common.uploadFailed'));
        }

        header('Content-Type: application/json');
        return $json->getString();
    }

    /**
     * Handle CSV import form submission
     */
    public function importBounce($args, $request)
    {
        $context = $request->getContext();
        $user = $request->getUser();

        $importType = $request->getUserVar('importType');
        $temporaryFileId = $request->getUserVar('temporaryFileId');

        // Validate required fields
        if (!$importType || !in_array($importType, ['users', 'issues'])) {
            return new JSONMessage(false, __('plugins.importexport.csv.importTypeRequired'));
        }

        if (!$temporaryFileId) {
            return new JSONMessage(false, __('plugins.importexport.csv.fileRequired'));
        }

        // Get the temporary file
        $temporaryFileManager = new TemporaryFileManager();
        $temporaryFile = $temporaryFileManager->getFile($temporaryFileId, $user->getId());

        if (!$temporaryFile) {
            return new JSONMessage(false, __('plugins.importexport.csv.invalidFile'));
        }

        try {
            // Create a temporary directory for processing
            $tempDir = sys_get_temp_dir() . '/csv_import_' . uniqid();
            mkdir($tempDir);

            // Copy the uploaded file to temp directory
            $csvFilePath = $tempDir . '/' . $temporaryFile->getOriginalFileName();
            copy($temporaryFile->getFilePath(), $csvFilePath);

            // Execute the import command
            switch ($importType) {
                case 'issues':
                    $command = new IssueCommand($tempDir, $user);
                    break;
                case 'users':
                    $sendWelcomeEmail = (bool) $request->getUserVar('sendWelcomeEmail');
                    $command = new UserCommand($tempDir, $user, $sendWelcomeEmail);
                    break;
            }

            $command->run();

            // Clean up temporary files
            unlink($csvFilePath);
            rmdir($tempDir);

            return new JSONMessage(true, __('plugins.importexport.csv.importSuccess'));

        } catch (Exception $e) {
            // Clean up on error
            if (file_exists($csvFilePath)) {
                unlink($csvFilePath);
            }
            if (is_dir($tempDir)) {
                rmdir($tempDir);
            }

            return new JSONMessage(false, __('plugins.importexport.csv.importError', ['error' => $e->getMessage()]));
        }
    }

    /**
     * Handle example file downloads
     */
    public function downloadExample($args, $request)
    {
        $exampleType = array_shift($args);

        if (!in_array($exampleType, ['users', 'issues'])) {
            return new JSONMessage(false, __('plugins.importexport.csv.invalidExampleType'));
        }

        $fileName = $exampleType . '_example.csv';
        $filePath = $this->getPluginPath() . '/examples/' . $exampleType . '/' . $fileName;

        if (!file_exists($filePath)) {
            return new JSONMessage(false, __('plugins.importexport.csv.exampleFileNotFound'));
        }

        // Clear any previous output
        if (ob_get_level()) {
            ob_end_clean();
        }

        // Set headers for file download
        header('Content-Type: application/octet-stream');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Expires: 0');

        // Prevent any additional output
        header('Connection: close');

        // Output file contents and force download
        readfile($filePath);
        die();
    }
}
