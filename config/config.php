<?php
declare(strict_types=1);

/**
 * Zentrale Konfiguration ("Konfiguration vor Programmierung").
 *
 * Liest Secrets aus Umgebungsvariablen bzw. aus einer .env-Datei, die im
 * Projekt-Root liegt – also OBERHALB des Web-Roots (public/) und damit nie
 * per URL erreichbar.
 */

/** Lädt KEY=VALUE-Zeilen aus einer .env-Datei in die Umgebung. */
function chatbot_load_env(string $path): void
{
    if (!is_readable($path)) {
        return;
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
        $key   = trim($key);
        $value = trim($value);
        // Optionale Anführungszeichen entfernen
        $value = preg_replace('/^([\'"])(.*)\1$/', '$2', $value) ?? $value;
        if ($key !== '' && getenv($key) === false) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

chatbot_load_env(__DIR__ . '/../.env');

function chatbot_env(string $key, ?string $default = null): ?string
{
    $v = getenv($key);
    return ($v === false || $v === '') ? $default : $v;
}

return [
    'api_key'         => chatbot_env('OPENAI_API_KEY'),
    'vector_store_id' => chatbot_env('OPENAI_VECTOR_STORE_ID'),
    'model'           => chatbot_env('OPENAI_MODEL', 'gpt-4o'),
    'system_prompt'   => (string) (@file_get_contents(__DIR__ . '/system_prompt.txt') ?: ''),
];
