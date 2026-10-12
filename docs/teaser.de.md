# Teaser

Glossar-Einträge können im Teaser-Content-Typ von Sulu ausgewählt werden (Provider `glossary_entries`). Der Teaser-Provider `Manuxi\SuluGlossaryBundle\Teaser\GlossaryEntryTeaserProvider` ist mit dem Tag `sulu.teaser.provider` registriert.

```xml
<property name="teasers" type="teaser_selection">
    <meta>
        <title lang="de">Teaser</title>
    </meta>
</property>
```

Es werden nur Einträge geliefert, die in der aktuellen Sprache veröffentlicht sind. Der Teaser enthält

- **Titel**: den Excerpt-Titel, sonst den Titel des Eintrags
- **Beschreibung**: die Excerpt-Beschreibung, sonst die Erklärung (Klartext)
- **URL**: die URL des Eintrags (ohne Request-Kontext wird der Slug verwendet)
- **Medien**: das Excerpt-Bild, sonst das Bild des Eintrags
- **Mehr-Text**: den Excerpt-Text "mehr"
