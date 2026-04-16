<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: text/html; charset=utf-8');
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Diagnóstico — Cofre de Senhas</title>
<style>
  body { font-family: monospace; background: #0f1117; color: #e8eaf0; max-width: 800px; margin: 2rem auto; padding: 1rem; }
  h1   { color: #4f8ef7; }
  h2   { color: #9aa3b8; font-size: .9rem; text-transform: uppercase; letter-spacing: .08em; margin-top: 1.5rem; }
  .ok  { color: #1db87a; }
  .err { color: #e24b4a; }
  .warn{ color: #f59e0b; }
  .box { background: #161b27; border: 1px solid #252d3d; border-radius: 8px; padding: 1rem; margin: .5rem 0; }
  pre  { margin: 0; white-space: pre-wrap; word-break: break-all; }
</style>
</head>
<body>
<h1>🔍 Diagnóstico do Cofre de Senhas</h1>

<?php
function ok($msg)   { echo "<div class='box ok'>✓ $msg</div>\n"; }
function err($msg)  { echo "<div class='box err'>✗ $msg</div>\n"; }
function warn($msg) { echo "<div class='box warn'>⚠ $msg</div>\n"; }

// ── 1. Versão PHP ─────────────────────────────────────────────────────────
echo "<h2>1. PHP</h2>";
$ver = phpversion();
if (version_compare($ver, '8.0.0', '>=')) ok("PHP $ver (OK — requer 8.0+)");
else err("PHP $ver — requer 8.0 ou superior! Atualize o PHP no XAMPP.");

// ── 2. Extensões necessárias ──────────────────────────────────────────────
echo "<h2>2. Extensões PHP</h2>";
foreach (['pdo', 'pdo_mysql', 'openssl', 'mbstring', 'json', 'session'] as $ext) {
    if (extension_loaded($ext)) ok("Extensão <b>$ext</b> carregada");
    else err("Extensão <b>$ext</b> NÃO encontrada — habilite em php.ini");
}

// ── 3. Arquivos do sistema ────────────────────────────────────────────────
echo "<h2>3. Arquivos</h2>";
$base  = __DIR__;
$files = [
    'config.php', 'bootstrap.php',
    'src/Database.php', 'src/Auth.php', 'src/Crypto.php',
    'src/Logger.php', 'src/Policy.php',
    'api/login.php', 'api/logout.php', 'api/passwords.php',
    'api/users.php', 'api/logs.php',
    'assets/style.css', 'index.php', 'dashboard.php',
];
foreach ($files as $f) {
    if (file_exists("$base/$f")) ok("$f");
    else err("$f — ARQUIVO NÃO ENCONTRADO");
}

// ── 4. Carrega config.php ─────────────────────────────────────────────────
echo "<h2>4. Configurações (config.php)</h2>";
try {
    require_once __DIR__ . '/config.php';
    ok("config.php carregado");
    ok("DB_HOST = <b>" . DB_HOST . "</b>");
    ok("DB_PORT = <b>" . DB_PORT . "</b>");
    ok("DB_NAME = <b>" . DB_NAME . "</b>");
    ok("DB_USER = <b>" . DB_USER . "</b>");
    ok("BASE_URL = <b>" . BASE_URL . "</b>");
    if (DB_PASS === '') warn("DB_PASS está vazio (OK para XAMPP padrão)");
    else ok("DB_PASS definida");
    if (CRYPTO_KEY === 'TROQUE_POR_32_BYTES_ALEATORIOS!!')
        err("CRYPTO_KEY ainda é o valor padrão! Troque em config.php antes de usar em produção.");
    else ok("CRYPTO_KEY definida");
} catch (Throwable $e) {
    err("Erro ao carregar config.php: " . $e->getMessage());
}

// ── 5. Conexão MySQL ──────────────────────────────────────────────────────
echo "<h2>5. Conexão MySQL</h2>";
try {
    require_once __DIR__ . '/src/Database.php';
    $pdo = Database::get();
    ok("Conexão PDO estabelecida com <b>" . DB_HOST . ":" . DB_PORT . "/" . DB_NAME . "</b>");
    $ver = Database::queryOne('SELECT VERSION() AS v');
    ok("Versão MySQL: <b>" . $ver['v'] . "</b>");
} catch (Throwable $e) {
    err("Falha na conexão MySQL: <b>" . $e->getMessage() . "</b>");
    echo "<div class='box warn'><pre>";
    echo "Verifique em config.php:\n";
    echo "  DB_HOST = '" . (defined('DB_HOST') ? DB_HOST : '?') . "'\n";
    echo "  DB_PORT = '" . (defined('DB_PORT') ? DB_PORT : '?') . "'\n";
    echo "  DB_NAME = '" . (defined('DB_NAME') ? DB_NAME : '?') . "'\n";
    echo "  DB_USER = '" . (defined('DB_USER') ? DB_USER : '?') . "'\n";
    echo "  DB_PASS = '" . (defined('DB_PASS') ? (DB_PASS ? '(definida)' : '(vazia)') : '?') . "'\n";
    echo "\nNo XAMPP, verifique em: phpMyAdmin → Contas de usuário";
    echo "</pre></div>";
}

// ── 6. Tabelas do banco ───────────────────────────────────────────────────
echo "<h2>6. Tabelas (hm_cofre)</h2>";
try {
    $tabelas = ['niveis_acesso','usuarios','categorias','senhas','politicas','log_acesso','tentativas_login'];
    foreach ($tabelas as $t) {
        $r = Database::queryOne("SELECT COUNT(*) AS n FROM $t");
        ok("Tabela <b>$t</b> — {$r['n']} registro(s)");
    }
} catch (Throwable $e) {
    err("Erro ao verificar tabelas: " . $e->getMessage());
}

// ── 7. Coluna senha_hash ──────────────────────────────────────────────────
echo "<h2>7. Estrutura da tabela usuarios</h2>";
try {
    $cols = Database::query("SHOW COLUMNS FROM usuarios");
    $nomes = array_column($cols, 'Field');
    if (in_array('senha_hash', $nomes))  ok("Coluna <b>senha_hash</b> encontrada ✓");
    elseif (in_array('password_hash', $nomes)) err("Coluna ainda é <b>password_hash</b> — execute o ALTER abaixo");
    else err("Nenhuma coluna de senha encontrada!");
    echo "<div class='box'><pre>" . implode(', ', $nomes) . "</pre></div>";
} catch (Throwable $e) {
    err("Erro: " . $e->getMessage());
}

// ── 8. Usuário admin ──────────────────────────────────────────────────────
echo "<h2>8. Usuário admin</h2>";
try {
    $u = Database::queryOne("SELECT id, username, ativo, bloqueado, nivel_id FROM usuarios WHERE username = 'admin'");
    if ($u) {
        ok("Usuário admin encontrado (id={$u['id']}, nivel={$u['nivel_id']}, ativo={$u['ativo']}, bloqueado={$u['bloqueado']})");
        $h = Database::queryOne("SELECT IF(senha_hash LIKE '\$2%', 'bcrypt_ok', 'hash_invalido') AS tipo FROM usuarios WHERE username='admin'");
        if ($h && $h['tipo'] === 'bcrypt_ok') ok("Hash bcrypt válido ✓");
        else err("Hash da senha inválido ou ainda é placeholder — rode o setup.php");
    } else {
        err("Usuário admin NÃO encontrado na tabela usuarios");
    }
} catch (Throwable $e) {
    err("Erro: " . $e->getMessage());
}

// ── 9. Teste de criptografia ──────────────────────────────────────────────
echo "<h2>9. Criptografia AES-256</h2>";
try {
    require_once __DIR__ . '/src/Crypto.php';
    $teste  = 'SenhaTesteCofre@2024';
    $cripto = Crypto::encrypt($teste);
    $plain  = Crypto::decrypt($cripto['enc'], $cripto['iv']);
    if ($plain === $teste) ok("AES-256-CBC encrypt/decrypt funcionando ✓");
    else                   err("Falha no teste de criptografia — plain='$plain'");
} catch (Throwable $e) {
    err("Erro na criptografia: " . $e->getMessage());
}

// ── 10. Teste do endpoint login ───────────────────────────────────────────
echo "<h2>10. Endpoint api/login.php</h2>";
$url = (defined('BASE_URL') ? BASE_URL : 'http://localhost:8081/hm_cofre') . '/api/login.php';
echo "<div class='box'>URL do endpoint: <b>$url</b><br>";
echo "Abra o DevTools (F12) → Network → tente logar e veja o status da requisição para <b>api/login.php</b><br>";
echo "Se aparecer erro 500, há um PHP fatal error em api/login.php</div>";

echo "<h2>✅ Diagnóstico concluído</h2>";
echo "<div class='box warn'>⚠ Delete ou restrinja o acesso a <b>debug.php</b> após resolver os problemas.</div>";
?>
</body>
</html>
