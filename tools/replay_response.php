<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../src/chat.php';
$path = $argv[1] ?? '';
if (!$path || !is_readable($path)) { fwrite(STDERR, "Aufruf: php tools/replay_response.php TRACE_DATEI\n"); exit(1); }
$text = file_get_contents($path);
// Akzeptiert API-JSON oder das mit --debug ausgegebene Protokoll.
$marker = 'Diagnose: ungefilterte Modellantwort und Suchtreffer';
$offset = strpos($text, $marker);
if ($offset !== false) {
    $after = strpos($text, "\n", $offset);
    $arrayStart = strpos($text, '[', $after);
    $objectStart = strpos($text, '{', $after);
    $starts = array_filter([$arrayStart, $objectStart], fn($value) => $value !== false);
    $start = $starts ? min($starts) : false;
    if ($start === false) { fwrite(STDERR, "Keine Diagnose gefunden.\n"); exit(1); }
    $depth = 0; $quoted = false; $escaped = false;
    for ($end = $start; $end < strlen($text); $end++) {
        $char = $text[$end];
        if ($quoted) {
            if ($escaped) { $escaped = false; continue; }
            if ($char === '\\') { $escaped = true; continue; }
            if ($char === '"') $quoted = false;
            continue;
        }
        if ($char === '"') { $quoted = true; continue; }
        if ($char === '[' || $char === '{') $depth++;
        if ($char === ']' || $char === '}') $depth--;
        if ($depth === 0) break;
    }
    $text = substr($text, $start, $end - $start + 1);
}
$decoded = json_decode($text, true);
if (!is_array($decoded)) { fwrite(STDERR, "Ungültiges Diagnose-JSON.\n"); exit(1); }
// Debug-Array enthält nur output; Prüffall simuliert einen abgeschlossenen Response.
$response = array_is_list($decoded) ? ['status' => 'completed', 'output' => $decoded] : $decoded;
echo "Nur Wortlaut und Kapitelzuordnung – keine Inhaltsprüfung, keine Antwortfreigabe.\n";
echo json_encode(['quote_check' => hermes_answer($response, $response['_hermes_evidence'] ?? []), 'claims' => hermes_diagnose_claims($response, $response['_hermes_evidence'] ?? [])], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), "\n";
