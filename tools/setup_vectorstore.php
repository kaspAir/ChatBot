<?php
declare(strict_types=1);

/**
 * Einmaliges Setup: lädt die Knowledge-Datei(en) zu OpenAI hoch und legt einen
 * Vector Store an. Die ausgegebene vs_...-ID kommt anschliessend als
 * OPENAI_VECTOR_STORE_ID in die .env.
 *
 * Aufruf (im Projekt-Root):
 *   php tools/setup_vectorstore.php "knowledge/Referenzhandbuch Projektmanagement HERMES 2022 DE 20251003_clean_BKI.pdf"
 *
 * Benötigt OPENAI_API_KEY in der .env oder als Umgebungsvariable.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

require __DIR__ . '/../config/config.php'; // lädt .env in die Umgebung

$apiKey = getenv('OPENAI_API_KEY');
if (!$apiKey) {
    fwrite(STDERR, "FEHLER: OPENAI_API_KEY ist nicht gesetzt (.env).\n");
    exit(1);
}

$files = array_slice($argv, 1);
$version = null;
if (($files[0] ?? '') === '--version') {
    array_shift($files);
    $version = array_shift($files) ?? '';
    if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._-]{0,79}$/D', $version)) {
        fwrite(STDERR, "Ungültige Versionskennung.\n"); exit(1);
    }
    $releaseDir = __DIR__ . '/../knowledge/releases';
    if (!is_dir($releaseDir) && !mkdir($releaseDir, 0700, true)) exit(1);
    if (file_exists("$releaseDir/$version.json")) {
        fwrite(STDERR, "Version existiert bereits; neue Kennung verwenden.\n"); exit(1);
    }
}
if (!$files) {
    $files = [__DIR__ . '/../knowledge/Referenzhandbuch Projektmanagement HERMES 2022 DE 20251003_clean_BKI.pdf'];
}

/** Generischer cURL-Aufruf gegen die OpenAI-API. */
function openai_request(string $method, string $url, string $apiKey, $body = null, array $extraHeaders = []): array
{
    $headers = array_merge(['Authorization: Bearer ' . $apiKey], $extraHeaders);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_TIMEOUT        => 300,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $resp = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($resp === false) {
        fwrite(STDERR, "Netzwerkfehler: $err\n");
        exit(1);
    }
    $json = json_decode($resp, true);
    if ($code >= 400) {
        $msg = $json['error']['message'] ?? $resp;
        fwrite(STDERR, "API-Fehler ($code): $msg\n");
        exit(1);
    }
    return is_array($json) ? $json : [];
}

// 1) Dateien hochladen ------------------------------------------------------
$fileIds = [];
foreach ($files as $path) {
    if (!is_readable($path)) {
        fwrite(STDERR, "Datei nicht lesbar: $path\n");
        exit(1);
    }
    echo "Lade hoch: $path\n";
    $post = [
        'purpose' => 'assistants',
        'file'    => new CURLFile($path),
    ];
    $res = openai_request('POST', 'https://api.openai.com/v1/files', $apiKey, $post);
    if (empty($res['id'])) { fwrite(STDERR, "Upload ohne Datei-ID.\n"); exit(1); }
    $fileIds[] = $res['id'];
    echo "  -> file_id: {$res['id']}\n";
}

// 2) Vector Store anlegen ---------------------------------------------------
echo "Erstelle Vector Store …\n";
$vs = openai_request(
    'POST',
    'https://api.openai.com/v1/vector_stores',
    $apiKey,
    json_encode(['name' => 'HERMES 2022 Referenzhandbuch', 'file_ids' => $fileIds], JSON_UNESCAPED_UNICODE),
    ['Content-Type: application/json', 'OpenAI-Beta: assistants=v2']
);
if (empty($vs['id'])) { fwrite(STDERR, "Vector Store ohne ID.\n"); exit(1); }
$vsId = $vs['id'];
echo "  -> vector_store_id: $vsId\n";

// 3) Auf Verarbeitung warten ------------------------------------------------
echo "Warte auf Indexierung";
$ready = false;
for ($i = 0; $i < 60; $i++) {
    $status = openai_request(
        'GET',
        "https://api.openai.com/v1/vector_stores/$vsId",
        $apiKey,
        null,
        ['OpenAI-Beta: assistants=v2']
    );
    $counts = $status['file_counts'] ?? [];
    if (($counts['failed'] ?? 0) > 0 || ($counts['cancelled'] ?? 0) > 0 || ($status['status'] ?? '') === 'expired') {
        fwrite(STDERR, "\nIndexierung fehlgeschlagen. Prüfe Vector Store $vsId; nicht als aktive Quelle konfigurieren.\n");
        exit(1);
    }
    if (($counts['in_progress'] ?? 0) === 0 && ($counts['completed'] ?? 0) === count($fileIds)) {
        $ready = true;
        echo " fertig.\n";
        break;
    }
    echo ".";
    sleep(2);
}

if (!$ready) {
    fwrite(STDERR, "\nIndexierung noch nicht abgeschlossen. Prüfe Vector Store $vsId später erneut.\n");
    exit(1);
}

echo "\n========================================\n";
echo "FERTIG. Trage in deine .env ein:\n\n";
echo "OPENAI_VECTOR_STORE_ID=$vsId\n";
echo "========================================\n";

if ($version !== null) {
    $record = ['version' => $version, 'status' => 'indexed', 'vector_store_id' => $vsId,
        'indexed_at' => gmdate('c'), 'files' => array_map(fn($path) => ['name' => basename($path), 'sha256' => hash_file('sha256', $path)], $files)];
    $handle = fopen("$releaseDir/$version.json", 'x');
    if (!$handle || fwrite($handle, json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
        fwrite(STDERR, "Versionsprotokoll konnte nicht geschrieben werden. Store-ID oben sichern.\n"); exit(1);
    }
    fclose($handle);
    echo "Version $version indexiert, noch nicht aktiviert. Erst fachlich prüfen, dann activate_knowledge.php verwenden.\n";
}
