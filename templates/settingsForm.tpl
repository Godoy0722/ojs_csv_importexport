{**
 * plugins/importexport/csv/templates/settingsForm.tpl
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * CSV Import form displayed on the plugin's own page.
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
	<h1 class="app__pageHeading">
		{translate key="plugins.importexport.csv.displayName"}
	</h1>

	<div class="app__contentPanel">
		<pkp-form
			v-bind="components.{$smarty.const.FORM_CSV_IMPORT}"
			@set="set"
		/>
	</div>

	<script>window.csvImportPluginConfig = {$csvImportPluginConfig};</script>
{/block}
