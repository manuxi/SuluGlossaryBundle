# Dashboard tile

With [manuxi/sulu-dashboard-bundle](https://github.com/manuxi/SuluDashboardBundle) the dashboard shows a **Glossary** tile. The dashboard is optional: without it the tile is not loaded and the bundle needs no configuration.

```bash
composer require manuxi/sulu-dashboard-bundle
```

## What the tile shows

- The number of entries and how many of them are not published in the selected content language (no live version).
- With more than one language, a bar per language: how many entries are translated, green when complete and orange when incomplete, plus the number of unpublished entries of that language.
- **What is missing?**: up to five entries that are still missing in a language. A click opens the form of the entry in that language.

The tile sits in the narrow column. It needs the **View** permission for `sulu.glossary.entries` and only reads data, through the repository of the bundle.

The latest changes are shown by the **Recent activity** tile of the dashboard; the bundle logs its events there (see [Publishing](publishing.en.md)). The translations tile of the dashboard counts pages and articles, not glossary entries, so this tile provides the language overview.

## Screenshots needed

- Dashboard with the **Glossary** tile for two languages, one of them incomplete, with the **What is missing?** list
