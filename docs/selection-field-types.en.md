# Selection field types

Two field types are available in page, snippet and other templates:

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
```

- `glossary_entry_selection`: multiple selection (list overlay), the order of the selection is kept.
- `single_glossary_entry_selection`: single selection.

The stored values are the UUIDs of the entries. In the template the **normalized live content** of the current locale is available (resolved by `GlossaryEntryResourceLoader`, resource loader key `glossary_entry`). Entries which are not published in the current locale are skipped (multiple selection) or `null` (single selection).

```twig
{% for entry in content.entries %}
    {{ entry.title }} ({{ entry.type }}): {{ entry.explanation }}
{% endfor %}

{% if content.entry %}
    {{ content.entry.title }}
{% endif %}
```
