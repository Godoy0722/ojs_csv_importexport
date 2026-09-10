{**
 * plugins/importexport/csv/templates/settingsForm.tpl
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2003-2025 John Willinsky
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
		<div style="background: #f3f6f9; border-left: 4px solid #006798; padding: 1rem 1.25rem; margin-left: 32px; margin-right: 32px; margin-bottom: 0.75rem; border-radius: 2px; font-size: 0.875rem; line-height: 1.6; color: #333;">
			<h2 style="margin: 0 0 0.5rem; font-size: 1rem;">{translate key="plugins.importexport.csv.form.importType.issues"}</h2>
			<p style="margin: 0 0 0.5rem;">{translate key="plugins.importexport.csv.form.zipHelp"}</p>
			<p style="margin: 0;">{translate key="plugins.importexport.csv.form.examplesHelp"}</p>
		</div>
		<div style="background: #f3f6f9; border-left: 4px solid #006798; padding: 1rem 1.25rem; margin-left: 32px; margin-right: 32px; margin-bottom: 1.5rem; border-radius: 2px; font-size: 0.875rem; line-height: 1.6; color: #333;">
			<h2 style="margin: 0 0 0.5rem; font-size: 1rem;">{translate key="plugins.importexport.csv.form.importType.users"}</h2>
			<p style="margin: 0 0 0.5rem;">{translate key="plugins.importexport.csv.form.userImportHelp"}</p>
			<p style="margin: 0;">{translate key="plugins.importexport.csv.form.usersExamplesHelp"}</p>
		</div>

		<pkp-form
			v-bind="components.csvImport"
			@set="set"
		/>
	</div>

	<script>window.csvImportPluginConfig = {$csvImportPluginConfig};</script>
{/block}
