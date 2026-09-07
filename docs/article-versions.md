# Article Versions

[← Prev: Multi-Locale Support](multi-locale.md) | [README](../README.md) | [Next: Supplementary Files Descriptions →](supplementary-files.md)

The CSV import plugin supports creating multiple versions of the same article in a single import operation.

## How It Works

- **versionIdentifier**: Links multiple versions of the same article
- **version**: Positive integer version number (1, 2, 3, …)

Articles with the same `versionIdentifier` are treated as versions of one submission. The highest version number becomes the current published version.

## Version Management Rules

1. **Version Identifiers**: Any unique string; leave empty for single-version articles.
2. **Version Numbers**: Positive integers; required when `versionIdentifier` is set; must be unique per article.
3. **Required Fields**:
   - Version 1: all mandatory fields
   - Versions > 1: partial updates allowed; omitted fields inherit from the previous version
4. **Current Version**: The highest version number is set as current after import.

## Practical Examples

- Single-version articles: [single_version_issues.csv](../examples/issues/single_version_issues.csv)
- Multi-version articles: [multi_version_issues.csv](../examples/issues/multi_version_issues.csv)
- Mixed single- and multi-version: [issues_example.csv](../examples/issues/issues_example.csv)
- Multi-version with multi-locale: [comprehensive_locale_version.csv](../examples/issues/comprehensive_locale_version.csv)

## Important Notes

- All versions share one submission ID but have different publication IDs.
- Each version can have its own DOI.
- Duplicate `versionIdentifier + version` combinations are rejected.
- Import versions in sequence within the same CSV (version 1 before version 2, etc.).
- Funders are stored at submission level and shared across all versions of the same article.

[← Prev: Multi-Locale Support](multi-locale.md) | [README](../README.md) | [Next: Supplementary Files Descriptions →](supplementary-files.md)
