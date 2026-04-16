<?php
declare(strict_types=1);

class Policy
{
    /**
     * Verifica todas as políticas ativas para o usuário/nível.
     * Lança PolicyException se alguma bloquear o acesso.
     */
    public static function check(int $userId, int $nivel): void
    {
        $politicas = Database::query(
            "SELECT * FROM politicas
              WHERE ativo = 1
                AND (aplica_nivel IS NULL OR aplica_nivel = ?)
                AND (aplica_user  IS NULL OR aplica_user  = ?)
              ORDER BY id",
            [$nivel, $userId]
        );

        foreach ($politicas as $p) {
            $valor = json_decode($p['valor'], true) ?? [];
            switch ($p['tipo']) {
                case 'ip_whitelist': self::checkIpWhitelist($p['nome'], $valor); break;
                case 'ip_blacklist': self::checkIpBlacklist($p['nome'], $valor); break;
                case 'horario':      self::checkHorario($p['nome'], $valor);      break;
            }
        }
    }

    private static function checkIpWhitelist(string $nome, array $conf): void
    {
        $ips = $conf['ips'] ?? [];
        $ip  = self::getIp();
        foreach ($ips as $allowed) {
            if (self::ipMatch($ip, $allowed)) return;
        }
        Logger::log(Logger::POLICY_BLOCK, $nome, false, "IP $ip não está na whitelist");
        throw new PolicyException("Acesso negado por política \"$nome\": IP $ip não autorizado.");
    }

    private static function checkIpBlacklist(string $nome, array $conf): void
    {
        $ips = $conf['ips'] ?? [];
        $ip  = self::getIp();
        foreach ($ips as $blocked) {
            if (self::ipMatch($ip, $blocked)) {
                Logger::log(Logger::POLICY_BLOCK, $nome, false, "IP $ip bloqueado");
                throw new PolicyException("Acesso negado por política \"$nome\".");
            }
        }
    }

    private static function checkHorario(string $nome, array $conf): void
    {
        $now      = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));
        $diasPerm = $conf['dias']   ?? [1,2,3,4,5];
        $horaIni  = $conf['inicio'] ?? '00:00';
        $horaFim  = $conf['fim']    ?? '23:59';
        $dia      = (int)$now->format('w');
        $hora     = $now->format('H:i');

        if (!in_array($dia, $diasPerm, true)) {
            Logger::log(Logger::POLICY_BLOCK, $nome, false, "Dia $dia não permitido");
            throw new PolicyException("Acesso negado: dia da semana não permitido pela política \"$nome\".");
        }
        if ($hora < $horaIni || $hora > $horaFim) {
            Logger::log(Logger::POLICY_BLOCK, $nome, false, "Hora $hora fora do intervalo $horaIni-$horaFim");
            throw new PolicyException("Acesso negado: fora do horário permitido ($horaIni–$horaFim) pela política \"$nome\".");
        }
    }

    private static function ipMatch(string $ip, string $range): bool
    {
        if (!str_contains($range, '/')) return $ip === $range;
        [$subnet, $bits] = explode('/', $range);
        $mask = -1 << (32 - (int)$bits);
        return (ip2long($ip) & $mask) === (ip2long($subnet) & $mask);
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

class PolicyException extends RuntimeException {}
