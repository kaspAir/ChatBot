# HERMES 2022 – Assistent mit Referenzhandbuch

> Aktueller Website-Modus: natürliche Antworten aus der Handbuchsuche. Die unten dokumentierten Einzelbehauptungs- und Verifier-Prüfungen gelten nur noch für den experimentellen lokalen Modus, nicht für den Website-Dialog. Siehe Abschnitt «Vereinfachter Website-Dialog» am Ende.

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

`php tools/test_question.php VERSION 'Frage' --debug` testet einen indexierten Stand ohne Aktivierung. Die Ausgabe enthält einen Diagnosecode und mit `--debug` auch die ungefilterte Modellantwort und Suchtreffer, jedoch keine API-Zugangsdaten. Keine vertraulichen Inhalte öffentlich posten. Jede Ausführung verursacht einen Generierungsaufruf und bei bestandener Wortlautprüfung einen weiteren API-Aufruf zur Inhaltsprüfung.

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

Die Suche gewichtet Wörter, Kapitelüberschriften, übergeordnete Überschriften und alleinstehende Begriffe im Text. Wird in der Frage ein Kapitelthema ausdrücklich genannt, dient der seltenste passende Überschriftenbegriff als Suchanker; Treffer ohne diesen Begriff werden ausgeschlossen. Bei Fragen nach mehreren Themen kann dies die Abdeckung einschränken und muss geprüft werden. Sie ist reproduzierbar, aber keine semantische Suche und kennt keine beliebigen Synonyme. Inhaltsverzeichniszeilen werden ausgefiltert; bei wiederholter Kapitelnummer bleibt die erste ausgeführte Beschreibung erhalten; spätere Registereinträge ersetzen sie nicht. Diese Heuristik ist bei neuen Handbuchversionen zu prüfen. Tabellen und Abbildungen werden dadurch nicht rekonstruiert.

Für den Phasenbericht-Test müssen insbesondere die Ergebnisbeschreibung 4.4.1.30 und der Reporting-Abschnitt 7.4.1.6 mit der Phasenaufzählung enthalten sein. `--full` zeigt den vollständigen ausgewählten Kontext. Auch Projektorganisation und Projektabschluss separat prüfen.

Erst nach Prüfung der Suchtreffer die Antwort daraus testen (dieser Befehl kostet bis zu zwei API-Aufrufe):

```sh
php tools/test_question.php 2025-10-03-bki-1 'Welche Phasen werden mit einem Phasenbericht abgeschlossen? Gibt es einen Phasenbericht für die Initialisierung?' --local --debug
```

Mit `--local` erhält das Modell genau die vorab ausgewählten Abschnitte und führt keine eigene Suche aus. Die bestehende Belegprüfung prüft die Antwort gegen dieselben Abschnitte. Das Kapitel wird bei jedem Abschnitt wiederholt, damit die Quellenzuordnung beim Aufteilen erhalten bleibt. Fehlende lokale Indizes führen zu einem Fehler statt zu einem stillen Wechsel der Suchmethode. Die fachliche Abnahme bleibt offen.

### Suchabnahme mit der vollständigen Handbuchdatei

Nach einem Update auf Indexformat 2 den lokalen Index neu aufbauen (kein erneuter Upload):

```sh
php tools/build_search_index.php 2025-10-03-bki-1 knowledge/referenzhandbuch.txt
php tools/check_retrieval.php 2025-10-03-bki-1
```

Der zweite Befehl prüft die Fundstellen für Phasenbericht, Projektorganisation und Projektabschluss. Beim Phasenbericht muss auch die tatsächliche Phasenaufzählung im gelieferten Kontext vorkommen; eine passende Kapitelnummer allein genügt nicht. Bei einem Fehler endet der Test mit Exitcode 1. Die Erwartungswerte gelten für die vorliegende HERMES-2022-Fassung und sind bei Methodenänderungen fachlich nachzuführen. Diese drei Fälle sind keine vollständige fachliche Abnahme.


### Zusätzliche Inhaltsprüfung vor der Antwortausgabe

