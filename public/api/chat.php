<?php
declare(strict_types=1);

/**
 * Proxy-Endpoint zwischen Browser und OpenAI Responses API.
 *
 * Der API-Key bleibt serverseitig (config/.env) und gelangt NIE in den Browser.
 * Das Konversations-Gedächtnis läuft über previous_response_id pro PHP-Session.
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

$config = require __DIR__ . '/../../config/config.php';

/** Bricht mit JSON-Fehler ab. */
function fail(int $code, string $msg): never
{
    http_response_code($code);
    echo json_encode(['error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail(405, 'Nur POST erlaubt.');
}

$raw  = file_get_contents('php://input');
$data = json_decode($raw ?: '', true);

// "Neue Unterhaltung": Kontext verwerfen.
if (is_array($data) && !empty($data['reset'])) {
    unset($_SESSION['previous_response_id']);
    echo json_encode(['ok' => true]);
    exit;
}

if (empty($config['api_key'])) {
    fail(500, 'OPENAI_API_KEY ist nicht konfiguriert.');
}
if (empty($config['vector_store_id'])) {
    fail(500, 'OPENAI_VECTOR_STORE_ID ist nicht konfiguriert.');
}

$message = is_array($data) ? trim((string) ($data['message'] ?? '')) : '';
if ($message === '') {
    fail(400, 'Leere Nachricht.');
}
if (mb_strlen($message) > 4000) {
    fail(400, 'Nachricht zu lang (max. 4000 Zeichen).');
}

$payload = [
    'model'        => $config['model'],
    'instructions' => $config['system_prompt'],
    'input'        => $message,
    'tools'        => [[
        'type'             => 'file_search',
        'vector_store_ids' => [$config['vector_store_id']],
    ]],
];
if (!empty($_SESSION['previous_response_id'])) {
    $payload['previous_response_id'] = $_SESSION['previous_response_id'];
}

$ch = curl_init('https://api.openai.com/v1/responses');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $config['api_key'],
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
    CURLOPT_TIMEOUT        => 120,
]);
$response = curl_exec($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($response === false) {
    fail(502, 'Verbindung zu OpenAI fehlgeschlagen: ' . $curlErr);
}

$result = json_decode($response, true);
if ($httpCode >= 400 || !is_array($result)) {
    $apiMsg = $result['error']['message'] ?? ('HTTP ' . $httpCode);
    fail(502, 'OpenAI-Fehler: ' . $apiMsg);
}

// Antworttext aus der Responses-API extrahieren.
$reply = '';
foreach (($result['output'] ?? []) as $item) {
    if (($item['type'] ?? '') !== 'message') {
        continue;
    }
    foreach (($item['content'] ?? []) as $c) {
        if (($c['type'] ?? '') === 'output_text') {
            $reply .= $c['text'] ?? '';
        }
    }
}
$reply = trim($reply);
if ($reply === '') {
    $reply = 'Es konnte keine Antwort erzeugt werden.';
}

// Kontext für den nächsten Aufruf merken.
if (!empty($result['id'])) {
    $_SESSION['previous_response_id'] = $result['id'];
}

echo json_encode(['reply' => $reply], JSON_UNESCAPED_UNICODE);
