{**
 * templates/csvImportExportTab.tpl
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * CSV Import/Export plugin -- displays the CSV import/export settings tab.
 *}
<tab id="csvImportExport" label="{translate key="plugins.importexport.csv.displayName"}">
	<div id="csvImportExportContainer">
		<h2>{translate key="plugins.importexport.csv.tab.title"}</h2>
		<p class="description">{translate key="plugins.importexport.csv.instructions"}</p>

		{* Example Files Download Section *}
		<div class="example-files-section">
			<h3>{translate key="plugins.importexport.csv.exampleFiles"}</h3>
			<p class="example-description">{translate key="plugins.importexport.csv.exampleFiles.description"}</p>
			<div class="download-buttons">
				<button type="button"
						class="pkp_button download-btn"
						data-download-url="{url router=PKP\core\PKPApplication::ROUTE_PAGE page="management" op="importexport" path=["plugin", "csv", "downloadExample", "users"]}"
						data-filename="users_example.csv">
					<span class="download-text">{translate key="plugins.importexport.csv.downloadUsersExample"}</span>
					<span class="download-loading" style="display: none;">{translate key="common.loading"}</span>
				</button>
				<button type="button"
						class="pkp_button download-btn"
						data-download-url="{url router=PKP\core\PKPApplication::ROUTE_PAGE page="management" op="importexport" path=["plugin", "csv", "downloadExample", "issues"]}"
						data-filename="issues_example.csv">
					<span class="download-text">{translate key="plugins.importexport.csv.downloadIssuesExample"}</span>
					<span class="download-loading" style="display: none;">{translate key="common.loading"}</span>
				</button>
			</div>
		</div>

		<script type="text/javascript">
			$(function() {ldelim}
				// Attach the form handler
				$('#csvImportForm').pkpHandler('$.pkp.controllers.form.FileUploadFormHandler',
					{ldelim}
						$uploader: $('#csvUploader'),
						uploaderOptions: {ldelim}
							uploadUrl: {url|json_encode router=PKP\core\PKPApplication::ROUTE_PAGE page="management" op="importexport" path="plugin"|to_array:"csv":"uploadImportCSV" escape=false},
							baseUrl: {$baseUrl|json_encode},
							filters: [
								{ldelim}
									title: 'CSV files',
									extensions: 'csv'
								{rdelim}
							]
						{rdelim}
					{rdelim}
				);

				// Handle download buttons
				$('.download-btn').click(function() {ldelim}
					var $btn = $(this);
					var downloadUrl = $btn.data('download-url');
					var filename = $btn.data('filename');

					// Show loading state
					$btn.prop('disabled', true);
					$btn.find('.download-text').hide();
					$btn.find('.download-loading').show();

					// Create invisible link for download
					var $link = $('<a>').attr({ldelim}
						href: downloadUrl,
						download: filename
					{rdelim}).appendTo('body');

					// Trigger download
					$link[0].click();

					// Clean up and reset button after delay
					setTimeout(function() {ldelim}
						$link.remove();
						$btn.prop('disabled', false);
						$btn.find('.download-text').show();
						$btn.find('.download-loading').hide();
					{rdelim}, 1500);
				{rdelim});

				// Handle import type change for additional options
				$('input[name="importType"]').change(function() {ldelim}
					var selectedType = $(this).val();
					if (selectedType === 'users') {ldelim}
						$('#userOptionsContainer').show();
					{rdelim} else {ldelim}
						$('#userOptionsContainer').hide();
					{rdelim}

					// Clear any previous error messages when user makes a selection
					$('#csvImportNotification .pkp_notification_content').empty();
					$('#csvImportNotification').hide();
				{rdelim});

				// Handle file upload completion
				$('#csvUploader').on('fileUploaded', function(event, data) {ldelim}
					if (data.temporaryFileId) {ldelim}
						$('#temporaryFileId').val(data.temporaryFileId);
						// Clear any previous error messages when file is uploaded
						$('#csvImportNotification .pkp_notification_content').empty();
						$('#csvImportNotification').hide();
					{rdelim}
				{rdelim});

				// Handle form submission
				$('#csvImportForm').submit(function(e) {ldelim}
					e.preventDefault();

					var importType = $('input[name="importType"]:checked').val();
					var temporaryFileId = $('#temporaryFileId').val();
					var hasErrors = false;
					var errorMessages = [];

					// Validate required fields
					if (!importType) {ldelim}
						errorMessages.push('{translate key="plugins.importexport.csv.importTypeRequired"|escape:"javascript"}');
						hasErrors = true;
					{rdelim}

					if (!temporaryFileId) {ldelim}
						errorMessages.push('{translate key="plugins.importexport.csv.fileRequired"|escape:"javascript"}');
						hasErrors = true;
					{rdelim}

					if (hasErrors) {ldelim}
						showNotification('error', errorMessages.join('<br>'));
						return false;
					{rdelim}

					// Show loading state
					$('#executeImportBtn').prop('disabled', true).text('{translate key="common.processing"|escape:"javascript"}');

					// Submit the form via AJAX
					$.ajax({ldelim}
						url: {url|json_encode router=PKP\core\PKPApplication::ROUTE_PAGE page="management" op="importexport" path="plugin"|to_array:"csv":"importBounce" escape=false},
						type: 'POST',
						data: $(this).serialize(),
						dataType: 'json',
						success: function(response) {ldelim}
							if (response.status === true) {ldelim}
								showNotification('success', response.content);
								// Reset form
								$('#csvImportForm')[0].reset();
								$('#temporaryFileId').val('');
								$('#userOptionsContainer').hide();
								$('#csvUploader').trigger('reset');
							{rdelim} else {ldelim}
								showNotification('error', response.content);
							{rdelim}
						{rdelim},
						error: function() {ldelim}
							showNotification('error', '{translate key="common.error"|escape:"javascript"}');
						{rdelim},
						complete: function() {ldelim}
							$('#executeImportBtn').prop('disabled', false).text('{translate key="plugins.importexport.csv.executeImport"|escape:"javascript"}');
						{rdelim}
					{rdelim});

					return false;
				{rdelim});

				// Function to show notifications
				function showNotification(type, message) {ldelim}
					var notificationClass = type === 'success' ? 'notifySuccess' : 'notifyError';
					var $notification = $('#csvImportNotification');
					var $content = $notification.find('.pkp_notification_content');

					$content.html('<div class="' + notificationClass + '">' + message + '</div>');
					$notification.show();

					// Auto-hide success messages after 5 seconds
					if (type === 'success') {ldelim}
						setTimeout(function() {ldelim}
							$notification.fadeOut();
						{rdelim}, 5000);
					{rdelim}
				{rdelim}
			{rdelim});
		</script>

		<style type="text/css">
			.example-files-section {
				background: #f8f9fa;
				border: 1px solid #e9ecef;
				border-radius: 8px;
				padding: 1.5rem;
				margin: 1.5rem 0 2rem 0;
			}

			.example-files-section h3 {
				margin: 0 0 0.5rem 0;
				color: #2d3748;
				font-size: 1.125rem;
				font-weight: 600;
				display: flex;
				align-items: center;
				gap: 0.5rem;
			}

			.example-files-section h3:before {
				content: "📁";
				font-size: 1rem;
			}

			.example-description {
				margin: 0 0 1rem 0;
				color: #4a5568;
				font-size: 0.9rem;
				line-height: 1.4;
			}

			.download-buttons {
				display: flex;
				gap: 1rem;
				flex-wrap: wrap;
			}

			.download-btn {
				position: relative;
				margin-right: 0.5rem;
			}

			.download-btn:disabled {
				opacity: 0.6;
				cursor: not-allowed;
			}

			.download-loading {
				position: absolute;
				left: 50%;
				top: 50%;
				transform: translate(-50%, -50%);
				white-space: nowrap;
			}

			@media (max-width: 640px) {
				.download-buttons {
					flex-direction: column;
				}

				.download-btn {
					justify-content: center;
					text-align: center;
				}
			}
		</style>

		{* Notification area for messages *}
		<div id="csvImportNotification" class="pkp_notification" style="display: none;">
			<div class="pkp_notification_content"></div>
		</div>

		<form id="csvImportForm" class="pkp_form" method="post">
			{csrf}

			{* Hidden field for temporary file ID *}
			<input type="hidden" name="temporaryFileId" id="temporaryFileId" value="" />

			{* Import Type Selection *}
			<div class="section">
				<h3>{translate key="plugins.importexport.csv.importType"}</h3>
				<div class="fields">
					<fieldset class="pkp_formfield">
						<legend class="label">
							{translate key="plugins.importexport.csv.selectImportType"}
							<span class="required" aria-hidden="true">*</span>
						</legend>
						<div class="radio-group">
							<label class="radio-option">
								<input type="radio" name="importType" id="importType_users" value="users" class="field radio" required>
								<span class="radio-label">{translate key="plugins.importexport.csv.importType.users"}</span>
							</label>
							<label class="radio-option">
								<input type="radio" name="importType" id="importType_issues" value="issues" class="field radio" required>
								<span class="radio-label">{translate key="plugins.importexport.csv.importType.issues"}</span>
							</label>
						</div>
					</fieldset>
				</div>
			</div>

			{* Additional options for user import *}
			<div id="userOptionsContainer" class="section" style="display: none;">
				<div class="fields">
					<div class="pkp_formfield">
						<label class="label">
							<input type="checkbox" name="sendWelcomeEmail" id="sendWelcomeEmail" value="1" class="field checkbox">
							{translate key="plugins.importexport.csv.sendWelcomeEmail"}
						</label>
					</div>
				</div>
			</div>

			{* File Upload Section *}
			<div class="section">
				<h4>{translate key="plugins.importexport.csv.selectFile"}</h4>
				<div class="fields">
					<div class="pkp_formfield">
						<label class="label" for="csvUploader">
							{translate key="plugins.importexport.csv.uploadCSV"}
							<span class="required" aria-hidden="true">*</span>
						</label>
						<div class="field">
							{include file="controllers/fileUploadContainer.tpl"
								id="csvUploader"
								stringDragFile="plugins.importexport.csv.dragCSVFile"
								stringAddFile="plugins.importexport.csv.addCSVFile"
								stringChangeFile="plugins.importexport.csv.changeCSVFile"}
						</div>
					</div>
				</div>
			</div>

			{* Submit Button *}
			<div class="section">
				<div class="fields">
					<button type="submit" id="executeImportBtn" class="pkp_button pkp_button_primary">
						{translate key="plugins.importexport.csv.executeImport"}
					</button>
				</div>
			</div>

			<p class="description">
				<span class="required">*</span> {translate key="common.requiredField"}
			</p>
		</form>

		{* Help Section *}
		<div class="section">
			<details>
				<summary><strong>{translate key="help.help"}</strong></summary>
				<div class="description">
					<p>{translate key="plugins.importexport.csv.tab.description"}</p>

					<div style="background: #fff3cd; border: 1px solid #f6c23e; border-radius: 6px; padding: 1rem; margin: 1rem 0;">
						<h4 style="margin: 0 0 0.5rem 0; color: #856404; font-weight: 600;">⚠️ {translate key="plugins.importexport.csv.help.structure.title"}</h4>
						<p style="margin: 0; color: #856404;">{translate key="plugins.importexport.csv.help.structure"}</p>
					</div>

					<p><strong>{translate key="plugins.importexport.csv.importType.users"}:</strong> {translate key="plugins.importexport.csv.help.users"}</p>
					<p><strong>{translate key="plugins.importexport.csv.importType.issues"}:</strong> {translate key="plugins.importexport.csv.help.issues"}</p>
				</div>
			</details>
		</div>
	</div>
</tab>
