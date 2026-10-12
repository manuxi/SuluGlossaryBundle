# Versionen

Bei jeder Veröffentlichung einer Sprache speichert Sulu eine Version des Live-Inhalts. Die Versionen werden unter **Einblicke > Versionen** aufgelistet (neueste zuerst), mit Benutzer und Datum der Veröffentlichung. Die Versionsnummer ist ein Unix-Zeitstempel.

## Wiederherstellen

Der Wiederherstellen-Button einer Version schreibt den gespeicherten Inhalt in den **Entwurf** der Sprache (nie direkt nach Live). Der Entwurf kann anschließend geprüft und veröffentlicht werden. Das Ereignis `version_restored` wird im Aktivitätsprotokoll protokolliert. Die Berechtigung `edit` ist erforderlich.

- `GET /admin/api/glossary-entries/{id}/versions?locale=de`
- `POST /admin/api/glossary-entries/{id}?action=restore&version=1700000000&locale=de`

## Konfiguration

```yaml
# config/packages/sulu_glossary.yaml
sulu_glossary:
    versioning:
        max_versions: 20  # Versionen pro Eintrag und Sprache, 0 = unbegrenzt
```

Nach jeder Veröffentlichung werden die ältesten Versionen oberhalb des Limits entfernt (`Manuxi\SuluGlossaryBundle\Versioning\VersionPruner`). Die Versionen werden zusammen mit dem Eintrag gelöscht.
