# Modellvergleich für den HERMES-Website-Bot

## Ausführen
Auf dem bestehenden Hosting, im Projektverzeichnis:

```sh
php tools/compare_models.php --live
```

Ohne --live zeigt das Werkzeug nur den Testplan und ruft keine API auf.

Verglichen werden gpt-4o, gpt-4.1 und gpt-5.4 mit sechs Fällen je Modell (maximal 18 Responses-Aufrufe plus deren File-Search-Nutzung). API-Zugriff vorausgesetzt. Es wird weder ein Modell automatisch ersetzt noch die Website-Konfiguration geändert. Authentifizierungs- und Limitfehler stoppen den Lauf; ungültige oder nicht erlaubte Modelle werden nach dem ersten Fehler übersprungen. Keine automatischen Wiederholungen.

Die Modelle erhalten den aktuellen Website-Prompt und denselben aktiven Suchspeicher. Ausgabelimit (4000 Tokens), Suchwerkzeug und Timeout (75 Sekunden) entsprechen dem Website-Betrieb. Reasoning verwendet die API-Standardwerte. Dies ist ein Vergleich unter den aktuellen Betriebsbedingungen, kein Vergleich maximaler Modellleistung. Falls ein Modell unvollständig antwortet oder das Zeitlimit überschreitet, ist das ein technisches Ergebnis und kein fachliches Fehlurteil.

Die Risiko-Anschlussfrage bekommt die tatsächliche erste Antwort desselben Modells als Kontext. Alle anderen Fragen beginnen ohne Verlauf. Die Suchtreffer können je nach Modell variieren: Wir vergleichen den gesamten Antwortweg inklusive Suchverhalten, nicht identische eingefrorene Textausschnitte. Ein Durchlauf ist eine erste Stichprobe; aussichtsreiche Kandidaten müssen anschliessend mit neuen Formulierungen und Wiederholungen bestätigt werden.

## Auswerten
Pro Antwort: brauchbar / kleine Schwäche / nicht brauchbar. Keine automatische Bewertung durch Schlagworttreffer.

- Risiko: konkreter Entscheidbedarf, nachvollziehbares weiteres Vorgehen, Unterstützung versus Entscheidungskompetenz; QRM nur falls vorhanden. Empfehlungen nicht pauschal als HERMES-Pflichten darstellen.
- Anschluss: dokumentierte und wiederholt angesprochene Risiken als Ausgangslage anerkennen. Nicht bloss erneut Dokumentation empfehlen. Keine erfundene übergeordnete Stelle oder pauschale letzte Mahnung.
- Phasen: klassische und agile Zuordnung präzise; keine Verallgemeinerung auf jede Phase; Initialisierung und Projektschlussbeurteilung korrekt einordnen.
- Vergleich: Zweck und zeitlicher Bezug; bedingte Releasefreigabe erhalten.
- Abschluss mit Mängeln: nach Auswirkungen und Voraussetzungen unterscheiden, konkrete Klärung ermöglichen, keine pauschale Ja/Nein-Antwort.
- Fachfremd: freundlich ablehnen, keine Sachantwort.
- Übergreifend: verständlicher Text, richtige Zuständigkeiten, Belege passend, keine unnötige Verweigerung.

Antwortzeit, Tokenverbrauch und Anzahl der Suchaufrufe separat auswerten. Tokenzahlen sind keine Geldbeträge; Modellpreise und Suchgebühren unterscheiden sich. Keine Kostenrangliste allein aus Tokenzahlen ableiten.

## Ergebnisse
Das Werkzeug speichert nach jeder Anfrage eine private JSON-Datei unter knowledge/model-comparisons/ ausserhalb von public/. Die Datei enthält Antworten, Suchtreffer, Laufzeit, Tokenverbrauch, zurückgemeldetes Modell sowie Hashes des Prompts, des Antwortcodes und des Suchspeichers. Kein API-Schlüssel und keine vollständige Konfiguration. Handbuchauszüge können enthalten sein; die Datei nicht öffentlich ablegen. Für die Auswertung die erzeugte JSON-Datei aus dem Hosting herunterladen.

Offizielle Modellreferenzen:
- https://developers.openai.com/api/docs/models/gpt-4.1
- https://developers.openai.com/api/docs/models/gpt-5.4
