<?php
declare(strict_types=1);

const HERMES_ABSTENTION = 'Im Referenzhandbuch habe ich dafür keine ausreichende Grundlage gefunden. Ich kann die Frage deshalb nicht verlässlich beantworten.';

function hermes_payload(array $config, string $message, array $history): array
{
    return [
        'model' => $config['model'],
        'instructions' => $config['system_prompt'],
        'store' => false,
        // Nur die letzten drei Frage-Antwort-Paare; kein unbegrenzt wachsender API-Kontext.
        'input' => array_merge(array_slice($history, -6), [['role' => 'user', 'content' => $message]]),
        'max_output_tokens' => 1800,
        'tools' => [['type' => 'file_search', 'vector_store_ids' => [$config['vector_store_id']], 'max_num_results' => 6]],
        'tool_choice' => 'required',
        'include' => ['file_search_call.results'],
    ];
}

/** Technische Quellenprüfung; KEIN Beweis für die sachliche Richtigkeit jeder Aussage. */
function hermes_answer(array $response): array
{
    $fallback = ['reply' => HERMES_ABSTENTION, 'sources' => [], 'grounded' => false];
    if (($response['status'] ?? '') !== 'completed') {
        return $fallback;
    }
    $results = [];
    $cited = [];
    $reply = '';
    foreach ($response['output'] ?? [] as $item) {
        if (($item['type'] ?? '') === 'file_search_call' && ($item['status'] ?? '') === 'completed') {
            foreach ($item['results'] ?? [] as $result) {
                if (!empty($result['file_id']) && !empty($result['text'])) {
                    $results[$result['file_id']][] = $result['text'];
                }
            }
        }
        if (($item['type'] ?? '') !== 'message') continue;
        foreach ($item['content'] ?? [] as $part) {
            if (($part['type'] ?? '') !== 'output_text') continue;
            $reply .= ($part['text'] ?? '') . "\n";
            foreach ($part['annotations'] ?? [] as $annotation) {
                if (($annotation['type'] ?? '') === 'file_citation') {
                    $cited[$annotation['file_id'] ?? ''] = true;
                }
            }
        }
    }
    $reply = trim($reply);
    if ($reply === HERMES_ABSTENTION || !$results || !$cited || !str_contains($reply, 'Grundlage im Referenzhandbuch')) return $fallback;
    $sources = [];
    foreach ($cited as $id => $_) {
        // Referenzen auf nicht in dieser Anfrage gefundene Dateien zurückweisen.
        if (!isset($results[$id])) return $fallback;
        foreach (array_unique($results[$id]) as $excerpt) {
            $sources[] = ['label' => 'Referenzhandbuch – Suchtreffer', 'text' => $excerpt];
        }
    }
    // Kapitelnummern müssen in einer Überschrift der aktuellen Suchtreffer vorkommen.
    // Dies prüft ihre Existenz, nicht die inhaltliche Tragfähigkeit der Antwort.
    $evidence = implode("\n", array_column($sources, 'text'));
    preg_match_all('/^\h*(\d+(?:\.\d+)+)(?=\s)/m', $evidence, $headings);
    $basis = explode('Grundlage im Referenzhandbuch', $reply, 2)[1];
    preg_match_all('/(?<![\d.])(\d+(?:\.\d+)+)(?![\d.])/', $basis, $references);
    if (($headings[1] && !$references[1]) || array_diff($references[1], $headings[1])) {
        return ['reply' => 'Die Antwort konnte nicht mit ausreichend nachvollziehbaren Kapitelangaben belegt werden. Bitte formuliere die Frage genauer oder versuche es nochmals.', 'sources' => [], 'grounded' => false];
    }
    // API-interne Zitationsmarker werden durch die ausklappbaren Suchtreffer ersetzt.
    $reply = trim(preg_replace('/【[^】]*】|filecite[^]*/u', '', $reply) ?? $reply);
    return ['reply' => $reply, 'sources' => $sources, 'grounded' => true];
}

function hermes_error(int $status, string $code): array
{
    if ($code === 'insufficient_quota') return [503, 'Das API-Kontingent ist derzeit nicht verfügbar. Der Betreiber muss die Abrechnung prüfen.'];
    if ($status === 429) return [429, 'Das Anfragelimit wurde erreicht. Bitte warte kurz und versuche es nochmals.'];
    if ($status === 401 || $status === 403) return [503, 'Die KI-Anbindung ist nicht korrekt freigeschaltet. Bitte informiere den Betreiber.'];
    if ($status === 0) return [504, 'Der KI-Dienst antwortet nicht rechtzeitig. Bitte versuche es später nochmals.'];
    return [502, 'Die Anfrage konnte technisch nicht verarbeitet werden. Bitte informiere den Betreiber mit der Fehlernummer.'];
}
