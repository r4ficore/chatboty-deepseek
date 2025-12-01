<?php
// deepseek_proxy.php
// Bezpieczny most do DeepSeek API - POPRAWIONA WERSJA

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'ok'    => false,
        'error' => 'Unsupported method. Send a POST request with JSON { model, messages[] }.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Włącz logowanie błędów
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/deepseek_errors.log');

const MAX_TOKENS_CAP = 5000;

function respond(int $status, array $payload): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Optymalizuje wiadomości - usuwa starą historię, zachowuje tylko najważniejsze
 */
function optimizeMessages(array $messages): array {
    $systemMessages = [];
    $otherMessages  = [];

    foreach ($messages as $message) {
        if (!is_array($message) || empty($message['role']) || !isset($message['content'])) {
            // pomijamy uszkodzone wpisy, żeby nie wysadzić całej rozmowy
            continue;
        }
        if ($message['role'] === 'system') {
            $systemMessages[] = $message;
        } else {
            $otherMessages[] = $message;
        }
    }

    // Zachowaj tylko ostatnie 8 wiadomości konwersacji + wszystkie systemowe
    $recentMessages = array_slice($otherMessages, -8);

    return array_merge($systemMessages, $recentMessages);
}

function validateMessages($messages): ?array {
    if (!is_array($messages)) {
        respond(400, ['ok' => false, 'error' => 'Missing messages[]']);
    }

    $normalized = [];
    foreach ($messages as $msg) {
        if (!is_array($msg) || empty($msg['role']) || !array_key_exists('content', $msg)) {
            continue; // pomijamy błędne rekordy
        }
        $role    = (string)$msg['role'];
        $content = is_string($msg['content']) ? $msg['content'] : json_encode($msg['content']);
        $normalized[] = ['role' => $role, 'content' => $content];
    }

    if (!$normalized) {
        respond(400, ['ok' => false, 'error' => 'No valid messages supplied']);
    }

    return $normalized;
}

/**
 * Ponawia żądanie z krótszym kontekstem
 */
function retryWithShorterContext(array $originalInput, string $apiKey): void {
    $optimizedMessages = optimizeMessages($originalInput['messages']);

    $payload = [
        'model'       => $originalInput['model'] ?? 'deepseek-chat',
        'messages'    => $optimizedMessages,
        'stream'      => false,
        'max_tokens'  => 3000,
        'temperature' => 0.7,
    ];

    $ch = curl_init('https://api.deepseek.com/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_TIMEOUT    => 120,
    ]);

    $resp     = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    http_response_code((int)$httpcode);
    echo $resp;
    exit;
}

try {
    $raw   = file_get_contents('php://input');
    $input = json_decode($raw, true);

    if (!is_array($input)) {
        respond(400, ['ok' => false, 'error' => 'Invalid JSON payload. Send { model, messages[] }']);
    }

    $model        = $input['model'] ?? 'deepseek-chat';
    $messages     = validateMessages($input['messages'] ?? null);

    // 🔑 Klucz DeepSeek API - preferuj zmienną środowiskową, w ostateczności fallback
    $apiKey = getenv('DEEPSEEK_API_KEY') ?: 'sk-91882eb201bf43a7ab5f18c4d52df92e';

    if (!$apiKey) {
        respond(500, ['ok' => false, 'error' => 'DeepSeek API key not configured']);
    }

    // Budujemy payload dla DeepSeek z OGRANICZONĄ historią
    $payload = [
        'model'       => $model,
        'messages'    => optimizeMessages($messages), // OPTYMALIZACJA HISTORII
        'stream'      => false,
        'max_tokens'  => MAX_TOKENS_CAP, // bezpieczny limit domyślny
        'temperature' => 0.7,
    ];

    // Dodajemy opcjonalne parametry
    foreach (['temperature', 'max_tokens', 'top_p', 'presence_penalty', 'frequency_penalty', 'stream'] as $opt) {
        if (!array_key_exists($opt, $input)) {
            continue;
        }

        $value = $input[$opt];

        if ($opt === 'max_tokens') {
            $value = (int)$value;
            if ($value <= 0) {
                respond(400, ['ok' => false, 'error' => 'max_tokens must be greater than 0']);
            }
            // Ograniczamy do bezpiecznego pułapu, by uniknąć błędów 400 z API
            $value = min($value, MAX_TOKENS_CAP);
        }

        $payload[$opt] = $value;
    }

    // DeepSeek API endpoint
    $ch = curl_init('https://api.deepseek.com/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
            'User-Agent: Enigma-EBook-Builder/1.0',
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_TIMEOUT        => 180, // Zwiększony timeout do 3 minut
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
    ]);

    $resp     = curl_exec($ch);
    $errno    = curl_errno($ch);
    $errmsg   = curl_error($ch);
    $httpcode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Logowanie dla debugowania
    error_log("DeepSeek API Response - HTTP: $httpcode, Error: $errmsg");

    if ($errno) {
        respond(502, ['ok' => false, 'error' => "DeepSeek API connection error: $errmsg"]);
    }

    if ($httpcode === 502) {
        // Próba ponowienia z krótszym kontekstem
        error_log('502 Error - Retrying with shorter context');
        retryWithShorterContext($input, $apiKey);
    }

    http_response_code($httpcode);
    echo $resp;

} catch (Throwable $e) {
    error_log('DeepSeek Proxy Exception: ' . $e->getMessage());
    respond(500, ['ok' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}
?>
