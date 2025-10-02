# OJS CSV Import Plugin (CLI)

This plugin allows administrators to import users and issues with their associated metadata in CSV format into OJS 3.3.X. This plugin operates exclusively via command-line interface (CLI).

## Table of Contents
- [Usage](#usage)
  - [Importing Users](#importing-users)
  - [Importing Issues](#importing-issues)
  - [Exporting Data](#exporting-data)
- [CSV File Format](#csv-file-format)
  - [Users CSV Format](#users-csv-format)
  - [Issues CSV Format](#issues-csv-format)
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
php tools/importExport.php CSVImportExportPlugin issues admin /path/to/csv_file_for_issues
```

### Important Notes:
- The CSV file and any referenced files (PDFs, images) must be readable by the web server user
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

**Note:** The column order must match exactly as shown below. All columns must be present in the CSV header, even if they are empty.

| Column | Required | Description | Example | Notes |
|--------|----------|-------------|---------|-------|
| journalPath | Yes | Path of the target journal | liv | Must exist in the system |
| locale | Yes | Article locale | en_US | Must be enabled in the journal |
| versionIdentifier | No | Unique identifier for versioning | article-002 | See [Article Versioning](#article-versioning) |
| version | Conditional | Version number | 1, 2, 3 | Required if versionIdentifier is set |
| articlePrefix | No | Article prefix | PREF | Optional title prefix |
| articleTitle | Yes* | Article title | My Research Paper | *Not required for versions > 1 |
| articleSubtitle | No | Article subtitle | A Study of... | Optional |
| articleAbstract | No | Article abstract | This paper examines... | Optional |
| authors | Yes* | Author information | See [Authors Format](#authors-format) | *Not required for versions > 1 |
| keywords | No | Semicolon-separated keywords | science;research | Optional |
| subjects | No | Semicolon-separated subjects | Biology;Ecology | Optional |
| coverage | No | Coverage information | Global study | Optional |
| categories | No | Semicolon-separated categories | Research Article | Will be created if needed |
| doi | No | Digital Object Identifier | 10.1234/abc123 | Must be valid format |
| coverImageFilename | No | Cover image filename | cover.jpg | Must be in same directory |
| coverImageAltText | No | Alt text for cover | Journal Cover | Required if cover image used |
| galleyFilenames | No | Semicolon-separated galley files | article.pdf;presentation.pptx | PDF, DOCX, PPTX, etc. |
| galleyLabels | No | Labels for galleys | PDF;SLIDES | Must match galleyFilenames count |
| suppFilenames | No | Semicolon-separated supplementary files | supplement.pdf;data.csv | Optional |
| suppLabels | No | Labels for supplementary files | Supplement;Dataset | Must match suppFilenames count |
| sectionTitle | No | Section name | Articles | Will be created if needed |
| sectionAbbrev | No | Section abbreviation | ART | Used if section is created |
| issueTitle | No | Issue title | Technology Issue | Optional |
| issueVolume | No | Volume number | 2 | Numeric value |
| issueNumber | No | Issue number | 1 | Numeric value |
| issueYear | No | Publication year | 2024 | Four-digit year |
| issueDescription | No | Issue description | Special Edition | Optional |
| datePublished | Yes* | Publication date | 2024-10-15 | *Not required for versions > 1. Format: YYYY-MM-DD |
| startPage | No | First page | 20 | Numeric value |
| endPage | No | Last page | 35 | Numeric value |
| copyrightYear | No | Copyright year | 2024 | If not provided, uses system default |
| copyrightHolder | No | Copyright holder | Public Knowledge Project | If not provided, uses system default |
| licenseUrl | No | License URL | https://creativecommons.org/licenses/by/4.0 | If not provided, uses system default |

### Article Versioning

The CSV import plugin supports creating multiple versions of articles, mimicking OJS's native versioning functionality. This allows you to track changes and updates to articles over time.

#### Overview

When using versioning:
- One **submission** contains multiple **publication versions**
- Each version can have different metadata, authors, content, and files
- The highest version number automatically becomes the "current" version visible to readers
- All versions remain accessible in the submission workflow

#### How It Works

##### Creating Version 1 (Initial Publication)

Set both `versionIdentifier` and `version`:
- `versionIdentifier`: Unique identifier for this article (e.g., "article-002")
- `version`: Set to `1`
- All required fields must be provided:
  - `journalPath`, `locale`, `articleTitle`, `authors`, `datePublished`
  - At least one issue field (`issueTitle`, `issueVolume`, `issueNumber`, or `issueYear`)

**Example:**
```csv
liv,en_US,article-002,1,,"Machine Learning Applications","Implementation Guide","Abstract text...","Maria Silva,Rodriguez,maria@university.edu,CS Dept","AI;ML","Computer Science",,Research,10.5678/ml2024,cover.jpg,"ML Cover","article.pdf","PDF","data.csv","Dataset",Research,RES,"Tech Issue",2,1,2024,"Tech special issue",2024-10-15,20,35,2024,Public Knowledge Project,https://creativecommons.org/licenses/by/4.0
```

##### Creating Additional Versions (2, 3, etc.)

Use the same `versionIdentifier` with an incremented `version` number:
- `versionIdentifier`: Same as version 1
- `version`: 2, 3, 4, etc.
- Only populate fields you want to **update**
- Leave fields empty to **inherit** from version 1

**Relaxed Requirements for Versions > 1:**
When `version > 1`, the following normally required fields become optional:
- `articleTitle` (inherits from version 1 if empty)
- `authors` (inherits from version 1 if empty)
- `datePublished` (inherits from version 1 if empty)
- Issue fields (inherits issue assignment from version 1 if all empty)

**Result:**
- Version 1: Has analysis.pdf and code.zip
- Version 2: Has ONLY updated_code.zip (no auto-copy because galley data was provided)


### Complete Example: Users CSV

```csv
journalPath,firstname,lastname,email,affiliation,country,username,tempPassword,roles,reviewInterests,subscriptionType,start_date,end_date
myjournal,John,Doe,john@example.com,University of Example,US,jdoe,temp123,"Reader;Author","science;research",1,2024-01-01,2024-12-31
myjournal,Jane,Smith,jane@example.com,Research Institute,CA,jsmith,temp456,Reader,"biology;ecology",2,2024-01-01,2024-12-31
```

### Complete Example: Issues CSV

```csv
journalPath,locale,versionIdentifier,version,articlePrefix,articleTitle,articleSubtitle,articleAbstract,authors,keywords,subjects,coverage,categories,doi,coverImageFilename,coverImageAltText,galleyFilenames,galleyLabels,suppFilenames,suppLabels,sectionTitle,sectionAbbrev,issueTitle,issueVolume,issueNumber,issueYear,issueDescription,datePublished,startPage,endPage,copyrightYear,copyrightHolder,licenseUrl
liv,en_US,article-001,1,,"Climate Change Impacts","Study of Coastal Effects","This study examines the impact of rising temperatures on coastal ecosystems over a 10-year period.","John,Doe,john@example.com,University of Example;Jane,Smith,jane@example.com,Research Institute","climate change;coastal ecosystems;environment","Environmental Science;Marine Biology",global,"Research Articles",10.5678/climate2024,cover.jpg,"Climate Research Cover","article.pdf","PDF","supplement.pdf;data.xlsx","Supplement;Dataset",Research Articles,RES,"Environmental Studies",5,1,2024,"Special issue on climate research",2024-03-15,1,15,2024,"University of Example",https://creativecommons.org/licenses/by/4.0
liv,en_US,article-002,1,,"Biodiversity Conservation","Methods and Approaches","This paper discusses modern approaches to biodiversity conservation in urban environments.","Alice,Johnson,alice@example.com,Conservation Org","biodiversity;conservation;urban ecology","Biology;Environmental Science",urban areas,"Research Articles",10.5678/biodiversity2024,,,"article2.pdf;presentation.pptx","PDF;SLIDES","data.csv","Research Data",Research Articles,RES,"Environmental Studies",5,1,2024,"Special issue on climate research",2024-03-20,16,30,2024,"Conservation Org",https://creativecommons.org/licenses/by-nc/4.0
liv,en_US,article-001,2,,"Climate Change Impacts - Updated Edition",,,,,,,,10.5678/climate2024v2,,,,,,,,,,,,,2024-06-15,1,15,2024,"University of Example",https://creativecommons.org/licenses/by/4.0
```

**Explanation:**
- **Row 2:** Creates article-001 version 1 with full metadata and files
- **Row 3:** Creates article-002 version 1 as a separate article
- **Row 4:** Creates version 2 of article-001, updating only the title and DOI. All other fields (abstract, authors, files, etc.) are inherited from version 1, and galleys are automatically copied.

## File Structure for Import

When importing issues, the following file structure is recommended:

```
import_directory/
├── issues.csv
├── article.pdf
├── article2.pdf
├── presentation.pptx
├── supplement.pdf
├── data.xlsx
├── supplementary_data.csv
├── cover.jpg
```

**Note:** All files referenced in the CSV (galleys, supplementary files, cover images) must be in the same directory as the CSV file or in a subdirectory relative to it.

## Troubleshooting

### Common Issues and Solutions

#### File and Path Issues
1. **File Not Found**
   - Error: `Could not read file: [file]. Error: [error]`
   - Solution:
     - Verify the file exists and the path is correct
     - Use absolute paths for reliability
     - For relative paths, they are resolved from the OJS root directory
     - Check file permissions (must be readable by the web server user)
     - Ensure the file is not empty

2. **Invalid Source Directory**
   - Error: `Invalid source dir: [dir]`
   - Solution:
     - Verify the directory exists and is accessible
     - Check for typos in the path
     - Ensure the web server user has read permissions

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

#### Versioning Issues
10. **Version Field Validation Errors**
    - Error: `Version must be a positive integer` or `versionIdentifier is required when version is provided`
    - Solution:
      - Ensure `version` is a number (1, 2, 3, etc.), not text
      - Always provide `versionIdentifier` when using `version`
      - Do not use decimals (e.g., use `2` not `2.0`)

11. **Duplicate Version Error**
    - Error: `Duplicate version: [identifier] version [number]`
    - Solution:
      - Check for duplicate rows in your CSV with the same versionIdentifier and version
      - Ensure you're not importing the same version twice
      - Each versionIdentifier + version combination must be unique

12. **Missing Base Version**
    - Error: Version 2 or higher imports but nothing appears in OJS
    - Solution:
      - Ensure version 1 exists and was imported successfully first
      - Import versions in sequential order (1, then 2, then 3)
      - Check that all versions use the exact same versionIdentifier (case-sensitive)

13. **Galleys Not Appearing in Versions**
    - Issue: Version 2+ doesn't show any files
    - Solution:
      - Verify version 1 has galleys successfully imported
      - Leave both `galleyFilenames` and `suppFilenames` empty to trigger auto-copy
      - If you provide ANY galley data, auto-copy is disabled - provide all files needed

14. **Version Not Set as Current**
    - Issue: Highest version doesn't show as current
    - Solution:
      - Ensure all versions were processed in the same import run
      - Check that version numbers are correctly set (1, 2, 3, not all 1)
      - Verify the `version` field contains numeric values, not text

#### General Troubleshooting Tips
- Always back up your database before running imports
- Test with a small CSV file first (1-2 articles) before large imports
- For versioning, test with a single article with 2 versions first
- Check the OJS error log for detailed error messages
- Ensure your CSV file is saved with UTF-8 encoding
- On Linux systems, check file permissions with `ls -l` and adjust with `chmod` if needed
- For large imports, monitor server resources as the process may be memory-intensive
- When importing versioned articles, process all versions in the same CSV file to ensure proper tracking
