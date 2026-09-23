<?php
declare(strict_types=1);
// Administrator-Diagnose auf dem Hosting; keine HTTP-Schnittstelle.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../src/chat.php';
$config = require __DIR__ . '/../config/config.php';
$live = in_array('--live', $argv, true);
$clean = static function ($value) use ($config): string {
    $text = is_scalar($value) ? (string) $value : '';
    foreach (['api_key', 'vector_store_id'] as $field) {
        $secret = (string) ($config[$field] ?? '');
        if ($secret !== '') $text = str_replace($secret, '[ausgeblendet]', $text);
    }
    $text = preg_replace('/sk-[A-Za-z0-9_-]+|Bearer\s+\S+/i', '[Schlüssel ausgeblendet]', $text) ?? '';
    $text = preg_replace('/[\x00-\x1F\x7F]/', ' ', $text) ?? '';
    return mb_substr($text, 0, 1800);
};
echo "Website-Konfiguration (im PHP-CLI-Prozess desselben Hostings)\n";
echo "Modell: " . $clean($config['model']) . "\n";
echo "Wissensversion: " . $clean($config['knowledge_version']) . "\n";
echo "Quelle: " . (is_file(__DIR__ . '/../knowledge/active.json') ? 'knowledge/active.json' : 'Umgebung / .env') . "\n";
echo "API-Schlüssel vorhanden: " . (!empty($config['api_key']) ? 'ja' : 'nein') . "\n";
$store = (string) ($config['vector_store_id'] ?? '');
echo "Suchspeicher vorhanden: " . ($store !== '' ? 'ja' : 'nein') . "\n";
echo "Suchspeicher hat vs_-Format: " . (preg_match('/^vs_[a-zA-Z0-9]+$/D', $store) ? 'ja' : 'nein') . "\n";
foreach (glob(__DIR__ . '/../knowledge/releases/*.json') ?: [] as $path) {
    $release = json_decode((string) file_get_contents($path), true);
    if (!is_array($release) || empty($release['vector_store_id'])) continue;
    echo "Release " . $clean(basename($path, '.json')) . ": " .
        ($store === $release['vector_store_id'] ? 'gleicher Suchspeicher' : 'anderer Suchspeicher') . "\n";
}
if (!$live) { echo "Kein API-Aufruf. Mit --live genau einen Website-Antwortaufruf diagnostizieren.\n"; exit; }
if (empty($config['api_key']) || $store === '' || empty($config['system_prompt'])) {
    fwrite(STDERR, "Konfiguration unvollständig. Kein API-Aufruf.\n"); exit(1);
}
// Derselbe Payload wie in public/api/chat.php, kein Überschreiben durch eine Release-Version.
$payload = hermes_payload($config, 'Wann werden in HERMES 2022 Phasenberichte erstellt?', []);
$ch = curl_init('https://api.openai.com/v1/responses');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $config['api_key'], 'Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 75,
]);
$raw = curl_exec($ch);
$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlCode = curl_errno($ch);
curl_close($ch);
$result = is_string($raw) ? json_decode($raw, true) : null;
echo "HTTP-Status: $status\ncURL-Code: $curlCode\n";
if ($status !== 200 || !is_array($result)) {
    foreach (['type', 'code', 'param', 'message'] as $field) {
        echo "API-Fehler $field: " . $clean($result['error'][$field] ?? '(nicht geliefert)') . "\n";
    }
    exit(1);
}
echo "Antwortstatus: " . $clean($result['status'] ?? 'unbekannt') . "\n";
echo "Der Erzeugeraufruf wurde akzeptiert. Keine Inhaltsprüfung und keine fachliche Freigabe durch diese Diagnose.\n";
