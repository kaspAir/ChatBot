<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../src/verification.php';
if (!in_array('--live', $argv, true)) {
    echo "Aufruf: php tools/check_verifier.php --live\nEin kostenpflichtiger API-Aufruf prüft vier feste Kontrollfälle. Keine Antwortneugenerierung.\n";
    exit;
}
$config = require __DIR__ . '/../config/config.php';
if (empty($config['api_key'])) { fwrite(STDERR, "API-Schlüssel fehlt.\n"); exit(1); }
$cases = require __DIR__ . '/../tests/verification_cases.php';
$payload = hermes_verification_payload($config, 'Prüfe die folgenden Aussagen zu Phasenberichten jeweils anhand ihres zugeordneten Belegs.', array_column($cases, 'claim'));
$response = hermes_verifier_request($config, $payload);
$result = hermes_verification_result($response, count($cases));
if ($result['diagnostic'] === 'verification_invalid') { fwrite(STDERR, "Prüfdienst fehlgeschlagen oder ungültige Prüfausgabe. Keine Freigabe.\n"); exit(1); }
$checks = array_column($result['checks'], null, 'claim');
$failed = 0;
foreach ($cases as $i => $case) {
    $check = $checks[$i + 1];
    $ok = $check['supported'] === $case['expected'];
    if (!$ok) $failed++;
    echo ($ok ? 'BESTANDEN: ' : 'NICHT BESTANDEN: ') . $case['name'] . "\n" . $check['reason'] . "\n";
}
echo "Ein API-Aufruf. Diese Kontrollfälle ersetzen keine fachliche Gesamtabnahme.\n";
exit($failed ? 1 : 0);
