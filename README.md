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

| Column | Required | Description | Example | Notes |
|--------|----------|-------------|---------|-------|
| journalPath | Yes | Path of the target journal | leo | Must exist in the system |
| locale | Yes | Article locale | en_US | Must be enabled in the journal |
| articleTitle | Yes | Article title | My Research Paper | |
| articlePrefix | No | Article prefix | PREF | Optional |
| articleSubtitle | No | Article subtitle | A Study of... | Optional |
| articleAbstract | No | Article abstract | This paper examines... | Optional |
| articleFilepath | Yes | Path to main article file | article.pdf | Relative to CSV location |
| authors | Yes | Author information | See [Authors Format](#authors-format) | |
| keywords | No | Semicolon-separated keywords | science;research | Optional |
| subjects | No | Semicolon-separated subjects | Biology;Ecology | Optional |
| coverage | No | Coverage information | Global study | Optional |
| categories | No | Semicolon-separated categories | Research Article | Will be created if needed |
| doi | No | Digital Object Identifier | 10.1234/abc123 | Must be valid format |
| coverImageFilename | No | Cover image filename | cover.jpg | Must be in same directory |
| coverImageAltText | No | Alt text for cover | Journal Cover | Required if cover image used |
| galleyFilenames | No | Semicolon-separated files | doc.docx;data.xlsx | Optional |
| galleyLabels | No | Labels for galleys | DOC;XLS | Must match galleyFilenames count |
| genreName | No | Content type | Article Text | Must exist in system |
| sectionTitle | No | Section name | Articles | Will be created if needed |
| sectionAbbrev | No | Section abbreviation | ART | Used if section is created |
| issueTitle | No | Issue title | Vol 1, No 1 (2024) | |
| issueVolume | No | Volume number | 1 | |
| issueNumber | No | Issue number | 1 | |
| issueYear | No | Publication year | 2024 | |
| issueDescription | No | Issue description | Special Edition | Optional |
| datePublished | No | Publication date | 2024-01-15 | Format: YYYY-MM-DD |
| startPage | No | First page | 1 | |
| endPage | No | Last page | 15 | |

### Complete Example: Users CSV

```csv
journalPath,firstname,lastname,email,affiliation,country,username,tempPassword,roles,reviewInterests,subscriptionType,start_date,end_date
myjournal,John,Doe,john@example.com,University of Example,US,jdoe,temp123,"Reader;Author","science;research",1,2024-01-01,2024-12-31
myjournal,Jane,Smith,jane@example.com,Research Institute,CA,jsmith,temp456,Reader,"biology;ecology",2,2024-01-01,2024-12-31
```

### Complete Example: Issues CSV

```csv
journalPath,locale,articleTitle,authors,articleAbstract,keywords,subjects,articleFilepath,coverImageFilename,coverImageAltText,galleyFilenames,galleyLabels,sectionTitle,issueTitle,issueVolume,issueNumber,issueYear,datePublished,startPage,endPage
myjournal,en_US,"Climate Change Impacts","John,Doe,john@example.com,University of Example;Jane,Smith,jane@example.com,Research Institute","This study examines...","climate change;environment","Environmental Science;Ecology",article.pdf,cover.jpg,"Journal Cover 2024","supplement.pdf;data.xlsx","PDF;XLS",Research Articles,"Volume 5, Issue 1",5,1,2024,2024-03-15,1,15
myjournal,en_US,"Biodiversity Loss","Alice,Johnson,alice@example.com,Conservation Org","This paper discusses...","biodiversity;conservation","Biology;Environmental Science",article2.pdf,,,"presentation.pptx","SLIDES",Research Articles,"Volume 5, Issue 1",5,1,2024,2024-03-20,16,30
```

## File Structure for Import

When importing issues, the following file structure is recommended:

```
import_directory/
├── users.csv
├── issues.csv
├── article.pdf
├── article2.pdf
├── cover.jpg
```

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

#### General Troubleshooting Tips
- Always back up your database before running imports
- Test with a small CSV file first
- Check the OJS error log for detailed error messages
- Ensure your CSV file is saved with UTF-8 encoding
- On Linux systems, check file permissions with `ls -l` and adjust with `chmod` if needed
- For large imports, monitor server resources as the process may be memory-intensive
