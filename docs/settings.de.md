# Einstellungen

Die Einstellungen (**Glossar > Einstellungen**, Sicherheitskontext `sulu.glossary.settings`) sind eine Singleton-Entität (Tabelle `glossary_entry_settings`):

- Header, Hero und Breadcrumbs auf der Detailseite anzeigen
- Die Übersichtsseite des Glossars (als Zwischenseite für Breadcrumbs)

Verwendung in Templates:

```twig
{% set settings = load_glossary_settings() %}
{% if settings.toggleBreadcrumbs %}
    ...
{% endif %}
```

REST: `GET|PUT /admin/api/glossary-settings` (Routen `sulu_glossary.get_glossary_settings` und `sulu_glossary.put_glossary_settings`). Änderungen werden im Aktivitätsprotokoll protokolliert.
