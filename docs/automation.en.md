# Automation

With `sulu/automation-bundle` (^3.0) installed, the edit form has an **Automation** tab. Tasks to publish or unpublish a locale at a given date are provided by

- `GlossaryEntryPublishTaskHandler`
- `GlossaryEntryUnpublishTaskHandler`

Versions, references, search and the HTTP cache are updated for automated transitions as well. Without the automation bundle the tab and the handlers are not registered and nothing breaks.

```console
composer require sulu/automation-bundle
```
