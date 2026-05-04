<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/Crypto.php';
require_once __DIR__ . '/src/Logger.php';
require_once __DIR__ . '/src/Policy.php';
require_once __DIR__ . '/src/Auth.php';

// ─── Handler global: garante resposta JSON em caso de exceção não capturada ─
set_exception_handler(function (Throwable $e): void {
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    $msg = DEBUG_MODE ? $e->getMessage() : 'Erro interno do servidor.';
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
});

// ─── Headers de segurança ─────────────────────────────────────────────────
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
if (!DEBUG_MODE) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
}

Auth::startSession();

function jsonResponse(array $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function readJsonBody(): array {
    $raw  = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        jsonResponse(['ok' => false, 'error' => 'JSON inválido.'], 400);
    }
    return $data ?? [];
}

function verifyCsrf(): void {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        jsonResponse(['ok' => false, 'error' => 'Token CSRF inválido.'], 403);
    }
}

function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
