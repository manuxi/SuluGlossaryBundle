# Teaser

Glossary entries can be selected in the teaser content type of Sulu (provider `glossary_entries`). The teaser provider `Manuxi\SuluGlossaryBundle\Teaser\GlossaryEntryTeaserProvider` is registered with the tag `sulu.teaser.provider`.

```xml
<property name="teasers" type="teaser_selection">
    <meta>
        <title lang="en">Teasers</title>
    </meta>
</property>
```

Only entries published in the current locale are returned. The teaser contains

- **title**: the excerpt title, otherwise the title of the entry
- **description**: the excerpt description, otherwise the explanation (plain text)
- **url**: the url of the entry (without a request context the slug is used)
- **media**: the excerpt image, otherwise the image of the entry
- **more text**: the excerpt "more" text
