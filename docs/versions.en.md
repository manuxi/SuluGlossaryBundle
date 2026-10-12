# Versions

Whenever a locale is published, Sulu stores a version of the live content. The versions are listed in **Insights > Versions** (newest first) with the user who published and the date. The version number is a unix timestamp.

## Restore

The restore button of a version writes the stored content into the **draft** of the locale (never directly into live). The draft can be reviewed and published afterwards. The event `version_restored` is written to the activity log. The `edit` permission is required.

- `GET /admin/api/glossary-entries/{id}/versions?locale=en`
- `POST /admin/api/glossary-entries/{id}?action=restore&version=1700000000&locale=en`

## Configuration

```yaml
# config/packages/sulu_glossary.yaml
sulu_glossary:
    versioning:
        max_versions: 20  # versions per entry and locale, 0 = unlimited
```

After each publish the oldest versions above the limit are removed (`Manuxi\SuluGlossaryBundle\Versioning\VersionPruner`). The versions are removed together with the entry.
