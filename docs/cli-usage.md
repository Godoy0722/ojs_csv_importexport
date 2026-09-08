# CLI Usage

[README](../README.md) | [Next: CSV Format →](csv-format.md)

## Importing Users

```bash
php tools/importExport.php CSVImportExportPlugin users [username] [pathToFolderWithCsvFiles] [sendWelcomeEmail]
```

Parameters:
- `username`: The username of a valid journal manager. Used to validate that the command is run by an authorized user.
- `pathToFolderWithCsvFiles`: Path to the folder containing user CSV files. Can be absolute or relative to the OJS root directory.
- `sendWelcomeEmail`: (Optional) Set to `true` to send welcome emails to newly imported users. The sender is the user identified by `username`.

Example:

```bash
php tools/importExport.php CSVImportExportPlugin users ggodoy /path/to/folder_with_csv_user_files true
```

## Importing Issues

```bash
php tools/importExport.php CSVImportExportPlugin issues [username] [pathToFolderWithCsvFiles]
```

Parameters:
- `username`: The username of a valid journal manager. Used to validate that the command is run by an authorized user.
- `pathToFolderWithCsvFiles`: Path to the folder containing issue CSV files. Can be absolute or relative to the OJS root directory.

Example:

```bash
php tools/importExport.php CSVImportExportPlugin issues ggodoy /path/to/folder_with_csv_issue_files
```

## Validating Without Importing

Both commands accept a `--dry-mode` flag that runs the whole pipeline and reports what would happen, without persisting anything:

```bash
php tools/importExport.php CSVImportExportPlugin --dry-mode issues ggodoy /path/to/folder_with_csv_issue_files
```

See [Dry Mode](dry-mode.md) for the report format and exit codes.

> **Important Notes**
>
> - The CLI user is assigned as the uploader of submission files unless a row provides a `username` column with an existing OJS user.
> - The last CLI argument must be the path to the folder containing CSV files, not a single CSV file.
> - All CSV files in the folder are processed except files whose names start with `invalid_`.
> - Referenced asset files (PDFs, images, etc.) must be readable by the user running the CLI script.
> - Run the command from the OJS installation directory.

[README](../README.md) | [Next: CSV Format →](csv-format.md)
