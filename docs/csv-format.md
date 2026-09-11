# CSV Format

[← Prev: CLI Usage](cli-usage.md) | [README](../README.md) | [Next: Multi-Locale Support →](multi-locale.md)

## Users CSV Format

A sample user CSV file is available here — [users_example.csv](../examples/users/users_example.csv).

Make sure to follow this CSV structure with all headers present, including non-required ones. Non-required fields may be empty as long as the header is present.

| Column | Required | Description | Example | Can be updated? |
|--------|----------|-------------|---------|-----------------|
| journalPath | Yes | Path of the journal | liv | n/a |
| firstname | Yes | User's first name | Homer | Yes |
| lastname | No | User's last name | Simpson | Yes |
| email | Yes | User's email address | homer@example.com | No — used to identify the user |
| affiliation | No | User's affiliation | University of British Columbia | Yes |
| country | No | Two-letter country code | CA | Yes |
| username | No | Username for login | hsimpson | No — new users only; auto-generated when empty |
| tempPassword | No | Temporary password | temppassword123 | No — new users only; auto-generated when empty |
| roles | Yes | Semicolon-separated list of roles | Reader;Author | No — new users only |
| reviewInterests | No | Semicolon-separated interests | interest one;interest two | Yes |
| subscriptionType | No | Subscription type ID | 1 | Yes |
| startDate | If subscriptionType is set | Subscription start date (YYYY-MM-DD) | 2023-01-01 | Yes |
| endDate | If subscriptionType is set | Subscription end date (YYYY-MM-DD) | 2023-12-31 | Yes |
| orcid | No | User's ORCID identifier | 0000-0002-1825-0097 | Yes |

> **tempPassword:** Applies only when creating a new user. If omitted for a new user, a password is generated automatically and the user must change it on first login. For existing users matched by `email`, `tempPassword` is **ignored** — the import never changes their password.

> **Updating existing users:** When a row's `email` matches an existing user, the importer updates that user instead of creating a new one. Updatable fields are listed in the **Can be updated?** column above. `username`, `tempPassword`, and `roles` are never changed on update — values in those columns are ignored. Use OJS user management to reset passwords or change roles.

> **User Interests:** Semicolon-separated. Leading/trailing spaces are trimmed; empty values are ignored.

> **ORCID:** Accepts full URL, dashed format (`0000-0002-1825-0097`), or numeric format (`0000000218250097`). Checksum is validated; invalid ORCIDs reject the row.

## Issues CSV Format

Example issue CSV files:
- [issues_example.csv](../examples/issues/issues_example.csv)
- [single_version_issues.csv](../examples/issues/single_version_issues.csv)
- [multi_version_issues.csv](../examples/issues/multi_version_issues.csv)
- [comprehensive_locale_version.csv](../examples/issues/comprehensive_locale_version.csv)

All 42 headers must be present in every issues CSV, even when values are empty.