CLI-Antworttests und der Web-Endpunkt prüfen nach dem Wortlautabgleich jede Aussage in einem separaten Modellaufruf gegen genau ihr ausgewähltes Zitat. Der Prüfer hat keine Suchwerkzeuge und erhält keine übrigen Kapiteltexte. Er darf andere Aussage-Beleg-Paare nicht als Ersatzbelege verwenden. Eine fehlende, doppelte, unvollständige oder negative Bewertung verhindert die Ausgabe der gesamten Antwort und die Übernahme in den Gesprächsverlauf. Auch ein technischer Prüffehler führt zur Sperre; es gibt keine automatische Wiederholung oder Neugenerierung.

Der Prüfer nutzt das konfigurierte Modell, aber einen separaten Aufruf. Das ist keine unabhängige fachliche Zertifizierung: Beide Modellaufrufe können dieselben Fehler machen. `semantic_check_passed` bedeutet nur, dass der Modellprüfer zugestimmt hat. Die reine Funktion `hermes_answer` und `replay_response.php` prüfen weiterhin ausschliesslich Wortlaut und Kapitelzuordnung; deren `grounded` ist keine semantische Freigabe. Die Antwortausgabe verwendet `hermes_verified_answer`.

Vor weiteren Antworttests den Prüfer gezielt testen:

```sh
php tools/check_verifier.php --live
```

Dieser Befehl verursacht genau einen API-Aufruf für vier feste Kontrollfälle: unpassender Reporting-Beleg, passende Phasenaufzählung, falsche Negation und korrekt belegte Initialisierung. Die beiden negativen Fälle müssen abgelehnt, die beiden positiven bestätigt werden. Ohne `--live` erfolgt kein API-Aufruf. Die 71 Offline-Tests prüfen technische Regeln und simulierte Prüfurteile; sie testen nicht das Urteilsvermögen des Modells. Die Live-Kontrollfälle sind noch auf dem Hosting auszuführen.

Ein normaler Antworttest benötigt nun bis zu zwei API-Aufrufe (Erzeuger und Prüfer); Latenz und Kosten steigen entsprechend. Die vier Kontrollfälle ersetzen weder die übrigen fachlichen Abnahmefälle noch Lasttests. Handbuch, Suchindex, aktiver Wissensstand und Website-Zuordnung bleiben von diesem Codeupdate unberührt.


### Beleg-IDs im lokalen Antwortmodus

`--local` sendet die gefundenen Originalausschnitte jetzt mit kurzen Beleg-IDs direkt im Kontext. Das Modell gibt pro Aussage nur `evidence_id` und `statement` zurück. Kapitelnummer und Zitat werden ausschliesslich auf dem Server aus dem aktuellen Katalog übernommen. Unbekannte IDs, fehlende aktuelle Belege und zusätzliche freie Quellfelder werden abgelehnt. Die ID-Auswahl ersetzt die bisherige lange Liste von Zitattexten im Antwortschema; sie ist für sämtliche lokalen Fragen gleich implementiert.

Die anschliessende Inhaltsprüfung bekommt den aufgelösten Originaltext. Eine gültige ID allein beweist keine Aussage. Bedingungen und Ausnahmen müssen weiterhin richtig wiedergegeben werden. Historische Diagnoseausgaben mit freien Zitaten bleiben auswertbar. Der öffentliche File-Search-Suchweg ist durch diese Änderung noch nicht auf lokale Suche umgestellt.

Geprüft: 65 technische Tests und die Zuordnung sämtlicher 198 Belege aus den drei gespeicherten Testläufen zu Auftraggeber, Projektabschluss sowie Release-/Phasenbericht. Das sind Offline-Prüfungen der Zuordnung, keine bestätigten Live-Modellantworten. Für die nächste Prüfung dieselben drei Fragen erneut mit `--local --debug` ausführen; kein Indexneuaufbau oder erneuter Handbuchupload erforderlich. Ein Antworttest benötigt weiterhin bis zu zwei API-Aufrufe.


### Tabellenbelege, Bedingungen und Vergleichsfragen

