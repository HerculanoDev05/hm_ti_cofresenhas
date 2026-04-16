<?php
declare(strict_types=1);

class Crypto
{
    private const CIPHER = 'AES-256-CBC';
    private const IV_LEN = 16;

    /**
     * Criptografa usando AES-256-CBC.
     * Retorna ['enc' => base64, 'iv' => base64]
     */
    public static function encrypt(string $plaintext): array
    {
        $key = self::deriveKey();
        $iv  = random_bytes(self::IV_LEN);

        $encrypted = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($encrypted === false) {
            throw new RuntimeException('Falha na criptografia: ' . openssl_error_string());
        }

        return [
            'enc' => base64_encode($encrypted),
            'iv'  => base64_encode($iv),
        ];
    }

    /**
     * Descriptografa AES-256-CBC.
     */
    public static function decrypt(string $encBase64, string $ivBase64): string
    {
        $key       = self::deriveKey();
        $encrypted = base64_decode($encBase64, true);
        $iv        = base64_decode($ivBase64,  true);

        if ($encrypted === false || $iv === false) {
            throw new InvalidArgumentException('Dados de criptografia inválidos.');
        }

        $plain = openssl_decrypt(
            $encrypted,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($plain === false) {
            throw new RuntimeException('Falha na descriptografia. Chave incorreta ou dado corrompido.');
        }

        return $plain;
    }

    /** Deriva chave de 32 bytes a partir de CRYPTO_KEY definida em config.php */
    private static function deriveKey(): string
    {
        if (!defined('CRYPTO_KEY')) {
            throw new RuntimeException('CRYPTO_KEY não definida em config.php');
        }
        return hash('sha256', CRYPTO_KEY, true);
    }

    /** Gera senha aleatória forte */
    public static function generatePassword(int $length = 16): string
    {
        $chars = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$%&*';
        $pass  = '';
        for ($i = 0; $i < $length; $i++) {
            $pass .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $pass;
    }

    /** Avalia força da senha: 0 (fraca) a 5 (forte) */
    public static function passwordStrength(string $password): int
    {
        $score = 0;
        if (strlen($password) >= 8)  $score++;
        if (strlen($password) >= 12) $score++;
        if (preg_match('/[A-Z]/', $password) && preg_match('/[a-z]/', $password)) $score++;
        if (preg_match('/[0-9]/', $password)) $score++;
        if (preg_match('/[^A-Za-z0-9]/', $password)) $score++;
        return min(5, $score);
    }
}
