# Upgrade from SuluAbbreviationsBundle

`manuxi/sulu-glossary-bundle` replaces `manuxi/sulu-abbreviations-bundle`. The data of the release **2.0** (Sulu 2.6, `sulu/content-bundle`) is migrated by a command. Installations on 1.x first update to 2.0 as described in the upgrade guide of that bundle, because the migration reads the tables of the 2.0 release.

## Names

| SuluAbbreviationsBundle 2.0 | SuluGlossaryBundle 1.0 |
|---|---|
| package `manuxi/sulu-abbreviations-bundle` | `manuxi/sulu-glossary-bundle` |
| entity `Abbreviation` | `GlossaryEntry` |
| resource key `abbreviations` | `glossary_entries` |
| template type `abbreviation` | `glossary_entry` |
| tables `abbr_*`, `app_abbreviation_settings` | `glossary_entries`, `glossary_entry_dimension_contents`, `glossary_entry_settings` |
| security context `sulu.abbreviations.abbreviations` | `sulu.glossary.entries` |
| field types `abbreviation_selection`, `single_abbreviation_selection` | `glossary_entry_selection`, `single_glossary_entry_selection` |
| smart content provider, link provider, teaser provider `abbreviations` | `glossary_entries` |
| twig `sulu_resolve_abbreviation`, `sulu_get_abbreviations`, `load_abbreviations_settings` | `sulu_resolve_glossary_entry`, `sulu_get_glossary_entries`, `load_glossary_settings` |
| configuration `sulu_abbreviations` | `sulu_glossary` |
| content field `name` | `title` |

New is the unlocalized field `type` (`abbreviation`, `acronym`, `term`). Migrated entries get the type `abbreviation`.

## Steps

1. Create a database backup.
2. Update the project to Sulu 3. The routes of Sulu 2 have to be kept in the table `ro_routes_old` (see the upgrade guide of Sulu 3, the table is created by its route migration).
3. Replace the package: `composer remove manuxi/sulu-abbreviations-bundle && composer require manuxi/sulu-glossary-bundle`.
4. Replace the bundle class in `config/bundles.php` and the route imports in `config/routes/sulu_admin.yaml` (see the [README](../README.md)). Rename `config/packages/sulu_abbreviations.yaml` to `sulu_glossary.yaml` and its root key.
5. Create the new tables with `doctrine:migrations:diff` or [templates/doctrine-migration.php.dist](templates/doctrine-migration.php.dist). Keep the old `abbr_*` tables until the migration is checked.
6. Dry run, then migrate:

   ```console
   bin/console sulu:glossary:migrate-from-2x --dry-run
   bin/console sulu:glossary:migrate-from-2x
   ```

   The command keeps the UUIDs, writes draft and live content of every locale through the content manager (routes, SEO, excerpt, workflow), converts the stored versions, copies the settings and can be repeated: entries which already exist are skipped. Use `--routes-table` if the old routes are stored in another table.
7. Rewrite links to abbreviations in pages, snippets and articles:

   ```console
   bin/console sulu:glossary:migrate-page-references --dry-run
   bin/console sulu:glossary:migrate-page-references
   ```

8. Adjust templates: property types (`abbreviation_selection` to `glossary_entry_selection`, `single_abbreviation_selection` to `single_glossary_entry_selection`), the smart content provider param (`abbreviations` to `glossary_entries`), twig functions and the content key `name` (now `title`). Rename `config/templates/abbreviations` to `config/templates/glossary_entries` and the overridden views.
9. Rebuild search and references:

   ```console
   bin/console sulu:search:reindex
   bin/console sulu:reference:refresh glossary_entries
   ```

10. Assign the permissions of the new security contexts in the user roles.
11. Drop the legacy tables after checking the result.

## Not migrated

The rows of the activity log, the trash and the reference module that use the old resource key `abbreviations` are not converted. Trashed abbreviations have to be restored before the migration.
