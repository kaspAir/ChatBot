<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../src/conversation.php';

$cases = [
    'risiko' => ['question'=>'In meinem Projekt reagiert der Auftraggeber überhaupt nicht auf meine Risiko-Bedenken. Wie soll ich vorgehen?'],
    'anschluss' => ['question'=>'Ich habe die Risiken bereits schriftlich dokumentiert und zweimal angesprochen. Eine Entscheidung bekomme ich trotzdem nicht. Was wäre jetzt mein nächster Schritt?', 'after'=>'risiko'],
    'phasen' => ['question'=>'Erkläre mir die Phasenberichte und wann sie erstellt werden. Unterscheide klassisches und agiles Vorgehen und sage auch, was bei Initialisierung und Abschluss gilt.'],
    'vergleich' => ['question'=>'Was ist der Unterschied zwischen Releasebericht und Phasenbericht? Ist nach jedem Release zwingend eine Freigabe nötig?'],
    'praxis' => ['question'=>'Wir sollen unser HERMES-Projekt abschliessen, aber es gibt noch offene Mängel und Pendenzen. Ist ein Abschluss trotzdem möglich? Was sollte ich als Projektleiter konkret klären?'],
    'fachfremd' => ['question'=>'Was ist die Hauptstadt von Frankreich?'],
];
$models = ['gpt-4o', 'gpt-4.1', 'gpt-5.4'];
echo "Modellvergleich: " . implode(', ', $models) . "\n";
echo "Sechs Fälle je Modell, maximal 18 kostenpflichtige Responses-Aufrufe mit File Search. Keine Wiederholungen.\n";
echo "Gleicher Website-Payload, 4000 Ausgabetokens, 75 Sekunden Timeout; Standard-Reasoning des jeweiligen Modells.\n";
echo "Gleiches Handbuch und Suchverfahren; die tatsächlich abgerufenen Textstellen können variieren.\n";
echo "Ein Durchlauf ist eine erste Stichprobe, keine statistische Rangliste. Website-Konfiguration bleibt unverändert.\n";
if (!in_array('--live', $argv, true)) {
    foreach ($cases as $id=>$case) echo "$id: {$case['question']}\n";
    echo "Start auf dem Hosting: php tools/compare_models.php --live\n";
    exit;
}
$config = require __DIR__ . '/../config/config.php';
if (empty($config['api_key']) || empty($config['vector_store_id']) || empty($config['conversation_prompt'])) {
    fwrite(STDERR, "Aktive Website-Konfiguration unvollständig. Kein API-Aufruf.\n"); exit(1);
}
if (!extension_loaded('curl')) { fwrite(STDERR, "cURL fehlt.\n"); exit(1); }
umask(0077);
$directory = __DIR__ . '/../knowledge/model-comparisons';
if (!is_dir($directory) && !mkdir($directory, 0700, true)) { fwrite(STDERR, "Ergebnisordner nicht erstellbar.\n"); exit(1); }
$file = $directory . '/' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.json';
$report = [
    'started_at'=>gmdate('c'), 'knowledge_version'=>$config['knowledge_version'],
    'prompt_sha256'=>hash('sha256', $config['conversation_prompt']),
    'conversation_code_sha256'=>hash_file('sha256', __DIR__ . '/../src/conversation.php'),
    'search_store_sha256'=>hash('sha256', $config['vector_store_id']),
    'models'=>$models, 'timeout_seconds'=>75, 'max_output_tokens'=>4000,
    'reasoning'=>'API defaults', 'repetitions'=>1, 'results'=>[],
];
$save = static function () use (&$report, $file): void {
    if (file_put_contents($file, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), LOCK_EX) === false) {
        throw new RuntimeException('Ergebnisse konnten nicht gespeichert werden.');
    }
};
$save();
echo "Ergebnisdatei: $file\n";
$stopAll = false;
foreach ($models as $model) {
    $history = [];
    foreach ($cases as $id=>$case) {
        if (isset($case['after']) && !$history) {
            $report['results'][] = ['model'=>$model, 'case'=>$id, 'status'=>'skipped', 'reason'=>'Vorherige Antwort technisch fehlgeschlagen.'];
            $save(); continue;
        }
        $question = $case['question'];
        $modelConfig = $config; $modelConfig['model'] = $model;
        $payload = hermes_conversation_payload($modelConfig, $question, isset($case['after']) ? $history : []);
        echo "\n[$model / $id] Anfrage läuft ...\n"; flush();
        $start = microtime(true);
        $ch = curl_init('https://api.openai.com/v1/responses');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true,
            CURLOPT_HTTPHEADER=>['Authorization: Bearer ' . $config['api_key'], 'Content-Type: application/json'],
            CURLOPT_POSTFIELDS=>json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_CONNECTTIMEOUT=>10, CURLOPT_TIMEOUT=>75,
        ]);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl = curl_errno($ch); curl_close($ch);
        $response = is_string($raw) ? json_decode($raw, true) : null;
        $row = ['model'=>$model, 'case'=>$id, 'question'=>$question,
            'elapsed_seconds'=>round(microtime(true)-$start, 2), 'http_status'=>$status, 'curl_code'=>$curl,
            'response_model'=>$response['model'] ?? null, 'usage'=>$response['usage'] ?? null];
        if ($status !== 200 || !is_array($response)) {
            // Keine unbearbeiteten API-Fehlermeldungen, Schlüssel oder Request-Payloads ausgeben.
            $row['status']='api_error';
            foreach (['code','param','type'] as $field) {
                $value = $response['error'][$field] ?? '';
                $row['error_'.$field] = is_string($value) && preg_match('/^[a-zA-Z0-9_.\[\]-]{0,120}$/D', $value) ? $value : '';
            }
            echo "Technischer Fehler: HTTP $status, Code " . $row['error_code'] . ". Keine fachliche Bewertung.\n";
        } else {
            $answer = hermes_conversation_answer($response);
            $row['status'] = $answer['remember'] ? 'answer' : 'technical_failure';
            $row['diagnostic'] = $answer['diagnostic'];
            $row['reply'] = $answer['reply'];
            $row['sources'] = $answer['sources'];
            $row['incomplete_details'] = $response['incomplete_details'] ?? null;
            $row['search_calls'] = [];
            foreach ($response['output'] ?? [] as $item) {
                if (($item['type'] ?? '') === 'file_search_call') {
                    $row['search_calls'][] = ['status'=>$item['status'] ?? null, 'queries'=>$item['queries'] ?? [],
                        'results'=>array_map(static fn($hit)=>['text'=>$hit['text'] ?? '', 'score'=>$hit['score'] ?? null], $item['results'] ?? [])];
                }
            }
            echo $answer['reply'] . "\n";
            if ($id === 'risiko' && $answer['remember']) $history = [
                ['role'=>'user','content'=>$question], ['role'=>'assistant','content'=>$answer['reply']],
            ];
        }
        echo "Dauer: {$row['elapsed_seconds']} s; Tokenverbrauch: " . json_encode($row['usage']) . "\n";
        $report['results'][] = $row; $save();
        // Nicht wiederholt mit fehlender Berechtigung/defektem Request anfragen.
        if (in_array($status, [401,429], true)) { $stopAll=true; break; }
        if (in_array($status, [400,403,404], true)) { echo "Weitere Fälle dieses Modells übersprungen.\n"; break; }
    }
    if ($stopAll) { echo "Lauf wegen Authentifizierung oder Limit angehalten. Teilresultate gespeichert.\n"; break; }
}
$report['finished_at']=gmdate('c'); $report['stopped_early']=$stopAll; $save();
echo "\nErgebnisse gespeichert: $file\n";
echo "Bitte die JSON-Datei zur fachlichen Auswertung bereitstellen. Sie enthält Testantworten und Handbuchauszüge, keine API-Schlüssel.\n";
