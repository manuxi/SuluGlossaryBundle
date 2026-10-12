# Upgrade vom SuluAbbreviationsBundle

`manuxi/sulu-glossary-bundle` ersetzt `manuxi/sulu-abbreviations-bundle`. Die Daten des Releases **2.0** (Sulu 2.6, `sulu/content-bundle`) werden durch einen Befehl migriert. Installationen mit 1.x aktualisieren zuerst wie im Upgrade-Guide jenes Bundles auf 2.0, denn die Migration liest die Tabellen des Releases 2.0.

## Namen

| SuluAbbreviationsBundle 2.0 | SuluGlossaryBundle 1.0 |
|---|---|
| Paket `manuxi/sulu-abbreviations-bundle` | `manuxi/sulu-glossary-bundle` |
| Entität `Abbreviation` | `GlossaryEntry` |
| Resource-Key `abbreviations` | `glossary_entries` |
| Template-Typ `abbreviation` | `glossary_entry` |
| Tabellen `abbr_*`, `app_abbreviation_settings` | `glossary_entries`, `glossary_entry_dimension_contents`, `glossary_entry_settings` |
| Sicherheitskontext `sulu.abbreviations.abbreviations` | `sulu.glossary.entries` |
| Feldtypen `abbreviation_selection`, `single_abbreviation_selection` | `glossary_entry_selection`, `single_glossary_entry_selection` |
| Smart-Content-, Link- und Teaser-Provider `abbreviations` | `glossary_entries` |
| Twig `sulu_resolve_abbreviation`, `sulu_get_abbreviations`, `load_abbreviations_settings` | `sulu_resolve_glossary_entry`, `sulu_get_glossary_entries`, `load_glossary_settings` |
| Konfiguration `sulu_abbreviations` | `sulu_glossary` |
| Inhaltsfeld `name` | `title` |

Neu ist das nicht übersetzbare Feld `type` (`abbreviation`, `acronym`, `term`). Migrierte Einträge erhalten den Typ `abbreviation`.

## Schritte

1. Datenbank-Backup erstellen.
2. Das Projekt auf Sulu 3 aktualisieren. Die Routen von Sulu 2 müssen in der Tabelle `ro_routes_old` vorliegen (siehe Upgrade-Guide von Sulu 3, die Tabelle entsteht durch dessen Routen-Migration).
3. Paket ersetzen: `composer remove manuxi/sulu-abbreviations-bundle && composer require manuxi/sulu-glossary-bundle`.
4. Bundle-Klasse in `config/bundles.php` und Routen-Importe in `config/routes/sulu_admin.yaml` ersetzen (siehe [README](../README.de.md)). `config/packages/sulu_abbreviations.yaml` in `sulu_glossary.yaml` umbenennen und den Root-Key ändern.
5. Neue Tabellen mit `doctrine:migrations:diff` oder [templates/doctrine-migration.php.dist](templates/doctrine-migration.php.dist) anlegen. Die alten `abbr_*`-Tabellen bleiben bis zur Prüfung der Migration bestehen.
6. Probelauf, dann migrieren:

   ```console
   bin/console sulu:glossary:migrate-from-2x --dry-run
   bin/console sulu:glossary:migrate-from-2x
   ```

   Der Befehl behält die UUIDs, schreibt Entwurf und Live-Inhalt jeder Sprache über den Content-Manager (Routen, SEO, Excerpt, Workflow), konvertiert die gespeicherten Versionen, kopiert die Einstellungen und ist wiederholbar: bereits vorhandene Einträge werden übersprungen. Mit `--routes-table` wird eine andere Tabelle für die alten Routen angegeben.
7. Links auf Abkürzungen in Seiten, Snippets und Artikeln umschreiben:

   ```console
   bin/console sulu:glossary:migrate-page-references --dry-run
   bin/console sulu:glossary:migrate-page-references
   ```

8. Templates anpassen: Property-Typen (`abbreviation_selection` zu `glossary_entry_selection`, `single_abbreviation_selection` zu `single_glossary_entry_selection`), den Smart-Content-Provider-Parameter (`abbreviations` zu `glossary_entries`), Twig-Funktionen und den Inhaltsschlüssel `name` (jetzt `title`). `config/templates/abbreviations` nach `config/templates/glossary_entries` umbenennen, ebenso überschriebene Views.
9. Suche und Referenzen neu aufbauen:

   ```console
   bin/console sulu:search:reindex
   bin/console sulu:reference:refresh glossary_entries
   ```

10. Die Berechtigungen der neuen Sicherheitskontexte in den Benutzerrollen vergeben.
11. Die alten Tabellen nach Prüfung des Ergebnisses löschen.

## Nicht migriert

Die Einträge des Aktivitätsprotokolls, des Papierkorbs und des Referenz-Moduls mit dem alten Resource-Key `abbreviations` werden nicht konvertiert. Abkürzungen im Papierkorb müssen vor der Migration wiederhergestellt werden.
