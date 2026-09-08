# Troubleshooting

[← Prev: Dry Mode](dry-mode.md) | [README](../README.md)

## Common Issues and Solutions

### File and Path Issues

1. **File Not Found**
   - Error: `Could not read file: [file]`
   - Verify the file exists in the same directory as the CSV (or use a relative path from that directory).
   - Check file permissions for the user running the CLI script.

2. **Invalid Source Directory**
   - Error: `Invalid source dir: [dir]`
   - Verify the directory exists and is accessible.

### CSV Format Issues

3. **Missing or Invalid Fields**
   - Error: `Row doesn't contain all fields`
   - Ensure the header row contains all required columns (14 for users, 42 for issues).
   - Ensure every data row has the same number of fields as the header.
   - Remove empty lines from the CSV.

4. **Invalid Date Formats**
   - Error: subscription or publication date validation failures
   - Use `YYYY-MM-DD` format for all date fields (`startDate`, `endDate`, `datePublished`, `issuePublicationDate`).

5. **Invalid Rows Output**
   - Failed rows are written to `invalid_[original-filename].csv` in the same directory, with an extra `error` column explaining the rejection.
   - Fix the rows and re-run the import. Files named `invalid_*.csv` are automatically skipped on subsequent runs.

### User Import Issues

6. **User Already Exists**
   - Error: `User already exists with username [value]` (new users only)
   - Existing users are matched by `email` and updated instead of created.
   - Usernames must be unique when creating new users.

7. **Role or Subscription Issues**
   - Verify role names match those configured in the journal.
   - When `subscriptionType` is provided, `startDate` and `endDate` must also be provided.

8. **ORCID Issues**
   - Invalid format or checksum errors reject the row.
   - Use full URL, dashed, or numeric format; verify against the official ORCID record.

### Issue Import Issues

9. **Journal or Locale Issues**
   - Verify `journalPath` matches the journal's path exactly.
   - Ensure the locale is enabled in journal settings.

10. **Section Fields**
    - Error: incomplete section fields
    - Provide at least one of `sectionTitle` or `sectionAbbrev` when specifying a section.

11. **Galley and Statistics Issues**
    - Error: `galleyViews cannot be provided without galleyLabels and galleyFilenames`
    - Provide `galleyFilenames` and `galleyLabels` whenever `galleyViews` is set, with matching counts.
    - On multi-locale rows that inherit galleys from a base locale row, leave `galleyViews` empty unless you also define the galleys on that row.

12. **HTML Galley Issues**
    - The first file in `htmlGalley` must have a `.html` or `.htm` extension.
    - All referenced files must exist in the import directory.

13. **Funding Plugin Issues**
    - Error: `Funders data was provided but the Funding plugin is not enabled`
    - Enable the Funding plugin under **Settings → Website → Plugins → Generic Plugins → Funding data** for the target journal.
    - Leave `funders` empty if the plugin is not installed or enabled.

14. **Version Import Issues**
    - `version` is required when `versionIdentifier` is provided.
    - Version numbers must be positive integers.
    - Duplicate `versionIdentifier + version` combinations are rejected.

15. **References File Issues**
    - References files must exist in the import directory and use a `.txt` extension.

### General Tips

- Back up your database before running imports.
- Test with a small CSV first.
- Save CSV files as UTF-8.
- Check the OJS error log for detailed PHP errors.
- Delete or move leftover `invalid_*.csv` files before a clean re-import if you no longer need them.

[← Prev: Dry Mode](dry-mode.md) | [README](../README.md)
