<?php
// gemini_proxy.php
// Proxy dla Gemini 3 (Generative Language API) używany przez Enigma Designer.
// Wymaga PHP 8.0+, rozszerzenia cURL i zmiennej środowiskowej GEMINI_API_KEY.

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

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/gemini_errors.log');

// Domyślny model: Gemini 3 Pro (zgodnie z wymaganiami projektu).
const DEFAULT_MODEL          = 'gemini-3.0-pro';
const DEFAULT_MAX_OUTPUT     = 9000; // pozwala na pełny dokument HTML
const MAX_OUTPUT_CAP         = 12000; // miękki limit bezpieczeństwa
// Gemini 3 dostępny jest w interfejsie v1beta (REST generativelanguage.googleapis.com/v1beta).
const API_BASE               = 'https://generativelanguage.googleapis.com/v1beta';

function respond(int $status, array $payload): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function normalizeMessages($messages): array {
    if (!is_array($messages)) {
        respond(400, ['ok' => false, 'error' => 'Missing messages[]']);
    }

    $system = null;
    $conversation = [];

    foreach ($messages as $idx => $msg) {
        if (!is_array($msg) || empty($msg['role']) || !array_key_exists('content', $msg)) {
            // pomijamy uszkodzone wpisy, ale nie przerywamy
            continue;
        }

        $roleRaw = strtolower((string)$msg['role']);
        $content = is_string($msg['content']) ? $msg['content'] : json_encode($msg['content']);

        if ($roleRaw === 'system' && $system === null) {
            $system = $content;
            continue;
        }

        $role = $roleRaw === 'assistant' ? 'model' : 'user';
        $conversation[] = [
            'role'  => $role,
            'parts' => [ ['text' => $content] ],
        ];
    }

    if (!$conversation) {
        respond(400, ['ok' => false, 'error' => 'No valid user/assistant messages supplied']);
    }

    return [$system, $conversation];
}

function buildPayload(array $input): array {
    [$systemPrompt, $contents] = normalizeMessages($input['messages'] ?? []);

    $model = $input['model'] ?? DEFAULT_MODEL;

    $maxOutputTokens = DEFAULT_MAX_OUTPUT;
    if (isset($input['max_output_tokens'])) {
        $maxOutputTokens = (int)$input['max_output_tokens'];
    } elseif (isset($input['max_tokens'])) { // kompatybilność wstecz
        $maxOutputTokens = (int)$input['max_tokens'];
    }
    if ($maxOutputTokens <= 0) {
        respond(400, ['ok' => false, 'error' => 'max_output_tokens must be > 0']);
    }
    if ($maxOutputTokens > MAX_OUTPUT_CAP) {
        respond(400, ['ok' => false, 'error' => 'max_output_tokens exceeds safe cap']);
    }

    $generationConfig = [
        'temperature'      => isset($input['temperature']) ? (float)$input['temperature'] : 0.35,
        'maxOutputTokens'  => $maxOutputTokens,
    ];

    if (isset($input['top_p'])) {
        $generationConfig['topP'] = (float)$input['top_p'];
    }
    if (isset($input['top_k'])) {
        $generationConfig['topK'] = (int)$input['top_k'];
    }

    $payload = [
        'contents'          => $contents,
        'generationConfig'  => $generationConfig,
    ];

    if ($systemPrompt) {
        // W v1beta dostępne jest pole systemInstruction; wstrzykujemy tam prompt,
        // zamiast traktować go jako wiadomość użytkownika.
        $payload['systemInstruction'] = [
            'role'  => 'system',
            'parts' => [ ['text' => $systemPrompt] ],
        ];
    }

    return [$model, $payload];
}

function callGemini(string $model, array $payload, string $apiKey): array {
    $url = API_BASE . '/models/' . rawurlencode($model) . ':generateContent?key=' . rawurlencode($apiKey);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'User-Agent: Enigma-Designer/1.0'
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_TIMEOUT        => 180,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $resp     = curl_exec($ch);
    $errno    = curl_errno($ch);
    $errmsg   = curl_error($ch);
    $httpcode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno) {
        $status = ($errno === CURLE_OPERATION_TIMEOUTED) ? 504 : 502;
        respond($status, ['ok' => false, 'error' => 'Gemini API connection error: ' . $errmsg]);
    }

    $decoded = json_decode((string)$resp, true);
    if (!is_array($decoded)) {
        respond($httpcode ?: 502, ['ok' => false, 'error' => 'Gemini API returned a non-JSON response']);
    }

    if ($httpcode >= 400) {
        $msg = $decoded['error']['message'] ?? ($decoded['error'] ?? 'Gemini API error');
        respond($httpcode, ['ok' => false, 'error' => $msg]);
    }

    return $decoded;
}

try {
    $raw   = file_get_contents('php://input');
    $input = json_decode($raw, true);

    if (!is_array($input)) {
        respond(400, ['ok' => false, 'error' => 'Invalid JSON payload. Send { model, messages[] }']);
    }

    if (!function_exists('curl_init')) {
        respond(500, ['ok' => false, 'error' => 'cURL extension missing on the server; enable php-curl.']);
    }

    // Obsługa literówki z dwukropkiem w nazwie zmiennej (GEMINI_API_KEY:),
    // aby uniknąć 500 przy błędnie skonfigurowanym .htaccess.
    $apiKey = $input['api_key'] ?? getenv('GEMINI_API_KEY');
    if (!$apiKey) {
        $apiKey = getenv('GEMINI_API_KEY:');
    }
    if (!$apiKey) {
        respond(500, ['ok' => false, 'error' => 'Gemini API key not configured (set GEMINI_API_KEY or pass api_key in body).']);
    }

    [$model, $payload] = buildPayload($input);

    $response = callGemini($model, $payload, $apiKey);

    $candidate = $response['candidates'][0] ?? null;
    $parts = $candidate['content']['parts'] ?? [];
    $text = '';
    foreach ($parts as $part) {
        if (isset($part['text'])) {
            $text .= (string)$part['text'];
        }
    }

    $finishReason = $candidate['finishReason'] ?? null;

    respond(200, [
        'choices' => [
            [
                'message' => [
                    'role'    => 'assistant',
                    'content' => $text,
                ],
                'finish_reason' => $finishReason,
            ]
        ],
        'model' => $model,
        'ok'    => true,
    ]);

} catch (Throwable $e) {
    error_log('Gemini proxy fatal error: ' . $e->getMessage());
    respond(500, ['ok' => false, 'error' => 'Unhandled proxy error: ' . $e->getMessage()]);
}
