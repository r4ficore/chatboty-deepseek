<?php
// deepseek_proxy.php
// Bezpieczny most do DeepSeek API - POPRAWIONA WERSJA

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

// Włącz logowanie błędów
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/deepseek_errors.log');

/**
 * Optymalizuje wiadomości - usuwa starą historię, zachowuje tylko najważniejsze
 */
function optimizeMessages($messages) {
    // Zawsze zachowuj systemowe instrukcje
    $systemMessages = [];
    $otherMessages = [];
    
    foreach ($messages as $message) {
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

/**
 * Ponawia żądanie z krótszym kontekstem
 */
function retryWithShorterContext($originalInput, $apiKey) {
    $optimizedMessages = optimizeMessages($originalInput['messages']);
    
    $payload = [
        'model' => $originalInput['model'] ?? 'deepseek-chat',
        'messages' => $optimizedMessages,
        'stream' => false,
        'max_tokens' => 3000,
        'temperature' => 0.7
    ];
    
    $ch = curl_init('https://api.deepseek.com/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer '.$apiKey,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 120,
    ]);
    
    $resp = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    http_response_code($httpcode);
    echo $resp;
    exit;
}

try {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);
    
    if (!is_array($input)) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>'Invalid JSON']);
        exit;
    }

    $model    = $input['model'] ?? 'deepseek-chat';
    $messages = $input['messages'] ?? null;
    
    if (!is_array($messages)) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>'Missing messages[]']);
        exit;
    }

    // 🔑 Klucz DeepSeek API
    $apiKey = 'sk-91882eb201bf43a7ab5f18c4d52df92e';
    
    if (!$apiKey) {
        http_response_code(500);
        echo json_encode(['ok'=>false,'error'=>'DeepSeek API key not configured']);
        exit;
    }

    // Budujemy payload dla DeepSeek z OGRANICZONĄ historią
    $payload = [
        'model'    => $model,
        'messages' => optimizeMessages($messages), // OPTYMALIZACJA HISTORII
        'stream'   => false,
        'max_tokens' => 5000, // Ogranicz odpowiedź
        'temperature' => 0.7
    ];
    
    // Dodajemy opcjonalne parametry
    foreach (['temperature','max_tokens','top_p','presence_penalty','frequency_penalty'] as $opt) {
        if (array_key_exists($opt, $input)) { 
            $payload[$opt] = $input[$opt]; 
        }
    }
    
    // DeepSeek API endpoint
    $ch = curl_init('https://api.deepseek.com/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer '.$apiKey,
            'Content-Type: application/json',
            'User-Agent: Enigma-EBook-Builder/1.0'
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
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
        http_response_code(502);
        echo json_encode(['ok'=>false,'error'=>"DeepSeek API connection error: $errmsg"]);
        exit;
    }

    if ($httpcode === 502) {
        // Próba ponowienia z krótszym kontekstem
        error_log("502 Error - Retrying with shorter context");
        retryWithShorterContext($input, $apiKey);
        exit;
    }

    // Przekazujemy odpowiedź DeepSeek 1:1 do frontendu
    http_response_code($httpcode);
    echo $resp;

} catch (Throwable $e) {
    error_log("DeepSeek Proxy Exception: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'Server error: '.$e->getMessage()]);
}
?>
