<?php

/**
 * @file plugins/importexport/csv/classes/forms/CsvImportForm.inc.php
 *
 * Copyright (c) 2014-2026 Simon Fraser University
 * Copyright (c) 2003-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CsvImportForm
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief CSV import form for the web GUI.
 */

namespace PKP\Plugins\ImportExport\CSV\Classes\Forms;

use PKP\components\forms\FieldOptions;
use PKP\components\forms\FieldSelect;
use PKP\components\forms\FieldUpload;
use PKP\components\forms\FormComponent;

define('FORM_CSV_IMPORT', 'csvImport');

class CsvImportForm extends FormComponent
{
    /** @copydoc FormComponent::$id */
    public $id = FORM_CSV_IMPORT;

    /** @copydoc FormComponent::$method */
    public $method = 'POST';

    /**
     * @param string $action Form submission URL
     * @param string $uploadUrl Temporary file upload API URL
     */
    public function __construct($action, $uploadUrl)
    {
        $this->action = $action;
        $primaryLocale = \AppLocale::getPrimaryLocale();
        $allLocales = \AppLocale::getAllLocales();
        $this->locales = [[
            'key' => $primaryLocale,
            'label' => $allLocales[$primaryLocale] ?? $primaryLocale,
        ]];

        $this
            ->addPage(['id' => 'default', 'submitButton' => ['label' => __('plugins.importexport.csv.form.submitButton')]])
            ->addGroup(['id' => 'default', 'pageId' => 'default'])
            ->addField(new FieldUpload('importFile', [
                'label' => __('plugins.importexport.csv.form.importFile'),
                'description' => __('plugins.importexport.csv.form.importFile.description'),
                'isRequired' => true,
                'groupId' => 'default',
                'options' => [
                    'url' => $uploadUrl,
                    'acceptedFiles' => '.zip,.csv',
                ],
            ]))
            ->addField(new FieldSelect('importType', [
                'label' => __('plugins.importexport.csv.form.importType'),
                'isRequired' => true,
                'groupId' => 'default',
                'options' => [
                    ['value' => 'issues', 'label' => __('plugins.importexport.csv.form.importType.issues')],
                    ['value' => 'users', 'label' => __('plugins.importexport.csv.form.importType.users')],
                ],
                'value' => 'issues',
            ]))
            ->addField(new FieldOptions('dryMode', [
                'label' => __('plugins.importexport.csv.form.dryMode'),
                'description' => __('plugins.importexport.csv.form.dryMode.description'),
                'type' => 'checkbox',
                'groupId' => 'default',
                'options' => [
                    ['value' => true, 'label' => __('plugins.importexport.csv.form.dryMode.enable')],
                ],
                'value' => [],
            ]))
            ->addField(new FieldOptions('sendWelcomeEmail', [
                'label' => __('plugins.importexport.csv.form.sendWelcomeEmail'),
                'type' => 'checkbox',
                'groupId' => 'default',
                'options' => [
                    ['value' => true, 'label' => __('plugins.importexport.csv.form.sendWelcomeEmail.enable')],
                ],
                'value' => [],
                'showWhen' => ['importType', 'users'],
            ]));
    }
}
