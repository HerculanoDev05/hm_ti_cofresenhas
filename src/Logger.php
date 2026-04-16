<?php
declare(strict_types=1);

class Logger
{
    const LOGIN_OK      = 'LOGIN_OK';
    const LOGIN_FAIL    = 'LOGIN_FAIL';
    const LOGIN_BLOCKED = 'LOGIN_BLOQUEADO';
    const LOGOUT        = 'LOGOUT';
    const SENHA_VER     = 'SENHA_VISUALIZADA';
    const SENHA_COPIAR  = 'SENHA_COPIADA';
    const SENHA_ADD     = 'SENHA_ADICIONADA';
    const SENHA_EDIT    = 'SENHA_EDITADA';
    const SENHA_DEL     = 'SENHA_REMOVIDA';
    const USER_ADD      = 'USUARIO_CRIADO';
    const USER_EDIT     = 'USUARIO_EDITADO';
    const USER_DEL      = 'USUARIO_REMOVIDO';
    const USER_BLOCK    = 'USUARIO_BLOQUEADO';
    const POLICY_BLOCK  = 'POLITICA_BLOQUEOU';
    const ACCESS_DENIED = 'ACESSO_NEGADO';

    public static function log(
        string  $acao,
        string  $recurso  = '',
        bool    $sucesso  = true,
        string  $detalhe  = '',
        ?int    $userId   = null,
        string  $username = ''
    ): void {
        if ($userId === null && isset($_SESSION['user_id'])) {
            $userId   = (int)$_SESSION['user_id'];
            $username = $_SESSION['username'] ?? '';
        }
        try {
            Database::execute(
                'INSERT INTO log_acesso (usuario_id,username,acao,recurso,ip,user_agent,sucesso,detalhe)
                 VALUES (?,?,?,?,?,?,?,?)',
                [
                    $userId,
                    $username ?: 'anonimo',
                    $acao,
                    $recurso,
                    self::getIp(),
                    substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
                    $sucesso ? 1 : 0,
                    $detalhe,
                ]
            );
        } catch (Throwable $e) {
            error_log('[Logger] Falha ao gravar log: ' . $e->getMessage());
        }
    }

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
