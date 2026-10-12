# Settings

The settings (**Glossary > Settings**, security context `sulu.glossary.settings`) are a singleton entity (table `glossary_entry_settings`):

- Display header, hero and breadcrumbs on the detail page
- The overview page of the glossary (used as intermediate page for breadcrumbs)

Use them in templates:

```twig
{% set settings = load_glossary_settings() %}
{% if settings.toggleBreadcrumbs %}
    ...
{% endif %}
```

REST: `GET|PUT /admin/api/glossary-settings` (route names `sulu_glossary.get_glossary_settings` and `sulu_glossary.put_glossary_settings`). Changes are written to the activity log.
