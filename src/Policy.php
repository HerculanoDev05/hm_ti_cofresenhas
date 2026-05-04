<?php
declare(strict_types=1);

class Policy
{
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

        // Comparação numérica (minutos desde meia-noite) para evitar erros lexicográficos.
        $toMin = static fn(string $h): int => (int)substr($h, 0, 2) * 60 + (int)substr($h, 3, 2);
        $agoraMin = $toMin($now->format('H:i'));
        $iniMin   = $toMin($horaIni);
        $fimMin   = $toMin($horaFim);

        if (!in_array($dia, $diasPerm, true)) {
            Logger::log(Logger::POLICY_BLOCK, $nome, false, "Dia $dia não permitido");
            throw new PolicyException("Acesso negado: dia da semana não permitido pela política \"$nome\".");
        }
        if ($agoraMin < $iniMin || $agoraMin > $fimMin) {
            Logger::log(Logger::POLICY_BLOCK, $nome, false, "Hora {$now->format('H:i')} fora do intervalo $horaIni-$horaFim");
            throw new PolicyException("Acesso negado: fora do horário permitido ($horaIni–$horaFim) pela política \"$nome\".");
        }
    }

    // Valida correspondência de IP com suporte a CIDR IPv4.
    private static function ipMatch(string $ip, string $range): bool
    {
        if (!str_contains($range, '/')) {
            return $ip === $range;
        }

        [$subnet, $bitsStr] = explode('/', $range, 2);
        $bits = (int)$bitsStr;

        // Rejeitar prefixos inválidos ou IPs malformados.
        if ($bits < 0 || $bits > 32) return false;

        $ipLong     = ip2long($ip);
        $subnetLong = ip2long($subnet);
        if ($ipLong === false || $subnetLong === false) return false;

        $mask = $bits === 0 ? 0 : (~0 << (32 - $bits));
        return ($ipLong & $mask) === ($subnetLong & $mask);
    }

    // Usa apenas REMOTE_ADDR para evitar spoofing via headers HTTP.
    private static function getIp(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) return $ip;
        return '0.0.0.0';
    }
}

class PolicyException extends RuntimeException {}
