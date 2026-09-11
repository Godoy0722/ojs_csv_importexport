# OJS CSV Import Plugin

This plugin allows administrators to import users and issues with their associated metadata in CSV format into OJS 3.3.X. This plugin supports both a web interface (GUI) and a command-line interface (CLI).

## Quick Start

### Web Interface

Navigate to **Tools → Import/Export → CSV Import Plugin**, select the import type (Issues or Users), upload your CSV file or a ZIP archive, optionally enable **Data Validation**, and click **Import**. See [Web Interface Usage](docs/web-interface.md) for details.

### CLI

Import users:

```bash
php tools/importExport.php CSVImportPlugin users [username] [pathToFolderWithCsvFiles] [sendWelcomeEmail]
```

Import issues:

```bash
php tools/importExport.php CSVImportPlugin issues [username] [pathToFolderWithCsvFiles]
```

See [CLI Usage](docs/cli-usage.md) for parameter details and examples.

## Documentation

- [Web Interface Usage](docs/web-interface.md) — import via the OJS GUI
- [CLI Usage](docs/cli-usage.md) — import from the command line
- [CSV Format](docs/csv-format.md) — column reference for users and issues CSVs
- [Multi-Locale Support](docs/multi-locale.md) — import articles in multiple languages
- [Article Versions](docs/article-versions.md) — track revisions of the same article
- [Supplementary Files Descriptions](docs/supplementary-files.md) — describe supp files per locale
- [Dry Mode](docs/dry-mode.md) — validate CSVs without persisting
- [Troubleshooting](docs/troubleshooting.md) — common errors and fixes

## Important Notes

> - **User imports:** Rows match existing accounts by email. New users receive `tempPassword` (or an auto-generated password) and role assignments. **Existing users are updated in place** (profile, interests, subscription) — password and roles are never changed on update, even if those columns are filled in the CSV.
> - The user obtained through the username will be the same one assigned to the submission files. It's also recommended that a dedicated importUser is created for this purpose with the Author role so that it's separate from existing Journal Manager and editor user accounts.
> - The last CLI attribute must be the path to the folder containing CSV files, and not directly the CSV file itself.
> - The CSV file and any referenced files (PDFs, images) must be readable by the user running the CLI script.
> - The script must be executed from the OJS installation directory
> - Ensure you have proper permissions to execute PHP scripts and access the files
