<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../src/retrieval.php';
$version = $argv[1] ?? '';
if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._-]{0,79}$/D', $version)) {
    fwrite(STDERR, "Aufruf: php tools/check_retrieval.php VERSION\n"); exit(1);
}
$release = json_decode((string) @file_get_contents(__DIR__ . "/../knowledge/releases/$version.json"), true);
try { $index = hermes_load_index($version, $release ?? []); }
catch (RuntimeException $e) { fwrite(STDERR, $e->getMessage() . "\n"); exit(1); }
// Erwartete Fundstellen dieser HERMES-2022-Fassung; bei Methodenänderungen fachlich nachführen.
$cases = [
    ['name' => 'Phasenbericht', 'question' => 'Welche Phasen werden mit einem Phasenbericht abgeschlossen? Gibt es einen Phasenbericht für die Initialisierung?',
        'chapters' => ['4.4.1.30', '7.4.1.6'],
        'quotes' => ['Am Ende der Phasen Konzept, Realisierung, Einführung und Umsetzung', 'In der Phase Initialisierung wird kein Phasenbericht erstellt.']],
    ['name' => 'Projektorganisation', 'question' => 'Wo finde ich Informationen über die Projektorganisation?', 'chapters' => ['6.1.3.1'], 'quotes' => []],
    ['name' => 'Projektabschluss', 'question' => 'Was ist beim Projektabschluss wichtig?', 'chapters' => ['5.4.1.7', '5.4.3.35'], 'quotes' => []],
];
$failed = 0;
foreach ($cases as $case) {
    $hits = hermes_retrieve($index, $case['question']);
    $missing = array_diff($case['chapters'], array_column($hits, 'chapter'));
    $text = hermes_normalize(implode("\n", array_column($hits, 'text')));
    foreach ($case['quotes'] as $quote) if (!str_contains($text, $quote)) $missing[] = 'Textbeleg: ' . $quote;
    if ($missing) {
        $failed++;
        echo 'NICHT BESTANDEN: ' . $case['name'] . ' – fehlt: ' . implode(', ', $missing) . "\n";
    } else echo 'BESTANDEN: ' . $case['name'] . "\n";
}
echo "Kein API-Aufruf. Diese Prüfung bewertet die Fundstellen, nicht die Modellantwort.\n";
exit($failed ? 1 : 0);
