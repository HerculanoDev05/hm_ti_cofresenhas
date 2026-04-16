<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap.php';

Auth::requireLogin();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Método inválido.'], 405);
}

verifyCsrf();

$body       = json_decode(file_get_contents('php://input'), true) ?? [];
$senhaAtual = $body['senha_atual'] ?? '';
$novaSenha  = $body['nova_senha']  ?? '';

// Validações básicas
if (!$senhaAtual || !$novaSenha) {
    jsonResponse(['ok' => false, 'error' => 'Preencha todos os campos.'], 400);
}

if (strlen($novaSenha) < 8) {
    jsonResponse(['ok' => false, 'error' => 'A nova senha deve ter no mínimo 8 caracteres.'], 400);
}

// Requisitos de complexidade
if (!preg_match('/[A-Z]/', $novaSenha)) {
    jsonResponse(['ok' => false, 'error' => 'A senha deve conter ao menos uma letra maiúscula.'], 400);
}
if (!preg_match('/[a-z]/', $novaSenha)) {
    jsonResponse(['ok' => false, 'error' => 'A senha deve conter ao menos uma letra minúscula.'], 400);
}
if (!preg_match('/[0-9]/', $novaSenha)) {
    jsonResponse(['ok' => false, 'error' => 'A senha deve conter ao menos um número.'], 400);
}
if (!preg_match('/[^A-Za-z0-9]/', $novaSenha)) {
    jsonResponse(['ok' => false, 'error' => 'A senha deve conter ao menos um caractere especial.'], 400);
}

// Busca usuário atual
$user = Database::queryOne(
    'SELECT id, username, senha_hash, trocar_senha FROM usuarios WHERE id = ? AND ativo = 1',
    [Auth::userId()]
);

if (!$user) {
    jsonResponse(['ok' => false, 'error' => 'Usuário não encontrado.'], 404);
}

// Verifica senha atual
if (!password_verify($senhaAtual, $user['senha_hash'])) {
    Logger::log('SENHA_TROCA_FALHOU', '', false, 'Senha atual incorreta');
    jsonResponse(['ok' => false, 'error' => 'Senha atual incorreta.'], 401);
}

// Não permite reutilizar a mesma senha
if (password_verify($novaSenha, $user['senha_hash'])) {
    jsonResponse(['ok' => false, 'error' => 'A nova senha não pode ser igual à senha atual.'], 400);
}

// Gera novo hash e atualiza
$novoHash = password_hash($novaSenha, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);

Database::execute(
    'UPDATE usuarios
        SET senha_hash      = ?,
            trocar_senha    = 0,
            tentativas_falha = 0,
            senha_expira_em = DATE_ADD(NOW(), INTERVAL 90 DAY)
      WHERE id = ?',
    [$novoHash, Auth::userId()]
);

Logger::log('SENHA_TROCADA', '', true, 'Troca obrigatória concluída');

jsonResponse(['ok' => true, 'message' => 'Senha alterada com sucesso!']);
