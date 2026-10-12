# Übersetzungen

Der Inhalt eines Glossar-Eintrags wird pro Sprache gespeichert. Die Sprache wird in der Toolbar des Formulars gewählt.

## In Sprache kopieren

**Bearbeiten > In Sprache kopieren** (nur sichtbar, wenn mehr als eine Sprache konfiguriert und der Eintrag gespeichert ist) kopiert den Entwurf der aktuellen Sprache in eine oder mehrere andere Sprachen. Vorhandene Entwürfe der Zielsprachen werden überschrieben. Die Berechtigung `edit` ist erforderlich.

`POST /admin/api/glossary-entries/{id}?action=copy_locale&locale=de&src=de&dest=en,fr`

## Einzelne Übersetzung löschen

**Löschen > Übersetzung löschen** entfernt nur die aktuelle Sprache:

- Entwurf und Live-Inhalt der Sprache
- die Route der Sprache
- die Suchdokumente und Referenzen der Sprache

`DELETE /admin/api/glossary-entries/{id}?locale=de&deleteLocale=true`

Ist die Sprache die **letzte** Sprache des Eintrags, wird der gesamte Eintrag gelöscht und im Papierkorb abgelegt, wie bei **Löschen**. Das Ereignis `translation_removed` wird im Aktivitätsprotokoll protokolliert und der HTTP-Cache geleert.

## Der Typ ist übergreifend

Das Feld `type` (Abkürzung, Akronym, Begriff) wird nicht übersetzt. Es wird einmal gespeichert und ist in allen Sprachen identisch.