Die lokale Belegauswahl und die Wortlautprüfung sperren nun erkennbare flach extrahierte Zuordnungstabellen (unter anderem Spaltenüberschriften oder dichte Folgen von Listen-, Meilenstein- und Checklistenbezeichnungen). Dies ist eine konservative Heuristik, keine Rekonstruktion des PDF-Layouts. Verlässliche Informationen in solchen Tabellen können dadurch fehlen; ausdrückliche Fliesstextbeschreibungen bleiben nutzbar. Beteiligung an einem Ergebnis darf nicht in gemeinsame Entscheidungskompetenz umgedeutet werden.

Erzeuger und Inhaltsprüfer müssen Bedingungen wie «eventuell vorgesehen», «falls festgelegt» und «je nach» erhalten. Der Erzeuger soll wenige präzise, belegnahe Aussagen liefern statt die Höchstzahl von acht Aussagen auszuschöpfen. Der lokale Suchweg reserviert bei ausdrücklich genannten Kapiteltiteln je einen Treffer, damit Vergleichsfragen nicht allein vom seltensten Suchwort bestimmt werden. Der bestehende Index kann unverändert weiterverwendet werden. Synonyme und komplizierte Vergleichsfragen bleiben fachlich zu prüfen.

Der Live-Kontrollsatz in `tools/check_verifier.php --live` umfasst jetzt acht Fälle in einem API-Aufruf: die bisherigen vier Fälle sowie Beteiligung versus Entscheidungsrecht und bedingte versus unbedingte Releasefreigabe, jeweils mit positivem Gegenbeispiel. Die 71 Offline-Tests prüfen Technik und simulierte Urteile. An der gespeicherten Dreierdiagnose ist zusätzlich geprüft, dass genau der problematische Rollen-Tabellenbeleg unter den zuvor verwendeten Belegen nicht mehr angeboten wird. Neue Live-Antworten sind damit noch nicht fachlich abgenommen.



### Demo-Stand: Bedienung und Themenbegrenzung (23.09.2026)

Die Website zeigt einen Einstieg mit drei Beispielfragen, eine mobile Gesprächsansicht und nummerierte aufklappbare Textbelege. Der sichtbare Demo-Hinweis bleibt bis zur fachlichen Freigabe bestehen. Nach einem erfolgreichen Chat-Reset erscheint wieder der Einstieg.

Erzeuger und lokale CLI verwenden im strukturierten Antwortschema zusätzlich `response_type`. Fachfremde Fragen erhalten `out_of_scope` und einen festen serverseitigen Hinweis. `clarification` und `greeting` liefern ebenfalls feste Texte ohne erfundene Quellen. Bei `mixed` darf nur der HERMES-Teil beantwortet werden; nach erfolgreicher Beleg- und Inhaltsprüfung ergänzt der Server die Ablehnung des fachfremden Teils. Die Einordnung erfolgt durch das Modell und ist deshalb mit den tatsächlichen Fragen aus dem Abnahmekatalog zu prüfen. Die neue Struktur ersetzt keine fachliche Abnahme. Historische Antwortprotokolle ohne dieses Feld bleiben auswertbar.

Die Website wartet bis zu 150 Sekunden, damit die beiden serverseitigen API-Zeitlimits (75 und 60 Sekunden) nicht bereits nach 90 Sekunden vom Browser abgebrochen werden. Hosting- und Proxy-Zeitlimits müssen dazu passen. Es gibt keine automatische Wiederholung kostenpflichtiger Anfragen. Die Suche bleibt im Website-Bot bei File Search; lokale CLI-Tests sind weiterhin ein separater Suchweg.

Für die erste Demo auf dem bestehenden Hosting:

```sh
cd ~/hermes-chatbot-v2 && git pull --ff-only && php tests/run.php
```

Danach die bisher eingerichtete Chat-Website neu laden und direkt dort prüfen:

1. Eine Beispielfrage anklicken und Antwort samt Quellen lesen.
2. «Erkläre es einfacher» als Anschlussfrage stellen.
3. «Was ist die Hauptstadt von Frankreich?» eingeben: erwartet wird ausschliesslich die Ablehnung.
4. «Wer muss das genehmigen?» in einem neuen Chat eingeben: erwartet wird eine Rückfrage.
5. «Neue Unterhaltung» prüfen und eine weitere Fachfrage stellen.

