# HERMES 2022 – Chatbot

Eigenständiger Chatbot für die Projektmanagement-Methode HERMES 2022, basierend
auf der **OpenAI Responses API** mit **file_search** über das Referenzhandbuch.
Läuft als statisches Frontend + schlanker PHP-Proxy auf `chatbot.hermespia.ch`
(Infomaniak, PHP 8.4) – unabhängig von der Hauptanwendung.

## Aufbau

```
public/                 ← Web-Root (Infomaniak hierauf zeigen lassen)
  index.html            ← Chat-Oberfläche
  assets/style.css
  assets/chat.js        ← spricht mit api/chat.php
  api/chat.php          ← Proxy zu OpenAI (hält den API-Key serverseitig)
config/
  config.php            ← lädt Secrets aus ../.env
  system_prompt.txt     ← die Instructions ("Chatty")
tools/
  setup_vectorstore.php ← einmalig: Knowledge-Datei hochladen, Vector Store anlegen
knowledge/              ← lokale Knowledge-Datei(en), NICHT im Repo
.env                    ← Secrets, NICHT im Repo (liegt über dem Web-Root)
```

## Einrichtung

1. **.env anlegen**

   ```bash
   cp .env.example .env
   # OPENAI_API_KEY eintragen
   ```

2. **Vector Store erstellen** (einmalig, lokal mit PHP-CLI)

   ```bash
   php tools/setup_vectorstore.php "knowledge/Referenzhandbuch Projektmanagement HERMES 2022 DE 20251003_clean_BKI.pdf"
   ```

   Die ausgegebene `vs_...`-ID als `OPENAI_VECTOR_STORE_ID` in die `.env` eintragen.

3. **Lokal testen**

   ```bash
   php -S localhost:8000 -t public
   # Browser: http://localhost:8000
   ```

## Deployment (Infomaniak)

- Den Inhalt von `public/` in den Web-Root der Subdomain `chatbot.hermespia.ch`
  legen (FTP/SSH), `config/`, `tools/` und `.env` **oberhalb** des Web-Roots.
- Alternativ den Web-Root der Subdomain im Infomaniak-Panel direkt auf den
  `public/`-Ordner zeigen lassen – dann liegt das ganze Repo ausserhalb erreichbar.
- `OPENAI_API_KEY` und `OPENAI_VECTOR_STORE_ID` entweder per `.env` (über Web-Root)
  oder als Umgebungsvariablen im Hosting-Panel setzen.

## Konfiguration ändern

- **Verhalten / Prompt:** `config/system_prompt.txt` bearbeiten.
- **Modell:** `OPENAI_MODEL` in der `.env` (Standard: `gpt-4o`).
- **Wissen aktualisieren:** neue Datei hochladen via `tools/setup_vectorstore.php`
  und die neue `vs_...`-ID in die `.env` setzen.

## Bekannte Einschränkung

`file_search` liefert **Text** aus dem Referenzhandbuch, keine eingebetteten
**Bilder**. Die Prompt-Anweisung „verwende passende Bilder" kann diese API daher
nicht erfüllen – Antworten sind rein textbasiert (mit Kapitelreferenz).
