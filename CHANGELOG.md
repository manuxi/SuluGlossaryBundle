# Changelog

All notable changes to this project are documented in this file.

## [Unreleased] - 2.0.0

### Breaking changes
- The content is now stored with `sulu/content-bundle` (`^0.8.3`): draft/live dimension contents instead of the translation entities. Existing data has to be migrated, see the upgrade guide.
- Abbreviation ids are UUIDs (string) instead of integers. Page content, text editor links and bookmarks referencing the old integer ids have to be migrated.
- Tables: `abbr_abbreviations`, `abbr_abbreviation_dimension_contents`, `abbr_abbreviation_versions`; `app_abbreviation_settings` is unchanged.
- Removed `manuxi/sulu-shared-tools-bundle` and `phpcr/phpcr-migrations-bundle`; `sulu/automation-bundle` moved to `suggest`.
- Removed the entities `AbbreviationTranslation`, `AbbreviationSeo*`, `AbbreviationExcerpt*`, the `Entity\Models` and `Entity\Interfaces` namespaces, the old repositories, `Common\DoctrineListRepresentationFactory`, `Mapper\AbbreviationDataMapper`, `Routing\AbbreviationsRouteDefaultsProvider`, `Preview\AbbreviationsObjectProvider`, the own task handlers and the own website controller (rendering is template based now).
- The `sulu_abbreviations.objects` bundle configuration was removed.
- The link provider alias is `abbreviations` (was `abbreviation`); the selection content types return normalized live content arrays instead of entities.
- The `copy` toolbar action was replaced by `copy_locale` (copy the content into another locale).
- The page template of the website is selected by the structure template `abbreviation_default` and rendered with `@SuluAbbreviations/abbreviation.html.twig`; the variable `content` replaces `abbreviation`.

### Added
- Draft/live separation with the content workflow (publish, unpublish, remove draft) for every locale.
- Structure template `abbreviation_default` (`name`, `url`, `explanation`, `description`, `image`, `link`) shipped in `src/Resources/config/templates/abbreviations/`.
- `Entity\Abbreviation` (UUID), `Entity\AbbreviationDimensionContent` (template, route, SEO, excerpt, author and workflow data), `Entity\AbbreviationVersion`; Doctrine XML mapping in `src/Resources/config/doctrine/`.
- `Controller\Admin\AbbreviationController` with `#[Route]` attributes (list, get, create, update, delete, publish, unpublish, remove-draft, copy-locale).
- Teaser provider `Teaser\AbbreviationTeaserProvider` (`sulu.teaser.provider` alias `abbreviations`).
- Link provider based on `ContentLinkProvider`, sitemap provider, smart content data provider, preview object provider, search metadata and reindex provider based on the content bundle services.
- Automation tab in the admin (schedule publish/unpublish, requires `sulu/automation-bundle`).
- Insights tab with activity log and references list.
- `Resolver\AbbreviationResolver` returns normalized live content and registers the abbreviation in the `abbreviations` reference store.
- Selection content types implement `ReferenceContentTypeInterface`, so pages show up in the references list of an abbreviation.
- `Route\AbbreviationRouteRemover` removes the routes of a deleted abbreviation (needed because the content bundle route remover is a Doctrine event subscriber, which Symfony 7.4 does not register anymore).
- Domain events `AbbreviationDraftRemovedEvent`, `AbbreviationTranslationRemovedEvent`, `AbbreviationVersionRestoredEvent`, `AbbreviationCopiedLanguageEvent` (now with the correct event type).
- Configuration `sulu_abbreviations.versioning.max_versions` and `sulu_abbreviations.twig.auto_abbr`.
- Twig filter `abbr` (only registered with `sulu_abbreviations.twig.auto_abbr: true`) wraps known abbreviations in `<abbr title="...">`.
- Delete a single translation: `Translation\\AbbreviationTranslationRemover` removes draft and live content of one locale, its routes and search documents and fires `AbbreviationTranslationRemovedEvent`; `DELETE /admin/api/abbreviations/{id}?locale=xx&deleteLocale=true` (requires the `EDIT` permission for the locale). Deleting the last remaining locale deletes the whole abbreviation (stored in the trash).
- Delete toolbar action is a dropdown with "delete" and "delete translation"; the edit dropdown contains "copy to locale" (only with more than one locale), "remove draft" and "unpublish".
- Versioning: `Versioning\\VersionRecorder` stores a snapshot of the draft (normalized content) for every publish (admin, automation) in `abbr_abbreviation_versions` and keeps the newest `sulu_abbreviations.versioning.max_versions` versions per abbreviation and locale (default 20, `0` disables versioning).
- Versions tab in the insights tab with the `restore_version` list item action; `GET /admin/api/abbreviations/{id}/versions?locale=xx` and `POST /admin/api/abbreviations/{id}/versions/{version}?action=restore&locale=xx` (restores into the draft, requires the `EDIT` permission, fires `AbbreviationVersionRestoredEvent`).
- References of the media, contacts, categories, tags and other resources used by an abbreviation (template properties implementing `ReferenceContentTypeInterface`, excerpt image/icon/categories/tags, author): `Reference\\AbbreviationReferenceUpdater` stores draft references in the `admin` and live references in the `website` context, `Reference\\AbbreviationReferenceSubscriber` keeps them in sync with publish, unpublish and remove draft (also for the automation), `Reference\\AbbreviationReferenceRefresher` is used by `bin/console sulu:reference:refresh abbreviations`.
- HTTP cache invalidation: `EventSubscriber\\AbbreviationCacheInvalidationSubscriber` purges the cached pages containing an abbreviation (reference alias `abbreviations`), the excerpt tags (`tag`) and categories (`category`) and the pages displaying lists of abbreviations when an abbreviation is published (admin and automation, executed after the flush), unpublished, removed, restored or loses a translation. The cache manager is optional (works without `sulu/http-cache-bundle`).
- Responses are tagged with the abbreviations they contain: the resolver and the smart content data provider (`Content\\AbbreviationsDataProvider`) add the abbreviation ids to the `abbreviations` reference store, the Twig function `sulu_get_abbreviations()` and the data provider additionally mark the response as list of abbreviations.
- Data migration from 1.x: `bin/console sulu:abbreviations:migrate-to-content [--dry-run]` (`Migration\\LegacyDataMigrator`) creates the abbreviations (new UUID, the old id is stored in `abbr_abbreviations.legacy_id`), the draft and live content per locale (name, explanation, description, image, link, route path, author, SEO, excerpt incl. tags, categories, image and icon), moves the routes to the new id and removes the routes of unpublished translations. Idempotent, the legacy tables are only read.
- `bin/console sulu:abbreviations:migrate-page-references [--dry-run]` (`Migration\\PageReferenceMigrator`) rewrites the integer ids of `abbreviation_selection` / `single_abbreviation_selection` properties (also inside blocks) and `<sulu-link provider="abbreviation">` links / link properties in the draft and live workspace to the new UUIDs and the provider `abbreviations`.
- Documentation in English and German in `docs/` (publishing, translations, versions, references, teaser, http cache, smart content, selection field types, twig, settings, upgrade 1.x to 2.0), `UPGRADE.md`, a Doctrine migration template (`docs/templates/doctrine-migration.php.dist`) and rewritten READMEs.
- Teaser: the image of the abbreviation is used when no excerpt image is set.
- Functional tests with an in-memory SQLite kernel and PHPCR (`tests/Functional`, test webspace and page template in `tests/App/config`).

