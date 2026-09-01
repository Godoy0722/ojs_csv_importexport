# Troubleshooting

[← Prev: Dry Mode](dry-mode.md) | [README](../README.md)

## Common Issues and Solutions

### File and Path Issues

1. **File Not Found**
   - Error: `Could not read file: [file]. Error: [error]`
   - Solution:
     - Verify the file exists and the path is correct
     - Use absolute paths for reliability
     - For relative paths, they are resolved from the OJS root directory
     - Check file permissions (must be readable by the user running the CLI script)
     - Ensure the file is not empty

2. **Invalid Source Directory**
   - Error: `Invalid source dir: [dir]`
   - Solution:
     - Verify the directory exists and is accessible
     - Check for typos in the path
     - Ensure the user running the CLI command has read permissions

### Web Interface Issues

3. **Import completes but no results modal appears**
   - Solution:
     - Hard-refresh the page (Ctrl+Shift+R) to reload plugin JavaScript
     - Ensure you are on the plugin's dedicated import page under **Tools → Import/Export → CSV Import Plugin**

4. **Download invalid rows returns an error**
   - Error: `Invalid file path` or `File not found`
   - Solution:
     - Download links are only valid while the results modal is open (temporary files are cleaned up when the modal closes)
     - Click the download link before closing the modal; links open in a new tab
     - If using **Data Validation**, invalid files are still created for failed rows — re-run the import if the modal was closed before downloading

5. **ZIP extraction failed**
   - Error shown in the form after upload
   - Solution:
     - Ensure the ZIP is not corrupted
     - Check that paths inside the archive do not contain `..` or absolute paths
     - Keep the archive under the size limits enforced by the plugin

### CSV Format Issues

6. **Missing or Invalid Fields**
   - Error: `Row doesn't contain all fields` or `Verify the required fields for this row`
   - Solution:
     - Check that all required columns are present in the CSV header
     - Ensure all rows have the same number of fields as the header
     - Verify there are no empty lines in the CSV file
     - Check for proper CSV escaping of fields containing commas or quotes

7. **Invalid Date Formats**
   - Error: `The subscription start/end date is not valid. Format required: YYYY-MM-DD`
   - Solution:
     - Ensure all dates are in YYYY-MM-DD format
     - Verify dates are valid (e.g., no February 30)
     - Check that end dates are after start dates

### User Import Issues

8. **User Already Exists**
   - Error: `User already exists with email/username [value]`
   - Solution:
     - Update existing users instead of creating new ones
     - Ensure usernames and emails are unique across the system
     - Check for case sensitivity in usernames/emails

9. **Role or Subscription Issues**
   - Error: `Role "[role]" doesn't exist` or `Invalid subscription type with ID [id]`
   - Solution:
     - Verify role names exactly match those in the system
     - Check that subscription type IDs exist in the database
     - Ensure required subscription fields (start_date, end_date) are provided

10. **ORCID Issues**
    - Error: `Invalid ORCID format: [orcid]`
    - Solution:
      - Ensure the ORCID uses one of the accepted formats: full URL, dashed, or numeric
      - Check that the ORCID has exactly 16 digits (plus dashes or URL prefix)
      - Verify there are no extra spaces or characters

    - Error: `Invalid ORCID checksum for: [orcid]`
    - Solution:
      - The ORCID checksum validation failed, meaning the ORCID is malformed
      - Double-check the ORCID against the official ORCID record
      - Ensure you copied the complete ORCID without typos

### Issue Import Issues

11. **Journal or Locale Issues**
    - Error: `Unknown journal with path [path]` or `Unknown locale [locale]`
    - Solution:
      - Verify the journal path in the CSV matches exactly
      - Check that the specified locale is enabled in the journal
      - Ensure the journal exists and is accessible to the importing user

12. **File Validation Errors**
    - Error: `Invalid [article/cover/galley] file for this submission`
    - Solution:
      - Verify all referenced files exist in the specified location
      - Check file permissions and formats
      - Ensure cover images are in a supported format (JPG, PNG)
      - Verify galley files match the specified labels

13. **Author and Metadata Issues**
    - Error: `There is no default author group in the journal`
    - Solution:
      - Ensure the journal has at least one author group configured
      - Verify author information follows the required format
      - Check that required author fields (first name) are provided

14. **Version Import Issues**
    - Error: `Version is required when versionIdentifier is provided`
    - Solution:
      - Always provide the `version` field when using `versionIdentifier`
      - Use positive integers (1, 2, 3, ...) for version numbers

    - Error: `Version must be a positive integer greater than 0`
    - Solution:
      - Ensure version numbers are positive integers
      - Don't use 0, negative numbers, or decimals

    - Error: `Duplicate article version found for identifier [id], version [num]`
    - Solution:
      - Check for duplicate rows with the same versionIdentifier and version
      - Ensure each version number is unique within the same article
      - Remove duplicate entries from your CSV file

15. **References File Issues**
    - Error: `Invalid references file: [filename]`
    - Solution:
      - Verify the references file exists in the same directory as the CSV file
      - Check file permissions (must be readable by the user running the CLI script)
      - Ensure the filename is spelled correctly in the CSV

    - Error: `Invalid references file extension`
    - Solution:
      - Ensure the references file has a .txt extension
      - References files must be in plain text format

### General Troubleshooting Tips

- Always back up your database before running imports
- Test with a small CSV file first; use **Data Validation** in the GUI or `--dry-mode` on the CLI before a real import
- Check the OJS error log for detailed error messages
- Ensure your CSV file is saved with UTF-8 encoding
- On Linux systems, check file permissions with `ls -l` and adjust with `chmod` if needed
- For large imports, monitor server resources as the process may be memory-intensive
- Review `invalid_*.csv` files for failed rows — they contain an `error` column with the rejection reason

[← Prev: Dry Mode](dry-mode.md) | [README](../README.md)
