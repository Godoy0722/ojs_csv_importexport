# Dry Mode

[← Prev: Supplementary Files Descriptions](supplementary-files.md) | [README](../README.md) | [Next: Troubleshooting →](troubleshooting.md)

The plugin supports a `--dry-mode` flag that runs the full import pipeline without persisting any data. This allows operators to preview and fix issues in their CSV files before committing a real import.

## How Dry Mode Works

When `--dry-mode` is passed, the plugin:

1. **Reads and validates every CSV row** exactly the same way as a real import — structural checks (column count, required fields) and semantic checks (journal existence, locale support, ORCID format, funder validation, etc.)
2. **Runs all processors inside a database transaction** — submissions, publications, authors, keywords, and all other entities are actually created in the database during processing, which means OJS-internal constraints (unique keys, foreign keys, etc.) are also validated
3. **Rolls back the transaction** at the end of each file — all database changes are undone, leaving the database in its original state
4. **Skips filesystem operations** — cover image uploads, galley file uploads, HTML galley uploads, and supplementary file uploads are not performed
5. **Produces a console report** — a per-file summary showing which rows passed and which failed, with the specific error reason for each failure
6. **Creates `invalid_*.csv` files** — failed rows are written to invalid files just like in a normal import, so you can use the same re-import workflow

Usage:

```bash
# Dry-mode for issues
php tools/importExport.php CSVImportPlugin --dry-mode issues admin /path/to/csv_files/

# Dry-mode for users
php tools/importExport.php CSVImportPlugin --dry-mode users admin /path/to/csv_files/

# Dry-mode for users with sendWelcomeEmail (emails are NOT sent in dry-mode)
php tools/importExport.php CSVImportPlugin --dry-mode users admin /path/to/csv_files/ true
```

The flag can be placed anywhere in the argument list; the positional arguments are read after it is removed.

## Dry Mode Console Report

The console output follows this structure for each CSV file:

```
=== issues.csv ===
ROW  | STATUS | ERROR
   3  | FAILED | Unknown locale or locale not supported by this journal: "pt_BR"
   5  | FAILED | Verify the required fields for this row.
Result: 8 passed, 2 failed (10 total)

=== users.csv ===
Result: 15 passed, 0 failed (15 total)

--- Dry-mode complete: 2 files, 23 passed, 2 failed ---
```

- **Row numbers** correspond to the actual CSV line numbers (row 1 is the header, data starts at row 2)
- Only **failed rows** are listed — passing rows are counted in the summary but not printed individually
- The **grand total** at the bottom summarizes results across all files in the directory
- If a file has no failures, only the summary line is printed (no table header or failed rows)

## Dry Mode Exit Codes

The process exit code indicates the overall result:

| Exit Code | Meaning |
|-----------|---------|
| `0` | All rows in all files passed validation |
| `1` | One or more rows failed validation |

This allows dry-mode to be used in scripts and CI pipelines:

```bash
php tools/importExport.php CSVImportPlugin --dry-mode issues admin /path/to/csv_files/
if [ $? -eq 0 ]; then
    echo "All rows valid — safe to import"
    php tools/importExport.php CSVImportPlugin issues admin /path/to/csv_files/
else
    echo "Validation errors found — check the output and invalid_*.csv files"
fi
```

Real imports are not affected: they keep exiting with `0` regardless of failed rows.

## Important Dry Mode Notes

1. **No database side effects**: All database changes are rolled back after each file. Your database is left exactly as it was before the dry-mode run.

2. **No filesystem side effects**: Cover images, galley files, and supplementary files are not uploaded. File existence and format are still validated, but no files are copied or moved.

3. **Welcome emails are never sent**: Even if `sendWelcomeEmail` is passed alongside `--dry-mode`, no emails will be sent.

4. **Read-only lookups work normally**: Entity lookups (journals, sections, categories, genres, user groups, users) are performed against the real database so that validation results accurately reflect what would happen in a real import.

5. **Multi-locale and multi-version detection works**: The same routing logic that detects multi-locale and multi-version rows in a real import also works in dry-mode. Tracking state is discarded between files, so each file is validated independently.

6. **Invalid files are created**: Failed rows are written to `invalid_*.csv` files in the same directory, following the same format as a normal import. This means you can fix the errors and re-import using the same workflow. Since files named `invalid_*` are skipped on every run, delete or move them before re-running if you want a clean validation.

7. **Recommended workflow**:
   ```bash
   # Step 1: Validate with dry-mode
   php tools/importExport.php CSVImportPlugin --dry-mode issues admin /path/to/csv_files/

   # Step 2: Fix any errors in the CSV files based on the report

   # Step 3: Run dry-mode again to verify fixes
   php tools/importExport.php CSVImportPlugin --dry-mode issues admin /path/to/csv_files/

   # Step 4: When all rows pass, run the real import
   php tools/importExport.php CSVImportPlugin issues admin /path/to/csv_files/
   ```

[← Prev: Supplementary Files Descriptions](supplementary-files.md) | [README](../README.md) | [Next: Troubleshooting →](troubleshooting.md)
