# HERMES 2022 – Assistent mit Referenzhandbuch

PHP-Anwendung für Infomaniak (PHP 8.4, cURL, mbstring). Der Browser spricht ausschliesslich mit dem eigenen Server; dieser verwendet die OpenAI Responses API mit File Search. Das Handbuch wird einmal in einen OpenAI Vector Store geladen. Es wird nicht bei jeder Frage vollständig übertragen.

**Stand: technische Arbeitsfassung.** Vor einer Veröffentlichung sind echte Tests mit der freigegebenen Handbuchversion erforderlich. Im Repository befinden sich weder das Handbuch noch Zugangsdaten. Die technische Quellenprüfung kontrolliert Belegwortlaut und Kapitelzuordnung, garantiert aber keine inhaltliche Richtigkeit der Schlussfolgerungen.

## Einrichtung

1. Repository auf den Server übernehmen. **Webroot auf `public/` setzen**, niemals auf den Projektordner. `.env`, `src/`, `config/`, `knowledge/` und `tools/` dürfen nicht über HTTP erreichbar sein.
2. `.env.example` nach `.env` kopieren. Dort den API-Schlüssel eines eigenen OpenAI-Projekts eintragen. Schlüssel niemals in Git, Browsercode oder Chatnachrichten einfügen. Das konfigurierte Modell muss Responses API und File Search unterstützen; `gpt-4o` ist die bisherige Voreinstellung und noch fachlich zu evaluieren.
3. Das freigegebene, durchsuchbare Handbuch lokal unter `knowledge/` ablegen. Kapitelüberschriften und Nummerierung müssen im extrahierten Text erhalten sein. Gescannte PDFs vorab mit OCR aufbereiten und prüfen.
4. `php tools/setup_vectorstore.php "knowledge/handbuch.pdf"` ausführen. Dies lädt Dateien zu OpenAI hoch und kann Kosten verursachen. Erst nach bestätigter Indexierung die ausgegebene `OPENAI_VECTOR_STORE_ID` in `.env` eintragen. Bei Fehlern/Timeout vorhandenen Store im OpenAI-Projekt prüfen; nicht blind neu hochladen, da sonst verwaiste Dateien/Stores entstehen können.
5. Testinstallation durch den Passwortschutz des Hostings absichern. Die Anwendung enthält keine Benutzerverwaltung. API-Endpunkt in diesen Schutz einschliessen.
6. Für den öffentlichen Betrieb zusätzlich serverseitige Limits über alle Nutzer hinweg, Kostenüberwachung, Hosting-Timeouts (mindestens 90 Sekunden), Datenschutzhinweise und Zugriff auf Serverprotokolle einrichten. Das Sessionlimit ist nur ein Schutz gegen versehentliche Mehrfachanfragen, kein wirksamer Schutz gegen Missbrauch.

## Antworten und Quellen

- Jede Anfrage erzwingt File Search; höchstens zehn Treffer je Suche und 2'400 Ausgabetokens sind konfiguriert.
- Nur die letzten drei erfolgreichen Frage-Antwort-Paare werden als Gesprächskontext übermittelt. Suchtreffer werden nicht im Verlauf wiederholt. Bei Themenwechsel «Neue Unterhaltung» verwenden. Verweise auf ältere Gesprächsinhalte können verloren gehen.
- `store: false` deaktiviert die Speicherung des Response-Objekts bei OpenAI. Dies ist **keine** Zusage vollständiger Datenlöschung: Dateien/Vector Stores, betriebliche API-Aufbewahrungsregeln und die PHP-Session sind davon getrennt. Fragen und kurze Antworten bleiben im serverseitigen Session-Verlauf bis Reset/Session-Bereinigung.
- Fachliche Antworten werden als strukturierte Aussagen mit Kapitelnummer und wörtlichem Beleg angefordert. Der Server prüft für jede Aussage, ob ihr Beleg im angegebenen Kapitel eines aktuellen Suchtreffers vorkommt. Er erzeugt Quellenliste und Belegnummern selbst. Fehlende Grundlage und fehlgeschlagene technische Prüfung erhalten unterschiedliche Meldungen.
- Ausklappbare Textstellen sind die wortlautgeprüften Belege zu den nummerierten Aussagen. Die Kapitelzuordnung wird innerhalb des jeweiligen Suchtreffers geprüft. Die logische Folgerung aus einem Zitat wird dadurch nicht automatisch bewiesen. Abschnitte ohne enthaltene Kapitelüberschrift können nicht verwendet werden; eine Aufbereitung nach Kapiteln bleibt eine mögliche Verbesserung.
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