| Column | Required | Description | Example | Notes |
|--------|----------|-------------|---------|-------|
| journalPath | Yes | Path of the target journal | liv | Must exist in the system |
| locale | Yes | Article locale | en | Must be enabled in the journal |
| versionIdentifier | No | Unique identifier for article versions | article-001 | Links versions together |
| version | No | Version number | 1 | Required if versionIdentifier is provided |
| articlePrefix | No | Article prefix | PREF | Optional |
| articleTitle | Yes | Article title | My Research Paper | Required for version 1 / first locale |
| articleSubtitle | No | Article subtitle | A Study of... | Optional |
| articleAbstract | No | Article abstract | This paper examines... | Optional |
| authors | Yes | Author information | See [Authors Format](#authors-format) | Required for version 1 / first locale |
| keywords | No | Semicolon-separated keywords | science;research | Optional |
| subjects | No | Semicolon-separated subjects | Biology;Ecology | Optional |
| coverage | No | Coverage information | Global study | Optional |
| categories | No | Semicolon-separated categories | Research Article | Created if needed |
| doi | No | Digital Object Identifier | 10.1234/abc123 | Must be valid format |
| coverImageFilename | No | Cover image filename | cover.jpg | Must be in same directory as CSV |
| coverImageAltText | No | Alt text for cover | Journal Cover | Required if cover image used |
| galleyFilenames | No | Semicolon-separated primary galley files | article.pdf;data.xlsx | Optional |
| galleyLabels | No | Labels for primary galleys | PDF;XLS | Must match galleyFilenames count |
| galleyViews | No | Semicolon-separated view counts per galley | 100;50 | Requires galleyFilenames and galleyLabels; non-negative integers |
| htmlGalley | No | Semicolon-separated HTML galley files | article.html;style.css | First file must be .html or .htm; see [HTML Galleys](#html-galleys) |
| suppFilenames | No | Semicolon-separated supplementary files | supplement.pdf;data.csv | Optional |
| suppLabels | No | Labels for supplementary files | Supplement;Dataset | Must match suppFilenames count |
| suppDescriptions | No | Descriptions for supplementary files | Extended methods;Raw dataset | Must match suppFilenames and suppLabels when provided |
| sectionTitle | No | Section name | Articles | At least one of sectionTitle or sectionAbbrev required when either is used |
| sectionAbbrev | No | Section abbreviation | ART | Used when creating a new section |
| issueTitle | No | Issue title | Vol 1, No 1 (2024) | |
| issueVolume | No | Volume number | 1 | |
| issueNumber | No | Issue number | 1 | |
| issueYear | No | Publication year | 2024 | |
| issueDescription | No | Issue description | Special Edition | Optional |
| issuePublicationDate | No | Issue publication date | 2024-01-01 | Format: YYYY-MM-DD. When empty, derived from article dates |
| datePublished | Yes | Article publication date | 2024-01-15 | Format: YYYY-MM-DD |
| startPage | No | First page | 1 | |
| endPage | No | Last page | 15 | |
| copyrightYear | No | Copyright year | 2025 | Defaults to system setting |
| copyrightHolder | No | Copyright holder | Public Knowledge Project | Defaults to system setting |
| licenseUrl | No | License URL | https://creativecommons.org/licenses/by/4.0 | Defaults to system setting |
| references | No | Path to references file (.txt) | references.txt | Must be in same directory as CSV |
| username | No | Username of an existing OJS user | csvimportuser | Used as submission uploader and optional primary author |
| funders | No | Semicolon-separated funder data | See [Funders Format](#funders-format) | Requires Funding plugin enabled in the journal |
| supportingAgencies | No | Semicolon-separated supporting agencies | NSF;ESA | Stored per locale on the publication |
| articleViews | No | Total abstract view count | 150 | Non-negative integer; imported into usage statistics |

> **Sections:** When creating or referencing a section, provide at least one of `sectionTitle` or `sectionAbbrev`. If only one is provided, the other is derived automatically.

### Authors Format

```
GivenName,FamilyName,Email,ORCiD,Affiliation;GivenName2,FamilyName2,Email2,ORCiD2,Affiliation2
```

- Fields are separated by commas within each author; multiple authors by semicolons.
- All fields except `GivenName` are optional.
- If `Email` is empty, the journal's primary contact email is used.

### Funders Format

```
FunderName,FunderIdentification,Award1|Award2;FunderName2,FunderIdentification2,Award3
```

- Funders separated by `;`; funder fields by `,`; multiple awards by `|`.
- `FunderName` is required; identification and awards are optional.
- The [Funding plugin](https://github.com/pkp/funding) must be enabled under **Settings → Website → Plugins → Generic Plugins → Funding data**.
- When the Funding plugin's Crossref validation is enabled, `FunderIdentification` must be a valid Crossref Funder Registry DOI.

### HTML Galleys

The `htmlGalley` column imports an HTML file as a galley with optional dependent files (CSS, JS, SVG, images):

```
htmlFile.html;dependentFile1.css;dependentFile2.svg
```

- The first file must have a `.html` or `.htm` extension.
- Remaining files are stored as dependent files.
- When combined with `galleyFilenames`, the HTML galley receives the label `HTML`.
- If `galleyViews` is provided, include a count for each galley label (including `HTML` when an HTML galley is present).

## Import File Structure

Keep all assets in the same directory as the CSV files:

```
import_directory/
├── issues_example.csv
├── users.csv
├── article.pdf
├── coverImage.png
├── supplement.pdf
├── references.txt
└── html-galley-simple.html
```

Files named `invalid_*.csv` in this directory are **not** imported. They are output files containing rejected rows from previous runs.

[← Prev: CLI Usage](cli-usage.md) | [README](../README.md) | [Next: Multi-Locale Support →](multi-locale.md)
