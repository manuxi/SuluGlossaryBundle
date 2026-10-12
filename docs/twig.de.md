# Twig

## Funktionen

- `sulu_resolve_glossary_entry(id, locale = null)`: liefert den normalisierten Live-Inhalt des Eintrags oder `null`, wenn er in der Sprache nicht veröffentlicht ist.
- `sulu_get_glossary_entries(limit = 100, locale = null)`: liefert die veröffentlichten Einträge der Sprache, sortiert nach Titel.
- `load_glossary_settings()`: liefert die [Einstellungen](settings.de.md).

Ohne Sprache wird die Sprache des aktuellen Requests verwendet. Die Ids sind UUIDs.

```twig
{% set entry = sulu_resolve_glossary_entry(id) %}
{% if entry %}
    <abbr title="{{ entry.explanation }}">{{ entry.title }}</abbr>
{% endif %}

{% for entry in sulu_get_glossary_entries(20) %}
    {{ entry.title }}
{% endfor %}
```

Der Inhalt ist ein Array mit u. a. `id`, `title`, `type`, `explanation`, `description`, `image`, `link`, `url`, `locale`, `excerptTitle`, `seoTitle`. Bilder werden über die Id referenziert: `sulu_resolve_media(entry.image.id, app.request.locale)`.

## Filter `abbr` (optional)

Der Filter umschließt jede bekannte Abkürzung eines Textes mit `<abbr title="Erklärung">`. Text innerhalb von `a`, `abbr`, `code`, `pre`, `script` und `style` bleibt unverändert. Standardmäßig ist er deaktiviert:

```yaml
sulu_glossary:
    twig:
        auto_abbr: true
```

```twig
{{ content.article|abbr }}
```

## Standard-View

Die Standard-View `@SuluGlossary/glossary_entries/default.html.twig` erweitert `base.html.twig` und rendert den Block `content`. Überschreiben mit `templates/bundles/SuluGlossaryBundle/glossary_entries/default.html.twig`; die Variable `content` enthält den normalisierten Live-Inhalt.
