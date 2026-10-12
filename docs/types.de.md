# Typen

Jeder Glossar-Eintrag hat einen Typ:

| Wert | Bedeutung |
|---|---|
| `abbreviation` | verkürzte Form eines Wortes, z. B. "usw." |
| `acronym` | aus Anfangsbuchstaben gebildetes Wort, z. B. "NASA" |
| `term` | jeder andere Begriff (Standard) |

Der Typ ist ein nicht übersetzbares Feld des Templates (`multilingual="false"`), wird im Formular als Auswahl angezeigt und steht als `type` im normalisierten Inhalt, in der Liste und im Smart Content zur Verfügung. Die Beschriftungen sind die Übersetzungen `sulu_glossary.type.{abbreviation,acronym,term}`.

Um Werte hinzuzufügen oder zu ändern, das Template `default` nach `config/templates/glossary_entries/` kopieren und die Werte der Auswahl anpassen.
