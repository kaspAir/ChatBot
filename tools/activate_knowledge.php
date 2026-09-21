<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Nur Administratoren mit Shellzugriff können einen geprüften Wissensstand aktivieren.
$version = $argv[1] ?? '';
if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._-]{0,79}$/D', $version) || ($argv[2] ?? '') !== '--reviewed') {
    fwrite(STDERR, "Aufruf nach fachlicher Prüfung: php tools/activate_knowledge.php VERSION --reviewed\n"); exit(1);
}
$directory = __DIR__ . '/../knowledge/releases';
$record = json_decode((string) @file_get_contents("$directory/$version.json"), true);
if (!is_array($record) || ($record['status'] ?? '') !== 'indexed' || empty($record['vector_store_id'])) {
    fwrite(STDERR, "Kein erfolgreich indexierter Wissensstand gefunden.\n"); exit(1);
}
$record['activated_at'] = gmdate('c');
// Umbenennen innerhalb desselben Dateisystems tauscht die Konfiguration atomar aus.
$target = __DIR__ . '/../knowledge/active.json';
$temp = tempnam(dirname($target), '.activate-');
if ($temp === false || file_put_contents($temp, json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false || !rename($temp, $target)) {
    fwrite(STDERR, "Aktivierung fehlgeschlagen.\n"); exit(1);
}
echo "Aktiviert: $version. Frühere Versionen bleiben für einen Rückwechsel erhalten.\n";
