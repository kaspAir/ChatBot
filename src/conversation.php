<?php
declare(strict_types=1);
require_once __DIR__ . '/chat.php';

/** Normaler Website-Dialog: freier Antworttext mit Suche im ganzen Handbuch. */
function hermes_conversation_payload(array $config, string $message, array $history): array
{
    $instructions = $config['conversation_prompt'] ??
        (string) file_get_contents(__DIR__ . '/../config/conversation_prompt.txt');
    return [
        'model' => $config['model'], 'instructions' => $instructions, 'store' => false,
        'input' => array_merge(array_slice($history, -6), [['role'=>'user', 'content'=>$message]]),
        'max_output_tokens' => 4000,
        'tools' => [['type'=>'file_search', 'vector_store_ids'=>[$config['vector_store_id']], 'max_num_results'=>16]],
        'tool_choice' => 'required', 'include' => ['file_search_call.results'],
    ];
}

/** Suchtreffer sind Orientierung, keine automatische Bestätigung einzelner Aussagen. */
function hermes_conversation_answer(array $response): array
{
    if (($response['status'] ?? '') !== 'completed') {
        return ['reply'=>'Die Antwort wurde nicht vollständig erstellt. Bitte versuche es nochmals.',
            'sources'=>[], 'diagnostic'=>'response_incomplete', 'remember'=>false];
    }
    $texts = []; $hits = []; $searchComplete = false;
    foreach ($response['output'] ?? [] as $item) {
        if (($item['type'] ?? '') === 'file_search_call' && ($item['status'] ?? '') === 'completed') {
            $searchComplete = true;
            foreach ($item['results'] ?? [] as $hit) {
                if (is_string($hit['text'] ?? null) && trim($hit['text']) !== '' && !empty($hit['file_id'])) {
                    $hits[hash('sha256', $hit['text'])] = $hit['text'];
                }
            }
        }
        if (($item['type'] ?? '') !== 'message' || ($item['role'] ?? 'assistant') !== 'assistant') continue;
        foreach ($item['content'] ?? [] as $part) {
            if (($part['type'] ?? '') === 'output_text' && is_string($part['text'] ?? null)) $texts[] = $part['text'];
        }
    }
    // Native Dateimarker benötigen einen speziellen Renderer. Die zugehörigen
    // Suchausschnitte zeigen wir separat und ohne behauptete Satz-Zitat-Zuordnung.
    $reply = trim(preg_replace('/\x{E200}.*?\x{E201}/us', '', implode("\n\n", $texts)) ?? '');
    if ($reply === '' || !$searchComplete) {
        return ['reply'=>'Die Anfrage konnte nicht vollständig verarbeitet werden. Bitte versuche es nochmals.',
            'sources'=>[], 'diagnostic'=>$reply === '' ? 'empty_answer' : 'search_incomplete', 'remember'=>false];
    }
    $sources = [];
    foreach (array_slice(array_values($hits), 0, 8) as $text) {
        $sources[] = ['label'=>'Fundstelle im Referenzhandbuch', 'text'=>$text];
    }
    return ['reply'=>$reply, 'sources'=>$sources, 'source_mode'=>'retrieval',
        'diagnostic'=>'handbook_conversation', 'remember'=>true];
}
