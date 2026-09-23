# HERMAESTRO in die BKI Website integrieren

Technische Übergabe an BKI und den beauftragten Website-Ersteller

Stand 23. September 2026 · Ansprechpartner Fachlichkeit Kaspar Brönnimann

## Ziel und empfohlene Umsetzung

HERMAESTRO wird als aufklappbarer HERMES-2022-Chat in die bestehende BKI-Website eingebunden. BKI betreibt die PHP-Anwendung und das OpenAI-Projekt unter eigener Verantwortung. Das vorgesehene Modell ist **gpt-5.4**. Der API-Schlüssel wird ausschliesslich auf dem Server eingetragen.

Empfohlen ist ein eigener Anwendungspfad unter derselben Origin wie die Website, beispielsweise **https://www.bki.ch/hermaestro/**. Nur das Verzeichnis public/ wird über diesen Pfad ausgeliefert. Die BKI-Seite lädt einen mitgelieferten Einbindungsbaustein; die Chatoberfläche ist durch Shadow DOM von der Gestaltung der übrigen Seite getrennt.

Der Entwickler muss den Chat nicht neu programmieren. Er richtet PHP, URL-Zuordnung, OpenAI-Zugang und Wissensspeicher ein und ergänzt danach eine Script-Zeile im Website-Template. Anschliessend prüft er die Integration zusammen mit der fachlich verantwortlichen Person.

## Die sechs Schritte zur Inbetriebnahme

1. Den richtigen GitHub-Branch übernehmen und einen nachvollziehbaren Code-Stand festhalten.
2. PHP-Anwendung installieren und ausschliesslich public/ unter /hermaestro/ veröffentlichen.
3. Einen eigenen BKI-API-Schlüssel und OPENAI_MODEL=gpt-5.4 in .env eintragen.
4. Das freigegebene Handbuch im BKI-OpenAI-Projekt indexieren, prüfen und aktivieren.
5. Den Einbindungsbaustein einmal in das gemeinsame BKI-Website-Template aufnehmen.
6. Technische und fachliche Abnahme durchführen und den Betrieb übergeben.

## Was bereitsteht und was BKI ergänzen muss

Der öffentliche Quellcode enthält Backend, Dialogsteuerung, Prompts, Oberfläche, animiertes HERMAESTRO-Logo, Einbindungsbaustein und Diagnosewerkzeuge. Nicht enthalten sind API-Schlüssel, das Referenzhandbuch, der aktive Suchspeicher und dessen lokale Freigabedateien. Das Handbuch beziehungsweise der bereits geprüfte Suchtext muss separat von Kaspar übernommen werden.

Die Modellvergleichstests sprechen für GPT-5.4. Sie sind keine vollständige fachliche Freigabe. Insbesondere Antwortlänge, Vollständigkeit bei Phasenfragen und projektspezifische Eskalationswege bleiben Gegenstand der Abnahme. Die Modellumstellung verkürzt die Antworten nicht automatisch.

<!-- PAGE -->

## Quellcode und Übergabestand

Repository: https://github.com/kaspAir/ChatBot

Zu verwendender Branch: **improve/grounded-hermes-chat**

Direktlink: https://github.com/kaspAir/ChatBot/tree/improve/grounded-hermes-chat

Das Repository ist öffentlich lesbar. Zum Klonen ist kein persönlicher GitHub-Zugang von Kaspar erforderlich. Der Standardbranch main ist nicht der hier beschriebene Übergabestand. Nicht versehentlich main oder ein älteres ZIP verwenden.

Der Einbindungsbaustein und diese Anleitung sind Ergänzungen zum zuvor geprüften Anwendungsstand bc0c204e677b81eb2e068780c2ab63b2baa41891. Für die Auslieferung einen Stand verwenden, der public/assets/embed.js und docs/BKI_INTEGRATION.md enthält, und dessen vollständige Commit-ID protokollieren.

```bash
git clone --branch improve/grounded-hermes-chat --single-branch \
  https://github.com/kaspAir/ChatBot.git hermes-chatbot
cd hermes-chatbot
git rev-parse HEAD
```

Die Befehle gelten für eine Neuinstallation. Bei bestehender Installation zuerst git status prüfen, lokale Anpassungen sichern und den vorgesehenen Aktualisierungsprozess verwenden. Das Klonen oder ein Git-Pull veröffentlicht die Anwendung noch nicht auf einer Website.

## Relevante Dateien

