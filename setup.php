<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/src/Database.php';

if (file_exists(__DIR__ . '/setup.lock')) {
    die('<h2 style="font-family:monospace;color:#e24b4a">Setup já executado. Delete setup.php do servidor.</h2>');
}

$users = [
    'admin'    => 'Admin@2024!',
    'gerente'  => 'Gerente@2024!',
    'operador' => 'Operador@2024!',
    'viewer'   => 'Viewer@2024!',
];

$ok = []; $erro = [];
foreach ($users as $u => $s) {
    $hash = password_hash($s, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
    $n    = Database::execute('UPDATE usuarios SET senha_hash=? WHERE username=?', [$hash, $u]);
    $n > 0 ? ($ok[] = "$u → OK") : ($erro[] = "$u → não encontrado");
}

file_put_contents(__DIR__ . '/setup.lock', date('Y-m-d H:i:s'));
?><!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="UTF-8"><title>Setup Cofre</title>
<style>body{font-family:monospace;max-width:600px;margin:3rem auto;background:#0f1117;color:#e8eaf0}
h1{color:#1db87a}.ok{color:#1db87a}.err{color:#e24b4a}
.box{background:#161b27;border:1px solid #252d3d;border-radius:12px;padding:1.5rem;margin-top:1.5rem}
table{width:100%;border-collapse:collapse}td,th{padding:6px 10px;border-bottom:1px solid #252d3d;text-align:left}
th{color:#9aa3b8;font-size:.8rem}
.warn{background:#2b1d0a;border:1px solid rgba(245,158,11,.4);border-radius:8px;padding:1rem;margin-top:1rem;color:#fcd34d;font-size:.85rem}
a{color:#4f8ef7}</style>
</head>
<body>
<h1>✅ Setup do Cofre de Senhas</h1>
<?php foreach($ok   as $m): ?><p class="ok">✓ <?=htmlspecialchars($m)?></p><?php endforeach; ?>
<?php foreach($erro as $m): ?><p class="err">✗ <?=htmlspecialchars($m)?></p><?php endforeach; ?>
<div class="box">
  <h3>Credenciais Iniciais</h3>
  <table><tr><th>Usuário</th><th>Senha</th><th>Nível</th></tr>
  <tr><td>admin</td>   <td>Admin@2024!</td>    <td>Administrador (4)</td></tr>
  <tr><td>gerente</td> <td>Gerente@2024!</td>  <td>Gerente (3)</td></tr>
  <tr><td>operador</td><td>Operador@2024!</td> <td>Operador (2)</td></tr>
  <tr><td>viewer</td>  <td>Viewer@2024!</td>   <td>Visualizador (1)</td></tr>
  </table>
</div>
<div class="warn">⚠️ Troque as senhas após o primeiro acesso. Delete ou restrinja acesso a <code>setup.php</code>.</div>
<p style="margin-top:1.5rem"><a href="<?=BASE_URL?>/index.php">→ Ir para o login</a></p>
</body></html>