Live-Fragen verursachen API-Kosten. Ohne verfügbaren API-Schlüssel und aktive Handbuchanbindung lässt sich die fachliche Demo hier nicht vorwegnehmen. Der Entwickler erhält die vorhandene PHP-Anwendung mit `public/` als Webroot; API-Schlüssel, Handbuch und Wissenskonfiguration verbleiben ausserhalb dieses Verzeichnisses. Die vollständige fachliche Prüfung und die Übergabekriterien stehen in [tests/acceptance.md](tests/acceptance.md).


### Vereinfachter Website-Dialog

Die Website und tools/test_question.php ohne --local verwenden src/conversation.php und config/conversation_prompt.txt. Der Bot durchsucht das gesamte aktive Referenzhandbuch, verbindet Abschnitte und antwortet in natürlicher Sprache. Keine feste Kapitelstruktur, kein wörtliches Zitat pro Satz und kein zweiter Verifier-Aufruf blockieren den normalen Dialog. Fachfremde Fragen sollen weiterhin abgelehnt werden. Diese Abgrenzung und die fachliche Qualität sind Modellverhalten und müssen live geprüft werden.

Die Oberfläche zeigt bis zu acht unveränderte, deduplizierte Suchausschnitte als «Gefundene Handbuchstellen». Diese sind keine Bestätigung jedes Satzes und werden nicht als einzeln geprüfte Zitate ausgegeben. Kapitel nennt das Modell nur, wenn sie aus der Suche hervorgehen. Unvollständige API-Antworten und fehlgeschlagene Suche werden weiterhin abgefangen. Ein Gesprächsschritt verwendet einen Responses-Aufruf mit File Search; Suchwerkzeuge können innerhalb dieses Aufrufs mehrfach ausgeführt werden und verursachen Kosten.

Der aktive Wissensstand, API-Schlüssel und Hostingpfad bleiben erhalten. Nach git pull --ff-only auf dem Hosting eine neue Unterhaltung beginnen. Insbesondere Phasenberichte, Auftraggeber, Projektabschluss, eine Anschlussfrage und eine fachfremde Frage direkt auf der Website prüfen. Der Abnahmekatalog bleibt massgeblich für die fachliche Qualität; Quellen sind jetzt auf Antwortebene zu prüfen, nicht durch erzwungene Einzelbehauptungen.



### Aufklappbares HERMAESTRO-Widget

Die Startseite zeigt unten rechts das originale HERMAESTRO-SVG von https://www.bki.ch/assets/chat/hermaestro.svg (übernommen am 23.09.2026). Wortmarke, Formen und Farben bleiben erhalten. Für den Launcher sind lediglich die vorhandenen Flügel und Pupillen gruppiert und per CSS animiert. Die Bewegung endet nach 4,8 Sekunden; Mausberührung oder Tastaturfokus startet sie erneut. Bei prefers-reduced-motion bleibt das Logo ruhig. Die separate SVG-Datei dient als unverändertes Logo im Kopfbereich.

Klick öffnet oder schliesst den Chat. Schliessen und Escape bewahren Verlauf und laufende Anfrage; «Neuer Chat» setzt den Verlauf wie bisher serverseitig zurück. Eine Antwort im geschlossenen Fenster öffnet es nicht und stiehlt keinen Fokus. ?chatActive=1 öffnet das Fenster beim Seitenaufruf ohne API-Anfrage. Ohne Parameter startet es geschlossen. Mobile Geräte erhalten ein an die Bildschirmhöhe angepasstes Fenster.

Der Hintergrund ist eine Demo-Seite, kein Nachbau der BKI-Website. Für den Einbau kann der Website-Entwickler Launcher und Chatbereich samt zugehörigen Assets übernehmen; die API-Adresse muss zur vereinbarten Backend-Einbindung passen. Eine fremde Website kann den Endpunkt nicht einfach per Cross-Site-Fetch verwenden, da der vorhandene Herkunftsschutz bestehen bleibt. Das Widget ist auf chatbot.hermespia.ch nach git pull und Neuladen mit Strg+F5 verfügbar.
