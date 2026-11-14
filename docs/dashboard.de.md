# Dashboard-Kachel

Mit [manuxi/sulu-dashboard-bundle](https://github.com/manuxi/SuluDashboardBundle) zeigt das Dashboard eine Kachel **Glossar**. Das Dashboard ist optional: Ohne installiertes Dashboard wird die Kachel nicht geladen, das Bundle braucht keine Konfiguration.

```bash
composer require manuxi/sulu-dashboard-bundle
```

## Was die Kachel zeigt

- Die Anzahl der Einträge und wie viele davon in der gewählten Inhaltssprache nicht veröffentlicht sind (kein Live-Stand).
- Bei mehr als einer Sprache je Sprache einen Balken: wie viele Einträge übersetzt sind, grün bei vollständiger, orange bei unvollständiger Übersetzung, dazu die Zahl der nicht veröffentlichten Einträge dieser Sprache.
- **Was fehlt?**: bis zu fünf Einträge, die in einer Sprache noch fehlen. Ein Klick öffnet das Formular des Eintrags in dieser Sprache.

Die Kachel steht in der schmalen Spalte. Sie braucht das Recht **Ansehen** für `sulu.glossary.entries` und liest die Daten nur, über das Repository des Bundles.

Die letzten Änderungen zeigt die Kachel **Letzte Aktivität** des Dashboards; das Bundle protokolliert seine Ereignisse dort (siehe [Veröffentlichen](publishing.de.md)). Die Übersetzungs-Kachel des Dashboards zählt Seiten und Artikel, keine Glossar-Einträge, deshalb übernimmt diese Kachel die Sprachübersicht.

## Screenshot-Bedarf

- Dashboard mit der Kachel **Glossar** bei zwei Sprachen, eine davon unvollständig, mit der Liste **Was fehlt?**
