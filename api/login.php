<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Método inválido.'], 405);
}

$body     = json_decode(file_get_contents('php://input'), true) ?? [];
$username = trim($body['username'] ?? '');
$senha    = trim($body['password'] ?? '');

if (!$username || !$senha) {
    jsonResponse(['ok' => false, 'error' => 'Usuário e senha são obrigatórios.'], 400);
}

$result = Auth::login($username, $senha);

if (!$result['ok']) {
    jsonResponse(['ok' => false, 'error' => $result['error']], 401);
}

// Define para onde redirecionar após login
$redirect = ($result['user']['trocar_senha'] ?? 0)
    ? BASE_URL . '/trocar_senha.php'
    : BASE_URL . '/dashboard.php';

jsonResponse([
    'ok'         => true,
    'user'       => $result['user'],
    'redirect'   => $redirect,
    'csrf_token' => csrfToken(),
]);
