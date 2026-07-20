# Web Interface Usage

[README](../README.md) | [Next: CLI Usage →](cli-usage.md)

The plugin also supports importing through the OJS web interface:

1. Navigate to **Tools → Import/Export → CSV Import Plugin**
2. Select the import type (**Issues** or **Users**)
3. Upload your CSV file or a ZIP archive containing CSV files and associated assets
4. Optionally enable **Data Validation** to validate without persisting changes
5. Optionally enable **Send Welcome Email** (visible only for user imports)
6. Click **Import**

When using a ZIP file for issue imports, place your CSV files along with any referenced assets (cover images, PDF files, supplementary files) either at the root of the archive or inside a single folder. If the ZIP contains exactly one folder and no CSV files at the root, that folder will be used automatically.

After the import completes, the interface displays a summary with:
- Import type and whether dry mode was used
- Number of files processed
- Total, successful, and failed row counts
- Download links for any `invalid_*.csv` files containing rejected rows

> **Note:** The same CSV format rules, multi-locale support, article versioning, and dry mode behavior described in the sections below apply equally to both the web interface and the CLI.

[README](../README.md) | [Next: CLI Usage →](cli-usage.md)
