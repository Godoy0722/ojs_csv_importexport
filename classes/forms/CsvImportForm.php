<?php

/**
 * @file plugins/importexport/csv/classes/forms/CsvImportForm.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CsvImportForm
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief OJS-specific CSV import form with Issues and Users import types.
 */

namespace APP\plugins\importexport\csv\classes\forms;

use APP\plugins\importexport\csv\shared\forms\CsvImportForm as SharedCsvImportForm;

class CsvImportForm extends SharedCsvImportForm
{
    public function __construct(string $action, string $uploadUrl)
    {
        parent::__construct($action, $uploadUrl, [
            ['value' => 'issues', 'label' => __('plugins.importexport.csv.form.importType.issues')],
            ['value' => 'users', 'label' => __('plugins.importexport.csv.form.importType.users')],
        ], 'issues');
    }
}