## Wissensstände nachpflegen (Infomaniak / chatbot.hermespia.ch)

Zielhosting bestätigt: Infomaniak, Domain `chatbot.hermespia.ch`, Webroot `public/`. Aktualisierungen werden bewusst freigegeben; es gibt keinen automatischen Import von Änderungen einer externen Website.

1. Neue PDF-Fassung mit einer eindeutigen Versionskennung versehen, z.B. `2025-10-03-bki-1`. PDF und Quellenfassung ausserhalb des öffentlichen Webroots archivieren.
2. Auf dem eigenen Rechner Python mit PyMuPDF installieren (`python -m pip install PyMuPDF`). Aufbereiten:
   ```sh
   python tools/prepare_handbook.py handbuch.pdf knowledge/prepared/2025-10-03-bki-1 --version 2025-10-03-bki-1
   ```
   Das Zielverzeichnis darf noch nicht existieren. Das Werkzeug erzeugt suchbaren Text mit PDF-Seiten und markiert grüne Textabschnitte als Ergänzungen Kaspar/BKI. `review.json` enthält PDF-Prüfsumme und erkannte Ergänzungen. Farberkennung bleibt eine Heuristik; andere Farben werden nicht automatisch als Ergänzungen erkannt. Tabellen, Grafiken und Lesereihenfolge prüfen. Das ist keine Rekonstruktion einer amtlichen Originalfassung.
3. Nach Prüfung die Textdatei auf dem Server bereitstellen und als neuen, separaten Vector Store hochladen:
   ```sh
   php tools/setup_vectorstore.php --version 2025-10-03-bki-1 knowledge/prepared/2025-10-03-bki-1/referenzhandbuch.txt
   ```
   Erst nach erfolgreicher Indexierung entsteht `knowledge/releases/2025-10-03-bki-1.json`. Neue Versionen überschreiben den aktiven Wissensstand nicht. PDF und aufbereitete Textdatei nicht gemeinsam indexieren, sonst wäre derselbe Inhalt einmal mit und einmal ohne Ergänzungskennzeichnung vorhanden.
4. In einer separaten, passwortgeschützten Testinstallation den neuen Stand aktivieren und `tests/acceptance.md` durchführen. Die gleiche Release-Datei kann danach in die Produktionsinstallation übernommen werden, sofern beide dasselbe OpenAI-Projekt nutzen.
5. Nach fachlicher Prüfung aktivieren:
   ```sh
   php tools/activate_knowledge.php 2025-10-03-bki-1 --reviewed
   ```
   `knowledge/active.json` hat Vorrang vor `OPENAI_VECTOR_STORE_ID`. Der Bot zeigt die Versionskennung an. Beim nächsten Aufruf einer bestehenden Sitzung wird ihr alter Gesprächskontext gelöscht.
6. Rückwechsel: denselben Aktivierungsbefehl mit der vorherigen Versionskennung ausführen. Alte Vector Stores dafür erhalten; sie verursachen gegebenenfalls weiterhin Speicherkosten. Keine automatische Löschung.

Die Aktivierung ist ein CLI-Werkzeug für den Betreiber, noch keine Administrationsoberfläche. Das normale Aktualisieren des Codes darf `.env` und `knowledge/` auf dem Server nicht löschen. Verzeichnisse und Dateien müssen für den PHP-Prozess lesbar sein, Änderungen an Freigabedateien nur für Administratoren möglich. Immer nur einen Upload je Versionskennung gleichzeitig starten.

### Prüfung der gelieferten Fassung

Die am 21.09.2026 gelieferte PDF-Fassung enthält 215 Dateiseiten und 17 erkannte grüne Textspannen (mehrere Spannen können eine Ergänzung bilden). Beispiele sind die BKI-Empfehlung auf PDF-Seite 3, die Klarstellung zum fehlenden Phasenbericht Initialisierung auf PDF-Seiten 21/34/59 und die Rollenbesetzung auf PDF-Seite 151. Diese Angaben dokumentieren die Arbeitsfassung; sie bestätigen nicht unabhängig die methodische Richtigkeit der Ergänzungen. Eine amtliche Vergleichsfassung wurde nicht geprüft.

