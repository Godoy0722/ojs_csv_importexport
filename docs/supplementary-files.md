# Supplementary Files Descriptions

[← Prev: Article Versions](article-versions.md) | [README](../README.md) | [Next: Dry Mode →](dry-mode.md)

You may optionally include a `suppDescriptions` column to provide a short description for each supplementary file. Use a semicolon-separated list matching the order of `suppFilenames` and `suppLabels`.

Example:

```
suppFilenames:      supplement_v2.pdf;data_v2.csv
suppLabels:         Supplementary Analysis;Dataset
suppDescriptions:   Extended methods;Raw experimental results (CSV)
```

Rules:
- The number of descriptions must match both `suppFilenames` and `suppLabels` when provided.
- Descriptions are stored per locale and can be provided again in multi-locale rows to set localized text.

[← Prev: Article Versions](article-versions.md) | [README](../README.md) | [Next: Dry Mode →](dry-mode.md)
