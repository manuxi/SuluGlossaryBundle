# Publishing (draft and live)

Every glossary entry has a **draft** and a **live** version per locale. Changes are saved into the draft, the website only shows the live version.

## Toolbar

The form toolbar offers:

- **Save** / **Save and publish** / **Publish**: saves the draft, optionally publishes it (copies the draft to live).
- **Edit** menu:
  - **Remove draft**: replaces the draft with the live version.
  - **Unpublish**: removes the live version of the current locale. The draft is kept.
  - **Copy to locale**: see [translations](translations.en.md).
- **Delete** menu: delete the entry or only the current translation.

Publishing, unpublishing and removing a draft require the `live` permission of the security context `sulu.glossary.entries`. Saving requires `edit`, deleting `delete`.

## Workflow places

The content workflow of Sulu knows the places `draft` (not published) and `published`. The state is shown in the list.

## REST

- `POST /admin/api/glossary-entries/{id}?action=publish&locale=en`
- `POST /admin/api/glossary-entries/{id}?action=unpublish&locale=en`
- `POST /admin/api/glossary-entries/{id}?action=remove_draft&locale=en`

## Activity log

The events `created`, `modified`, `removed`, `restored`, `published`, `unpublished`, `draft_removed`, `copied_language`, `translation_removed`, `translation_restored` and `version_restored` are written to the activity log (insights tab). Publishing and unpublishing at a given date: see [automation](automation.en.md).
