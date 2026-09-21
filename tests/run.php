<?php
declare(strict_types=1);
require __DIR__ . '/../src/retrieval.php';
$count = 0;
function check(bool $ok, string $label): void {
    global $count;
    if (!$ok) { fwrite(STDERR, "FAIL: $label\n"); exit(1); }
    $count++;
}
function fixture(array $answer, ?string $evidence = null): array {
    $evidence ??= "7.4.1.6 Reporting\nAm Ende der Phasen Konzept, Realisierung, Einführung und Umsetzung werden die Ergebnisse der Phase aufbereitet.\n3.4.1.1 Projektsteuerung\n[ERGÄNZUNG KASPAR/BKI: Es gibt keinen Phasenbericht Initialisierung.]";
    return ['status' => 'completed', 'output' => [
        ['type' => 'file_search_call', 'status' => 'completed', 'results' => [['file_id' => 'file_test', 'text' => $evidence]]],
        ['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode($answer)]]],
    ]];
}
$a = ['sufficient_evidence' => true, 'claims' => [
    ['statement' => 'Für die Initialisierung gibt es keinen Phasenbericht.', 'chapter' => '3.4.1.1', 'evidence_quote' => 'Es gibt keinen Phasenbericht Initialisierung.'],
]];
$r = fixture($a);
check(hermes_answer($r)['grounded'], 'Wörtlicher Beleg im richtigen Kapitel akzeptiert');
check(str_contains(hermes_answer($r)['reply'], '[1] Kapitel 3.4.1.1 – Projektsteuerung'), 'Quelle aus gefundenem Titel erzeugt');
check(count(hermes_answer($r)['sources']) === 1, 'Nur verwendeter Beleg ausgegeben');
$bad = $a; $bad['claims'][0]['chapter'] = '99.1';
check(!hermes_answer(fixture($bad))['grounded'], 'Erfundenes Kapitel gesperrt');
$bad = $a; $bad['claims'][0]['chapter'] = '7.4.1.6';
check(!hermes_answer(fixture($bad))['grounded'], 'Richtiger Wortlaut unter falschem Kapitel gesperrt');
$bad = $a; $bad['claims'][0]['evidence_quote'] = 'Es gibt immer einen Phasenbericht Initialisierung.';
check(!hermes_answer(fixture($bad))['grounded'], 'Erfundener Wortlaut gesperrt');
$bad = $a; $bad['claims'][0]['chapter'] = 'Kapitel nicht ermittelt';
check(!hermes_answer(fixture($bad))['grounded'], 'Unbestimmte Kapitelangabe gesperrt');
$bad = $a; $bad['claims'][] = ['statement' => 'Unbelegte Zusatzbehauptung', 'chapter' => '', 'evidence_quote' => ''];
check(!hermes_answer(fixture($bad))['grounded'], 'Unbelegte zusätzliche Aussage sperrt Antwort');
$bad = $r; $bad['output'][0]['results'] = [];
check(!hermes_answer($bad)['grounded'], 'Keine Treffer');
$bad = $r; $bad['output'][0]['status'] = 'failed';
check(!hermes_answer($bad)['grounded'], 'Fehlgeschlagene Suche');
$bad = $r; $bad['status'] = 'incomplete';
check(!hermes_answer($bad)['grounded'], 'Unvollständige Antwort');
$bad = $r; $bad['output'][1]['content'][0]['text'] = 'Freitext ohne Struktur';
check(!hermes_answer($bad)['grounded'], 'Unstrukturierter Freitext');
check(hermes_answer(fixture(['sufficient_evidence' => false, 'claims' => []]))['diagnostic'] === 'model_abstained', 'Fachliche Enthaltung separat erkannt');
check(hermes_normalize("Die Er-\ngebnisse  der Phase") === 'Die Ergebnisse der Phase', 'PDF-Trennungen normalisiert');
check(hermes_sections("3.4.1.1\nProjektsteuerung\nText")[0]['title'] === 'Projektsteuerung', 'Kapitelüberschrift mit Zeilenumbruch');
check(hermes_error(429, 'insufficient_quota')[0] === 503, 'Quota separat');
check(hermes_error(429, 'rate_limit_exceeded')[0] === 429, 'Rate Limit separat');
check(hermes_error(0, '')[0] === 504, 'Timeout separat');
$p = hermes_payload(['model'=>'test', 'system_prompt'=>'test', 'vector_store_id'=>'vs_test'], 'Frage', array_fill(0, 20, ['role'=>'user','content'=>'alt']));
check(count($p['input']) === 7, 'Kontext begrenzt');
check($p['tool_choice'] === 'required' && $p['store'] === false, 'Suche erzwungen, keine Response-Speicherung');
check($p['text']['format']['strict'] === true, 'Strukturiertes Antwortschema angefordert');
$marked = $a;
$marked['claims'][0]['evidence_quote'] = '[ERGÄNZUNG KASPAR/BKI: Es gibt keinen Phasenbericht Initialisierung.]';
$spaced = "3.4.1.1 Projektsteuerung\n[ERGÄNZUNG KASPAR/BKI: Es gibt keinen Phasenbericht Initialisierung. ]";
check(hermes_answer(fixture($marked, $spaced))['grounded'], 'Leerzeichen vor Markierungsende normalisiert');
$marked['claims'][0]['evidence_quote'] = '[ERGÄNZUNG KASPAR/BKI: Es gibt einen Phasenbericht Initialisierung.]';
check(!hermes_answer(fixture($marked, $spaced))['grounded'], 'Veränderte Negation weiterhin gesperrt');
$before = $a;
$before['claims'][0]['chapter'] = '1.4.4.1';
check(!hermes_answer(fixture($before, "Es gibt keinen Phasenbericht Initialisierung.\n1.4.4.1 Abschluss\nAnderer Abschnitt."))['grounded'], 'Text vor Überschrift nicht nachfolgendem Kapitel zugeordnet');
$mixed = $a;
$mixed['claims'][] = $before['claims'][0];
$checks = hermes_diagnose_claims(fixture($mixed));
check(count($checks) === 2 && $checks[0]['diagnostic'] === 'evidence_matched' && $checks[1]['diagnostic'] === 'quote_not_found_in_chapter', 'Einzelfehler sichtbar, ohne gesamte Antwort freizugeben');
$sample = "4.4.1.30 Phasenbericht\n" . str_repeat("Der Phasenbericht dokumentiert Ergebnisse. ", 5) . "\n7.4.1.6 Reporting\nPhasenbericht\nAm Ende der Phasen Konzept, Realisierung, Einführung und Umsetzung werden die Ergebnisse der Phase aufbereitet.\n" . str_repeat("Weitere Angaben zum Reporting. ", 5);
$index = hermes_build_index($sample, 'test-v1');
$hits = hermes_retrieve($index, 'Welche Phasen haben einen Phasenbericht?');
check(in_array('7.4.1.6', array_column($hits, 'chapter')), 'Reporting über Unterabschnitt gefunden');
check(in_array('4.4.1.30', array_column($hits, 'chapter')), 'Ergebnisbeschreibung gefunden');
check($hits === hermes_retrieve($index, 'Welche Phasen haben einen Phasenbericht?'), 'Suche reproduzierbar');
check(hermes_retrieve($index, 'Quantenverschränkung') === [], 'Unbekannter Suchbegriff ohne Treffer');
$localEvidence = [['text' => "3.4.1.1 Projektsteuerung\nEs gibt keinen Phasenbericht Initialisierung."]];
$localResponse = fixture($a); unset($localResponse['output'][0]);
check(hermes_answer($localResponse, $localEvidence)['grounded'], 'Lokaler Textbeleg ohne erfundenen Tool-Aufruf geprüft');
$localPayload = hermes_local_payload(['model'=>'test','system_prompt'=>'test','vector_store_id'=>'vs_test'], 'Frage', [], $localEvidence);
check(!isset($localPayload['tools']) && str_contains($localPayload['instructions'], 'referenzhandbuch_daten'), 'Antwort nutzt vorab ausgewählte Belege');
$original = "6.4.3.9 Testverantwortlicher\n" . str_repeat("Rollenbeschreibung für Testverantwortliche. ", 8);
$register = "6.4.3.9 Testverantwortli-\n" . str_repeat("Registerverweis Phasenbericht Initialisierung abgeschlossen. ", 20);
$duplicateIndex = hermes_build_index($original . "\n" . $register, 'test-v2');
check($duplicateIndex['passages'][0]['title'] === 'Testverantwortlicher', 'Langer späterer Registereintrag ersetzt keine Kapitelbeschreibung');
check(!str_contains(implode(' ', array_column($duplicateIndex['passages'], 'text')), 'Registerverweis'), 'Registerinhalt nicht dem ursprünglichen Kapitel zugeschlagen');
$rankIndex = hermes_build_index($sample . "\n8.1 Fremdthema\n" . str_repeat("Initialisierung abgeschlossen Phasen abgeschlossen. ", 10), 'test-v2');
$rankHits = hermes_retrieve($rankIndex, 'Welche Phasen werden mit einem Phasenbericht abgeschlossen? Gibt es einen Phasenbericht für die Initialisierung?');
check(!in_array('8.1', array_column($rankHits, 'chapter')), 'Explizites Kapitelthema verdrängt Treffer nur auf allgemeine Fragewörter');
check($rankIndex['format'] === 2, 'Neue Indexversion erzwingt Neuaufbau');
echo "$count Prüfungen erfolgreich.\n";
