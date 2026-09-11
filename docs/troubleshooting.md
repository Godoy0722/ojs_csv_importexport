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

### CSV Format Issues
3. **Missing or Invalid Fields**
   - Error: `Row doesn't contain all fields` or `Verify the required fields for this row`
   - Solution:
     - Check that all columns are present in the CSV header, including the non-required ones. It's ok for the non-required columnns to be empty as long as the header is present.
     - Ensure all rows have the same number of fields as the header
     - Verify there are no empty lines in the CSV file
     - Check for proper CSV escaping of fields containing commas or quotes
     - Some software may change the UTF encoding or interfere with the separators in the sample CSV, which may result in this error. Editing the CSV in Excel or Google Sheets seems to be less error-prone.

4. **Invalid Date Formats**
   - Error: `The subscription start/end date is not valid. Format required: YYYY-MM-DD`
   - Solution:
     - Ensure all dates are in YYYY-MM-DD format
     - Verify dates are valid (e.g., no February 30)
     - Check that end dates are after start dates

### User Import Issues
5. **User Already Exists**
   - Error: `User already exists with email/username [value]`
   - Solution:
     - If the email already exists, the importer updates that user's profile data (name, affiliation, country, ORCID, etc.) instead of creating a duplicate account
     - Username, password (`tempPassword`), and roles are not changed for existing users — leave those columns empty or omit their values when updating
     - To reset an existing user's password, use the OJS user management interface; CSV import cannot change passwords for existing accounts
     - Ensure usernames and emails are unique when creating **new** users
     - Check for case sensitivity in usernames/emails

6. **Role or Subscription Issues**
   - Error: `Role "[role]" doesn't exist` or `Invalid subscription type with ID [id]`
   - Solution:
     - Verify role names exactly match those in the system
     - Check that subscription type IDs exist in the database
     - Ensure required subscription fields (start_date, end_date) are provided

7. **ORCID Issues**
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
8. **Journal or Locale Issues**
   - Error: `Unknown journal with path [path]` or `Unknown locale [locale]`
   - Solution:
     - Verify the journal path in the CSV matches exactly
     - Check that the specified locale is enabled in the journal
     - Ensure the journal exists and is accessible to the importing user

9. **File Validation Errors**
   - Error: `Invalid [article/cover/galley] file for this submission`
   - Solution:
     - Verify all referenced files exist in the specified location
     - Check file permissions and formats
     - Ensure cover images are in a supported format (JPG, PNG)
     - Verify galley files match the specified labels

10. **Author and Metadata Issues**
   - Error: `There is no default author group in the journal`
   - Solution:
     - Ensure the journal has at least one author group configured
     - Verify author information follows the required format
     - Check that required author fields (first name) are provided

11. **Version Import Issues**
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

12. **References File Issues**
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
- Test with a small CSV file first
- Ensure your CSV file is saved with UTF-8 encoding
- CLI: Always back up your database before running imports
- CLI: Check the OJS error log for detailed error messages
- CLI: On Linux systems, check file permissions with `ls -l` and adjust with `chmod` if needed
- CLI: For large imports, monitor server resources as the process may be memory-intensive

[← Prev: Dry Mode](dry-mode.md) | [README](../README.md)
