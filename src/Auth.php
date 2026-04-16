<?php
declare(strict_types=1);

class Auth
{
    // ─── Sessão ───────────────────────────────────────────────────────────

    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'secure'   => false,
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
            session_start();
        }
    }

    public static function check(): bool
    {
        self::startSession();
        if (empty($_SESSION['user_id'])) return false;
        $inativo = time() - ($_SESSION['last_act'] ?? 0);
        if ($inativo > SESSION_TIMEOUT_MIN * 60) { self::logout(); return false; }
        $_SESSION['last_act'] = time();
        return true;
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            header('Location: ' . BASE_URL . '/index.php?expired=1');
            exit;
        }
    }

    public static function requireLevel(int $minLevel): void
    {
        self::requireLogin();
        if ((int)$_SESSION['nivel_id'] < $minLevel) {
            Logger::log(Logger::ACCESS_DENIED, '', false,
                "Nivel {$_SESSION['nivel_id']} tentou acessar recurso nivel $minLevel");
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Acesso negado: nivel insuficiente.']);
            exit;
        }
    }

    public static function nivel(): int      { return (int)($_SESSION['nivel_id'] ?? 0); }
    public static function userId(): int     { return (int)($_SESSION['user_id']  ?? 0); }
    public static function username(): string { return $_SESSION['username'] ?? ''; }

    // ─── Login ────────────────────────────────────────────────────────────

    public static function login(string $username, string $senha): array
    {
        $ip = self::getIp();

        $tentativa = Database::queryOne(
            'SELECT * FROM tentativas_login WHERE username = ? AND ip = ?',
            [$username, $ip]
        );

        if ($tentativa && $tentativa['bloqueado_ate'] &&
            new DateTime() < new DateTime($tentativa['bloqueado_ate'])) {
            $min = (int)ceil((strtotime($tentativa['bloqueado_ate']) - time()) / 60);
            Logger::log(Logger::LOGIN_BLOCKED, '', false,
                "Bloqueado ate {$tentativa['bloqueado_ate']}", null, $username);
            return ['ok' => false,
                'error' => "Conta bloqueada por excesso de tentativas. Tente novamente em {$min} minuto(s)."];
        }

        $user = Database::queryOne(
            'SELECT u.*, n.nome AS nivel_nome
               FROM usuarios u
               JOIN niveis_acesso n ON n.id = u.nivel_id
              WHERE u.username = ? LIMIT 1',
            [$username]
        );

        if (!$user || !password_verify($senha, $user['senha_hash'])) {
            self::registrarFalha($username, $ip);
            Logger::log(Logger::LOGIN_FAIL, '', false, 'Usuario/senha incorretos', null, $username);
            return ['ok' => false, 'error' => 'Usuario ou senha incorretos.'];
        }

        if (!$user['ativo']) {
            Logger::log(Logger::LOGIN_FAIL, '', false, 'Conta inativa', (int)$user['id'], $username);
            return ['ok' => false, 'error' => 'Conta desativada. Contate o administrador.'];
        }

        if ($user['bloqueado']) {
            Logger::log(Logger::LOGIN_BLOCKED, '', false, $user['motivo_bloqueio'],
                (int)$user['id'], $username);
            return ['ok' => false, 'error' => 'Conta bloqueada: ' . $user['motivo_bloqueio']];
        }

        if ($user['senha_expira_em'] && new DateTime() > new DateTime($user['senha_expira_em'])) {
            return ['ok' => false,
                'error' => 'Sua senha expirou. Contate o administrador para redefini-la.'];
        }

        try {
            Policy::check((int)$user['id'], (int)$user['nivel_id']);
        } catch (PolicyException $e) {
            Logger::log(Logger::POLICY_BLOCK, '', false, $e->getMessage(),
                (int)$user['id'], $username);
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        Database::execute(
            'DELETE FROM tentativas_login WHERE username = ? AND ip = ?',
            [$username, $ip]
        );

        session_regenerate_id(true);
        $_SESSION['user_id']    = $user['id'];
        $_SESSION['username']   = $user['username'];
        $_SESSION['nome']       = $user['nome_completo'];
        $_SESSION['nivel_id']   = $user['nivel_id'];
        $_SESSION['nivel_nome'] = $user['nivel_nome'];
        $_SESSION['logado_em']  = time();
        $_SESSION['last_act']   = time();
        $_SESSION['trocar_senha'] = (int)($user['trocar_senha'] ?? 0);

        Database::execute(
            'UPDATE usuarios SET ultimo_acesso = NOW() WHERE id = ?',
            [(int)$user['id']]
        );

        Logger::log(Logger::LOGIN_OK, '', true, '', (int)$user['id'], $username);

        return ['ok' => true, 'user' => [
            'id'         => $user['id'],
            'username'   => $user['username'],
            'nome'       => $user['nome_completo'],
            'nivel_id'   => $user['nivel_id'],
            'nivel_nome'   => $user['nivel_nome'],
            'trocar_senha' => (int)($user['trocar_senha'] ?? 0),
        ]];
    }

    // ─── Logout ───────────────────────────────────────────────────────────

    public static function logout(): void
    {
        self::startSession();
        if (!empty($_SESSION['user_id'])) {
            Logger::log(Logger::LOGOUT);
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    // ─── Brute-force ──────────────────────────────────────────────────────

    private static function registrarFalha(string $username, string $ip): void
    {
        $t = Database::queryOne(
            'SELECT * FROM tentativas_login WHERE username = ? AND ip = ?',
            [$username, $ip]
        );

        if ($t) {
            $novas = (int)$t['tentativas'] + 1;
            if ($novas >= MAX_LOGIN_ATTEMPTS) {
                $ate = date('Y-m-d H:i:s', strtotime('+' . LOCKOUT_MINUTES . ' minutes'));
                Database::execute(
                    'UPDATE tentativas_login SET tentativas=?, bloqueado_ate=?, ultimo_em=NOW() WHERE id=?',
                    [$novas, $ate, $t['id']]
                );
                Database::execute(
                    "UPDATE usuarios SET bloqueado=1, motivo_bloqueio='Excesso de tentativas de login' WHERE username=?",
                    [$username]
                );
            } else {
                Database::execute(
                    'UPDATE tentativas_login SET tentativas=?, ultimo_em=NOW() WHERE id=?',
                    [$novas, $t['id']]
                );
            }
        } else {
            Database::execute(
                'INSERT INTO tentativas_login (username, ip, tentativas) VALUES (?,?,1)',
                [$username, $ip]
            );
        }
    }

    // ─── IP ───────────────────────────────────────────────────────────────

    private static function getIp(): string
    {
        foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'] as $k) {
            if (!empty($_SERVER[$k])) {
                $ip = trim(explode(',', $_SERVER[$k])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
            }
        }
        return '0.0.0.0';
    }
}
