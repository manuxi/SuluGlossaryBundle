# SuluAbbreviationsBundle
![CI](https://github.com/manuxi/SuluAbbreviationsBundle/actions/workflows/ci.yml/badge.svg?branch=2.x)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://github.com/manuxi/SuluAbbreviationsBundle/LICENSE)
![GitHub Tag](https://img.shields.io/github/v/tag/manuxi/SuluAbbreviationsBundle)
![Supports Sulu 2.6](https://img.shields.io/badge/%20Sulu-2.6-0088cc?color=00b2df)
![PHP 8.2+](https://img.shields.io/badge/PHP-%3E%3D8.2-777bb4)

[Deutsche Version](README.de.md)

A Sulu bundle to manage abbreviations (glossary entries) in your projects. Version 2 is built on the `sulu/content-bundle`, the same concepts as in Sulu 3.

## Features
- Translatable content with **draft and live** version per locale, publish / unpublish / remove draft, scheduled publishing with the automation bundle ([publishing](docs/publishing.en.md))
- Structure template (`name`, `url`, `explanation`, `description`, `image`, `link`), SEO and excerpt tabs, author
- **Copy to locale** and **delete a single translation** ([translations](docs/translations.en.md))
- **Versions** of every publish with restore ([versions](docs/versions.en.md))
- **References**: where an abbreviation is used and what it uses ([references](docs/references.en.md))
- **HTTP cache** tagging and purging ([http cache](docs/http-cache.en.md))
- Teaser provider ([teaser](docs/teaser.en.md)), link provider, sitemap provider, smart content provider ([smart content](docs/smart-content.en.md))
- Field types `abbreviation_selection` / `single_abbreviation_selection` ([field types](docs/selection-field-types.en.md))
- Twig functions and the optional filter `abbr` ([twig](docs/twig.en.md)), settings ([settings](docs/settings.en.md))
- Preview, trash, activity log, search indexes (draft and live)
- Migration of the data of version 1.x ([upgrade](docs/upgrade-1-to-2.en.md))

Please feel comfortable submitting feature requests.
This bundle is still in development. Use at own risk 🤞🏻

![image](https://github.com/user-attachments/assets/fbd68da1-710d-436c-bee2-9f83a7a8ca32)

## 👩🏻‍🏭 Installation
Install the package with:
```console
composer require manuxi/sulu-abbreviations-bundle
```
If you're *not* using Symfony Flex, you'll also need to register the bundles in your `config/bundles.php` file.
The `SuluContentBundle` has to be registered **before** this bundle:

```php
return [
    //...
    Sulu\Bundle\ContentBundle\SuluContentBundle::class => ['all' => true],
    Manuxi\SuluAbbreviationsBundle\SuluAbbreviationsBundle::class => ['all' => true],
];
```
Please add the following to your `routes_admin.yaml`:
```yaml
SuluAbbreviationsBundle:
    resource: '@SuluAbbreviationsBundle/Resources/config/routes_admin.yaml'
```
Add the index of the published abbreviations to your `sulu_search.yaml`. "abbreviations_published" is the index of the live content, "abbreviations" the index of the drafts. Both indexes are searchable in the admin.
```yaml
sulu_search:
    website:
        indexes:
            - abbreviations_published
            - ...
```
Optionally install `sulu/automation-bundle` (scheduled publishing) and use the HTTP cache of Sulu (`sulu_http_cache`).

Last but not least the database schema needs to be updated. The tables `abbr_abbreviations`, `abbr_abbreviation_dimension_contents`, `abbr_abbreviation_dimension_content_excerpt_tags`, `abbr_abbreviation_dimension_content_excerpt_categories`, `abbr_abbreviation_versions` and `app_abbreviation_settings` are used. Create a Doctrine migration with
```console
php bin/console doctrine:migrations:diff
```
or copy the [migration template](docs/templates/doctrine-migration.php.dist) (MySQL / MariaDB). Without migrations use `php bin/console doctrine:schema:update --dump-sql` / `--force`. Make sure you only process the schema updates of this bundle!

Upgrading from 1.x? Read the [upgrade guide](docs/upgrade-1-to-2.en.md).

## 🎣 Usage
First: Grant permissions for abbreviations (security context `sulu.abbreviations.abbreviations`, permissions view, add, edit, delete and live).
After reload you should see the abbreviations item in the navigation. Start to create abbreviations.

Use the `smart_content` property type to show a list of abbreviations:
```xml
<property name="abbreviations" type="smart_content">
    <meta>
        <title lang="en">Abbreviations</title>
    </meta>
    <params>
        <param name="provider" value="abbreviations"/>
        <param name="max_per_page" value="5"/>
        <param name="page_parameter" value="page"/>
    </params>
</property>
```
Use the field types `abbreviation_selection` (multiple) or `single_abbreviation_selection` (single) to select abbreviations in a template. Only abbreviations published in the current locale are returned, in the order of the selection.

```twig
{% for abbreviation in abbreviations %}
    <h2>{{ abbreviation.name }}</h2>
    <p>{{ abbreviation.explanation|raw }}</p>
{% endfor %}
```

The detail page of an abbreviation is rendered with the template `@SuluAbbreviations/abbreviation.html.twig` (variable `content`), override it with `templates/bundles/SuluAbbreviationsBundle/abbreviation.html.twig`.
Further templates of the type `abbreviation` can be added through `sulu_core.content.structure.paths`.

### Twig functions
- `sulu_resolve_abbreviation(id, locale = null)`: returns the live content of the abbreviation if it is published in the given locale, otherwise `null`.
- `sulu_get_abbreviations(limit = 100, locale = null)`: returns the published abbreviations of the given locale.
- `abbr` filter (optional): wraps the known abbreviations of a text in `<abbr>`.

## 🧶 Configuration
```yaml
sulu_abbreviations:
    versioning:
        max_versions: 20 # versions per abbreviation and locale, 0 disables versioning
    twig:
        auto_abbr: false # registers the twig filter "abbr"
```
The settings in the admin (Abbreviations > Settings) control the single view (header, default hero, breadcrumbs) and the intermediate page for the breadcrumbs.

See [CHANGELOG.md](CHANGELOG.md) for all changes.

## 👩‍🍳 Contributing
For the sake of simplicity this extension was kept small.
Please feel comfortable submitting issues or pull requests. As always I'd be glad to get your feedback to improve the extension :).
