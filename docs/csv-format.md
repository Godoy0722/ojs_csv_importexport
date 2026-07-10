# CSV Format

[← Prev: CLI Usage](cli-usage.md) | [README](../README.md) | [Next: Multi-Locale Support →](multi-locale.md)

## Users CSV Format

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
| startDate | If subscriptionType is set | Subscription start date (YYYY-MM-DD) | 2023-01-01 |
| endDate | If subscriptionType is set | Subscription end date (YYYY-MM-DD) | 2023-12-31 |
| orcid | No | User's ORCID identifier | 0000-0002-1825-0097 |

> **Finding your `journalPath`:** The `journalPath` is the journal's **Path** — the same value that appears in the journal's web address. You can find it in two easy ways:
>
>  1. **From the URL:** Open the journal in your browser and look at the address bar. The path is the segment that identifies the journal in the URL — for example, in `https://example.com/index.php/leo/...` the `journalPath` is `leo`.
>  2. **From the admin area:** Go to **Administration → Hosted Journals**. The list shows a **Path** column next to each journal's name — use that exact value.
>
>  - The journal must already exist in OJS; the importer does not create journals.

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

### Users CSV Example

You can take a look at the example we provide on the [User CSV file](../examples/users/users_example.csv).

Make sure to follow this CSV structure with all headers present, including the non-required ones. It is ok for non-required fields to have no values as long as the header is present.

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
| galleyViews | No | Semicolon-separated view counts per galley | 120;45 | Must match galleyLabels count. Each value is a non-negative integer |
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
| username | No | Username of the submission author for this row | jdoe | Overrides the CLI/web user as the submission's assigned user. Must exist in the system |
| funders | No | Semicolon-separated funder data | See [Funders Format](#funders-format) | Requires the Funding plugin to be enabled in the journal |
| supportingAgencies | No | Semicolon-separated supporting agencies | NSF;ESA | Localized field — provide per locale in multi-locale rows |
| articleViews | No | Total view count for the article | 256 | Must be a non-negative integer |

> **DOIs (`doi` column):** Only fill in this column if the article was **already published with a DOI assigned to it** (for example, it was previously hosted on another platform that minted the DOI). Enter that existing DOI exactly as it was registered.
>
>  - If your journal does **not** yet use DOIs and you are just starting out, **leave this column empty**. After migration you can enable and configure DOIs under **Settings → Distribution** (set your DOI prefix and pattern there), then open the **DOIs** page in the main menu and use its bulk **Assign** action to **batch-assign DOIs** to your articles at once. Letting OJS generate them keeps your DOIs consistent and avoids duplicates.
>  - Do **not** invent DOIs to fill the column — an unregistered DOI will not resolve.

> **Sections & Categories:** These describe how your content is organized in the journal. If a section or category named here does not exist yet, the importer creates it for you.
>
>  - **`sectionTitle` / `sectionAbbrev`** — Sections are the divisions a journal uses to organize an issue's table of contents (for example `Articles`). You can see and manage your journal's sections under **Settings → Journal → Sections**. If the `sectionTitle` you provide does not match an existing section, the importer creates a new one, using `sectionAbbrev` as its abbreviation.
>  - **`categories`** — Categories are an optional way to group submissions across the journal. You can see and manage them under **Settings → Journal → Categories**. Provide one or more as a semicolon-separated list; any category that does not exist yet is created during import.
>  - If you only need a simple table of contents, set `sectionTitle` (e.g. `Articles`) and leave `categories` empty.

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

> **Funders Format**
> The `funders` field accepts a semicolon-separated list of funders. Each funder uses commas to separate its fields, and a pipe character (`|`) to separate multiple awards for the same funder:
>
> ```
> FunderName,FunderIdentification,Award1|Award2;FunderName2,FunderIdentification2,Award3
> ```
>
>  - Funders are separated by `;`
>  - Funder fields are separated by `,`
>  - Multiple awards for the same funder are separated by `|`
>  - `FunderName` is required; `FunderIdentification` and awards are optional
>
> Examples:
>
> ```
> "National Science Foundation,https://doi.org/10.13039/100000001,NSF-1234|NSF-5678"
> "European Space Agency,https://doi.org/10.13039/501100000844,"
> "Some Funder,,"
> ```
>
> Validation:
>  - The Funding plugin must be enabled in the journal — rows with funder data are rejected otherwise
>  - When the Funding plugin's `enableGrantIdValidation` setting is on, each `FunderIdentification` (when provided) must be a Crossref Funder Registry DOI matching `https://doi.org/10.13039/...`
>  - Rows that fail funder validation are written to the `invalid_*.csv` file

### Issues CSV Example

You can take a look at the example we provide on the [Issue CSV file](../examples/issues/issues_example.csv).

Make sure to follow this CSV structure with all headers present, including the non-required ones. It is ok for non-required fields to have no values as long as the header is present.

### Import File Structure

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

[← Prev: CLI Usage](cli-usage.md) | [README](../README.md) | [Next: Multi-Locale Support →](multi-locale.md)
