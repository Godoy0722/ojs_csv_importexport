<?php

/**
 * @file plugins/importexport/csv/CSVImportExportPlugin.inc.php
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

use APP\facades\Repo;
use APP\plugins\importexport\csv\classes\cachedAttributes\CachedDaos;
use APP\plugins\importexport\csv\classes\commands\IssueCommand;
use APP\plugins\importexport\csv\classes\commands\UserCommand;
use PKP\config\Config;
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
				import('plugins.importexport.csv.classes.commands.IssueCommand');
				(new IssueCommand($this->sourceDir, $this->user))->run();
                break;
            case 'users':
				import('plugins.importexport.csv.classes.commands.UserCommand');
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
}
