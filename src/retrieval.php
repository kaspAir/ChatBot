<?php
declare(strict_types=1);
require_once __DIR__ . '/chat.php';

function hermes_terms(string $text): array
{
    $stop = array_flip(explode(' ', 'aber alle als am an auch auf aus bei bis das dass dem den der des die diese dieser dieses doch ein eine einem einen einer eines es für gibt hat ich im in ist kann kein keine mit nach nicht noch nur ob oder sich sind so über um und vom von vor war was welche welcher welches werden wie wird wo zu zum zur'));
    preg_match_all('/[\p{L}\p{N}]+/u', mb_strtolower(hermes_normalize($text)), $words);
    $terms = [];
    foreach ($words[0] as $word) {
        if (isset($stop[$word]) || mb_strlen($word) < 3) continue;
        // Leichte Flexionsnormalisierung, kein semantisches Modell.
        if (mb_strlen($word) > 6) $word = preg_replace('/(?:ern|en|er|es|e|s)$/u', '', $word) ?? $word;
        $terms[] = $word;
    }
    return $terms;
}

function hermes_build_index(string $text, string $version): array
{
    $chapters = []; $titles = [];
    foreach (hermes_sections($text) as $section) {
        if (!preg_match('/\.{3}|…/', $section['title']) && !isset($titles[$section['chapter']])) $titles[$section['chapter']] = $section['title'];
    }
    foreach (hermes_sections($text) as $section) {
        // Inhaltsverzeichnis / Registerzeilen mit Punktführungen sind keine Kapiteltexte.
        if (preg_match('/\.{3}|…/', $section['title'])) continue;
        if (mb_strlen($section['text']) < 120) continue;
        $chapter = $section['chapter'];
        // Die erste ausgeführte Beschreibung ist massgeblich; spätere Registerverweise dürfen sie nicht überschreiben.
        if (!isset($chapters[$chapter])) $chapters[$chapter] = $section;
    }
    $passages = [];
    foreach ($chapters as $section) {
        $parentTitles = [];
        $parent = $section['chapter'];
        while (str_contains($parent, '.')) {
            $parent = substr($parent, 0, strrpos($parent, '.'));
            if (isset($titles[$parent])) $parentTitles[] = $titles[$parent];
        }
        $body = preg_replace('/(\p{L})[-\x{00AD}]\h*\R\h*(?=\p{L})/u', '$1', $section['text']) ?? $section['text'];
        $offset = 0; $part = 0; $length = mb_strlen($body);
        while ($offset < $length) {
            $end = min($offset + 4000, $length);
            if ($end < $length) {
                $candidate = mb_substr($body, $offset, $end - $offset);
                $break = mb_strrpos($candidate, "\n");
                if ($break !== false && $break > 2800) $end = $offset + $break;
            }
            $chunk = mb_substr($body, $offset, $end - $offset);
            $passages[] = ['id' => $section['chapter'] . ':' . $part++, 'chapter' => $section['chapter'],
                'title' => $section['title'], 'parents' => implode(' / ', array_reverse($parentTitles)), 'text' => $section['chapter'] . ' ' . $section['title'] . "\n" . $chunk];
            if ($end >= $length) break;
            // Überlappung erhält Zusammenhänge an Abschnittsgrenzen.
            $offset = max($offset + 1, $end - 450);
        }
    }
    return ['format' => 2, 'version' => $version, 'source_sha256' => hash('sha256', $text), 'passages' => $passages];
}

/** Deterministische Wortsuche: reproduzierbar, ohne API-Kosten; keine semantische Garantie. */
function hermes_retrieve(array $index, string $question, int $limit = 8): array
{
    $terms = array_unique(hermes_terms($question));
    if (!$terms) return [];
    $docs = []; $df = []; $total = 0;
    foreach ($index['passages'] ?? [] as $passage) {
        $tokens = hermes_terms($passage['text']);
        $freq = array_count_values($tokens);
        $docs[] = ['passage' => $passage, 'freq' => $freq, 'length' => count($tokens), 'title' => hermes_terms($passage['title'] . ' ' . ($passage['parents'] ?? '')), 'labels' => array_merge(...array_map(fn($line) => preg_match('/^\h*[\p{L}]{4,}\h*$/u', $line) ? hermes_terms($line) : [], explode("\n", $passage['text'])))];
        $total += count($tokens);
        foreach ($terms as $term) if (isset($freq[$term])) $df[$term] = ($df[$term] ?? 0) + 1;
    }
    if (!$docs) return [];
    // Ein ausdrücklich benanntes Kapitelthema erhält Vorrang vor beiläufigen Fragewörtern.
    // Wähle den seltensten Suchbegriff, der auch in einer Kapitelüberschrift vorkommt.
    $anchor = null;
    foreach ($terms as $term) {
        if (!isset($df[$term])) continue;
        $isHeading = false;
        foreach ($docs as $doc) {
            if (in_array($term, $doc['title'], true)) { $isHeading = true; break; }
        }
        if ($isHeading && ($anchor === null || $df[$term] < $df[$anchor])) $anchor = $term;
    }
    $average = max(1, $total / count($docs)); $ranked = [];
    foreach ($docs as $doc) {
        if ($anchor !== null && !isset($doc['freq'][$anchor])) continue;
        $score = 0.0;
        foreach ($terms as $term) {
            $tf = $doc['freq'][$term] ?? 0;
            if (!$tf) continue;
            $idf = log(1 + (count($docs) - ($df[$term] ?? 0) + 0.5) / (($df[$term] ?? 0) + 0.5));
            if ($anchor !== null && $term !== $anchor) $idf *= 0.2;
            $score += $idf * ($tf * 2.2) / ($tf + 1.2 * (0.25 + 0.75 * $doc['length'] / $average));
            if (in_array($term, $doc['title'], true)) $score += 2 * $idf;
            if (in_array($term, $doc['labels'], true)) $score += 2 * $idf;
        }
        if ($score > 0) $ranked[] = $doc['passage'] + ['score' => round($score, 5)];
    }
    usort($ranked, fn($a, $b) => ($b['score'] <=> $a['score']) ?: strcmp($a['id'], $b['id']));
    return array_slice($ranked, 0, $limit);
}

function hermes_load_index(string $version, array $release): array
{
    if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._-]{0,79}$/D', $version)) throw new RuntimeException('Ungültige Wissensversion.');
    $path = __DIR__ . "/../knowledge/indexes/$version.json";
    $index = json_decode((string) @file_get_contents($path), true);
    $hashes = array_column($release['files'] ?? [], 'sha256');
    if (!is_array($index) || ($index['format'] ?? null) !== 2 || ($index['version'] ?? '') !== $version || !in_array($index['source_sha256'] ?? '', $hashes, true) || empty($index['passages'])) {
        throw new RuntimeException('Lokaler Suchindex fehlt oder passt nicht zur Wissensversion.');
    }
    return $index;
}
