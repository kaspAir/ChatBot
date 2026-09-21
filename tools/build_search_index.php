<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../src/retrieval.php';
$version = $argv[1] ?? ''; $path = $argv[2] ?? '';
if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._-]{0,79}$/D', $version) || !is_readable($path)) {
    fwrite(STDERR, "Aufruf: php tools/build_search_index.php VERSION knowledge/referenzhandbuch.txt\n"); exit(1);
}
$release = json_decode((string) @file_get_contents(__DIR__ . "/../knowledge/releases/$version.json"), true);
$text = file_get_contents($path);
if (!is_array($release) || !in_array(hash('sha256', $text), array_column($release['files'] ?? [], 'sha256'), true)) {
    fwrite(STDERR, "Datei passt nicht zur indexierten Wissensversion. Keine Änderung durchgeführt.\n"); exit(1);
}
$index = hermes_build_index($text, $version);
if (!$index['passages']) { fwrite(STDERR, "Keine Kapitel gefunden.\n"); exit(1); }
$dir = __DIR__ . '/../knowledge/indexes';
if (!is_dir($dir) && !mkdir($dir, 0700, true)) exit(1);
$temp = tempnam($dir, '.build-');
if ($temp === false || file_put_contents($temp, json_encode($index, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)) === false || !rename($temp, "$dir/$version.json")) {
    fwrite(STDERR, "Speichern fehlgeschlagen.\n"); exit(1);
}
echo count($index['passages']) . " Suchabschnitte erstellt. Keine API-Aufrufe, keine Aktivierung.\n";
