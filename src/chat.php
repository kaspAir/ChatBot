<?php
declare(strict_types=1);

const HERMES_ABSTENTION = 'Im Referenzhandbuch habe ich dafür keine ausreichende Grundlage gefunden. Ich kann die Frage deshalb nicht verlässlich beantworten.';

function hermes_payload(array $config, string $message, array $history): array
{
    $claim = ['type' => 'object', 'additionalProperties' => false,
        'properties' => [
            'statement' => ['type' => 'string'],
            'chapter' => ['type' => 'string'],
            'evidence_quote' => ['type' => 'string'],
        ], 'required' => ['statement', 'chapter', 'evidence_quote']];
    return [
        'model' => $config['model'], 'instructions' => $config['system_prompt'], 'store' => false,
        'input' => array_merge(array_slice($history, -6), [['role' => 'user', 'content' => $message]]),
        'max_output_tokens' => 2400,
        'tools' => [['type' => 'file_search', 'vector_store_ids' => [$config['vector_store_id']], 'max_num_results' => 10]],
        'tool_choice' => 'required', 'include' => ['file_search_call.results'],
        'text' => ['format' => ['type' => 'json_schema', 'name' => 'hermes_evidence_answer', 'strict' => true,
            'schema' => ['type' => 'object', 'additionalProperties' => false,
                'properties' => ['sufficient_evidence' => ['type' => 'boolean'],
                    'claims' => ['type' => 'array', 'items' => $claim]],
                'required' => ['sufficient_evidence', 'claims']]]],
    ];
}

function hermes_normalize(string $text): string
{
    // Ausschliesslich PDF-Trennungen und Leerraum normalisieren; keine Wörter ergänzen.
    $text = preg_replace('/(\p{L})[-\x{00AD}]\h*\R\h*(?=\p{L})/u', '$1', $text) ?? $text;
    return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
}

/** Kapitelabschnitte innerhalb eines Suchtreffers; Nummer und Titel stammen aus dem Text. */
function hermes_sections(string $text): array
{
    preg_match_all('/^\h*(\d+(?:\.\d+)+)(?=\s)\h*(?:\R\h*)?([^\r\n]+)/m', $text, $matches, PREG_OFFSET_CAPTURE);
    $sections = [];
    foreach ($matches[0] as $i => $match) {
        $start = $match[1];
        $end = $matches[0][$i + 1][1] ?? strlen($text);
        $sections[] = ['chapter' => $matches[1][$i][0], 'title' => trim($matches[2][$i][0]),
            'text' => substr($text, $start, $end - $start)];
    }
    return $sections;
}

/** Prüft Belegwortlaut und Kapitelzuordnung, NICHT die logische Folgerung jeder Aussage. */
function hermes_answer(array $response): array
{
    $reject = fn(string $reason) => ['reply' => 'Die erzeugte Antwort hat die Quellenprüfung nicht bestanden und wird deshalb nicht angezeigt.',
        'sources' => [], 'grounded' => false, 'diagnostic' => $reason];
    if (($response['status'] ?? '') !== 'completed') return $reject('response_incomplete');
    $sections = [];
    $raw = '';
    foreach ($response['output'] ?? [] as $item) {
        if (($item['type'] ?? '') === 'file_search_call' && ($item['status'] ?? '') === 'completed') {
            foreach ($item['results'] ?? [] as $hit) {
                if (!empty($hit['file_id']) && is_string($hit['text'] ?? null)) {
                    array_push($sections, ...hermes_sections($hit['text']));
                }
            }
        }
        if (($item['type'] ?? '') !== 'message') continue;
        foreach ($item['content'] ?? [] as $part) {
            if (($part['type'] ?? '') === 'output_text') $raw .= $part['text'] ?? '';
        }
    }
    $answer = json_decode($raw, true);
    if (!is_array($answer) || !is_bool($answer['sufficient_evidence'] ?? null) || !is_array($answer['claims'] ?? null)) return $reject('invalid_answer_structure');
    if (!$answer['sufficient_evidence']) return ['reply' => HERMES_ABSTENTION, 'sources' => [], 'grounded' => false, 'diagnostic' => 'model_abstained'];
    if (!$sections) return $reject('no_search_sections');
    if (!$answer['claims'] || count($answer['claims']) > 8) return $reject('invalid_claim_count');
    $lines = []; $sources = []; $basis = [];
    foreach ($answer['claims'] as $claim) {
        foreach (['statement', 'chapter', 'evidence_quote'] as $field) {
            if (!is_string($claim[$field] ?? null) || trim($claim[$field]) === '') return $reject('missing_claim_field');
        }
        $quote = hermes_normalize($claim['evidence_quote']);
        if (mb_strlen($quote) < 30 || mb_strlen($quote) > 1800) return $reject('invalid_quote_length');
        $matched = null;
        foreach ($sections as $section) {
            if ($section['chapter'] === $claim['chapter'] && str_contains(hermes_normalize($section['text']), $quote)) {
                $matched = $section; break;
            }
        }
        if ($matched === null) return $reject('quote_not_found_in_chapter');
        $number = count($sources) + 1;
        $label = 'Kapitel ' . $matched['chapter'] . ' – ' . $matched['title'];
        $lines[] = trim($claim['statement']) . " [$number]";
        $basis[] = "[$number] $label";
        $sources[] = ['label' => $label, 'text' => $label . "\n\n" . $quote];
    }
    return ['reply' => implode("\n\n", $lines) . "\n\nGrundlage im Referenzhandbuch\n" . implode("\n", $basis),
        'sources' => $sources, 'grounded' => true, 'diagnostic' => 'evidence_matched'];
}

function hermes_error(int $status, string $code): array
{
    if ($code === 'insufficient_quota') return [503, 'Das API-Kontingent ist derzeit nicht verfügbar. Der Betreiber muss die Abrechnung prüfen.'];
    if ($status === 429) return [429, 'Das Anfragelimit wurde erreicht. Bitte warte kurz und versuche es nochmals.'];
    if ($status === 401 || $status === 403) return [503, 'Die KI-Anbindung ist nicht korrekt freigeschaltet. Bitte informiere den Betreiber.'];
    if ($status === 0) return [504, 'Der KI-Dienst antwortet nicht rechtzeitig. Bitte versuche es später nochmals.'];
    return [502, 'Die Anfrage konnte technisch nicht verarbeitet werden. Bitte informiere den Betreiber mit der Fehlernummer.'];
}
