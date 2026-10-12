# Upgrade guide

[Deutsche Version](UPGRADE.de.md)

## From SuluAbbreviationsBundle 2.0

`manuxi/sulu-glossary-bundle` is the Sulu 3 successor of `manuxi/sulu-abbreviations-bundle`. It is a new package with new names (entity `GlossaryEntry`, resource key `glossary_entries`, tables `glossary_*`, security context `sulu.glossary.entries`).

Summary:

1. Backup, update to Sulu 3, replace the package and the bundle registration.
2. Create the tables, run `sulu:glossary:migrate-from-2x` (use `--dry-run` first).
3. Run `sulu:glossary:migrate-page-references`.
4. Adjust templates and field types, run `sulu:search:reindex` and `sulu:reference:refresh glossary_entries`.

The complete guide with the rename table: [docs/upgrade-from-abbreviations.en.md](docs/upgrade-from-abbreviations.en.md) / [docs/upgrade-from-abbreviations.de.md](docs/upgrade-from-abbreviations.de.md).

Installations on SuluAbbreviationsBundle 1.x first update to 2.0 and then follow this guide.
