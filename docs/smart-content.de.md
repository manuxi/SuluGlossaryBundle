# Smart Content

Mit dem Smart-Content-Provider `glossary_entries` werden Listen von Glossar-Einträgen angezeigt:

```xml
<property name="entries" type="smart_content">
    <meta>
        <title lang="de">Glossar</title>
    </meta>
    <params>
        <param name="provider" value="glossary_entries"/>
        <param name="max_per_page" value="5"/>
        <param name="page_parameter" value="page"/>
    </params>
</property>
```

Filter: Excerpt-Tags und -Kategorien, Limit, Paginierung, Darstellung und Sortierung. Es wird nur der Live-Inhalt der aktuellen Sprache geliefert.

```twig
{% for entry in entries %}
    <h2>{{ entry.title }}</h2>
    <p>{{ entry.explanation }}</p>
{% endfor %}
```

Die Elemente sind der normalisierte Inhalt: `id`, `title`, `type`, `explanation`, `description`, `image` (`{id: ...}`), `link`, `url`, `excerptTitle`, `seoTitle` usw. Die Ids sind UUIDs.
