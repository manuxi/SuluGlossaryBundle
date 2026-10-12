# Changelog

## 1.0.0

First release of SuluGlossaryBundle, the Sulu 3 successor of `manuxi/sulu-abbreviations-bundle`.

### Added

- Entity `GlossaryEntry` (UUID v7) with `GlossaryEntryDimensionContent` (draft and live per locale), fields `title`, `explanation`, `description`, `image`, `link` and the unlocalized field `type` (`abbreviation`, `acronym`, `term`)
- Content workflow with publish, unpublish, remove draft, copy to locale, delete translation and restore of versions
- Admin views, list, form, security contexts `sulu.glossary.entries` and `sulu.glossary.settings`, REST controllers with `#[Route]` attributes
- Domain events for the activity log (created, modified, removed, restored, published, unpublished, draft_removed, copied_language, translation_removed, translation_restored, version_restored)
- Content types `glossary_entry_selection` and `single_glossary_entry_selection`, resource loader `glossary_entry`
- Providers: smart content, teaser, link, sitemap, preview, route defaults (key `glossary_entries`)
- SEAL reindex providers for the indexes `admin` and `website`, update of the indexes on content changes
- HTTP cache tagging and invalidation, reference refresher, trash handler
- Version limit `sulu_glossary.versioning.max_versions` (default 20, 0 = unlimited)
- Twig functions `sulu_resolve_glossary_entry`, `sulu_get_glossary_entries`, `load_glossary_settings` and the optional filter `abbr` (`sulu_glossary.twig.auto_abbr`)
- Settings (header, hero, breadcrumbs, overview page)
- Scheduled publishing and unpublishing with `sulu/automation-bundle` (optional)
- Commands `sulu:glossary:migrate-from-2x` and `sulu:glossary:migrate-page-references` to migrate the data of SuluAbbreviationsBundle 2.0
- MySQL migration template in `docs/templates/doctrine-migration.php.dist`
- Documentation in English and German, unit and functional tests, CI for PHP 8.2 to 8.4 (PHPUnit, PHPStan level 6, PHP CS Fixer)
