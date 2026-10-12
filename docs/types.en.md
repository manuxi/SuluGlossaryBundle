# Types

Every glossary entry has a type:

| Value | Meaning |
|---|---|
| `abbreviation` | shortened form of a word, e.g. "etc." |
| `acronym` | word formed from initial letters, e.g. "NASA" |
| `term` | any other term (default) |

The type is an unlocalized field of the template (`multilingual="false"`), shown as a select in the form and available as `type` in the normalized content, in the list and in smart content. The labels are the translations `sulu_glossary.type.{abbreviation,acronym,term}`.

To add or change values, copy the template `default` to `config/templates/glossary_entries/` and adjust the select values.
