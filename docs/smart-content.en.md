# Smart content

Use the smart content provider `glossary_entries` to display lists of glossary entries:

```xml
<property name="entries" type="smart_content">
    <meta>
        <title lang="en">Glossary</title>
    </meta>
    <params>
        <param name="provider" value="glossary_entries"/>
        <param name="max_per_page" value="5"/>
        <param name="page_parameter" value="page"/>
    </params>
</property>
```

Filters: excerpt tags and categories, limit, pagination, present as and sorting. Only the live content of the current locale is returned.

```twig
{% for entry in entries %}
    <h2>{{ entry.title }}</h2>
    <p>{{ entry.explanation }}</p>
{% endfor %}
```

The items are the normalized content: `id`, `title`, `type`, `explanation`, `description`, `image` (`{id: ...}`), `link`, `url`, `excerptTitle`, `seoTitle` etc. The ids are UUIDs.
