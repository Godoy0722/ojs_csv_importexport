# Multi-Locale Support

[← Prev: CSV Format](csv-format.md) | [README](../README.md) | [Next: Article Versions →](article-versions.md)

The CSV import plugin supports importing articles in multiple languages, allowing journals to publish content for international audiences. This feature enables you to create articles with content in different locales while maintaining proper relationships between translations.

## How Multi-Locale Works

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

## Multi-Locale Management Rules

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
   - `supportingAgencies`
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

## Multi-Locale Best Practices

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

## Important Multi-Locale Notes

- All locales for a version share the same publication ID
- Readers can switch between available locales in the frontend
- Categories can have different titles per locale
- Author names and affiliations can be provided in multiple locales
- Keywords and subjects are stored per locale
- Non-localized fields (DOI, dates, etc.) remain the same across all locales
- Files (galleys, supplementary) are shared across all locales


For a comprehensive example of multi-locale articles with versions, see the [comprehensive locale version CSV file](../examples/issues/comprehensive_locale_version.csv).

## ORCiD in Multi-Locale and Multi-Version

- Multi-Locale: ORCiD is non-localized. When importing another locale for the same version, if an ORCiD is provided in that row, it updates the existing author matched by email. If omitted, the existing value is preserved.
- Multi-Version: If the `authors` field is empty for a new version, authors (including ORCiD) are cloned from the previous version. If authors are provided, the ORCiD is read per author (as above) and saved for that version.

[← Prev: CSV Format](csv-format.md) | [README](../README.md) | [Next: Article Versions →](article-versions.md)
