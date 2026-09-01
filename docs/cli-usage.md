# CLI Usage

[← Prev: Web Interface Usage](web-interface.md) | [README](../README.md) | [Next: CSV Format →](csv-format.md)

## Importing Users

To import users from a CSV file, use the following command:

```bash
php tools/importExport.php CSVImportExportPlugin [--sendWelcomeEmail] [--dry-mode] users [username] [pathToFolderWithCsvFiles] [sendWelcomeEmail]
```

Parameters:
- `username`: The username of a valid Journal manager. This username is used to validate that the command is running by a valid user as a security step.
- `pathToFolderWithCsvFiles`: Path to the folder containing user CSV files. Can be absolute or relative to the OJS root directory.

Optional flags (must be placed before the `users`/`issues` positional argument):
- `--sendWelcomeEmail`: Send welcome emails to imported users. The sender email will be the user retrieved by the username on the CLI command. No emails are sent when combined with `--dry-mode`.
- `--dry-mode`: Validate the CSV without persisting any data. See [Dry Mode](dry-mode.md).

The optional trailing `sendWelcomeEmail` positional argument (`true`/`false`) is also supported as an alternative to the flag.

Example:
```bash
php tools/importExport.php CSVImportExportPlugin --sendWelcomeEmail users admin /path/to/folder_with_csv_user_files
```

## Importing Issues

To import issues from a CSV file, use the following command:

```bash
php tools/importExport.php CSVImportExportPlugin [--dry-mode] issues [username] [pathToFolderWithCsvFiles]
```

Parameters:
- `username`: The username of a valid Journal manager. This username is used to validate that the command is running by a valid user as a security step.
- `pathToFolderWithCsvFiles`: Path to the folder containing issue CSV files. Can be absolute or relative to the OJS root directory.

Optional flags (must be placed before the `issues` positional argument):
- `--dry-mode`: Validate the CSV without persisting any data. See [Dry Mode](dry-mode.md).

Example:
```bash
php tools/importExport.php CSVImportExportPlugin issues admin /path/to/folder_with_csv_issue_files
```

> **Important Notes**
>
>  - The user obtained through the username will be the same one assigned to the submission files. It's also recommended that a dedicated importUser is created for this purpose with the Author role so that it's separate from existing Journal Manager and editor user accounts.
>  - The last CLI attribute must be the path to the folder containing CSV files, and not directly the CSV file itself.
>  - The CSV file and any referenced files (PDFs, images) must be readable by the user running the CLI script.
>  - The script must be executed from the OJS installation directory
>  - Ensure you have proper permissions to execute PHP scripts and access the files
>

[← Prev: Web Interface Usage](web-interface.md) | [README](../README.md) | [Next: CSV Format →](csv-format.md)
