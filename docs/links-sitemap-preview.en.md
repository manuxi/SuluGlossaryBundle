# Links, sitemap and preview

## Links

The link provider `glossary_entries` makes entries available in the link dialog of the text editor and in link properties. In the website the link resolves to the url of the live entry; entries without a live route are skipped.

## Sitemap

`GlossaryEntrySitemapProvider` adds the published entries (with alternate locales) to the sitemap of Sulu.

## Preview

The form of an entry shows a preview of the detail page with the unsaved draft (`sulu_glossary.preview_object_provider`).

## Trash

Deleted entries are stored in the trash (`GlossaryEntryTrashItemHandler`) and can be restored with all locales and routes.
