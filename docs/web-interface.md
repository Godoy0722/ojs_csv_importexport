# Web Interface Usage

[README](../README.md) | [Next: CLI Usage →](cli-usage.md)

The plugin supports importing issues and users through the OJS web interface:

1. Prepare the CSV file for issue or user import in accordance with the [CSV preparation instructions](csv-format.md).
2. Navigate to **Tools → Import/Export → CSV Import Plugin**.
3. Select the import type (**Issues** or **Users**).
4. Upload your CSV file or a ZIP archive containing CSV files and associated assets.
5. Optionally enable **Data Validation** to validate without persisting changes. More on the [Data Validation mode here](dry-mode.md).
6. Optionally enable **Send Welcome Email** (visible only for user imports).
7. Click **Import**.

When using a ZIP file for issue imports, place your CSV files along with any referenced assets (cover images, PDF files, supplementary files) either at the root of the archive or inside a single folder. If the ZIP contains exactly one folder and no CSV files at the root, that folder will be used automatically.

After the import completes, the interface displays a summary with:
- Import type and whether Data Validation mode was used
- Number of files processed
- Total, successful, and failed row counts
- Download links for any `invalid_*.csv` files containing rejected rows

> If you receive any errors, see the [Troubleshooting page](troubleshooting.md) for common issues and solutions.

> **Note:** The same CSV format rules, multi-locale support, article versioning, and Data Validation mode behavior described in the sections below apply equally to both the web interface and the CLI.

[README](../README.md) | [Next: CLI Usage →](cli-usage.md)
