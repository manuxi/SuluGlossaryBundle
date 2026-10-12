# Auswahl-Feldtypen

In Seiten-, Snippet- und anderen Templates stehen zwei Feldtypen zur Verfügung:

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
```

- `glossary_entry_selection`: Mehrfachauswahl (Listen-Overlay), die Reihenfolge der Auswahl bleibt erhalten.
- `single_glossary_entry_selection`: Einzelauswahl.

Gespeichert werden die UUIDs der Einträge. Im Template steht der **normalisierte Live-Inhalt** der aktuellen Sprache zur Verfügung (aufgelöst durch `GlossaryEntryResourceLoader`, Resource-Loader-Key `glossary_entry`). Einträge, die in der aktuellen Sprache nicht veröffentlicht sind, werden übersprungen (Mehrfachauswahl) oder sind `null` (Einzelauswahl).

```twig
{% for entry in content.entries %}
    {{ entry.title }} ({{ entry.type }}): {{ entry.explanation }}
{% endfor %}

{% if content.entry %}
    {{ content.entry.title }}
{% endif %}
```
