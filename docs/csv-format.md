# CSV Format

[← Prev: CLI Usage](cli-usage.md) | [README](../README.md) | [Next: Multi-Locale Support →](multi-locale.md)

## Users CSV Format

A sample user CSV file is available here — [User CSV file](../examples/users/users_example.csv).

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

## Issues CSV Format

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
| issuePublicationDate | No | Issue publication date | 2024-01-01 | Format: YYYY-MM-DD. When empty, the issue receives the most recent datePublished among its articles at the end of the CSV file processing |
| datePublished | Yes | Publication date | 2024-01-15 | Format: YYYY-MM-DD |
| startPage | No | First page | 1 | |
| endPage | No | Last page | 15 | |
| copyrightYear | No | Copyright year | 2025 | Defaults to system setting if not provided |
| copyrightHolder | No | Copyright holder | Public Knowledge Project | Defaults to system setting if not provided |
| licenseUrl | No | License URL | https://creativecommons.org/licenses/by/4.0 | Defaults to system setting if not provided |
| references | No | Path to references file (.txt) | references.txt | Optional file containing article references |

### Authors Format

The `authors` field in the articles CSV must contain author information in the following format:

```
GivenName,FamilyName,Email,ORCiD,Affiliation;GivenName2,FamilyName2,Email2,ORCiD2,Affiliation2
```

- Fields are separated by commas within each author
- Multiple authors are separated by semicolons
- All fields except `GivenName` are optional and can be left empty
- If `Email` is empty, the primary contact email of the server will be used
- `ORCiD` must be the author identifier and is optional; see input options below

Examples:

```
"John,Doe,john@example.com,0000-0002-1825-0097,University of Example; Jane,Smith,,https://orcid.org/0000-0002-1694-233X,Another University"
"Maria,Silva,maria@example.com,0000000218250097,"
"Carlos,,carlos@example.com,,Example Corp"
```

**ORCiD Input Options** — you may provide the ORCiD in any of the following forms:
- Full URL: `https://orcid.org/0000-0002-1825-0097`
- Hyphenated ID: `0000-0002-1825-0097`
- Digits only: `0000000218250097`

Notes:
- The system normalizes the value to the canonical URL form `https://orcid.org/0000-0000-0000-0000`
- The last character may be `X` (checksum), e.g., `0000-0002-1694-233X`
- Invalid formats are ignored without blocking the import

## Import File Structure

When importing issues, keep all issue assets in the same directory as the CSV file so you only need to pass asset names instead of paths. Example structure:

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

For the web interface, the same structure applies inside a ZIP archive. See [Web Interface Usage](web-interface.md).

Example issue CSV files:
- [single_version_issues.csv](../examples/issues/single_version_issues.csv)
- [mixed_issues.csv](../examples/issues/mixed_issues.csv)

[← Prev: CLI Usage](cli-usage.md) | [README](../README.md) | [Next: Multi-Locale Support →](multi-locale.md)
