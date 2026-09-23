<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../src/retrieval.php';
$version = $argv[1] ?? ''; $question = $argv[2] ?? '';
if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._-]{0,79}$/D', $version) || !$question) {
    fwrite(STDERR, "Aufruf: php tools/test_retrieval.php VERSION 'Frage'\n"); exit(1);
}
$release = json_decode((string) @file_get_contents(__DIR__ . "/../knowledge/releases/$version.json"), true);
try { $index = hermes_load_index($version, $release ?? []); }
catch (RuntimeException $e) { fwrite(STDERR, $e->getMessage() . "\n"); exit(1); }
$hits = hermes_retrieve($index, $question);
echo "Lokale Suche – keine API-Kosten\n";
foreach ($hits as $i => $hit) {
    echo ($i + 1) . '. Kapitel ' . $hit['chapter'] . ' – ' . $hit['title'] . ' (Abschnitt ' . $hit['id'] . ")\n";
    if (in_array('--full', $argv, true)) echo $hit['text'] . "\n\n";
}
if (!$hits) echo "Keine Treffer.\n";
