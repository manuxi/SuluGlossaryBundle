# Twig

## Functions

- `sulu_resolve_glossary_entry(id, locale = null)`: returns the normalized live content of the entry or `null` if it is not published in the locale.
- `sulu_get_glossary_entries(limit = 100, locale = null)`: returns the published entries of the locale, ordered by title.
- `load_glossary_settings()`: returns the [settings](settings.en.md).

Without a locale the locale of the current request is used. The ids are UUIDs.

```twig
{% set entry = sulu_resolve_glossary_entry(id) %}
{% if entry %}
    <abbr title="{{ entry.explanation }}">{{ entry.title }}</abbr>
{% endif %}

{% for entry in sulu_get_glossary_entries(20) %}
    {{ entry.title }}
{% endfor %}
```

The content is an array with, among others, `id`, `title`, `type`, `explanation`, `description`, `image`, `link`, `url`, `locale`, `excerptTitle`, `seoTitle`. Images are referenced by id: `sulu_resolve_media(entry.image.id, app.request.locale)`.

## Filter `abbr` (optional)

The filter wraps every known abbreviation of a text in `<abbr title="explanation">`. Text inside `a`, `abbr`, `code`, `pre`, `script` and `style` is not changed. It is disabled by default:

```yaml
sulu_glossary:
    twig:
        auto_abbr: true
```

```twig
{{ content.article|abbr }}
```

## Default view

The default view `@SuluGlossary/glossary_entries/default.html.twig` extends `base.html.twig` and renders the block `content`. Override it with `templates/bundles/SuluGlossaryBundle/glossary_entries/default.html.twig`; the variable `content` contains the normalized live content.
