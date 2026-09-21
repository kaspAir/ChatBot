# HERMES 2022 – Assistent mit Referenzhandbuch

PHP-Anwendung für Infomaniak (PHP 8.4, cURL, mbstring). Der Browser spricht ausschliesslich mit dem eigenen Server; dieser verwendet die OpenAI Responses API mit File Search. Das Handbuch wird einmal in einen OpenAI Vector Store geladen. Es wird nicht bei jeder Frage vollständig übertragen.

**Stand: technische Arbeitsfassung.** Vor einer Veröffentlichung sind echte Tests mit der freigegebenen Handbuchversion erforderlich. Im Repository befinden sich weder das Handbuch noch Zugangsdaten. Die technische Quellenprüfung garantiert keine inhaltliche Richtigkeit und verifiziert keine Kapitelnummern.

## Einrichtung

1. Repository auf den Server übernehmen. **Webroot auf `public/` setzen**, niemals auf den Projektordner. `.env`, `src/`, `config/`, `knowledge/` und `tools/` dürfen nicht über HTTP erreichbar sein.
2. `.env.example` nach `.env` kopieren. Dort den API-Schlüssel eines eigenen OpenAI-Projekts eintragen. Schlüssel niemals in Git, Browsercode oder Chatnachrichten einfügen. Das konfigurierte Modell muss Responses API und File Search unterstützen; `gpt-4o` ist die bisherige Voreinstellung und noch fachlich zu evaluieren.
3. Das freigegebene, durchsuchbare Handbuch lokal unter `knowledge/` ablegen. Kapitelüberschriften und Nummerierung müssen im extrahierten Text erhalten sein. Gescannte PDFs vorab mit OCR aufbereiten und prüfen.
4. `php tools/setup_vectorstore.php "knowledge/handbuch.pdf"` ausführen. Dies lädt Dateien zu OpenAI hoch und kann Kosten verursachen. Erst nach bestätigter Indexierung die ausgegebene `OPENAI_VECTOR_STORE_ID` in `.env` eintragen. Bei Fehlern/Timeout vorhandenen Store im OpenAI-Projekt prüfen; nicht blind neu hochladen, da sonst verwaiste Dateien/Stores entstehen können.
5. Testinstallation durch den Passwortschutz des Hostings absichern. Die Anwendung enthält keine Benutzerverwaltung. API-Endpunkt in diesen Schutz einschliessen.
6. Für den öffentlichen Betrieb zusätzlich serverseitige Limits über alle Nutzer hinweg, Kostenüberwachung, Hosting-Timeouts (mindestens 90 Sekunden), Datenschutzhinweise und Zugriff auf Serverprotokolle einrichten. Das Sessionlimit ist nur ein Schutz gegen versehentliche Mehrfachanfragen, kein wirksamer Schutz gegen Missbrauch.

## Antworten und Quellen

- Jede Anfrage erzwingt File Search; höchstens sechs Treffer je Suche und 1'800 Ausgabetokens sind konfiguriert.
- Nur die letzten drei erfolgreichen Frage-Antwort-Paare werden als Gesprächskontext übermittelt. Suchtreffer werden nicht im Verlauf wiederholt. Bei Themenwechsel «Neue Unterhaltung» verwenden. Verweise auf ältere Gesprächsinhalte können verloren gehen.
- `store: false` deaktiviert die Speicherung des Response-Objekts bei OpenAI. Dies ist **keine** Zusage vollständiger Datenlöschung: Dateien/Vector Stores, betriebliche API-Aufbewahrungsregeln und die PHP-Session sind davon getrennt. Fragen und kurze Antworten bleiben im serverseitigen Session-Verlauf bis Reset/Session-Bereinigung.
- Fachliche Antworten benötigen eine abgeschlossene Suche, nichtleere Treffer, Dateizitate auf aktuell gefundene Dateien und die Überschrift «Grundlage im Referenzhandbuch». Andernfalls erscheint eine feste Enthaltung.
- Ausklappbare Textstellen sind Suchtreffer aus zitierten Dateien, keine automatisch verifizierten Belege für jede einzelne Aussage. Dateizitate beweisen insbesondere keine Kapitelnummer. Bei unzureichender Qualität ist eine Aufbereitung nach Kapiteln plus strengere Absatz-/Belegprüfung der nächste Schritt.
- Antworten zu Anbieter-Ranglisten oder Beratungsangeboten sind nicht durch das Handbuch gedeckt. Es gibt derzeit keine automatische Werbung. Freigegebene BKI-Informationen müssten als eigene, klar getrennte Quelle ergänzt werden.
- Kein Webwissen, keine Bildausgabe und keine Behauptung, niemals Fehler zu machen. Dokumententwürfe verwenden Platzhalter für fehlende Projektdaten.

Dokumentation: [OpenAI File Search](https://developers.openai.com/api/docs/guides/tools-file-search).

## Fehlerdiagnose

Der Browser zeigt verständliche Meldungen und eine Fehlernummer. Die PHP-Serverlogs enthalten dazu OpenAI-Request-ID, HTTP-Status, cURL-Code, API-Fehlercode, Laufzeit, Tokenverbrauch und Anzahl angezeigter Suchtreffer. Fragen, Antworten und Schlüssel werden nicht von der Anwendung geloggt. Hosting-Logs separat prüfen.

- `429` / Rate Limit: tatsächliche Modell-/Projektlimits und Lastspitzen prüfen; Monatskosten allein reichen nicht zur Diagnose.
- `insufficient_quota`: Kontingent/Abrechnung prüfen. Ein neuer Schlüssel im selben Projekt behebt dies normalerweise nicht.
- `401/403`: Schlüssel, Projekt und Berechtigungen prüfen.
- Timeout: Netzwerk/Hosting-Laufzeit und API-Latenz prüfen.
- Quellenprüfung ohne Treffer: Indexierungsstatus, richtigen Vector Store und Textextraktion prüfen. Die Enthaltung ist kein Nachweis, dass das Handbuch die Antwort nicht enthält.

Es gibt absichtlich keine automatischen Wiederholungen bei Fehlern: Nutzer können erneut senden; die Frage bleibt im Eingabefeld. Dadurch werden Kosten und Last durch Wiederholungen begrenzt. Als spätere Optimierung sind begrenzte Wiederholungen ausschliesslich für vorübergehende Fehler möglich.

## Prüfung

```sh
php tests/run.php
find config public src tools tests -name '*.php' -print0 | xargs -0 -n1 php -l
node --check public/assets/chat.js
```

GitHub Actions führt dieselben Prüfungen mit PHP 8.4 aus. Sie prüfen die technische Quellen- und Fehlerbehandlung ohne API-Kosten. Sie ersetzen keine fachliche Abnahme.

Die fachliche Abnahme steht in [tests/acceptance.md](tests/acceptance.md). Vor der Freigabe müssen die erwarteten Kapitel anhand der tatsächlichen Handbuchversion ergänzt und die Antworten geprüft werden.
