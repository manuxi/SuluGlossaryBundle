# SuluAbbreviationsBundle
![CI](https://github.com/manuxi/SuluAbbreviationsBundle/actions/workflows/ci.yml/badge.svg?branch=2.x)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://github.com/manuxi/SuluAbbreviationsBundle/LICENSE)
![GitHub Tag](https://img.shields.io/github/v/tag/manuxi/SuluAbbreviationsBundle)
![Supports Sulu 2.6](https://img.shields.io/badge/%20Sulu-2.6-0088cc?color=00b2df)
![PHP 8.2+](https://img.shields.io/badge/PHP-%3E%3D8.2-777bb4)

[English version](README.md)

Ein Sulu-Bundle zur Verwaltung von Abkürzungen (Glossar-Einträgen) in Projekten. Version 2 basiert auf dem `sulu/content-bundle` und damit auf denselben Konzepten wie Sulu 3.

## Funktionen
- Übersetzbare Inhalte mit **Entwurf und Live**-Version je Sprache, Veröffentlichen / Veröffentlichung beenden / Entwurf verwerfen, zeitgesteuertes Veröffentlichen mit dem Automation-Bundle ([Veröffentlichen](docs/publishing.de.md))
- Struktur-Template (`name`, `url`, `explanation`, `description`, `image`, `link`), Tabs für SEO und Excerpt, Autor
- **In Sprache kopieren** und **einzelne Übersetzung löschen** ([Übersetzungen](docs/translations.de.md))
- **Versionen** bei jeder Veröffentlichung mit Wiederherstellung ([Versionen](docs/versions.de.md))
- **Referenzen**: wo eine Abkürzung verwendet wird und was sie selbst verwendet ([Referenzen](docs/references.de.md))
- **HTTP-Cache**: Tagging und Leeren ([HTTP-Cache](docs/http-cache.de.md))
- Teaser-Provider ([Teaser](docs/teaser.de.md)), Link-Provider, Sitemap-Provider, Smart-Content-Provider ([Smart Content](docs/smart-content.de.md))
- Feldtypen `abbreviation_selection` / `single_abbreviation_selection` ([Feldtypen](docs/selection-field-types.de.md))
- Twig-Funktionen und der optionale Filter `abbr` ([Twig](docs/twig.de.md)), Einstellungen ([Einstellungen](docs/settings.de.md))
- Vorschau, Papierkorb, Aktivitätenprotokoll, Suchindizes (Entwurf und Live)
- Migration der Daten der Version 1.x ([Upgrade](docs/upgrade-1-to-2.de.md))

Feature-Wünsche sind jederzeit willkommen.
Das Bundle befindet sich weiterhin in Entwicklung. Nutzung auf eigene Gefahr 🤞🏻

![image](https://github.com/user-attachments/assets/fbd68da1-710d-436c-bee2-9f83a7a8ca32)

## 👩🏻‍🏭 Installation
Paket installieren:
```console
composer require manuxi/sulu-abbreviations-bundle
```
Ohne Symfony Flex müssen die Bundles zusätzlich in `config/bundles.php` registriert werden.
Das `SuluContentBundle` muss **vor** diesem Bundle registriert werden:

```php
return [
    //...
    Sulu\Bundle\ContentBundle\SuluContentBundle::class => ['all' => true],
    Manuxi\SuluAbbreviationsBundle\SuluAbbreviationsBundle::class => ['all' => true],
];
```
Folgendes in die `routes_admin.yaml` eintragen:
```yaml
SuluAbbreviationsBundle:
    resource: '@SuluAbbreviationsBundle/Resources/config/routes_admin.yaml'
```
Den Index der veröffentlichten Abkürzungen in der `sulu_search.yaml` ergänzen. "abbreviations_published" ist der Index des Live-Inhalts, "abbreviations" der Index der Entwürfe. Beide Indizes sind im Admin durchsuchbar.
```yaml
sulu_search:
    website:
        indexes:
            - abbreviations_published
            - ...
```
Optional `sulu/automation-bundle` (zeitgesteuertes Veröffentlichen) installieren und den HTTP-Cache von Sulu (`sulu_http_cache`) verwenden.

Zum Schluss muss das Datenbankschema aktualisiert werden. Verwendet werden die Tabellen `abbr_abbreviations`, `abbr_abbreviation_dimension_contents`, `abbr_abbreviation_dimension_content_excerpt_tags`, `abbr_abbreviation_dimension_content_excerpt_categories`, `abbr_abbreviation_versions` und `app_abbreviation_settings`. Eine Doctrine-Migration erzeugen mit
```console
php bin/console doctrine:migrations:diff
```
oder die [Migrations-Vorlage](docs/templates/doctrine-migration.php.dist) kopieren (MySQL / MariaDB). Ohne Migrationen `php bin/console doctrine:schema:update --dump-sql` / `--force` verwenden. Dabei nur die Schema-Änderungen dieses Bundles ausführen!

Upgrade von 1.x? Den [Upgrade-Leitfaden](docs/upgrade-1-to-2.de.md) lesen.

## 🎣 Verwendung
Zuerst die Berechtigungen für Abkürzungen vergeben (Sicherheitskontext `sulu.abbreviations.abbreviations`, Berechtigungen view, add, edit, delete und live).
Nach einem Neuladen erscheint der Menüpunkt für Abkürzungen in der Navigation. Danach können Abkürzungen angelegt werden.

Für eine Liste von Abkürzungen den Property-Typ `smart_content` verwenden:
```xml
<property name="abbreviations" type="smart_content">
    <meta>
        <title lang="de">Abkürzungen</title>
    </meta>
    <params>
        <param name="provider" value="abbreviations"/>
        <param name="max_per_page" value="5"/>
        <param name="page_parameter" value="page"/>
    </params>
</property>
```
Zur Auswahl einzelner Abkürzungen in einem Template die Feldtypen `abbreviation_selection` (mehrere) oder `single_abbreviation_selection` (eine) verwenden. Es werden nur in der aktuellen Sprache veröffentlichte Abkürzungen in der Reihenfolge der Auswahl geliefert.

```twig
{% for abbreviation in abbreviations %}
    <h2>{{ abbreviation.name }}</h2>
    <p>{{ abbreviation.explanation|raw }}</p>
{% endfor %}
```

Die Detailseite einer Abkürzung wird mit dem Template `@SuluAbbreviations/abbreviation.html.twig` gerendert (Variable `content`), überschreibbar mit `templates/bundles/SuluAbbreviationsBundle/abbreviation.html.twig`.
Weitere Templates des Typs `abbreviation` lassen sich über `sulu_core.content.structure.paths` ergänzen.

### Twig-Funktionen
- `sulu_resolve_abbreviation(id, locale = null)`: liefert den Live-Inhalt der Abkürzung, wenn sie in der Sprache veröffentlicht ist, sonst `null`.
- `sulu_get_abbreviations(limit = 100, locale = null)`: liefert die veröffentlichten Abkürzungen der Sprache.
- Filter `abbr` (optional): umschließt die bekannten Abkürzungen eines Textes mit `<abbr>`.

## 🧶 Konfiguration
```yaml
sulu_abbreviations:
    versioning:
        max_versions: 20 # Versionen je Abkürzung und Sprache, 0 deaktiviert die Versionierung
    twig:
        auto_abbr: false # registriert den Twig-Filter "abbr"
```
Die Einstellungen im Admin (Abkürzungen > Einstellungen) steuern die Einzelansicht (Header, Standard-Hero, Breadcrumbs) und die Zwischenseite für die Breadcrumbs.

Alle Änderungen stehen in der [CHANGELOG.md](CHANGELOG.md).

## 👩‍🍳 Mitwirken
Der Einfachheit halber wurde die Erweiterung klein gehalten.
Issues und Pull Requests sind jederzeit willkommen. Über Feedback zur Verbesserung freue ich mich :).
