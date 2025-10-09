# OJS CSV Import Plugin (CLI)

This plugin allows administrators to import users and issues with their associated metadata in CSV format into OJS 3.4.X. This plugin operates exclusively via command-line interface (CLI).

## Table of Contents
- [Usage](#usage)
  - [Importing Users](#importing-users)
  - [Importing Issues](#importing-issues)
  - [Exporting Data](#exporting-data)
- [CSV File Format](#csv-file-format)
  - [Users CSV Format](#users-csv-format)
  - [Issues CSV Format](#issues-csv-format)
- [Multiversion Import](#multiversion-import)
  - [How It Works](#how-it-works)
  - [Version Management Rules](#version-management-rules)
  - [Practical Examples](#practical-examples)
- [Troubleshooting](#troubleshooting)
- [Support](#support)


## Command Line Usage

### Importing Users

To import users from a CSV file, use the following command:

```bash
php tools/importExport.php CSVImportExportPlugin users [username] [pathToCsvFile] [sendWelcomeEmail]
```

Parameters:
- `username`: The username of an administrator who will be associated with the import
- `pathToCsvFile`: Path to the CSV file containing user data. Can be absolute or relative to the OJS root directory.
- `sendWelcomeEmail`: (Optional) Set to `true` to send welcome emails to imported users

Example:
```bash
php tools/importExport.php CSVImportExportPlugin users admin /path/to/users.csv true
```

### Importing Issues

To import issues from a CSV file, use the following command:

```bash
php tools/importExport.php CSVImportExportPlugin issues [username] [pathToCsvFile]
```

Parameters:
- `username`: The username of an administrator who will be associated with the import
- `pathToCsvFile`: Path to the CSV file containing issue data. Can be absolute or relative to the OJS root directory.

Example:
```bash
php tools/importExport.php CSVImportExportPlugin issues admin /path/to/folder_with_csv_issue_files
```

### Important Notes:
- The last CLI attribute must be the path to the CSV file, and not directly the CSV file itself.
- The CSV file and any referenced files (PDFs, images) must be readable by the user running the CLI script.
- The script must be executed from the OJS installation directory
- Ensure you have proper permissions to execute PHP scripts and access the files

## CSV File Format

## Data Structure Reference

### Authors Format

The `authors` field in the issues CSV must contain author information in the following format:

```
GivenName,FamilyName,Email,Affiliation;GivenName2,FamilyName2,Email2,Affiliation2
```

- Fields are separated by commas within each author
- Multiple authors are separated by semicolons
- All fields except GivenName are optional and can be left empty
- If email is empty, the primary contact email will be used

Examples:
```
"John,Doe,john@example.com,University of Example; Jane,Smith,,Another University"
"Maria,Silva,maria@example.com,"
"Carlos,,carlos@example.com,Example Corp"
```

### Keywords, Subjects, and Categories

These fields use a simple semicolon-separated format:

- **Keywords**: `keyword1; keyword two; another keyword`
- **Subjects**: `subject1; subject two; another subject`
- **Categories**: `Category1; Category Two; Another Category`

Notes:
- Leading/trailing spaces are automatically trimmed
- Empty values are ignored
- Categories will be created if they don't exist

### User Interests

User interests in the users CSV use a semicolon-separated format:

```
interest one; interest two; another interest
```

- Leading/trailing spaces are automatically trimmed
- Empty values are ignored
- Each interest will be associated with the user's profile

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

### Example: Users CSV

You can take a look at the example we provide on the [User CSV file](./examples/users/users_example.csv).

### Example: Issues CSV

You can take a look at the example we provide on the [Issue CSV file](./examples/issues/issues_example.csv).

## File Structure for Import

When importing issues, the following file structure is recommended:

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

## Multiversion Import

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

#### Example 3: Multiple Articles with and without Versions

You can mix single-version and multi-version articles in the same CSV file. Take a look at [the default CSV file](./examples/issues/issues_example.csv).

### Important Notes

- All versions of an article share the same submission ID but have different publication IDs
- Each version can have its own DOI if needed
- Readers can access previous versions through the article's version history
- The import process validates that no duplicate versions exist (same identifier + version number)
- Versions must be imported in sequence within a single CSV file (version 1 before version 2, etc.)

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

#### Issue Import Issues
7. **Journal or Locale Issues**
   - Error: `Unknown journal with path [path]` or `Unknown locale [locale]`
   - Solution:
     - Verify the journal path in the CSV matches exactly
     - Check that the specified locale is enabled in the journal
     - Ensure the journal exists and is accessible to the importing user

8. **File Validation Errors**
   - Error: `Invalid [article/cover/galley] file for this submission`
   - Solution:
     - Verify all referenced files exist in the specified location
     - Check file permissions and formats
     - Ensure cover images are in a supported format (JPG, PNG)
     - Verify galley files match the specified labels

9. **Author and Metadata Issues**
   - Error: `There is no default author group in the journal`
   - Solution:
     - Ensure the journal has at least one author group configured
     - Verify author information follows the required format
     - Check that required author fields (first name) are provided

10. **Version Import Issues**
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

#### General Troubleshooting Tips
- Always back up your database before running imports
- Test with a small CSV file first
- Check the OJS error log for detailed error messages
- Ensure your CSV file is saved with UTF-8 encoding
- On Linux systems, check file permissions with `ls -l` and adjust with `chmod` if needed
- For large imports, monitor server resources as the process may be memory-intensive
