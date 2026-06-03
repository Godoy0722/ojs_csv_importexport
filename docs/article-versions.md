# Article Versions

[← Prev: Multi-Locale Support](multi-locale.md) | [README](../README.md) | [Next: Supplementary Files Descriptions →](supplementary-files.md)

The CSV import plugin supports creating multiple versions of the same article in a single import operation. This feature allows you to track revisions, corrections, and updates to published articles while maintaining a complete version history

## How It Works

The multiversion system uses two key fields to manage article versions:

- **versionIdentifier**: A unique string that links multiple versions of the same article together
- **version**: A positive integer indicating the version number (1, 2, 3, etc.)

When you provide these fields in your CSV:
1. Articles with the same `versionIdentifier` are treated as different versions of the same submission
2. Each version can have updated content, metadata, or files
3. The system automatically sets the highest version number as the current published version
4. All versions remain accessible in the system's version history

## Version Management Rules

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

## Practical Examples

### Example 1: Single Article Without Versions

For articles that don't need version tracking, simply leave `versionIdentifier` and `version` empty. You can take a look at the [single version CSV file](../examples/issues/single_version_issues.csv).


### Example 2: Multi Version Articles

For article with multiple versions, you'll need to set the `versionIdentifier` and `version` fields. The `versionIdentifier` tracks the same article and the `version` handles with the article different verisons. See [multi version CSV file](../examples/issues/multi_version_issues.csv) example.

### Example 3: Mixed Articles

You can mix single-version and multi-version articles in the same CSV file. Take a look at [the default CSV file](../examples/issues/issues_example.csv).

## Important Notes

- All versions of an article share the same submission ID but have different publication IDs
- Each version can have its own DOI if needed
- Readers can access previous versions through the article's version history
- The import process validates that no duplicate versions exist (same identifier + version number)
- Versions must be imported in sequence within a single CSV file (version 1 before version 2, etc.)

[← Prev: Multi-Locale Support](multi-locale.md) | [README](../README.md) | [Next: Supplementary Files Descriptions →](supplementary-files.md)
