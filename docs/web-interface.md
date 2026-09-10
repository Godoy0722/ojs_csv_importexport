# Web Interface Usage

[README](../README.md) | [Next: CLI Usage →](cli-usage.md)

The plugin supports importing through the OJS web interface:

1. Navigate to **Tools → Import/Export → CSV Import Export Plugin**
2. Select the import type (**Issues** or **Users**)
3. Upload your CSV file or a ZIP archive containing CSV files and associated assets
4. Optionally enable **Data Validation** to validate without persisting changes
5. Optionally enable **Send Welcome Email** (visible only for user imports)
6. Click **Import**

When using a ZIP file for issue imports, place your CSV files along with any referenced assets (cover images, PDF files, supplementary files) either at the root of the archive or inside a single folder. If the ZIP contains exactly one folder and no CSV files at the root, that folder will be used automatically.

## Import Results Modal

After the import completes, a modal dialog displays a summary of the run:

- **Summary badges** — files processed, total rows, successful rows, created rows, updated rows, and failed rows
- **Per-file sections** — one block per CSV file, showing:
  - A table of failed rows (row number, status, and error message) when applicable
  - A table of updated users (for user imports) when applicable
  - A per-file result line (passed, created, updated, failed)
- **Download links** — for each file that had failed rows, a link to download the corresponding `invalid_*.csv` file containing only the rejected rows

Close the modal when you are finished reviewing the results. Closing the modal (via the **Close** button or the × control) triggers cleanup of the temporary extraction directory on the server.

> **Tip:** Download links open in a new browser tab so the import page stays open and temporary files remain available until you close the modal.

## Data Validation in the GUI

Enable the **Data Validation** checkbox to run the same validation pipeline as a real import without persisting database changes. See [Dry Mode](dry-mode.md) for full details on what is and is not changed during validation.

In the GUI, dry mode behaves the same as the CLI `--dry-mode` flag:

- All row-level validation runs against the live database
- Database changes are rolled back after each CSV file
- Filesystem uploads (cover images, galleys, supplementary files) are skipped
- Welcome emails are never sent, even if **Send Welcome Email** is checked
- Failed rows are written to `invalid_*.csv` files that can be downloaded from the results modal

The modal title and intro text indicate when Data Validation was used.

> **Note:** The same CSV format rules, multi-locale support, article versioning, and dry mode behavior described in the other documentation sections apply equally to both the web interface and the CLI.

[README](../README.md) | [Next: CLI Usage →](cli-usage.md)
