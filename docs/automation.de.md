# Automatisierung

Mit installiertem `sulu/automation-bundle` (^3.0) hat das Bearbeiten-Formular einen Reiter **Automatisierung**. Aufgaben zum Veröffentlichen oder Zurückziehen einer Sprache zu einem bestimmten Zeitpunkt liefern

- `GlossaryEntryPublishTaskHandler`
- `GlossaryEntryUnpublishTaskHandler`

Versionen, Referenzen, Suche und HTTP-Cache werden auch bei automatisierten Übergängen aktualisiert. Ohne das Automation-Bundle werden Reiter und Handler nicht registriert und nichts geht kaputt.

```console
composer require sulu/automation-bundle
```
