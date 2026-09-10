# OJS CSV Import Plugin

This plugin allows administrators to import users and issues with their associated metadata in CSV format into OJS 3.4.X via the web interface or command-line interface (CLI).

## Quick Start

Import users:

```bash
php tools/importExport.php CSVImportExportPlugin users [username] [pathToFolderWithCsvFiles] [sendWelcomeEmail]
```

Import issues:

```bash
php tools/importExport.php CSVImportExportPlugin issues [username] [pathToFolderWithCsvFiles]
```

Validate the files without importing anything:

```bash
php tools/importExport.php CSVImportExportPlugin --dry-mode issues [username] [pathToFolderWithCsvFiles]
```

See [CLI Usage](docs/cli-usage.md) for parameter details and examples.

## Web Interface

Go to **Tools → Import/Export → CSV Import Export Plugin** to upload CSV or ZIP files, run data validation, and review results in a modal dialog. See [Web Interface Usage](docs/web-interface.md) for details.

## Documentation

- [Web Interface Usage](docs/web-interface.md) — import from the OJS admin UI
- [CLI Usage](docs/cli-usage.md) — import from the command line
- [CSV Format](docs/csv-format.md) — column reference for users and issues CSVs
- [Multi-Locale Support](docs/multi-locale.md) — import articles in multiple languages
- [Article Versions](docs/article-versions.md) — track revisions of the same article
- [Supplementary Files Descriptions](docs/supplementary-files.md) — describe supp files per locale
- [Dry Mode](docs/dry-mode.md) — validate CSV files without writing anything
- [Troubleshooting](docs/troubleshooting.md) — common errors and fixes

## Important Notes

> - The user obtained through the username will be the same one assigned to the submission files. It's also recommended that a dedicated importUser is created for this purpose with the Author role so that it's separate from existing Journal Manager and editor user accounts.
> - The last CLI attribute must be the path to the folder containing CSV files, and not directly the CSV file itself.
> - The CSV file and any referenced files (PDFs, images) must be readable by the user running the CLI script.
> - The script must be executed from the OJS installation directory.
> - Ensure you have proper permissions to execute PHP scripts and access the files.
> - Files named `invalid_*.csv` are skipped during import. They are generated when rows fail validation so you can fix and re-import.
