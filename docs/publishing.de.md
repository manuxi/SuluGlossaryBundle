# Veröffentlichen (Entwurf und Live)

Jeder Glossar-Eintrag hat pro Sprache eine **Entwurfs-** und eine **Live-Version**. Änderungen werden im Entwurf gespeichert, die Website zeigt nur die Live-Version.

## Toolbar

Die Toolbar des Formulars bietet:

- **Speichern** / **Speichern und veröffentlichen** / **Veröffentlichen**: speichert den Entwurf, optional wird er veröffentlicht (Entwurf wird nach Live kopiert).
- Menü **Bearbeiten**:
  - **Entwurf entfernen**: ersetzt den Entwurf durch die Live-Version.
  - **Veröffentlichung zurückziehen**: entfernt die Live-Version der aktuellen Sprache. Der Entwurf bleibt erhalten.
  - **In Sprache kopieren**: siehe [Übersetzungen](translations.de.md).
- Menü **Löschen**: den Eintrag oder nur die aktuelle Übersetzung löschen.

Veröffentlichen, Zurückziehen und Entwurf entfernen benötigen die Berechtigung `live` des Sicherheitskontexts `sulu.glossary.entries`. Speichern benötigt `edit`, Löschen `delete`.

## Workflow-Stati

Der Content-Workflow von Sulu kennt die Stati `draft` (nicht veröffentlicht) und `published`. Der Status wird in der Liste angezeigt.

## REST

- `POST /admin/api/glossary-entries/{id}?action=publish&locale=de`
- `POST /admin/api/glossary-entries/{id}?action=unpublish&locale=de`
- `POST /admin/api/glossary-entries/{id}?action=remove_draft&locale=de`

## Aktivitätsprotokoll

Die Ereignisse `created`, `modified`, `removed`, `restored`, `published`, `unpublished`, `draft_removed`, `copied_language`, `translation_removed`, `translation_restored` und `version_restored` werden im Aktivitätsprotokoll (Reiter Einblicke) protokolliert. Veröffentlichen und Zurückziehen zu einem Zeitpunkt: siehe [Automatisierung](automation.de.md).