- **public/api/chat.php** verarbeitet Browseranfragen, verwaltet die Sitzung und ruft OpenAI auf.
- **src/conversation.php** erstellt den Website-Payload und verarbeitet Antworten und Suchtreffer. src/chat.php wird weiterhin als Hilfsdatei benötigt.
- **config/config.php** liest Modell, Schlüssel, Suchspeicher und Prompt ein.
- **config/conversation_prompt.txt** steuert das aktuelle Antwortverhalten der Website.
- **public/assets/embed.js, embed.css und embed.html** bilden den neuen Einbindungsbaustein.
- **public/assets/hermaestro.svg** ist das Logo. Launcher und Animation sind im Widget enthalten.
- **tools/** enthält Einrichtung, Diagnose und Wissensaktivierung; **tests/** enthält technische Prüfungen und den Abnahmekatalog.

Die älteren Dateien system_prompt.txt und verification.php sowie die lokalen Retrieval-Werkzeuge dokumentieren frühere Prüfverfahren. Der normale Website-Dialog verwendet conversation.php und conversation_prompt.txt; ein zusätzlicher Verifier blockiert ihn nicht. Für eine neue Installation das vollständige Repository übernehmen, nicht einzelne PHP-Dateien zusammensuchen.

Kaspar hat die Übernahme des Codes durch BKI beziehungsweise den beauftragten Website-Ersteller für dieses Vorhaben freigegeben. Eine allgemeine LICENSE-Datei ist im geprüften Repository nicht vorhanden; mit dieser Übergabe wird keine neue allgemeine Open-Source-Lizenz ergänzt. Handbuch und Markenmaterial werden separat durch BKI bereitgestellt beziehungsweise freigegeben.

<!-- PAGE -->

## Hosting und URL Zuordnung

Benötigt werden PHP 8.4 mit cURL, mbstring und JSON-Unterstützung, funktionsfähige PHP-Sessions sowie ausgehende HTTPS-Verbindungen zu api.openai.com. Die Anwendung benötigt im laufenden Betrieb weder Node.js noch eine Datenbank. Python ist nur für optionale Aufbereitung des Handbuchs oder Neuerzeugung der Einbindungsdateien erforderlich.

Beispiel für die private Installation: **/srv/hermaestro/**. Der tatsächliche Pfad hängt vom Hosting ab. Darin bleiben .env, config/, src/, knowledge/, tools/ und tests/ ausserhalb des öffentlich zugänglichen Verzeichnisses.

Der Website-Ersteller richtet diese Zuordnung ein:

- https://www.bki.ch/hermaestro/ → /srv/hermaestro/public/
- https://www.bki.ch/hermaestro/assets/embed.js → /srv/hermaestro/public/assets/embed.js
- https://www.bki.ch/hermaestro/api/chat.php → Ausführung von /srv/hermaestro/public/api/chat.php durch PHP

Das kann durch eine Pfadzuordnung des Webservers oder einen Reverse Proxy zu einem separaten PHP-Backend geschehen. Ein vorhandener CMS-Frontcontroller darf /hermaestro/ nicht abfangen. Beim Reverse Proxy bleiben Browser-URL und Cookie-Verkehr unter www.bki.ch; der Proxy muss POST-Bodies, Content-Type, Set-Cookie, Cookies und Fehlerstatus korrekt weiterreichen. PHP muss die HTTPS-Verbindung korrekt erkennen. Die konkrete Serverkonfiguration erstellt der Betreiber passend zu seiner Plattform.

**Nicht das gesamte Repository in den öffentlichen CMS-Ordner kopieren.** Ebenso wenig reicht es, nur public/ zu kopieren: Die relativen PHP-Verweise auf die privaten Nachbarverzeichnisse müssen weiterhin stimmen. Ein Alias, eine korrekt konfigurierte Verzeichniszuordnung oder ein vollständiges Backend hinter dem Proxy erhält diese Struktur.

## Laufzeit und Rechte

Der OpenAI-Aufruf hat im Backend 75 Sekunden Zeit; der Browser wartet bis zu 150 Sekunden. PHP-FPM-, Webserver- und Proxy-Abbruchgrenzen für diesen Endpunkt müssen den 75-Sekunden-Aufruf einschliesslich Verarbeitung zulassen, beispielsweise 100 bis 120 Sekunden. Der Timeout der Indexierung im CLI-Werkzeug kann länger sein und ist davon getrennt.

PHP braucht Leserechte auf Konfiguration und Wissensmetadaten sowie Schreibrechte auf sein eigenes Session-Verzeichnis. Der Deployment-Benutzer braucht Schreibrechte für die Wissensinstallation. Der öffentliche PHP-Prozess benötigt keine allgemeinen Schreibrechte auf Quellcode oder .env. Bei mehreren Serverinstanzen muss die Sitzung beim gleichen Backend landen oder ein gemeinsamer Session-Speicher eingerichtet werden.

Nur HTTPS verwenden. Sicherstellen, dass .env, .env.bak, knowledge/ und die Git-Metadaten von aussen nicht abrufbar sind. Ein nicht vorhandener API-Schlüssel gehört nicht als Ersatzwert in JavaScript oder das CMS-Template.

<!-- PAGE -->

## OpenAI Zugang und Modell einrichten

BKI legt ein eigenes OpenAI-API-Projekt mit Abrechnung und Zugang zum Modell gpt-5.4 an. Schlüssel und Wissensspeicher müssen im passenden Projekt zugänglich sein. Der persönliche ChatGPT-GPT von Kaspar und dessen ChatGPT-Abonnement ersetzen diese API-Konfiguration nicht.

Im privaten Projektverzeichnis einmalig die Vorlage kopieren, ohne eine vorhandene .env zu überschreiben:

```bash
test -e .env || cp .env.example .env
chmod 600 .env
nano .env
```

Wenn PHP unter einem anderen Systembenutzer läuft, muss der Betreiber gezielt Leserechte für dessen Gruppe gewähren, etwa mit 640 und passender Gruppenzuordnung. Keine allgemeinen Schreibrechte vergeben.

Die Datei enthält nach Eingabe des eigenen Schlüssels:

```dotenv
OPENAI_API_KEY=HIER_DEN_EIGENEN_BKI_API_SCHLUESSEL_EINTRAGEN
OPENAI_VECTOR_STORE_ID=
OPENAI_MODEL=gpt-5.4
```

Der Schlüsselplatzhalter wird ersetzt. Die Suchspeicher-ID bleibt bis zur Einrichtung leer. Keine spitzen Klammern, erklärenden Kommentare oder URLs als Teil eines Werts eintragen. Die .env niemals zur Diagnose in einen Chat, ein Ticket oder ein Repository kopieren.

## Welche Einstellung tatsächlich gilt

Bereits vom Hosting gesetzte Umgebungsvariablen haben Vorrang vor .env. Steht etwa OPENAI_MODEL=gpt-4o in der PHP-FPM-Konfiguration, genügt eine Änderung in .env nicht. Diese Doppelkonfiguration entfernen oder übereinstimmend auf gpt-5.4 setzen und gegebenenfalls PHP-FPM neu laden lassen.

Für den Suchspeicher hat knowledge/active.json Vorrang vor OPENAI_VECTOR_STORE_ID. Eine fremde active.json aus Kaspars Installation darf daher nicht ungeprüft übernommen werden. Die neue BKI-Installation erhält einen eigenen Suchspeicher und eine eigene Aktivierung.

Die aktualisierte .env.example und der Code-Standardwert verwenden gpt-5.4. Bestehende .env-Dateien werden durch Git nicht geändert; der explizite Eintrag und die Prüfung bleiben notwendig. Es gibt keinen automatischen Modellwechsel bei einem Fehler. Zugriff, Quota oder Konfiguration sind zu korrigieren, statt unbemerkt ein anderes Modell zu verwenden.

Die Modellkennung gpt-5.4 ist der vorgesehene API-Alias. Die API kann als Antwortmodell eine datierte Kennung derselben Modellfamilie zurückgeben. Ein späterer Wechsel auf einen anderen Alias oder einen festen Snapshot wird bewusst konfiguriert und erneut geprüft.

<!-- PAGE -->

## Die Wissensgrundlage übernehmen

Der Bot beantwortet Fragen aus dem bereitgestellten Referenzhandbuch einschliesslich der freigegebenen BKI-Ergänzungen. Ein Git-Clone enthält diese Inhalte nicht. Ein neuer API-Schlüssel übernimmt auch nicht automatisch Kaspars hochgeladene Dateien oder seinen Suchspeicher.

Für eine möglichst gleiche Wissensgrundlage übernimmt BKI den bereits geprüften Suchtext **referenzhandbuch.txt** von Kaspar und legt ihn privat unter knowledge/referenzhandbuch.txt ab. Zusätzlich werden die zugrunde liegende PDF-Fassung und, soweit vorhanden, das Aufbereitungsprotokoll review.json übergeben. Prüfsumme und Herkunft des übernommenen Textes werden dokumentiert.

```bash
sha256sum knowledge/referenzhandbuch.txt
```

Das Handbuch nicht mit einem leeren Beispieldokument ersetzen. Insbesondere BKI-Ergänzungen, Tabellen und Angaben zu Phasen, Rollen und Entscheidungsbefugnissen müssen enthalten und lesbar sein.

## Alternative bei einer neuen PDF Fassung

Nur wenn der geprüfte Suchtext fehlt oder das Handbuch geändert wurde, wird neu aufbereitet. Das geschieht auf einem administrativen Rechner, nicht zwingend auf dem Webhosting. Beispiel mit Python und PyMuPDF:

```bash
python3 -m venv .venv
.venv/bin/python -m pip install PyMuPDF
.venv/bin/python tools/prepare_handbook.py \
  /pfad/zum/freigegebenen-handbuch.pdf \
  knowledge/prepared/bki-2026-09-23-1 \
  --version bki-2026-09-23-1
```

Das Zielverzeichnis darf noch nicht existieren. Das Werkzeug erzeugt referenzhandbuch.txt und review.json. Es markiert grüne Textabschnitte als Ergänzungen Kaspar/BKI und erhält PDF-Seitenangaben. Die Farberkennung ist eine Heuristik; Tabellen, Lesereihenfolge und Ergänzungen sind vor dem Upload fachlich zu prüfen.

Der so geprüfte Text kann unter knowledge/referenzhandbuch.txt auf den Zielserver übertragen werden. Wird eine neue Fassung verwendet, eine neue Versionskennung wählen. **bki-2026-09-23-1** in dieser Anleitung ist eine Beispielkennung für die BKI-Übernahme, keine Behauptung eines neuen Veröffentlichungsdatums der HERMES-Methode.

Die Versionskennung bleibt intern für Betrieb und Rückwechsel erhalten. Die frühere Zeile «Wissensstand …» wird in der Chatoberfläche nicht mehr als Antwortfuss angezeigt.

<!-- PAGE -->

## Suchspeicher erstellen prüfen und aktivieren

Die folgenden Schritte im privaten Projektverzeichnis auf einer noch nicht öffentlich freigegebenen Installation ausführen. Upload und Antworttests verursachen API-Kosten im BKI-Projekt.

```bash
php tools/setup_vectorstore.php --version bki-2026-09-23-1 \
  knowledge/referenzhandbuch.txt
```

Das Werkzeug lädt die Datei hoch, erstellt den Suchspeicher und wartet auf die Indexierung. Bei Erfolg entsteht knowledge/releases/bki-2026-09-23-1.json. Die ausgegebene ID beginnt mit vs_. Diese ID zusätzlich als OPENAI_VECTOR_STORE_ID in .env eintragen. Sie ist eine Kennung, kein Weblink und kein Dateiname.

Bei einem Abbruch den bereits angelegten Store im OpenAI-Projekt prüfen. Nicht blind denselben Upload wiederholen: Dabei können zusätzliche kostenpflichtige Dateien und Stores entstehen. Erst nach abgeschlossener Indexierung weiterarbeiten. Eine bereits verwendete Release-Kennung lässt sich nicht einfach überschreiben.

## Vor der Aktivierung prüfen

```bash
php tools/test_question.php bki-2026-09-23-1 \
  'Erkläre die Phasenberichte und wann sie erstellt werden.'
php tools/test_question.php bki-2026-09-23-1 \
  'Mein Auftraggeber reagiert nicht auf meine Risiko-Bedenken. Was tun?'
```

Das Werkzeug prüft mit dem konfigurierten Modell gezielt den angegebenen Release-Suchspeicher. Für diesen Website-Test **kein --local** hinzufügen; dies würde einen anderen, älteren Prüfweg verwenden. Die Antworten und die angezeigten Suchstellen fachlich beurteilen und bei Bedarf weitere Fälle aus tests/acceptance.md durchführen.

Erst nach dieser Prüfung die Wissensgrundlage aktivieren:

```bash
php tools/activate_knowledge.php bki-2026-09-23-1 --reviewed
php tools/check_website_config.php
php tools/check_website_config.php --live
```

--reviewed bestätigt eine tatsächlich durchgeführte Prüfung; das Werkzeug führt die fachliche Prüfung nicht selbst aus. Die Aktivierung schreibt knowledge/active.json. Erwartete Diagnose: Modell gpt-5.4, eigene Wissensversion, Quelle knowledge/active.json, Schlüssel vorhanden, Suchspeicher im vs_-Format, HTTP 200, cURL-Code 0 und Antwortstatus completed. Das neue Feld Antwortmodell muss zur Modellfamilie gpt-5.4 gehören.

Ein erfolgreicher CLI-Test beweist noch nicht, dass PHP im Website-Prozess dieselbe Umgebung verwendet. Deshalb folgt nach der Einbindung zwingend eine echte Browseranfrage.

<!-- PAGE -->

## Das Widget in die BKI Seite einfügen

Wenn /hermaestro/ eingerichtet ist, ergänzt der Website-Ersteller diese Zeile einmal im gemeinsamen Seiten-Template, vorzugsweise vor dem schliessenden body-Tag:

```html
<script type="module"
  src="/hermaestro/assets/embed.js?v=bki-1"></script>
```

Der Baustein erstellt selbst einen Container mit der ID bki-hermaestro. Er lädt embed.html und embed.css, zeigt den animierten Launcher unten rechts an und öffnet den Chat auf Klick. Ein vorhandenes altes BKI-Chatwidget wird für diese Seiten deaktiviert, damit nicht zwei Launcher übereinander liegen.

Die Oberfläche läuft in einem Shadow DOM. Widget-CSS, interne IDs und SVG-Stile werden dadurch von der übrigen Website getrennt. Der Baustein verändert weder den Seitenhintergrund noch das Favicon der BKI-Website. Das animierte Favicon bleibt Bestandteil der eigenständigen Chat-Demoseite; eine Änderung des BKI-Favicons wäre eine separate Gestaltungsentscheidung.

Der direkte Einstieg **https://www.bki.ch/?chatActive=1** öffnet den Chat. Ohne Parameter startet er geschlossen. Das Öffnen löst keinen OpenAI-Aufruf aus; erst das Absenden einer Frage tut dies. Schliessen erhält den Gesprächsverlauf. «Neuer Chat» setzt ihn auf dem Server zurück. Escape schliesst das Panel. Die Logoanimation berücksichtigt die Einstellung für reduzierte Bewegung.

## Pfad und Domain müssen stimmen

embed.js leitet die URLs für Logo, HTML, CSS und API aus seiner eigenen Adresse ab. Wird ein anderer Anwendungspfad gewählt, genügt dessen Anpassung im Script-src, solange die Verzeichnisstruktur erhalten bleibt. Die Dateien nicht einzeln an beliebige CDN- oder CMS-Asset-Adressen verschieben.

Die Script-Datei muss unter derselben Origin wie die Seite ausgeliefert werden: gleiches Protokoll, gleicher Hostname und Port. Beispielsweise www.bki.ch und bki.ch sind unterschiedliche Origins. Der Baustein lehnt eine fremde Origin bewusst ab. Für ein externes Backend einen Reverse Proxy unter der BKI-Origin einrichten; den vorhandenen Herkunftsschutz nicht durch pauschale CORS-Freigaben umgehen.

Das API verwendet das separate Sitzungscookie **BKI_HERMAESTRO**, damit es nicht die übliche CMS-Sitzung verwendet. Session-Cookies müssen im Browser funktionieren. Bei einer bestehenden Content Security Policy sind eigene Script-, Style-, Bild- und Fetch-Ressourcen gezielt zuzulassen. Das Widget benötigt kein eval und keine Inline-Script-Freigabe. Eine verpflichtende Trusted-Types-Policy muss der Entwickler mit dem statischen HTML-Ladevorgang abstimmen.

Bei einer Website mit clientseitigen Seitenwechseln bleibt der einmal angelegte Container bestehen. Den Baustein nicht bei jedem Routenwechsel erneut initialisieren. Ein ?chatActive=1-Direktlink wird beim Start ausgewertet; spätere clientseitige Parameteränderungen öffnen ihn nicht automatisch.

<!-- PAGE -->

## Den wirklichen Website Betrieb prüfen

Nach der Installation auf der BKI-Staging-Seite einen neuen Chat starten und eine HERMES-Frage senden. In den Browser-Entwicklerwerkzeugen muss ein POST auf /hermaestro/api/chat.php sichtbar sein. Der Browser ruft api.openai.com nicht direkt auf und erhält keinen API-Schlüssel.

Im PHP-Log enthält das Ereignis hermes_api nun unter anderem:

```json
{
  "event": "hermes_api",
  "model_requested": "gpt-5.4",
  "model_returned": "gpt-5.4-2026-03-05",
  "http_status": 200,
  "curl_code": 0
}
```

Die datierte Modellkennung ist ein Beispiel aus dem bisherigen Vergleich, kein fest zugesicherter Rückgabewert des Alias. Entscheidend ist, dass die tatsächliche Website-Anfrage gpt-5.4 anfordert und die erwartete Modellfamilie zurückkommt. request_id, Laufzeit und Tokenverbrauch sind weitere Logfelder. Fragen, Antworten und Schlüssel werden von dieser Anwendung nicht im API-Log ausgegeben; zusätzliche Hosting- und Proxy-Logs sind separat zu prüfen.

## Vertrag der vorhandenen API

Der Einbindungsbaustein verwendet bereits den richtigen Vertrag. Falls BKI später eine eigene Oberfläche anschliesst, gelten folgende Aufrufe mit Content-Type application/json und Sitzungscookie:

```json
{"message":"Wann wird ein Phasenbericht erstellt?"}
```

Eine erfolgreiche Antwort enthält reply als Text, sources als Liste mit label und text, source_mode mit dem Wert retrieval und die interne knowledge_version. Beim Zurücksetzen wird {"reset":true} gesendet; der Server antwortet mit {"ok":true}. Fehler liefern einen passenden HTTP-Status sowie error und request_id.

Antworten weiterhin als Text ausgeben, nicht ungeprüft als HTML. Die vorhandene Oberfläche nutzt textContent. Die Handbuchauszüge sind Suchtreffer zum Nachlesen und kein automatisch geprüfter Beleg für jeden einzelnen Satz. Diese Unterscheidung bei einer neuen Darstellung erhalten.

Das Backend behält die letzten drei erfolgreichen Frage-Antwort-Paare als Modellkontext. Der sichtbare Verlauf kann länger sein. Ein Neuladen der Seite baut die sichtbaren Nachrichten nicht aus der Sitzung wieder auf; «Neuer Chat» schafft einen definierten Ausgangspunkt. Eine neue Wissensaktivierung verwirft beim nächsten Aufruf alten Kontext.

<!-- PAGE -->

## Abnahme vor der Veröffentlichung

Zuerst die kostenfreien technischen Prüfungen ausführen:

```bash
php tests/run.php
```

Alle Prüfungen müssen erfolgreich sein. Sie ersetzen weder die Website-Integration noch die fachliche Abnahme. Danach die folgenden Fälle im tatsächlichen BKI-Frontend prüfen; Antworten kurz protokollieren und Abweichungen vor der Freigabe bearbeiten.

1. **Modell und Wissen:** Eine echte Website-Anfrage zeigt im Serverlog gpt-5.4; der eigene BKI-Suchspeicher ist aktiv. Der Browser enthält keinen Schlüssel.
2. **Phasenberichte:** «Erkläre die Phasenberichte und wann sie erstellt werden.» Die Antwort unterscheidet klassisches und agiles Vorgehen, Initialisierung und Abschluss gemäss der freigegebenen Wissensfassung.
3. **Berichtsvergleich:** «Was unterscheidet Releasebericht und Phasenbericht? Braucht jeder Release eine Freigabe?» Bericht und bedingte Releasefreigabe werden nicht verwechselt.
4. **Praxis:** «Mein Auftraggeber reagiert nicht auf meine Risiko-Bedenken. Wie soll ich vorgehen?» Erwartet werden konkrete Schritte und ein Entscheidbedarf; keine frei erfundene Kompetenz, den Auftraggeber zu übergehen.
5. **Anschlussfrage:** Direkt danach: «Ich habe das schon dokumentiert und zweimal angesprochen. Was nun?» Der Bot muss den bisherigen Verlauf berücksichtigen und einen nächsten Schritt nennen.
6. **Projektabschluss:** «Können wir trotz offener Mängel und Pendenzen abschliessen?» Kritische Mängel und geordnete Übergabe offener Punkte werden unterschieden.
7. **Fachfremde Frage:** «Was kann ich am Wochenende in Paris besichtigen?» Erwartet ist eine kurze Ablehnung mit Hinweis auf den HERMES-Aufgabenbereich.
8. **Offene Suche:** Eine bislang nicht getestete Frage aus dem Handbuch stellen, etwa zum Tailoring. Sie darf nicht allein wegen einer fehlenden vordefinierten Frage zurückgewiesen werden.
9. **Bedienung:** Öffnen, Schliessen, Neuer Chat, Enter, Shift+Enter, Escape, Quellenansicht, Tastaturbedienung, reduzierte Bewegung und mobile Bildschirmtastatur prüfen.
10. **Website-Verträglichkeit:** Navigation, Menüs, Formulare und Cookie-Banner bleiben bedienbar; kein zweiter Chatlauncher, kein überlagerter Pflichtdialog und kein horizontaler Überlauf.

Für die Freigabe zählen richtige, verständliche und hilfreiche Antworten. Nicht nur das Vorhandensein von Suchtreffern bewerten. Der ausführlichere Katalog liegt unter tests/acceptance.md. Den Demo-Hinweis erst nach dokumentierter fachlicher Freigabe entfernen.

<!-- PAGE -->

## Betrieb und spätere Aktualisierungen

BKI benennt eine fachlich verantwortliche Person und eine Stelle für Hosting, Schlüssel und Störungen. Der Website-Ersteller dokumentiert Anwendungspfad, deployten Commit, Modellkennung, aktive Wissensversion, Datum der Abnahme und zuständige Kontakte. Der Schlüssel selbst gehört nicht in dieses Protokoll.

Der vorhandene Schutz begrenzt eine Sitzung auf sechs Anfragen pro Minute. Das ist kein umfassender Schutz gegen automatisierte öffentliche Nutzung. Der Betreiber ergänzt zum erwarteten Verkehr passende Limits am Hosting oder Proxy, überwacht API-Verbrauch und setzt geeignete Kostenalarme. Bei einer Störung sollte sich der Launcher über das Website-Template vorübergehend deaktivieren lassen.

Die Anwendung sendet die Frage, den kurzen Gesprächskontext und Suchanfragen an OpenAI. store:false im Payload schaltet die Speicherung des Response-Objekts ab; es löscht nicht automatisch hochgeladene Dateien, Suchspeicher oder PHP-Sitzungen. BKI legt Session-Aufbewahrung, Log-Aufbewahrung und die Information der Website-Besucher passend zum Betrieb fest.

## Code aktualisieren

Änderungen zunächst auf Staging übernehmen. .env und knowledge/ bleiben erhalten und werden von Deployment-Werkzeugen nicht gelöscht. Nach einem sauberen git status kann ein Update des Übergabebranchs beispielsweise so erfolgen:

```bash
git pull --ff-only
php tests/run.php
php tools/check_website_config.php --live
```

Danach einen Browser-Smoketest ausführen, die neue Commit-ID protokollieren und erst anschliessend auf Produktion ausrollen. Keine automatische Produktionseinspielung aller künftigen Branch-Änderungen einrichten. Bei Cache-Problemen embed.js, embed.css und embed.html gemeinsam invalidieren. Der Versionsparameter der Script-Zeile allein aktualisiert nicht zuverlässig alle abhängigen Dateien. Für diese Dateien kurze Cache-Zeiten oder Revalidierung verwenden.

Die Einbindungsdateien werden aus der bestehenden Oberfläche erzeugt. Bei Änderungen an public/index.html, assets/style.css oder assets/chat.js führt der Entwickler zusätzlich aus:

```bash
python3 tools/build_embed.py
```

Die erzeugten drei Embed-Dateien mit ausliefern und den Widget-Test wiederholen. Python wird dafür nur beim Entwickeln benötigt. Das Favicon der eigenständigen Seite wird nicht in die BKI-Seite übernommen.

## Rückwechsel

Den zuletzt freigegebenen Code-Stand über das übliche Release-Verfahren wieder bereitstellen. Ein Code-Rückwechsel stellt weder .env noch knowledge/ automatisch zurück. Das gewünschte Modell separat kontrollieren. Ein früherer, weiterhin verfügbarer und freigegebener Wissensstand kann mit activate_knowledge.php ALTE_VERSION --reviewed erneut aktiviert werden; der zugehörige OpenAI-Store muss noch existieren.

<!-- PAGE -->

## Fehler gezielt beheben

**Die BKI-Seite zeigt weiterhin den alten Chat.** Prüfen, ob der richtige Branch deployt wurde, das neue Script im gemeinsamen Template steht und das alte Widget entfernt wurde. Website-, CDN- und Browsercaches für alle Embed-Dateien leeren. Eine erfolgreiche Git-Übernahme ändert kein falsch zugeordnetes Webverzeichnis.

**Der Launcher erscheint nicht.** Im Browser die Konsole und die Antworten für embed.js, embed.css, embed.html und hermaestro.svg prüfen. Häufige Ursachen sind 404, falscher JavaScript-MIME-Typ, CMS-Umleitung, CSP-Sperre oder eine abweichende Origin. HTML-Fehlerseiten dürfen nicht als JavaScript ausgeliefert werden.

**HTTP 400 mit invalid_value und vector_store_ids.** Die tatsächlich aktive Suchspeicher-ID kontrollieren. Sie muss eine echte vs_-ID sein. Platzhalter, URLs, zusätzliche Zeichen oder alte Werte in active.json korrigieren. Ein neuer Schlüssel erfordert Zugriff auf den damit verwendeten Store.

**HTTP 401 oder fehlende Modellberechtigung.** Den Schlüssel im eigenen OpenAI-Projekt, seine Berechtigungen sowie Modellzugang und Abrechnung prüfen. Nicht den privaten Schlüssel von Kaspar einsetzen.

**HTTP 403 beim Browseraufruf.** Herkunftsschutz, URL-Zuordnung, Proxy oder WAF prüfen. Die Empfehlung lautet same-origin unter der BKI-Website; keine pauschale Freigabe fremder Websites ergänzen.

**HTTP 429.** Zwischen dem Sitzungs-Limit der Anwendung und einem OpenAI-Limit unterscheiden. Im API-Log stehen Upstream-Status und Fehlercode. Bei der Sitzung kurz warten; bei API-Limits Kapazität, Quota oder Abrechnung prüfen. Keine endlose automatische Wiederholung starten.

**HTTP 503 oder Einrichtung unvollständig.** Prüfen, ob der Website-PHP-Prozess Schlüssel, Suchspeicher und conversation_prompt.txt lesen kann. Mit check_website_config.php diagnostizieren; anschliessend die abweichenden CLI- und Webserver-Umgebungen abgleichen.

**HTTP 502 oder Antwort dauert zu lange.** API-Status, cURL-Code und Laufzeit mit der Fehlernummer abgleichen. PHP-FPM- und Proxy-Timeouts kontrollieren. Das Backend wartet bis zu 75 Sekunden auf OpenAI. Eine unvollständige Antwort ist kein erfolgreicher Inhaltsnachweis.

**Antworten sind fachlich schlecht oder unerwartet eingeschränkt.** Modell im echten Website-Log, aktive Wissensfassung und conversation_prompt.txt prüfen. Relevante Passagen müssen im hochgeladenen Text enthalten sein. Nicht reflexartig auf den älteren --local-Prüfweg wechseln. Konkrete Frage, Antwort und Fundstellen intern zur fachlichen Prüfung festhalten, ohne Schlüssel weiterzugeben.

**Es läuft weiterhin GPT-4o.** Die vom PHP-Webprozess gesetzte OPENAI_MODEL-Umgebung hat möglicherweise Vorrang. Eine vorhandene .env wird beim Code-Update nicht überschrieben. Die tatsächlichen model_requested- und model_returned-Felder einer neuen Anfrage prüfen.

<!-- PAGE -->

## Übergabeprotokoll

Vor der Veröffentlichung diese Angaben durch BKI und Website-Ersteller vervollständigen:

- Verantwortliche Person für Betrieb und Störungen:
- Verantwortliche Person für fachliche Freigabe:
- Produktionsdomain und Anwendungspfad:
- Privates Installationsverzeichnis und öffentliches Verzeichnis:
- Übernommener Git-Commit:
- Konfiguriertes Modell und im Website-Log bestätigtes Antwortmodell:
- Aktive Wissensversion und Prüfsumme des Suchtextes:
- OpenAI-Projekt und zuständige Administration ohne Schlüssel:
- Datum und Ergebnis der technischen Prüfung:
- Datum und Ergebnis der fachlichen Abnahme:
- Ablage der Sicherungen und zuletzt freigegebener Rückwechselstand:

## Prüfung dieses Übergabebausteins

Der neue Embed-Baustein wurde in Chromium mit simulierten API-Antworten geprüft: geschlossener Start, Öffnen per ?chatActive=1, API-Pfad unter /hermaestro/, Textausgabe ohne HTML-Ausführung, Quellenansicht, Schliessen mit erhaltenem Verlauf, Zurücksetzen, Escape, Mobilbreite und reduzierte Bewegung. Eine Testseite mit absichtlich abweichenden Button- und Chat-Stilen blieb vom Widget-CSS getrennt.

Diese Prüfung fand nicht im produktiven BKI-CMS statt. CMS-Template, Proxy, CSP, reale Sitzungen sowie die Verbindung mit BKI-eigenem Schlüssel und Suchspeicher werden vom Website-Ersteller auf Staging und nach Freigabe in Produktion geprüft. Die sechs zuvor ausgewerteten GPT-5.4-Antworten liefen gegen Kaspars damalige Wissenskonfiguration; sie ersetzen diese Inbetriebnahmeprüfung nicht.

## Quellen und weiterführende Unterlagen

- Quellcode und aktuelle Anleitung: https://github.com/kaspAir/ChatBot/tree/improve/grounded-hermes-chat
- Fachlicher Abnahmekatalog: https://github.com/kaspAir/ChatBot/blob/improve/grounded-hermes-chat/tests/acceptance.md
- OpenAI-Modellbeschreibung GPT-5.4 mit Unterstützung für File Search in der Responses API: https://developers.openai.com/api/docs/models/gpt-5.4
- OpenAI-Anleitung zur Einrichtung und Nutzung von File Search und Vector Stores: https://developers.openai.com/api/docs/guides/tools-file-search

Die technischen Angaben zur Anwendung beruhen auf dem geprüften Quellcode und den für diese Übergabe ergänzten Embed- und Diagnosefunktionen. Die allgemeinen OpenAI-Verweise wurden am 23. September 2026 geprüft. Konkrete Hosting-Einstellungen und das CMS von BKI werden durch den Website-Ersteller festgelegt.
