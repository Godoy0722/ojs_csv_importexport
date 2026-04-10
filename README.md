# OJS CSV Import Plugin (CLI)

This plugin allows administrators to import users and issues with their associated metadata in CSV format into OJS 3.5.X. This plugin operates exclusively via command-line interface (CLI).

## Table of Contents
- [OJS CSV Import Plugin (CLI)](#ojs-csv-import-plugin-cli)
  - [Table of Contents](#table-of-contents)
  - [CLI Usage](#cli-usage)
    - [Importing Users](#importing-users)
    - [Importing Issues](#importing-issues)
  - [CSV General Rules](#csv-general-rules)
    - [Users CSV Format](#users-csv-format)
      - [Users CSV Example](#users-csv-example)
    - [Issues CSV Format](#issues-csv-format)
      - [Issues CSV Example](#issues-csv-example)
      - [Import File Structure](#import-file-structure)
  - [Multi-Locale Support](#multi-locale-support)
    - [How Multi-Locale Works](#how-multi-locale-works)
    - [Multi-Locale Management Rules](#multi-locale-management-rules)
    - [Multi-Locale Best Practices](#multi-locale-best-practices)
    - [Important Multi-Locale Notes](#important-multi-locale-notes)
	 - [ORCiD in Multi-Locale and Multi-Version](#orcid-in-multi-locale-and-multi-version)
  - [Article Versions](#article-versions)
    - [How It Works](#how-it-works)
    - [Version Management Rules](#version-management-rules)
    - [Practical Examples](#practical-examples)
      - [Example 1: Single Article Without Versions](#example-1-single-article-without-versions)
      - [Example 2: Multi Version Articles](#example-2-multi-version-articles)
      - [Example 3: Mixed Articles](#example-3-mixed-articles)
    - [Important Notes](#important-notes)
  - [Supplementary Files Descriptions](#supplementary-files-descriptions)
  - [Dry Mode](#dry-mode)
    - [How Dry Mode Works](#how-dry-mode-works)
    - [Dry Mode Console Report](#dry-mode-console-report)
    - [Dry Mode Exit Codes](#dry-mode-exit-codes)
    - [Important Dry Mode Notes](#important-dry-mode-notes)
  - [Troubleshooting](#troubleshooting)
    - [Common Issues and Solutions](#common-issues-and-solutions)
      - [File and Path Issues](#file-and-path-issues)
      - [CSV Format Issues](#csv-format-issues)
      - [User Import Issues](#user-import-issues)
      - [Issue Import Issues](#issue-import-issues)
      - [General Troubleshooting Tips](#general-troubleshooting-tips)


## CLI Usage

### Importing Users

To import users from a CSV file, use the following command:

```bash
php tools/importExport.php CSVImportExportPlugin users [username] [pathToFolderWithCsvFiles] [sendWelcomeEmail]
```

Parameters:
- `username`: The username of a valid Journal manager. This username is used to validate that the command is running by a valid user as a security step.
- `pathToFolderWithCsvFiles`: Path to the CSV file containing user data. Can be absolute or relative to the OJS root directory.
- `sendWelcomeEmail`: (Optional) Set to `true` to send welcome emails to imported users. If set to true, the sender email will be the user retrieved by the username on the CLI command.

Example:
```bash
php tools/importExport.php CSVImportExportPlugin users admin /path/to/folder_with_csv_user_files true
```

### Importing Issues

To import issues from a CSV file, use the following command:

```bash
php tools/importExport.php CSVImportExportPlugin issues [username] [pathToFolderWithCsvFiles]
```

Parameters:
- `username`: The username of a valid Journal manager. This username is used to validate that the command is running by a valid user as a security step.
- `pathToFolderWithCsvFiles`: Path to the CSV file containing issue data. Can be absolute or relative to the OJS root directory.

Example:
```bash
php tools/importExport.php CSVImportExportPlugin issues admin /path/to/folder_with_csv_issue_files
```

> **Important Notes**
>
>  - The user obtained through the username will be the same one assigned to the submission files. It's also recommended that a dedicated importUser is created for this purpose with the Author role so that it's separate from existing Journal Manager and editor user accounts.
>  - The last CLI attribute must be the path to the CSV file, and not directly the CSV file itself.
>  - The CSV file and any referenced files (PDFs, images) must be readable by the user running the CLI script.
>  - The script must be executed from the OJS installation directory
>  - Ensure you have proper permissions to execute PHP scripts and access the files
>

## CSV General Rules

### Users CSV Format

| Column | Required | Description | Example |
|--------|----------|-------------|---------|
| journalPath | Yes | Path of the journal | leo |
| firstname | Yes | User's first name | Homer |
| lastname | Yes | User's last name | Simpson |
| email | Yes | User's email address | homer@example.com |
| affiliation | No | User's affiliation | University of British Columbia |
| country | No | Two-letter country code | CA |
| username | Yes | Username for login | hsimpson |
| tempPassword | Yes | Temporary password | temppassword123 |
| roles | No | Semicolon-separated list of roles | Reader;Author |
| reviewInterests | No | Semicolon-separated interests | interest one;interest two |
| subscriptionType | No | Subscription type ID | 1 |
| start_date | If subscriptionType is set | Subscription start date (YYYY-MM-DD) | 2023-01-01 |
| end_date | If subscriptionType is set | Subscription end date (YYYY-MM-DD) | 2023-12-31 |
| orcid | No | User's ORCID identifier | 0000-0002-1825-0097 |

> **User Interests:** User interests in the users CSV use a semicolon-separated format:
>
> ```
> interest one; interest two; another interest
> ```
>
>  - Leading/trailing spaces are automatically trimmed
>  - Empty values are ignored
>  - Each interest will be associated with the created user's profile
>

> **ORCID:** The ORCID field accepts multiple formats and will be automatically normalized to the standard URL format:
>
> Accepted formats:
>  - **Full URL**: `https://orcid.org/0000-0002-1825-0097` or `https://sandbox.orcid.org/0000-0002-1825-0097`
>  - **Dashed format**: `0000-0002-1825-0097`
>  - **Numeric format**: `0000000218250097`
>
> Notes:
>  - The last character can be a digit (0-9) or the letter X (checksum character)
>  - The ORCID checksum is validated during import
>  - Invalid ORCIDs will cause the row to be rejected
>  - Leave empty if the user doesn't have an ORCID
>
> Examples:
>
> ```
> https://orcid.org/0000-0002-1825-0097
> 0000-0001-5109-3700
> 0000000256781235
> ```

#### Users CSV Example

You can take a look at the example we provide on the [User CSV file](./examples/users/users_example.csv).

### Issues CSV Format

| Column | Required | Description | Example | Notes |
|--------|----------|-------------|---------|-------|
| journalPath | Yes | Path of the target journal | leo | Must exist in the system |
| locale | Yes | Article locale | en_US | Must be enabled in the journal |
| versionIdentifier | No | Unique identifier for article versions | article-001 | Links versions together. Leave empty for single-version articles |
| version | No | Version number | 1 | Required if versionIdentifier is provided. Must be positive integer |
| articlePrefix | No | Article prefix | PREF | Optional |
| articleTitle | Yes | Article title | My Research Paper | Required for version 1, optional for versions > 1 |
| articleSubtitle | No | Article subtitle | A Study of... | Optional |
| articleAbstract | No | Article abstract | This paper examines... | Optional |
| authors | Yes | Author information | See [Authors Format](#authors-format) | Required for version 1, optional for versions > 1 |
| keywords | No | Semicolon-separated keywords | science;research | Optional |
| subjects | No | Semicolon-separated subjects | Biology;Ecology | Optional |
| coverage | No | Coverage information | Global study | Optional |
| categories | No | Semicolon-separated categories | Research Article | Will be created if needed |
| doi | No | Digital Object Identifier | 10.1234/abc123 | Must be valid format |
| coverImageFilename | No | Cover image filename | cover.jpg | Must be in same directory |
| coverImageAltText | No | Alt text for cover | Journal Cover | Required if cover image used |
| galleyFilenames | No | Semicolon-separated primary galley files | doc.docx;data.xlsx | Optional |
| galleyLabels | No | Labels for primary galleys | DOC;XLS | Must match galleyFilenames count |
| suppFilenames | No | Semicolon-separated supplementary files | supplement.pdf;data.csv | Optional |
| suppLabels | No | Labels for supplementary files | Supplement;Dataset | Must match suppFilenames count |
| suppDescriptions | No | Semicolon-separated descriptions for supplementary files | Supplementary analysis;Raw dataset (CSV) | Optional; if provided must match suppFilenames and suppLabels count |
| sectionTitle | No | Section name | Articles | Will be created if needed |
| sectionAbbrev | No | Section abbreviation | ART | Used if section is created |
| issueTitle | No | Issue title | Vol 1, No 1 (2024) | |
| issueVolume | No | Volume number | 1 | |
| issueNumber | No | Issue number | 1 | |
| issueYear | No | Publication year | 2024 | |
| issueDescription | No | Issue description | Special Edition | Optional |
| datePublished | Yes | Publication date | 2024-01-15 | Format: YYYY-MM-DD |
| startPage | No | First page | 1 | |
| endPage | No | Last page | 15 | |
| copyrightYear | No | Copyright year | 2025 | Defaults to system setting if not provided |
| copyrightHolder | No | Copyright holder | Public Knowledge Project | Defaults to system setting if not provided |
| licenseUrl | No | License URL | https://creativecommons.org/licenses/by/4.0 | Defaults to system setting if not provided |
| references | No | Path to references file (.txt) | references.txt | Optional file containing article references |

> **Authors Format**
> The `authors` field in the articles CSV must contain author information in the following format:
>
> ```
> GivenName,FamilyName,Email,ORCiD,Affiliation;GivenName2,FamilyName2,Email2,ORCiD2,Affiliation2
> ```
>
>  - Fields are separated by commas within each author
>  - Multiple authors are separated by semicolons
>  - All fields except `GivenName` are optional and can be left empty
>  - If `Email` is empty, the primary contact email of the server will be used
>  - `ORCiD` must be the author identifier and is optional; see input options below
>
> Examples:
>
> ```
> "John,Doe,john@example.com,0000-0002-1825-0097,University of Example; Jane,Smith,,https://orcid.org/0000-0002-1694-233X,Another University"
> "Maria,Silva,maria@example.com,0000000218250097,"
> "Carlos,,carlos@example.com,,Example Corp"
> ```

> **ORCiD Input Options**
> You may provide the ORCiD in any of the following forms:
>  - Full URL: `https://orcid.org/0000-0002-1825-0097`
>  - Hyphenated ID: `0000-0002-1825-0097`
>  - Digits only: `0000000218250097`
>
> Notes:
>  - The system normalizes the value to the canonical URL form `https://orcid.org/0000-0000-0000-0000`
>  - The last character may be `X` (checksum), e.g., `0000-0002-1694-233X`
>  - Invalid formats are ignored without blocking the import

#### Issues CSV Example

You can take a look at the example we provide on the [Issue CSV file](./examples/issues/issues_example.csv).

#### Import File Structure

When importing issues, it's important to keep all issue assets in the same directory as the CSV file, so you just need to pass the asset names instead of a path for the asset. Here's an example of the recommended structure:

```
import_directory/
├── users.csv
├── issues.csv
├── article.pdf
├── article2.pdf
├── presentation.pptx
├── supplement.pdf
├── data.xlsx
├── supplementary_data.csv
├── cover.jpg
```

## Multi-Locale Support

The CSV import plugin supports importing articles in multiple languages, allowing journals to publish content for international audiences. This feature enables you to create articles with content in different locales while maintaining proper relationships between translations.

### How Multi-Locale Works

The multi-locale system uses three key fields to manage article translations:

- **versionIdentifier**: Links all versions of an article together
- **version**: Indicates the version number
- **locale**: Specifies the language/locale of the content (e.g., `en`, `pt_BR`, `fr_CA`)

When you provide multiple CSV rows with:
- Same `versionIdentifier`
- Same `version`
- Different `locale`

The system will:
1. Detect that you're adding a translation to an existing publication
2. Update the existing publication with the new locale data
3. Preserve all existing data in other locales

### Multi-Locale Management Rules

1. **Locale Codes**:
   - Must match locales enabled in your journal settings
   - Common examples: `en` (English), `pt_BR` (Brazilian Portuguese), `fr_CA` (Canadian French)
   - Must be validated by the server before import

2. **Required Fields for Multi-Locale**:
   - First locale import (base): Requires ALL mandatory fields (`journalPath`, `locale`, `articleTitle`, `authors`, `datePublished`)
   - Additional locale imports: Only require `versionIdentifier`, `version`, and `locale` (you can include other fields you want to translate)
   - Fields not provided will remain empty for that locale (they won't inherit from other locales), with the exception of the coverImage, which if not passed on a second locale but present on the first one, will inherit it from the first one.

3. **Localized Fields**:
   The following fields support multi-locale data:
   - `articleTitle`
   - `articleSubtitle`
   - `articleAbstract`
   - `articlePrefix`
   - `coverage`
   - `copyrightHolder`
   - `keywords`
   - `subjects`
   - `categories` (category titles)
   - Author names (`givenName`, `familyName`)
   - Author affiliations
   - `issueTitle`
   - `issueDescription`

4. **Non-Localized Fields**:
   These fields are shared across all locales:
   - `copyrightYear`
   - `licenseUrl`
   - `doi`
   - `datePublished`
   - `startPage` and `endPage`
   - File attachments (galleys and supplementary files)

1. **Import Order**:
   - Always import the primary/default locale first
   - Then add additional locales in subsequent rows
   - You can import all locales in a single CSV file

2. **Consistency**:
   - Keep `versionIdentifier` and `version` consistent across locales

4. **Validation**:
   - The system validates that `identifier` + `version` + `locale` is unique
   - Duplicate combinations will be rejected with error message
   - Check the `invalid_[filename].csv` file for any failed rows

### Multi-Locale Best Practices

1. **Import Order**:
   - Always import the primary/default locale first
   - Then add additional locales in subsequent rows
   - You can import all locales in a single CSV file

2. **Consistency**:
   - Keep `versionIdentifier` and `version` consistent across locales

4. **Validation**:
   - The system validates that `identifier` + `version` + `locale` is unique
   - Duplicate combinations will be rejected with error message
   - Check the `invalid_[filename].csv` file for any failed rows

### Important Multi-Locale Notes

- All locales for a version share the same publication ID
- Readers can switch between available locales in the frontend
- Categories can have different titles per locale
- Author names and affiliations can be provided in multiple locales
- Keywords and subjects are stored per locale
- Non-localized fields (DOI, dates, etc.) remain the same across all locales
- Files (galleys, supplementary) are shared across all locales


For a comprehensive example of multi-locale articles with versions, see the [comprehensive locale version CSV file](./examples/issues/comprehensive_locale_version.csv).

### ORCiD in Multi-Locale and Multi-Version

- Multi-Locale: ORCiD is non-localized. When importing another locale for the same version, if an ORCiD is provided in that row, it updates the existing author matched by email. If omitted, the existing value is preserved.
- Multi-Version: If the `authors` field is empty for a new version, authors (including ORCiD) are cloned from the previous version. If authors are provided, the ORCiD is read per author (as above) and saved for that version.

## Article Versions

The CSV import plugin supports creating multiple versions of the same article in a single import operation. This feature allows you to track revisions, corrections, and updates to published articles while maintaining a complete version history.

### How It Works

The multiversion system uses two key fields to manage article versions:

- **versionIdentifier**: A unique string that links multiple versions of the same article together
- **version**: A positive integer indicating the version number (1, 2, 3, etc.)

When you provide these fields in your CSV:
1. Articles with the same `versionIdentifier` are treated as different versions of the same submission
2. Each version can have updated content, metadata, or files
3. The system automatically sets the highest version number as the current published version
4. All versions remain accessible in the system's version history

### Version Management Rules

1. **Version Identifiers**:
   - Can be any unique string (e.g., "article-001", "ml-paper-2024", "climate-study")
   - Leave empty for single-version articles
   - Must be unique across different articles (don't reuse identifiers)

2. **Version Numbers**:
   - Must be positive integers (1, 2, 3, ...)
   - Required when `versionIdentifier` is provided
   - Must be unique for each version of the same article
   - Version 1 is always the initial/base version

3. **Required Fields**:
   - **Version 1** must include ALL required fields: `articleTitle`, `authors`, `datePublished`, etc.
   - **Versions > 1** can include only the fields you want to update (partial updates)
   - Fields not provided in higher versions inherit values from the previous version

4. **Automatic Current Version**:
   - After import, the system automatically sets the highest version as "current"
   - All other versions remain in the system as historical versions
   - Readers will see the highest version by default

### Practical Examples

#### Example 1: Single Article Without Versions

For articles that don't need version tracking, simply leave `versionIdentifier` and `version` empty. You can take a look at the [single version CSV file](./examples/issues/single_version_issues.csv).


#### Example 2: Multi Version Articles

For article with multiple versions, you'll need to set the `versionIdentifier` and `version` fields. The `versionIdentifier` tracks the same article and the `version` handles with the article different verisons. See [multi version CSV file](./examples/issues/multi_version_issues.csv) example.

#### Example 3: Mixed Articles

You can mix single-version and multi-version articles in the same CSV file. Take a look at [the default CSV file](./examples/issues/issues_example.csv).

### Important Notes

- All versions of an article share the same submission ID but have different publication IDs
- Each version can have its own DOI if needed
- Readers can access previous versions through the article's version history
- The import process validates that no duplicate versions exist (same identifier + version number)
- Versions must be imported in sequence within a single CSV file (version 1 before version 2, etc.)

## Supplementary Files Descriptions

You may optionally include a `suppDescriptions` column to provide a short description for each supplementary file. Use a semicolon-separated list matching the order of `suppFilenames` and `suppLabels`.

Example:

```
suppFilenames:      supplement_v2.pdf;data_v2.csv
suppLabels:         Supplementary Analysis;Dataset
suppDescriptions:   Extended methods;Raw experimental results (CSV)
```

Rules:
- The number of descriptions must match both `suppFilenames` and `suppLabels` when provided.
- Descriptions are stored per locale and can be provided again in multi-locale rows to set localized text.

## Dry Mode

The plugin supports a `--dry-mode` flag that runs the full import validation pipeline without persisting any data to the database. This allows operators to preview and fix issues in their CSV files before committing a real import.

### How Dry Mode Works

When `--dry-mode` is passed, the plugin:

1. **Reads and validates every CSV row** exactly the same way as a real import — structural checks (column count, required fields) and semantic checks (journal existence, locale support, ORCID format, funder validation, etc.)
2. **Runs all processors inside a database transaction** — submissions, publications, authors, keywords, and all other entities are actually created in the database during processing, which means OJS-internal constraints (unique keys, foreign keys, etc.) are also validated
3. **Rolls back the transaction** at the end of each file — all database changes are undone, leaving the database in its original state
4. **Skips filesystem operations** — cover image uploads, galley file uploads, and supplementary file uploads are not performed
5. **Produces a console report** — a per-file summary showing which rows passed and which failed, with the specific error reason for each failure
6. **Creates `invalid_*.csv` files** — failed rows are written to invalid files just like in a normal import, so you can use the same re-import workflow

Usage:
```bash
# Dry-mode for issues
php tools/importExport.php CSVImportExportPlugin --dry-mode issues admin /path/to/csv_files/

# Dry-mode for users
php tools/importExport.php CSVImportExportPlugin --dry-mode users admin /path/to/csv_files/

# Dry-mode for users with sendWelcomeEmail (emails are NOT sent in dry-mode)
php tools/importExport.php CSVImportExportPlugin --dry-mode users admin /path/to/csv_files/ true
```

### Dry Mode Console Report

The console output follows this structure for each CSV file:

```
=== issues.csv ===
ROW  | STATUS | ERROR
   3  | FAILED | Unknown locale or locale not supported by this journal: "pt_BR". Supported locales: en
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

### Dry Mode Exit Codes

The process exit code indicates the overall result:

| Exit Code | Meaning |
|-----------|---------|
| `0` | All rows in all files passed validation |
| `1` | One or more rows failed validation |

This allows dry-mode to be used in scripts and CI pipelines:

```bash
# Example: run dry-mode and check the result
php tools/importExport.php CSVImportExportPlugin --dry-mode issues admin /path/to/csv_files/
if [ $? -eq 0 ]; then
    echo "All rows valid — safe to import"
    php tools/importExport.php CSVImportExportPlugin issues admin /path/to/csv_files/
else
    echo "Validation errors found — check the output and invalid_*.csv files"
fi
```

### Important Dry Mode Notes

1. **No database side effects**: All database changes are rolled back after each file. Your database is left exactly as it was before the dry-mode run.

2. **No filesystem side effects**: Cover images, galley files, and supplementary files are not uploaded. File existence and format are still validated, but no files are copied or moved.

3. **Welcome emails are never sent**: Even if `sendWelcomeEmail` is passed alongside `--dry-mode`, no emails will be sent.

4. **Read-only lookups work normally**: Entity lookups (journals, sections, categories, genres, user groups, users) are performed against the real database so that validation results accurately reflect what would happen in a real import.

5. **Multi-locale and multi-version detection works**: The same routing logic that detects multi-locale and multi-version rows in a real import also works in dry-mode. However, if a base row fails validation, subsequent multi-locale or multi-version rows for the same identifier will also fail with a descriptive message: *"Skipped: the base row for identifier 'X' failed validation earlier in this file."*

6. **Invalid files are created**: Failed rows are written to `invalid_*.csv` files in the same directory, following the same format as a normal import. This means you can fix the errors and re-import using the same workflow.

7. **Recommended workflow**:
   ```bash
   # Step 1: Validate with dry-mode
   php tools/importExport.php CSVImportExportPlugin --dry-mode issues admin /path/to/csv_files/
   
   # Step 2: Fix any errors in the CSV files based on the report
   
   # Step 3: Run dry-mode again to verify fixes
   php tools/importExport.php CSVImportExportPlugin --dry-mode issues admin /path/to/csv_files/
   
   # Step 4: When all rows pass, run the real import
   php tools/importExport.php CSVImportExportPlugin issues admin /path/to/csv_files/
   ```

8. **`invalid_*` files from dry-mode**: Since dry-mode creates `invalid_*.csv` files, these will be automatically skipped on subsequent runs (both dry-mode and real imports). Delete or move them before re-running if you want a clean validation.

## Troubleshooting

### Common Issues and Solutions

#### File and Path Issues
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

#### CSV Format Issues
3. **Missing or Invalid Fields**
   - Error: `Row doesn't contain all fields` or `Verify the required fields for this row`
   - Solution:
     - Check that all required columns are present in the CSV header
     - Ensure all rows have the same number of fields as the header
     - Verify there are no empty lines in the CSV file
     - Check for proper CSV escaping of fields containing commas or quotes

4. **Invalid Date Formats**
   - Error: `The subscription start/end date is not valid. Format required: YYYY-MM-DD`
   - Solution:
     - Ensure all dates are in YYYY-MM-DD format
     - Verify dates are valid (e.g., no February 30)
     - Check that end dates are after start dates

#### User Import Issues
5. **User Already Exists**
   - Error: `User already exists with email/username [value]`
   - Solution:
     - Update existing users instead of creating new ones
     - Ensure usernames and emails are unique across the system
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

#### Issue Import Issues
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

#### General Troubleshooting Tips
- Always back up your database before running imports
- Test with a small CSV file first
- Check the OJS error log for detailed error messages
- Ensure your CSV file is saved with UTF-8 encoding
- On Linux systems, check file permissions with `ls -l` and adjust with `chmod` if needed
- For large imports, monitor server resources as the process may be memory-intensive
