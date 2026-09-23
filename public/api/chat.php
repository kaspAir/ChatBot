<?php
declare(strict_types=1);
require __DIR__ . '/../../src/verification.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
ini_set('session.use_strict_mode', '1');
session_set_cookie_params(['httponly' => true, 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'samesite' => 'Strict']);
session_start();
$config = require __DIR__ . '/../../config/config.php';
$requestId = bin2hex(random_bytes(8));
function respond(int $status, array $body): never {
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
function fail(int $status, string $message): never {
    global $requestId;
    respond($status, ['error' => $message, 'request_id' => $requestId]);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Allow: POST'); fail(405, 'Nur POST erlaubt.'); }
if (strtolower(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]) !== 'application/json') fail(415, 'JSON erforderlich.');
if (($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'cross-site') fail(403, 'Anfrage von fremder Website nicht erlaubt.');
$raw = file_get_contents('php://input', false, null, 0, 20001);
if (strlen($raw ?: '') > 20000) fail(413, 'Nachricht zu lang.');
$data = json_decode($raw ?: '', true);
if (!is_array($data)) fail(400, 'Ungültige Anfrage.');
if (($data['reset'] ?? false) === true) {
    unset($_SESSION['history'], $_SESSION['previous_response_id']);
    respond(200, ['ok' => true]);
}
if (!is_string($data['message'] ?? null)) fail(400, 'Eine Textnachricht ist erforderlich.');
$message = trim($data['message']);
if ($message === '' || mb_strlen($message) > 4000) fail(400, 'Bitte gib eine Frage mit höchstens 4000 Zeichen ein.');
if (!$config['api_key'] || !$config['vector_store_id'] || !$config['system_prompt']) fail(503, 'Der Assistent ist noch nicht vollständig eingerichtet.');
// Schutz vor versehentlichen Mehrfachanfragen. Öffentliche Nutzung braucht zusätzlich Hosting-Limits.
$now = time();
$attempts = array_values(array_filter($_SESSION['attempts'] ?? [], fn($time) => $time > $now - 60));
if (count($attempts) >= 6) { header('Retry-After: 60'); fail(429, 'Bitte warte eine Minute vor weiteren Fragen.'); }
$attempts[] = $now;
$_SESSION['attempts'] = $attempts;
$knowledgeKey = $config['vector_store_id'] . ':' . $config['knowledge_version'];
if (($_SESSION['knowledge_key'] ?? '') !== $knowledgeKey) {
    unset($_SESSION['history']);
    $_SESSION['knowledge_key'] = $knowledgeKey;
}
$payload = hermes_payload($config, $message, $_SESSION['history'] ?? []);
$started = microtime(true);
$upstreamId = '';
$ch = curl_init('https://api.openai.com/v1/responses');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $config['api_key'], 'Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 75,
    CURLOPT_HEADERFUNCTION => function ($curl, string $header) use (&$upstreamId): int {
        if (stripos($header, 'x-request-id:') === 0) $upstreamId = trim(substr($header, 13));
        return strlen($header);
    },
]);
$rawResponse = curl_exec($ch);
$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlCode = curl_errno($ch);
curl_close($ch);
$result = is_string($rawResponse) ? json_decode($rawResponse, true) : null;
$code = (string) ($result['error']['code'] ?? '');
// Keine Fragen, Antworten, API-Schlüssel oder unbearbeiteten Fehlermeldungen protokollieren.
error_log(json_encode(['event' => 'hermes_api', 'request_id' => $requestId, 'upstream_request_id' => $upstreamId,
    'http_status' => $status, 'curl_code' => $curlCode, 'error_code' => $code,
    'latency_ms' => (int) ((microtime(true) - $started) * 1000), 'usage' => $result['usage'] ?? null]));
if ($rawResponse === false || $status >= 400 || !is_array($result)) {
    [$publicStatus, $text] = hermes_error($rawResponse === false ? 0 : $status, $code);
    fail($publicStatus, $text);
}
if (($result['status'] ?? '') !== 'completed') fail(502, 'Die Antwort wurde nicht vollständig erstellt. Bitte versuche eine kürzere Frage.');
$answer = hermes_verified_answer($config, $message, $result);
if ($answer['grounded'] || in_array($answer['diagnostic'] ?? '', ['clarification', 'greeting'], true)) {
    $history = $_SESSION['history'] ?? [];
    $history[] = ['role' => 'user', 'content' => $message];
    $history[] = ['role' => 'assistant', 'content' => $answer['reply']];
    $_SESSION['history'] = array_slice($history, -6);
}
error_log(json_encode(['event' => 'hermes_evidence', 'request_id' => $requestId, 'evidence_present' => $answer['grounded'], 'source_count' => count($answer['sources']), 'diagnostic' => $answer['diagnostic'] ?? null]));
unset($answer['grounded'], $answer['diagnostic'], $answer['verification']);
$answer['knowledge_version'] = $config['knowledge_version'];
respond(200, $answer);

