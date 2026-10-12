# SuluGlossaryBundle

[Deutsche Version](README.de.md)

Glossary bundle for Sulu 3. It manages abbreviations, acronyms and terms with an explanation, a detail page per entry and the content features of Sulu: draft and live content per locale, versions, SEO and excerpt data, search, references, trash and a scheduled publishing.

This bundle is the successor of `manuxi/sulu-abbreviations-bundle` (Sulu 2). A command migrates the data of the 2.0 release, see [UPGRADE.md](UPGRADE.md).

## Features

- Glossary entries with a **type** (`abbreviation`, `acronym`, `term`), title, explanation, description, image and link
- Draft and live content per locale, [publishing](docs/publishing.en.md) and [translations](docs/translations.en.md)
- [Versions](docs/versions.en.md) with restore
- SEO, excerpt, categories, tags and author
- Detail page with a route per locale, [sitemap](docs/links-sitemap-preview.en.md), links, preview
- [Selection field types](docs/selection-field-types.en.md), [smart content](docs/smart-content.en.md) and [teaser](docs/teaser.en.md) provider
- [Twig functions](docs/twig.en.md) and an optional `abbr` filter
- [Search](docs/search.en.md) (admin and website index), [references](docs/references.en.md), [HTTP cache](docs/http-cache.en.md)
- [Scheduled publishing](docs/automation.en.md) with `sulu/automation-bundle`
- Trash and activity log
- [Settings](docs/settings.en.md) (header, hero, breadcrumbs, overview page)

## Requirements

- PHP 8.2 or higher
- Sulu 3.0 (`sulu/sulu ^3.0`)

## Installation

```console
composer require manuxi/sulu-glossary-bundle
```

Register the bundle in `config/bundles.php`:

```php
return [
    // ...
    Manuxi\SuluGlossaryBundle\SuluGlossaryBundle::class => ['all' => true],
];
```

Import the admin routes in `config/routes/sulu_admin.yaml`:

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

Create the database tables (`glossary_entries`, `glossary_entry_dimension_contents`, `glossary_entry_settings`):

```console
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

A MySQL migration template is available in [docs/templates/doctrine-migration.php.dist](docs/templates/doctrine-migration.php.dist).

Rebuild the search indexes (the bundle registers a reindex provider for the `admin` and the `website` index of the SEAL search of Sulu):

```console
bin/console sulu:search:reindex
```

Grant the permissions in **Settings > User roles**: the security context `sulu.glossary.entries` (view, add, edit, delete, live) and `sulu.glossary.settings` (view, edit).

## Usage

### Selection and smart content

```xml
<property name="entries" type="glossary_entry_selection">
    <meta>
        <title lang="en">Glossary entries</title>
    </meta>
</property>

<property name="entry" type="single_glossary_entry_selection">
    <meta>
        <title lang="en">Glossary entry</title>
    </meta>
</property>

<property name="list" type="smart_content">
    <meta>
        <title lang="en">Glossary</title>
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

### Custom templates

The bundle ships the template `default`. Add own templates in `config/templates/glossary_entries/*.xml` (template type `glossary_entry`) and own views in `templates/`.

## Configuration

```yaml
# config/packages/sulu_glossary.yaml
sulu_glossary:
    versioning:
        max_versions: 20   # versions per entry and locale, 0 = unlimited
    twig:
        auto_abbr: false   # enables the abbr twig filter
```

## Documentation

All feature documents are available in English and German in the [docs](docs) directory.

## Contributing

Pull requests are welcome. Please run the checks before:

```console
composer test
composer phpstan
composer cs
```

## License

MIT, see [LICENSE](LICENSE).
