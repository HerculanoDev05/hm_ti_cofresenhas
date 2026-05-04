<?php
declare(strict_types=1);

// ─── Banco de Dados ───────────────────────────────────────────────────────
// Leia as credenciais de variáveis de ambiente, nunca hardcoded.
define('DB_HOST',    getenv('DB_HOST')    ?: 'herculano_mysql_hm');
define('DB_PORT',    getenv('DB_PORT')    ?: '3306');
define('DB_NAME',    getenv('DB_NAME')    ?: 'hm_cofre');
define('DB_USER',    getenv('DB_USER')    ?: 'hm_mysql');
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
define('BASE_URL',            'https://cofresenhas.grupoherculano.tech/');


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
