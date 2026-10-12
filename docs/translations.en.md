# Translations

The content of a glossary entry is stored per locale. The locale is selected in the toolbar of the form.

## Copy to locale

**Edit > Copy to locale** (only visible if more than one locale is configured and the entry was saved) copies the draft of the current locale into one or more other locales. Existing drafts of the target locales are overwritten. The `edit` permission is required.

`POST /admin/api/glossary-entries/{id}?action=copy_locale&locale=en&src=en&dest=de,fr`

## Delete a single translation

**Delete > Delete translation** removes only the current locale:

- draft and live content of the locale
- the route of the locale
- the search documents and references of the locale

`DELETE /admin/api/glossary-entries/{id}?locale=de&deleteLocale=true`

If the locale is the **last** locale of the entry the whole entry is deleted and stored in the trash, the same as with **Delete**. The event `translation_removed` is written to the activity log and the HTTP cache is purged.

## The type is shared

The field `type` (abbreviation, acronym, term) is not translated. It is stored once and identical in all locales.
