# OJS CSV Import Plugin

This plugin allows administrators to import users and issues with their associated metadata in CSV format into OJS 3.5.X. This plugin supports both a web interface (GUI) and a command-line interface (CLI).

## Quick Start

### Web Interface

Navigate to **Tools → Import/Export → CSV Import Plugin**, select the import type (Issues or Users), upload your CSV file or a ZIP archive, optionally enable Data Validation Mode, and click **Import**. See [Web Interface Usage](docs/web-interface.md) for details.

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
