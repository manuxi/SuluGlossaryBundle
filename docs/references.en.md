# References

The bundle uses the reference module of Sulu. The tab **Insights > References** of a glossary entry lists the pages, snippets and other content which reference it.

## Where is an entry used?

- The content types `glossary_entry_selection` and `single_glossary_entry_selection` report their selection (also inside blocks).
- Links created with the text editor (`provider="glossary_entries"`) are collected by Sulu itself.

## What does an entry use?

For the draft (context `admin`) and the live version (context `website`) the references to media (template properties and excerpt image), the author contact, excerpt categories and excerpt tags are stored, so the media, contact, category and tag lists show where they are used. The references are refreshed by `Manuxi\SuluGlossaryBundle\Reference\GlossaryEntryReferenceRefresher`.

## Rebuild

```console
bin/console sulu:reference:refresh glossary_entries
```
