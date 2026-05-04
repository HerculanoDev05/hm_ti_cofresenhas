<?php
declare(strict_types=1);

// ─── Banco de Dados ───────────────────────────────────────────────────────
// Leia as credenciais de variáveis de ambiente, nunca hardcoded.
define('DB_HOST',    getenv('DB_HOST')    ?: 'mysql_hm');
define('DB_PORT',    getenv('DB_PORT')    ?: '3306');
define('DB_NAME',    getenv('DB_NAME')    ?: 'hm_cofre');
define('DB_USER',    getenv('DB_USER')    ?: 'root');
define('DB_PASS',    getenv('DB_PASS')    ?: 'H3r(Ul@n0');
define('DB_CHARSET', 'utf8mb4');

// ─── Criptografia AES-256-CBC ─────────────────────────────────────────────
// Defina CRYPTO_KEY como variável de ambiente de no mínimo 32 bytes aleatórios.
define('CRYPTO_KEY', getenv('CRYPTO_KEY') ?: 'MqY8P4izt51ljcDNLuF53ryj7pPx2PnPJaFoq3zckxI=');

// Em produção (EasyPanel), defina CRYPTO_KEY como variável de ambiente
// para sobrescrever o valor padrão acima.

// ─── Sessão / Segurança ───────────────────────────────────────────────────
define('SESSION_TIMEOUT_MIN', 480);
define('MAX_LOGIN_ATTEMPTS',  5);
define('LOCKOUT_MINUTES',     30);
define('BCRYPT_COST',         12);
define('APP_NAME',            'Cofre de Senhas');
define('APP_VERSION',         '1.0.0');
// BASE_URL detectado automaticamente a partir da requisição atual.
// Pode ser sobrescrito pela variável de ambiente BASE_URL em produção.
if (getenv('BASE_URL')) {
    define('BASE_URL', rtrim(getenv('BASE_URL'), '/'));
} else {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir    = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
    // Sobe um nível se estiver dentro de /api ou similar
    if (str_ends_with($dir, '/api')) $dir = dirname($dir);
    define('BASE_URL', $scheme . '://' . $host . ($dir === '/' ? '' : $dir));
}

// ─── Timezone ─────────────────────────────────────────────────────────────
date_default_timezone_set('America/Sao_Paulo');

// ─── Modo debug — SEMPRE desativado em produção ───────────────────────────
define('DEBUG_MODE', true);
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}
