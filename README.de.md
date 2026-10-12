# SuluGlossaryBundle

[English version](README.md)

Glossar-Bundle für Sulu 3. Es verwaltet Abkürzungen, Akronyme und Begriffe mit Erklärung, einer Detailseite pro Eintrag und den Content-Funktionen von Sulu: Entwurf und Live-Inhalt pro Sprache, Versionen, SEO- und Excerpt-Daten, Suche, Referenzen, Papierkorb und zeitgesteuertes Veröffentlichen.

Das Bundle ist der Nachfolger von `manuxi/sulu-abbreviations-bundle` (Sulu 2). Ein Befehl migriert die Daten des Releases 2.0, siehe [UPGRADE.de.md](UPGRADE.de.md).

## Funktionen

- Glossar-Einträge mit **Typ** (`abbreviation`, `acronym`, `term`), Titel, Erklärung, Beschreibung, Bild und Link
- Entwurf und Live-Inhalt pro Sprache, [Veröffentlichen](docs/publishing.de.md) und [Übersetzungen](docs/translations.de.md)
- [Versionen](docs/versions.de.md) mit Wiederherstellung
- SEO, Excerpt, Kategorien, Tags und Autor
- Detailseite mit Route pro Sprache, [Sitemap](docs/links-sitemap-preview.de.md), Links, Vorschau
- [Auswahl-Feldtypen](docs/selection-field-types.de.md), [Smart Content](docs/smart-content.de.md) und [Teaser](docs/teaser.de.md)
- [Twig-Funktionen](docs/twig.de.md) und ein optionaler `abbr`-Filter
- [Suche](docs/search.de.md) (Admin- und Website-Index), [Referenzen](docs/references.de.md), [HTTP-Cache](docs/http-cache.de.md)
- [Zeitgesteuertes Veröffentlichen](docs/automation.de.md) mit `sulu/automation-bundle`
- Papierkorb und Aktivitätsprotokoll
- [Einstellungen](docs/settings.de.md) (Header, Hero, Breadcrumbs, Übersichtsseite)

## Voraussetzungen

- PHP 8.2 oder höher
- Sulu 3.0 (`sulu/sulu ^3.0`)

## Installation

```console
composer require manuxi/sulu-glossary-bundle
```

Bundle in `config/bundles.php` registrieren:

```php
return [
    // ...
    Manuxi\SuluGlossaryBundle\SuluGlossaryBundle::class => ['all' => true],
];
```

Admin-Routen in `config/routes/sulu_admin.yaml` importieren:

```yaml
sulu_glossary_admin_api:
    resource: Manuxi\SuluGlossaryBundle\Controller\Admin\GlossaryEntryController
    type: attribute
    options:
        expose: true

sulu_glossary_settings_admin_api:
    resource: Manuxi\SuluGlossaryBundle\Controller\Admin\SettingsController
    type: attribute
    options:
        expose: true
```

Datenbanktabellen anlegen (`glossary_entries`, `glossary_entry_dimension_contents`, `glossary_entry_settings`):

```console
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

Eine MySQL-Migrationsvorlage liegt in [docs/templates/doctrine-migration.php.dist](docs/templates/doctrine-migration.php.dist).

Suchindizes neu aufbauen (das Bundle registriert einen Reindex-Provider für den Index `admin` und den Index `website` der SEAL-Suche von Sulu):

```console
bin/console sulu:search:reindex
```

Berechtigungen unter **Einstellungen > Benutzerrollen** vergeben: Sicherheitskontext `sulu.glossary.entries` (anzeigen, hinzufügen, bearbeiten, löschen, live) und `sulu.glossary.settings` (anzeigen, bearbeiten).

## Verwendung

### Auswahl und Smart Content

```xml
<property name="entries" type="glossary_entry_selection">
    <meta>
        <title lang="de">Glossar-Einträge</title>
    </meta>
</property>

<property name="entry" type="single_glossary_entry_selection">
    <meta>
        <title lang="de">Glossar-Eintrag</title>
    </meta>
</property>

<property name="list" type="smart_content">
    <meta>
        <title lang="de">Glossar</title>
    </meta>
    <params>
        <param name="provider" value="glossary_entries"/>
        <param name="max_per_page" value="10"/>
    </params>
</property>
```

### Twig

```twig
{% set entry = sulu_resolve_glossary_entry(id) %}
{% if entry %}
    <abbr title="{{ entry.explanation }}">{{ entry.title }}</abbr>
{% endif %}

{% for entry in sulu_get_glossary_entries(20) %}
    {{ entry.title }} ({{ entry.type }})
{% endfor %}
```

### Eigene Templates

Das Bundle liefert das Template `default`. Eigene Templates liegen in `config/templates/glossary_entries/*.xml` (Template-Typ `glossary_entry`), eigene Views in `templates/`.

## Konfiguration

```yaml
# config/packages/sulu_glossary.yaml
sulu_glossary:
    versioning:
        max_versions: 20   # Versionen pro Eintrag und Sprache, 0 = unbegrenzt
    twig:
        auto_abbr: false   # aktiviert den Twig-Filter abbr
```

## Dokumentation

Alle Funktionsdokumente gibt es auf Englisch und Deutsch im Verzeichnis [docs](docs).

## Mitwirken

Pull Requests sind willkommen. Bitte vorher die Prüfungen ausführen:

```console
composer test
composer phpstan
composer cs
```

## Lizenz

MIT, siehe [LICENSE](LICENSE).
