# SuluGlossaryBundle!
![ci workflow](https://github.com/manuxi/SuluGlossaryBundle/actions/workflows/ci.yml/badge.svg)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://github.com/manuxi/SuluGlossaryBundle/blob/1.x/LICENSE)
![GitHub Tag](https://img.shields.io/github/v/tag/manuxi/SuluGlossaryBundle)
![Github Release](https://img.shields.io/github/v/release/manuxi/SuluGlossaryBundle?color=116ea3)
![Supports Sulu 3.0 or later](https://img.shields.io/badge/%20Sulu->=3.0-0088cc?color=00b2df)

[🇩🇪 German Version](README.de.md)

The SuluGlossaryBundle extends Sulu 3.0 with a glossary for abbreviations, acronyms and terms.

Every entry has a type, an explanation, an optional description, image and link, and its own detail page. Entries use the content features of Sulu: draft and live content per locale, versions, SEO and excerpt data, search, references and scheduled publishing.

This bundle is the successor of `manuxi/sulu-abbreviations-bundle` (Sulu 2). The data of the 2.0 release can be migrated with a command, see [UPGRADE.md](UPGRADE.md).

## ✨ Features

### 📚 Glossary Management
- **Entry Types** - Abbreviation, acronym or term
- **Rich Content** - Title, explanation, description, image and link
- **Draft & Live** - Separate draft and live content per locale, publish and unpublish
- **Versions** - Restore older versions into the draft
- **SEO & Excerpt** - Full SEO and excerpt management
- **Multilingual** - Full translation support, copy to locale
- **Author Management** - Assign contacts as authors
- **Settings** - Header, hero, breadcrumbs and overview page
- **More** - Trash, references, sitemaps, teaser, preview, activity log, etc.

### 🔄 Advanced Features
- **Selection Field Types** - Select one or multiple entries in pages, snippets and other templates
- **Smart Content** - List entries filtered by categories and tags
- **Twig Functions** - Resolve entries in templates, optional `abbr` filter for automatic markup
- **Search** - Admin and website index (SEAL)
- **HTTP Cache** - Tagging and automatic invalidation
- **Scheduled Publishing** - Publish and unpublish at a given date (`sulu/automation-bundle`)
- **Migration** - Command to migrate the data of SuluAbbreviationsBundle 2.0

## 📋 Prerequisites

- PHP 8.2 or higher
- Sulu CMS 3.0 or higher
- Symfony 6.4 or higher
- MySQL 5.7+ / MariaDB 10.2+ / PostgreSQL 11+

## 👩🏻‍🏭 Installation

### Step 1: Install the package

```bash
composer require manuxi/sulu-glossary-bundle
```

If you are *not* using Symfony Flex, add the bundle to `config/bundles.php`:

```php
return [
    //...
    Manuxi\SuluGlossaryBundle\SuluGlossaryBundle::class => ['all' => true],
];
```

### Step 2: Configure routes

Add to `routes_admin.yaml`:

```yaml
SuluGlossaryBundle:
    resource: '@SuluGlossaryBundle/Resources/config/routes_admin.yaml'
```

### Step 3: Update the database

```bash
# Check what will be created
php bin/console doctrine:schema:update --dump-sql

# Execute migration
php bin/console doctrine:schema:update --force
```

With Doctrine migrations use `doctrine:migrations:diff`. A MySQL migration template is available in [docs/templates/doctrine-migration.php.dist](docs/templates/doctrine-migration.php.dist).

### Step 4: Rebuild the search index

```bash
php bin/console sulu:search:reindex
```

### Step 5: Grant permissions

1. Go to Sulu Admin → Settings → User Roles
2. Find the appropriate role
3. Enable the permissions for the glossary entries (`sulu.glossary.entries`) and the glossary settings (`sulu.glossary.settings`)
4. Reload the page

## 🎣 Usage

### Create your first entry

1. Navigate to **Glossary** in the Sulu admin navigation
2. Click on **Add**
3. Choose the type, enter title and explanation
4. Add description, image and link (optional)
5. Publish your entry

### Use entries in templates

```xml
<property name="entries" type="glossary_entry_selection">
    <meta>
        <title lang="en">Glossary entries</title>
    </meta>
</property>
```

```twig
{% set entry = sulu_resolve_glossary_entry(id) %}
{% if entry %}
    <abbr title="{{ entry.explanation }}">{{ entry.title }}</abbr>
{% endif %}
```

More examples: [Selection field types](docs/selection-field-types.en.md), [Smart content](docs/smart-content.en.md), [Twig](docs/twig.en.md).

## 🧶 Configuration

```yaml
# config/packages/sulu_glossary.yaml
sulu_glossary:
    versioning:
        max_versions: 20   # versions per entry and locale, 0 = unlimited
    twig:
        auto_abbr: false   # enables the abbr twig filter
```

Further options: [Settings](docs/settings.en.md)

## 📖 Documentation

Detailed documentation in the [docs/](docs/) directory.

- [Types](docs/types.en.md) - Abbreviation, acronym, term
- [Publishing](docs/publishing.en.md) - Draft, live and the activity log
- [Translations](docs/translations.en.md) - Copy to locale, delete a translation
- [Versions](docs/versions.en.md) - Versions and restore
- [Selection field types](docs/selection-field-types.en.md) - Select entries in templates
- [Smart content](docs/smart-content.en.md) - Filtering by categories and tags on the website
- [Teaser](docs/teaser.en.md) - Teaser provider
- [Twig](docs/twig.en.md) - Functions, `abbr` filter and default view
- [Links, sitemap & preview](docs/links-sitemap-preview.en.md) - Links, sitemap, preview and trash
- [Search](docs/search.en.md) - Admin and website index
- [References](docs/references.en.md) - Where entries are used
- [HTTP cache](docs/http-cache.en.md) - Tagging and invalidation
- [Automation](docs/automation.en.md) - Scheduled publishing
- [Settings](docs/settings.en.md) - Header, hero, breadcrumbs, overview page
- [Upgrade from SuluAbbreviationsBundle](docs/upgrade-from-abbreviations.en.md) - Migration of the data

## 👩‍🍳 Contributing

Contributions are welcome! Please create issues or pull requests.

## 📝 License

This bundle is licensed under the MIT License. See [LICENSE](LICENSE).

## 🎉 Credits

Created and maintained by [manuxi](https://github.com/manuxi).

Thanks to the Sulu team for the great CMS and fantastic support!

And thank *you* for your support and testing!
