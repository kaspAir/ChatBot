<?php
declare(strict_types=1);
require __DIR__ . '/../src/chat.php';
$count = 0;
function check(bool $ok, string $label): void {
    global $count;
    if (!$ok) { fwrite(STDERR, "FAIL: $label\n"); exit(1); }
    $count++;
}
$r = ['status' => 'completed', 'output' => [
    ['type' => 'file_search_call', 'status' => 'completed', 'results' => [['file_id' => 'file_test', 'text' => 'Testabschnitt: ein Quellentext.']]],
    ['type' => 'message', 'content' => [['type' => 'output_text', 'text' => "Testantwort\nGrundlage im Referenzhandbuch: Testabschnitt", 'annotations' => [['type' => 'file_citation', 'file_id' => 'file_test']]]]],
]];
check(hermes_answer($r)['grounded'], 'Antwort mit aktuellen Dateibelegen zugelassen');
check(count(hermes_answer($r)['sources']) === 1, 'Suchtreffer angezeigt');
$bad = $r; $bad['output'][0]['results'] = [];
check(!hermes_answer($bad)['grounded'], 'Leere Suche sperrt Fachantwort');
$bad = $r; $bad['output'][1]['content'][0]['annotations'][0]['file_id'] = 'invented';
check(!hermes_answer($bad)['grounded'], 'Erfundene Datei gesperrt');
$bad = $r; $bad['output'][1]['content'][0]['annotations'] = [];
check(!hermes_answer($bad)['grounded'], 'Fehlende Annotation gesperrt');
$bad = $r; $bad['status'] = 'incomplete';
check(!hermes_answer($bad)['grounded'], 'Unvollständige Antwort gesperrt');
$bad = $r; $bad['output'][0]['status'] = 'failed';
check(!hermes_answer($bad)['grounded'], 'Fehlgeschlagene Suche gesperrt');
$bad = $r; $bad['output'][1]['content'][0]['text'] = 'Unbelegte Antwort';
check(!hermes_answer($bad)['grounded'], 'Fehlende Quellengrundlage gesperrt');
check(hermes_error(429, 'insufficient_quota')[0] === 503, 'Quota nicht als kurzfristiges Limit behandelt');
check(hermes_error(429, 'rate_limit_exceeded')[0] === 429, 'Rate-Limit erkannt');
check(hermes_error(0, '')[0] === 504, 'Timeout erkannt');
$p = hermes_payload(['model'=>'test', 'system_prompt'=>'test', 'vector_store_id'=>'vs_test'], 'Frage', array_fill(0, 20, ['role'=>'user','content'=>'alt']));
check(count($p['input']) === 7, 'Kontext begrenzt');
check($p['tool_choice'] === 'required' && $p['store'] === false, 'Suche erzwungen und Response-Speicherung deaktiviert');
$numbered = $r;
$numbered['output'][0]['results'][0]['text'] = "7.4.1.6 Reporting\nTestbeleg";
check(!hermes_answer($numbered)['grounded'], 'Vorhandene Kapitelnummer nicht durch Stichwort ersetzen');
$numbered['output'][1]['content'][0]['text'] = "Testantwort\nGrundlage im Referenzhandbuch: Kapitel 7.4.1.6 – Reporting";
check(hermes_answer($numbered)['grounded'], 'Existierende Kapitelnummer akzeptiert');
$numbered['output'][1]['content'][0]['text'] = "Testantwort\nGrundlage im Referenzhandbuch: Kapitel 99.1 – Erfunden";
check(!hermes_answer($numbered)['grounded'], 'Nicht gefundene Kapitelnummer gesperrt');
echo "$count Prüfungen erfolgreich.\n";
