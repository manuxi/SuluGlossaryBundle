# Referenzen

Das Bundle nutzt das Referenz-Modul von Sulu. Der Reiter **Einblicke > Referenzen** eines Glossar-Eintrags listet die Seiten, Snippets und weiteren Inhalte auf, die ihn referenzieren.

## Wo wird ein Eintrag verwendet?

- Die Content-Typen `glossary_entry_selection` und `single_glossary_entry_selection` melden ihre Auswahl (auch innerhalb von Blöcken).
- Links, die mit dem Texteditor erstellt wurden (`provider="glossary_entries"`), werden von Sulu selbst erfasst.

## Was verwendet ein Eintrag?

Für den Entwurf (Kontext `admin`) und die Live-Version (Kontext `website`) werden die Referenzen auf Medien (Template-Properties und Excerpt-Bild), den Autor-Kontakt, Excerpt-Kategorien und Excerpt-Tags gespeichert, sodass die Listen von Medien, Kontakten, Kategorien und Tags zeigen, wo sie verwendet werden. Die Referenzen werden von `Manuxi\SuluGlossaryBundle\Reference\GlossaryEntryReferenceRefresher` aktualisiert.

## Neu aufbauen

```console
bin/console sulu:reference:refresh glossary_entries
```
