<?php
// combine_ebooks.php
// Zapis zaakceptowanych plików + scalanie w finalny TXT.

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

// Bazowa ścieżka zapisu (publicznie dostępna)
$BASE_DIR = __DIR__ . '/ebooks/temp';

function json_out($data, int $code=200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
function sanitize_id(string $s): string {
    return preg_replace('/[^A-Za-z0-9_\-]/', '', $s);
}
function sanitize_filename(?string $s) {
    if (!$s) return false;
    $s = basename($s);
    // Dopuszczalne tylko txt-y z oczekiwanym wzorcem nazw:
    if (!preg_match('/^(spis_tresci|rozdzial_\d{2}|reasoning_\d{2}|ebook_final)\.txt$/i', $s)) {
        return false;
    }
    return $s;
}
function ensure_dir(string $dir): bool {
    return is_dir($dir) ?: mkdir($dir, 0755, true);
}

try {
    $raw   = file_get_contents('php://input');
    $input = json_decode($raw, true);
    if (!is_array($input)) json_out(['ok'=>false,'error'=>'Invalid JSON'], 400);

    $action = $input['action'] ?? '';

    if ($action === 'save') {
        $sessionId = sanitize_id((string)($input['sessionId'] ?? ''));
        $filename  = sanitize_filename($input['filename'] ?? '');
        $content   = (string)($input['content'] ?? '');

        if (!$sessionId || !$filename) json_out(['ok'=>false,'error'=>'Invalid sessionId/filename'], 400);
        if (strlen($content) > 10 * 1024 * 1024) json_out(['ok'=>false,'error'=>'File too large'], 413);

        $dir = $BASE_DIR . '/' . $sessionId;
        if (!ensure_dir($dir)) json_out(['ok'=>false,'error'=>'Cannot create directory'], 500);

        $path = $dir . '/' . $filename;
        $ok = file_put_contents($path, $content);
        if ($ok === false) json_out(['ok'=>false,'error'=>'Write failed'], 500);
        @chmod($path, 0644);

        $url = '/ebooks/temp/' . rawurlencode($sessionId) . '/' . rawurlencode($filename);
        json_out(['ok'=>true, 'url'=>$url]);
    }

    if ($action === 'delete') {
        $sessionId = sanitize_id((string)($input['sessionId'] ?? ''));
        $filename  = sanitize_filename($input['filename'] ?? '');
        if (!$sessionId || !$filename) json_out(['ok'=>false,'error'=>'Invalid sessionId/filename'], 400);

        $dir = $BASE_DIR . '/' . $sessionId;
        if (!is_dir($dir)) json_out(['ok'=>true, 'deleted'=>false]);

        $path = $dir . '/' . $filename;
        if (!is_file($path)) json_out(['ok'=>true, 'deleted'=>false]);

        if (!@unlink($path)) json_out(['ok'=>false,'error'=>'Delete failed'], 500);

        json_out(['ok'=>true, 'deleted'=>true]);
    }

    if ($action === 'combine') {
        $sessionId = sanitize_id((string)($input['sessionId'] ?? ''));
        if (!$sessionId) json_out(['ok'=>false,'error'=>'Invalid sessionId'], 400);

        $dir = $BASE_DIR . '/' . $sessionId;
        if (!is_dir($dir)) json_out(['ok'=>false,'error'=>'Session not found'], 404);

        $final = '';

        // 1) Spis treści (jeśli istnieje)
        $spis = $dir . '/spis_tresci.txt';
        if (is_file($spis)) {
            $final .= file_get_contents($spis) . "\n\n";
        }

        // 2) Rozdziały – sort po numerze
        $chapters = glob($dir . '/rozdzial_*.txt') ?: [];
        usort($chapters, function($a, $b) {
            $na = (int)preg_replace('/\D+/', '', basename($a));
            $nb = (int)preg_replace('/\D+/', '', basename($b));
            return $na <=> $nb;
        });

        foreach ($chapters as $cf) {
            $final .= file_get_contents($cf) . "\n\n";
        }

        if ($final === '') json_out(['ok'=>false,'error'=>'No chapters to combine'], 400);

        $out = $dir . '/ebook_final.txt';
        $ok  = file_put_contents($out, $final);
        if ($ok === false) json_out(['ok'=>false,'error'=>'Write final failed'], 500);
        @chmod($out, 0644);

        $url = '/ebooks/temp/' . rawurlencode($sessionId) . '/ebook_final.txt';
        json_out(['ok'=>true, 'url'=>$url]);
    }

    json_out(['ok'=>false,'error'=>'Unknown action'], 400);

} catch (Throwable $e) {
    json_out(['ok'=>false,'error'=>$e->getMessage()], 500);
}
