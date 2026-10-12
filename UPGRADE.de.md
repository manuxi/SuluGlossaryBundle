# Upgrade-Guide

[English version](UPGRADE.md)

## Von SuluAbbreviationsBundle 2.0

`manuxi/sulu-glossary-bundle` ist der Sulu-3-Nachfolger von `manuxi/sulu-abbreviations-bundle`. Es ist ein neues Paket mit neuen Namen (Entität `GlossaryEntry`, Resource-Key `glossary_entries`, Tabellen `glossary_*`, Sicherheitskontext `sulu.glossary.entries`).

Zusammenfassung:

1. Backup, Update auf Sulu 3, Paket und Bundle-Registrierung ersetzen.
2. Tabellen anlegen, `sulu:glossary:migrate-from-2x` ausführen (zuerst mit `--dry-run`).
3. `sulu:glossary:migrate-page-references` ausführen.
4. Templates und Feldtypen anpassen, `sulu:search:reindex` und `sulu:reference:refresh glossary_entries` ausführen.

Der vollständige Guide mit der Umbenennungstabelle: [docs/upgrade-from-abbreviations.de.md](docs/upgrade-from-abbreviations.de.md) / [docs/upgrade-from-abbreviations.en.md](docs/upgrade-from-abbreviations.en.md).

Installationen mit SuluAbbreviationsBundle 1.x aktualisieren zuerst auf 2.0 und folgen danach diesem Guide.
