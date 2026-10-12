# SuluGlossaryBundle!
![ci workflow](https://github.com/manuxi/SuluGlossaryBundle/actions/workflows/ci.yml/badge.svg)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://github.com/manuxi/SuluGlossaryBundle/blob/1.x/LICENSE)
![GitHub Tag](https://img.shields.io/github/v/tag/manuxi/SuluGlossaryBundle)
![Github Release](https://img.shields.io/github/v/release/manuxi/SuluGlossaryBundle?color=116ea3)
![Supports Sulu 3.0 or later](https://img.shields.io/badge/%20Sulu->=3.0-0088cc?color=00b2df)

[🇬🇧 English Version](README.md)

Das SuluGlossaryBundle erweitert Sulu 3.0 um ein Glossar für Abkürzungen, Akronyme und Begriffe.

Jeder Eintrag hat einen Typ, eine Erklärung, optional Beschreibung, Bild und Link sowie eine eigene Detailseite. Die Einträge nutzen die Content-Funktionen von Sulu: Entwurf und Live-Inhalt pro Sprache, Versionen, SEO- und Excerpt-Daten, Suche, Referenzen und zeitgesteuertes Veröffentlichen.

Dieses Bundle ist der Nachfolger von `manuxi/sulu-abbreviations-bundle` (Sulu 2). Die Daten des Releases 2.0 können mit einem Befehl migriert werden, siehe [UPGRADE.de.md](UPGRADE.de.md).

## ✨ Features

### 📚 Glossar-Verwaltung
- **Eintragstypen** - Abkürzung, Akronym oder Begriff
- **Umfangreiche Inhalte** - Titel, Erklärung, Beschreibung, Bild und Link
- **Entwurf & Live** - Getrennter Entwurf und Live-Inhalt pro Sprache, Veröffentlichen und Zurückziehen
- **Versionen** - Ältere Versionen in den Entwurf wiederherstellen
- **SEO & Excerpt** - Vollständige SEO- und Excerpt-Verwaltung
- **Mehrsprachig** - Vollständige Übersetzungsunterstützung, In Sprache kopieren
- **Autoren-Verwaltung** - Kontakte können als Autoren zugewiesen werden
- **Einstellungen** - Header, Hero, Breadcrumbs und Übersichtsseite
- **Weiteres** - Papierkorb, Referenzen, Sitemaps, Teaser, Vorschau, Aktivitätsprotokoll, usw.

### 🔄 Erweiterte Features
- **Auswahl-Feldtypen** - Einen oder mehrere Einträge in Seiten, Snippets und anderen Templates auswählen
- **Smart Content** - Einträge nach Kategorien und Tags gefiltert auflisten
- **Twig-Funktionen** - Einträge in Templates auflösen, optionaler `abbr`-Filter für automatisches Markup
- **Suche** - Admin- und Website-Index (SEAL)
- **HTTP-Cache** - Tagging und automatische Invalidierung
- **Zeitgesteuertes Veröffentlichen** - Veröffentlichen und Zurückziehen zu einem Zeitpunkt (`sulu/automation-bundle`)
- **Migration** - Befehl zur Migration der Daten des SuluAbbreviationsBundle 2.0

## 📋 Voraussetzungen

- PHP 8.2 oder höher
- Sulu CMS 3.0 oder höher
- Symfony 6.4 oder höher
- MySQL 5.7+ / MariaDB 10.2+ / PostgreSQL 11+

## 👩🏻‍🏭 Installation

### Schritt 1: Paket installieren

```bash
composer require manuxi/sulu-glossary-bundle
```

Falls du *nicht* Symfony Flex verwendest, füge das Bundle in `config/bundles.php` hinzu:

```php
return [
    //...
    Manuxi\SuluGlossaryBundle\SuluGlossaryBundle::class => ['all' => true],
];
```

### Schritt 2: Routen konfigurieren

Zu `routes_admin.yaml` hinzufügen:

```yaml
SuluGlossaryBundle:
    resource: '@SuluGlossaryBundle/Resources/config/routes_admin.yaml'
```

### Schritt 3: Datenbank aktualisieren

```bash
# Prüfe was erstellt wird
php bin/console doctrine:schema:update --dump-sql

# Führe Migration aus
php bin/console doctrine:schema:update --force
```

Mit Doctrine Migrations wird `doctrine:migrations:diff` verwendet. Eine MySQL-Migrationsvorlage liegt in [docs/templates/doctrine-migration.php.dist](docs/templates/doctrine-migration.php.dist).

### Schritt 4: Suchindex neu aufbauen

```bash
php bin/console sulu:search:reindex
```

### Schritt 5: Berechtigungen erteilen

1. Gehe zu Sulu Admin → Einstellungen → Benutzerrollen
2. Finde die passende Rolle
3. Aktiviere die Berechtigungen für die Glossar-Einträge (`sulu.glossary.entries`) und die Glossar-Einstellungen (`sulu.glossary.settings`)
4. Lade die Seite neu

## 🎣 Verwendung

### Ersten Eintrag erstellen

1. Navigiere zu **Glossar** in der Sulu-Admin-Navigation
2. Klicke auf **Hinzufügen**
3. Wähle den Typ, gib Titel und Erklärung ein
4. Ergänze Beschreibung, Bild und Link (optional)
5. Veröffentliche deinen Eintrag

### Einträge in Templates verwenden

```xml
<property name="entries" type="glossary_entry_selection">
    <meta>
        <title lang="de">Glossar-Einträge</title>
    </meta>
</property>
```

```twig
{% set entry = sulu_resolve_glossary_entry(id) %}
{% if entry %}
    <abbr title="{{ entry.explanation }}">{{ entry.title }}</abbr>
{% endif %}
```

Weitere Beispiele: [Auswahl-Feldtypen](docs/selection-field-types.de.md), [Smart Content](docs/smart-content.de.md), [Twig](docs/twig.de.md).

## 🧶 Konfiguration

```yaml
# config/packages/sulu_glossary.yaml
sulu_glossary:
    versioning:
        max_versions: 20   # Versionen pro Eintrag und Sprache, 0 = unbegrenzt
    twig:
        auto_abbr: false   # aktiviert den Twig-Filter abbr
```

Weitere Optionen: [Einstellungen](docs/settings.de.md)

## 📖 Dokumentation

Detaillierte Dokumentation im [docs/](docs/) Verzeichnis.

- [Typen](docs/types.de.md) - Abkürzung, Akronym, Begriff
- [Veröffentlichen](docs/publishing.de.md) - Entwurf, Live und Aktivitätsprotokoll
- [Übersetzungen](docs/translations.de.md) - In Sprache kopieren, Übersetzung löschen
- [Versionen](docs/versions.de.md) - Versionen und Wiederherstellung
- [Auswahl-Feldtypen](docs/selection-field-types.de.md) - Einträge in Templates auswählen
- [Smart Content](docs/smart-content.de.md) - Filtern nach Kategorien und Tags auf der Website
- [Teaser](docs/teaser.de.md) - Teaser-Provider
- [Twig](docs/twig.de.md) - Funktionen, `abbr`-Filter und Standard-View
- [Links, Sitemap & Vorschau](docs/links-sitemap-preview.de.md) - Links, Sitemap, Vorschau und Papierkorb
- [Suche](docs/search.de.md) - Admin- und Website-Index
- [Referenzen](docs/references.de.md) - Wo Einträge verwendet werden
- [HTTP-Cache](docs/http-cache.de.md) - Tagging und Invalidierung
- [Automatisierung](docs/automation.de.md) - Zeitgesteuertes Veröffentlichen
- [Einstellungen](docs/settings.de.md) - Header, Hero, Breadcrumbs, Übersichtsseite
- [Upgrade vom SuluAbbreviationsBundle](docs/upgrade-from-abbreviations.de.md) - Migration der Daten

## 👩‍🍳 Mitwirken

Beiträge sind willkommen! Bitte erstelle Issues oder Pull Requests.

## 📝 Lizenz

Dieses Bundle ist unter der MIT-Lizenz lizenziert. Siehe [LICENSE](LICENSE).

## 🎉 Credits

Erstellt und gewartet von [manuxi](https://github.com/manuxi).

Danke an das Sulu-Team für das tolle CMS und den fantastischen Support!

Und danke an *Dich* für Deine Mithilfe, Tests und Bugsuche!
