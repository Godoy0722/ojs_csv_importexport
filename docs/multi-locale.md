# Multi-Locale Support

[← Prev: CSV Format](csv-format.md) | [README](../README.md) | [Next: Article Versions →](article-versions.md)

The CSV import plugin supports importing articles in multiple languages, allowing journals to publish content for international audiences.

## How Multi-Locale Works

Three key fields manage article translations:

- **versionIdentifier**: Links all versions of an article together
- **version**: Indicates the version number
- **locale**: Language/locale of the content (e.g., `en`, `pt_BR`, `fr_CA`)

When multiple CSV rows share the same `versionIdentifier` and `version` but use different `locale` values, the system adds locale data to the existing publication rather than creating a separate submission.

## Multi-Locale Management Rules

1. **Locale Codes**: Must match locales enabled in journal settings.

2. **Required Fields**:
   - First locale (base): all mandatory fields (`journalPath`, `locale`, `articleTitle`, `authors`, `datePublished`)
   - Additional locales: only `versionIdentifier`, `version`, and `locale` are strictly required; include other fields you want to translate
   - Fields not provided remain empty for that locale (no cross-locale inheritance), except cover images which inherit from the first locale when omitted

3. **Localized Fields**: `articleTitle`, `articleSubtitle`, `articleAbstract`, `articlePrefix`, `coverage`, `copyrightHolder`, `keywords`, `subjects`, `categories`, author names/affiliations, `issueTitle`, `issueDescription`, `supportingAgencies`, `suppDescriptions`

4. **Non-Localized Fields**: `copyrightYear`, `licenseUrl`, `doi`, `datePublished`, `startPage`, `endPage`, galleys, supplementary files, `funders`, `articleViews`

## Best Practices

- Import the primary/default locale first, then additional locales.
- Keep `versionIdentifier` and `version` consistent across locales.
- Duplicate `identifier + version + locale` combinations are rejected.
- Check `invalid_[filename].csv` for failed rows after import.

## Important Notes

- All locales for a version share the same publication ID.
- Files (galleys, supplementary) are shared across all locales.
- Do not provide `galleyViews` on locale-only rows unless you also provide matching `galleyFilenames` and `galleyLabels` (or inherit galleys from the base locale row without specifying views).

See [comprehensive_locale_version.csv](../examples/issues/comprehensive_locale_version.csv) for a full example.

### ORCiD in Multi-Locale and Multi-Version

- **Multi-Locale**: ORCiD is non-localized. On additional locale rows, an ORCiD updates the author matched by email; if omitted, the existing value is preserved.
- **Multi-Version**: If `authors` is empty for a new version, authors (including ORCiD) are cloned from the previous version.

[← Prev: CSV Format](csv-format.md) | [README](../README.md) | [Next: Article Versions →](article-versions.md)
