# HTTP-Cache

Das Bundle versieht die Antworten der Website mit Tags und leert den Cache, wenn sich ein Glossar-Eintrag ändert. Es arbeitet mit dem HTTP-Cache von Sulu (`sulu_http_cache`); ohne das Cache-Bundle wird nichts geleert und nichts geht kaputt.

## Tagging

Der Reference-Store sammelt die Ids aller Einträge, die beim Rendern einer Seite verwendet werden:

- die Detailseite eines Eintrags
- die Content-Typen `glossary_entry_selection` / `single_glossary_entry_selection`
- die Twig-Funktionen `sulu_resolve_glossary_entry()` / `sulu_get_glossary_entries()`
- der Smart-Content-Provider `glossary_entries`

Antworten mit einer **Liste** von Einträgen (`sulu_get_glossary_entries()`, Smart Content) erhalten zusätzlich die Markierung `list` der Ressource `glossary_entries`, damit ein neu veröffentlichter Eintrag auch diese Seiten leert.

## Leeren

`Manuxi\SuluGlossaryBundle\EventSubscriber\GlossaryEntryCacheInvalidationSubscriber` leert die Tags des Eintrags, die Listen-Markierung, die Excerpt-Tags und die Excerpt-Kategorien, wenn ein Eintrag veröffentlicht, zurückgezogen, gelöscht, aus dem Papierkorb wiederhergestellt oder eine Übersetzung gelöscht wird. Die Invalidierung übernimmt der Cache-Manager von Sulu, auch bei automatisierten Übergängen.
