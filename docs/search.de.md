# Suche

Das Bundle registriert zwei Reindex-Provider für die SEAL-Suche von Sulu:

- `GlossaryEntryAdminReindexProvider`: der Entwurfs-Inhalt im Index `admin` (globale Suche in der Administration).
- `GlossaryEntryWebsiteReindexProvider`: der Live-Inhalt im Index `website`, für jeden Webspace.

Die Indizes werden beim Speichern, Veröffentlichen, Zurückziehen und Löschen eines Eintrags aktualisiert. Neu aufbauen mit:

```console
bin/console sulu:search:reindex
```

Die Indizes `admin` und `website` müssen in der Konfiguration von `cmsig_seal` des Projekts existieren (sie sind Teil des Sulu-Skeletons). In Tests kann der Memory-Adapter (`cmsig/seal-memory-adapter`) verwendet werden.
