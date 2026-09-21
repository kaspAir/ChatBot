<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../src/chat.php';
$config = require __DIR__ . '/../config/config.php';
$version = $argv[1] ?? '';
$question = $argv[2] ?? '';
if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._-]{0,79}$/D', $version) || trim($question) === '') {
    fwrite(STDERR, "Aufruf: php tools/test_question.php VERSION 'Frage'\n"); exit(1);
}
$release = json_decode((string) @file_get_contents(__DIR__ . "/../knowledge/releases/$version.json"), true);
if (empty($release['vector_store_id']) || empty($config['api_key'])) {
    fwrite(STDERR, "Wissensstand oder API-Schlüssel fehlt.\n"); exit(1);
}
$config['vector_store_id'] = $release['vector_store_id'];
$ch = curl_init('https://api.openai.com/v1/responses');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $config['api_key'], 'Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode(hermes_payload($config, $question, [])),
    CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 90]);
$raw = curl_exec($ch);
$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$result = is_string($raw) ? json_decode($raw, true) : null;
echo "HTTP-Status: $status\n";
if ($status !== 200 || !is_array($result)) {
    [$publicStatus, $text] = hermes_error($status, (string) ($result['error']['code'] ?? ''));
    fwrite(STDERR, "$text\n"); exit(1);
}
echo json_encode(hermes_answer($result), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), "\n";

if (in_array('--debug', $argv, true)) {
    echo "\nDiagnose: ungefilterte Modellantwort und Suchtreffer (keine Zugangsdaten)\n";
    echo json_encode($result['output'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), "\n";
}