### Changed
- `sulu_resolve_abbreviation()` and `sulu_get_abbreviations()` return normalized live content arrays.
- The settings REST route is `sulu_abbreviations.get_abbreviations_settings` and defined with `#[Route]` (path `/admin/api/abbreviations-settings`).
- Services are defined in `services.yaml`; routes use `type: attribute`.
- The activity translations of the English locale were stored under the wrong resource key.

## [1.9.0] - 2026-10-12

### Changed
- Require PHP `^8.2`, Sulu `^2.6` and Symfony `^6.4 | ^7.0`.
- Test suite migrated to PHPUnit 10.5 (`phpunit.xml.dist` uses the PHPUnit 10 schema with `<source>`); Prophecy is used via `phpspec/prophecy-phpunit`.
- Entity unit tests no longer boot the Sulu test kernel (`TestCase` instead of `SuluTestCase`).
- `.php-cs-fixer.php` enforces `declare(strict_types=1)` explicitly (newer `@Symfony:risky` rule sets would remove it); code base reformatted.

### Added
- Copy toolbar action (`sulu_admin.copy`, requires the `ADD` permission) in the edit form; `POST /admin/api/abbreviations?action=copy&id={id}` is handled by `AbbreviationsController::postAction()`.
- `Mapper\AbbreviationDataMapper` (service `sulu_abbreviations.data_mapper`): maps admin form data onto an abbreviation; used by `AbbreviationModel` and the preview. `AbbreviationModel` receives the mapper instead of the media and contact repositories.
- GitHub Actions workflow `ci.yml`: PHP 8.2/8.3/8.4 matrix with `composer validate`, php-cs-fixer (dry run), PHPStan and PHPUnit.
- PHPStan (level 6) with `phpstan.neon.dist` and `phpstan-baseline.neon`.
- Composer scripts `phpstan`, `cs` and `cs-fix`.
- `README.de.md` (German README); both READMEs document the selection field types, the Twig functions and the upgrade to 1.9.

