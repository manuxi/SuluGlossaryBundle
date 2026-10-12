# HTTP cache

The bundle tags the responses of the website and purges them when a glossary entry changes. It works with the HTTP cache of Sulu (`sulu_http_cache`); without the cache bundle nothing is purged and nothing breaks.

## Tagging

The reference store collects the ids of all entries used while a page is rendered:

- the detail page of an entry
- the content types `glossary_entry_selection` / `single_glossary_entry_selection`
- the Twig functions `sulu_resolve_glossary_entry()` / `sulu_get_glossary_entries()`
- the smart content provider `glossary_entries`

Responses which contain a **list** of entries (`sulu_get_glossary_entries()`, smart content) are additionally tagged with the marker `list` of the resource `glossary_entries`, so a newly published entry also purges these pages.

## Purging

`Manuxi\SuluGlossaryBundle\EventSubscriber\GlossaryEntryCacheInvalidationSubscriber` purges the tags of the entry, the list marker, the excerpt tags and the excerpt categories when an entry is published, unpublished, deleted, restored from the trash or when a translation is deleted. The invalidation is executed by the cache manager of Sulu, also for automated transitions.
