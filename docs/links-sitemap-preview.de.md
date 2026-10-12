# Links, Sitemap und Vorschau

## Links

Der Link-Provider `glossary_entries` stellt Einträge im Link-Dialog des Texteditors und in Link-Properties bereit. Auf der Website wird der Link zur URL des Live-Eintrags aufgelöst; Einträge ohne Live-Route werden übersprungen.

## Sitemap

`GlossaryEntrySitemapProvider` fügt die veröffentlichten Einträge (mit alternativen Sprachen) zur Sitemap von Sulu hinzu.

## Vorschau

Das Formular eines Eintrags zeigt eine Vorschau der Detailseite mit dem ungespeicherten Entwurf (`sulu_glossary.preview_object_provider`).

## Papierkorb

Gelöschte Einträge werden im Papierkorb abgelegt (`GlossaryEntryTrashItemHandler`) und können mit allen Sprachen und Routen wiederhergestellt werden.