### Fixed
- `AbbreviationRepository` and `AbbreviationsRouteDefaultsProvider` were registered twice (the route defaults provider was added twice to the route defaults chain); each is now one service, `sulu_abbreviations.repository.abbreviation` and `AbbreviationsRouteDefaultsProvider` are aliases.
- Selection content types: the backend registered `abbreviations_selection` twice while the admin field types are `abbreviation_selection` / `single_abbreviation_selection`. The content type aliases now match the field types. `abbreviation_selection` returns only abbreviations published in the current locale, keeps the selection order and sets the locale; `single_abbreviation_selection` sets the locale and returns `null` for unpublished entries.
- Wrong translation key `sulu_abbreviations.abbreviations_selection_label` in the selection field type options.
- Settings view could not load or save: the admin resource was registered as `abbreviations-settings` while the views use `AbbreviationsSettings::RESOURCE_KEY` (`abbreviations_settings`). The resource is now registered as `abbreviations_settings` (detail route `sulu_abbreviations.get_abbreviations-settings`, URL `/admin/api/abbreviations-settings` unchanged).
- The settings payload passed to `Settings\ModifiedEvent` was silently dropped (the constructor accepted only the entity). `Settings\AbstractEvent` now accepts and returns the payload; missing keys in the PUT body no longer raise warnings.
- Translations moved from `src/Resources/config/translations/` to `src/Resources/translations/`, where Symfony discovers them automatically. The extension no longer prepends `framework.translator.paths` and no longer overrides the host application's `framework.default_locale` with `en`.
- Automation task handlers translated the non-existing keys `sulu_abbreviation.publish` / `sulu_abbreviation.unpublish`; they now use `sulu_abbreviations.publish` / `sulu_abbreviations.unpublish`. A unit test checks that every key passed to `trans()` exists in the de/en translation files.
- Twig: `sulu_get_abbreviations()` requested page 0 and defaulted to the locale `en`; it now requests page 1 and uses the locale of the current request when no locale is passed. `sulu_resolve_abbreviation()` also uses the request locale by default and returns `null` for abbreviations that are not published in that locale.
- Sitemap: `AbbreviationsSitemapProvider` could access an undefined array index for unknown hosts, used only one locale per host, ignored URL prefixes (e.g. `/de`) and reported the locale of the entity default instead of the queried locale. It now creates entries for every localization of the host (falling back to the default localization of the portal), builds URLs via `WebspaceManager::findUrlByResourceLocator()`, uses typed return values and computes `getMaxPage()` as an integer. `AbbreviationRepository::countForSitemap()` returns `int`. The service receives `%kernel.environment%` as third argument.
- Settings tab of an abbreviation contained page-only fields (`shadowOn` schema, webspace settings) that were never mapped. The preview of the SEO, excerpt and settings tabs used the page condition `nodeType == 1` and never rendered; it now uses `id != null`.
- The edit form always showed the delete action and the publish toggler because its toolbar was overwritten after the permission checks. Delete now requires the `DELETE` permission, the publish toggler the `LIVE` permission; the add form only offers save.
- Preview crashed: `AbbreviationsObjectProvider::getSecurityContext()` accessed an undefined `documentInspector`, `setContext()` called the non-existing `setStructureType()` and `setValues()` was empty. The provider now returns `Abbreviation::SECURITY_CONTEXT`, maps the form data via `AbbreviationDataMapper` and initializes lazy translation collections before serializing.
- The `copy` action failed with a `TypeError` because `Abbreviation::copy()` returned `null`. `Abbreviation::copy()` now returns a new, not yet persisted abbreviation with copies of all translations, SEO and excerpt data (categories, tags and media are shared). The copy is unpublished in all locales and the route paths get the suffix `-copy`; `AbbreviationModel::copy()` creates the routes of all locales (conflicts are resolved by the route manager) and collects an `AbbreviationCreatedEvent`.
- `POST /admin/api/abbreviations/{id}?action=publish|unpublish` only required the `ADD` permission (derived from the HTTP method). Both actions now require the `LIVE` permission for the requested locale.
- Trash restore created the route with an integer entity id and an integer history flag (`Route::setEntityId()` expects a string, `setHistory()` a bool) and put the JSON-decoded `ext` arrays back into the entity instead of the SEO/excerpt objects.
- `AbbreviationLinkProvider` passes the id and title as strings and the published state as bool to `LinkItem`.
- Wrong `@extends ServiceEntityRepository<...>` generics in the SEO, excerpt and translation repositories (referenced the non-existing classes `Event` / `AbbreviationTranslation`).

### Removed
- `Content\AbbreviationsSelectionContentType` (duplicate of `Content\Type\AbbreviationsSelection`). Templates must use the property type `abbreviation_selection`.
- `Admin\Helper\WebspaceSelector` (service `sulu_abbreviations.webspace_selector`) and the unused webspace/shadow translation keys.
- Unused constant `AbbreviationsAdmin::SECURITY_CONTEXT` (`sulu.modules.abbreviation`); use `Abbreviation::SECURITY_CONTEXT`.
- Workflows `php.yml` and `symfony.yml` (broken `.env.test` copy step and `composer.lock` cache key).
- Dev dependencies `symfony/phpunit-bridge` and `phpspec/prophecy` (replaced by `phpspec/prophecy-phpunit`).