Die grünen fachlichen Texte sind gemäss Betreiber verbindliche Ergänzungen der Wissensgrundlage, keine Empfehlungen. Die Herkunft wird für die Pflege markiert, ohne sie in jeder Antwort gesondert relativieren zu müssen. Ausgenommen ist die BKI-Werbung; sie begründet keine objektive Anbieter-Rangliste.

### Live-Diagnose

`php tools/test_question.php VERSION 'Frage' --debug` testet einen indexierten Stand ohne Aktivierung. Die Ausgabe enthält einen Diagnosecode und mit `--debug` auch die ungefilterte Modellantwort und Suchtreffer, jedoch keine API-Zugangsdaten. Keine vertraulichen Inhalte öffentlich posten. Jede Ausführung verursacht einen API-Aufruf.

Structured Outputs erzwingt nur die Antwortstruktur; auch passende Zitate können falsch interpretiert werden. Deshalb bleibt die fachliche Abnahme zwingend. Dokumentation: https://developers.openai.com/api/docs/guides/structured-outputs

Vorhandene Debug-Ausgaben lassen sich ohne API-Aufruf erneut prüfen:
`php tools/replay_response.php knowledge/diagnose.txt`.
Das Werkzeug akzeptiert gespeicherte `--debug`-Ausgaben oder Response-JSON und zeigt den technischen Befund pro Aussage. Debug-Ausgaben bleiben ausserhalb des öffentlichen Webroots und werden nicht ins Repository übernommen. Bei einem reinen Output-Array simuliert die Wiederholung einen abgeschlossenen Response; sie prüft keine Netzwerk- oder Generierungsfehler. Ein technisch passender Beleg ist weiterhin kein Beweis für eine richtige Schlussfolgerung oder eine vollständige Antwort.

## Lokale Kapitelsuche als getrennt testbarer Suchweg

Die neue Suche ist zunächst ein expliziter CLI-Testmodus. Der öffentliche API-Endpunkt verwendet weiterhin File Search; es erfolgt keine automatische Umstellung oder Aktivierung.

```sh
php tools/build_search_index.php 2025-10-03-bki-1 knowledge/referenzhandbuch.txt
php tools/test_retrieval.php 2025-10-03-bki-1 'Welche Phasen werden mit einem Phasenbericht abgeschlossen? Gibt es einen Phasenbericht für die Initialisierung?'
```

Beide Befehle laufen lokal ohne OpenAI-Aufruf. Der Index wird aus der vorhandenen Textdatei erzeugt und anhand ihrer SHA-256-Prüfsumme der bereits hochgeladenen Version zugeordnet. Kein neuer Upload und keine Änderung des aktiven Wissensstands. PHP übernimmt die Aufbereitung; Python wird auf dem Hosting dafür nicht benötigt.

Die Suche gewichtet Wörter, Kapitelüberschriften, übergeordnete Überschriften und alleinstehende Begriffe im Text. Sie ist reproduzierbar, aber keine semantische Suche und kennt keine beliebigen Synonyme. Inhaltsverzeichniszeilen werden ausgefiltert; bei wiederholter Kapitelnummer wird der längste Abschnitt gewählt. Diese Heuristik ist bei neuen Handbuchversionen zu prüfen. Tabellen und Abbildungen werden dadurch nicht rekonstruiert.

Für den Phasenbericht-Test müssen insbesondere die Ergebnisbeschreibung 4.4.1.30 und der Reporting-Abschnitt 7.4.1.6 mit der Phasenaufzählung enthalten sein. `--full` zeigt den vollständigen ausgewählten Kontext. Auch Projektorganisation und Projektabschluss separat prüfen.

Erst nach Prüfung der Suchtreffer die Antwort daraus testen (dieser Befehl kostet einen API-Aufruf):

```sh
php tools/test_question.php 2025-10-03-bki-1 'Welche Phasen werden mit einem Phasenbericht abgeschlossen? Gibt es einen Phasenbericht für die Initialisierung?' --local --debug
```

Mit `--local` erhält das Modell genau die vorab ausgewählten Abschnitte und führt keine eigene Suche aus. Die bestehende Belegprüfung prüft die Antwort gegen dieselben Abschnitte. Das Kapitel wird bei jedem Abschnitt wiederholt, damit die Quellenzuordnung beim Aufteilen erhalten bleibt. Fehlende lokale Indizes führen zu einem Fehler statt zu einem stillen Wechsel der Suchmethode. Die fachliche Abnahme bleibt offen.
