<?php
declare(strict_types=1);

class Database
{
    private static ?PDO $instance = null;

    public static function get(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                DB_HOST, DB_PORT, DB_NAME
            );
            self::$instance = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_FOUND_ROWS   => true,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ]);
        }
        return self::$instance;
    }

    /** SELECT — retorna todos os resultados */
    public static function query(string $sql, array $params = []): array
    {
        $st = self::get()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    /** SELECT — retorna apenas a primeira linha */
    public static function queryOne(string $sql, array $params = []): ?array
    {
        $st = self::get()->prepare($sql);
        $st->execute($params);
        $row = $st->fetch();
        return $row ?: null;
    }

    /** INSERT / UPDATE / DELETE — retorna linhas afetadas */
    public static function execute(string $sql, array $params = []): int
    {
        $st = self::get()->prepare($sql);
        $st->execute($params);
        return $st->rowCount();
    }

    /** Retorna o último ID inserido */
    public static function lastId(): string
    {
        return self::get()->lastInsertId();
    }

    // ── Aliases para compatibilidade com código legado ──────────────────
    public static function getInstance(): PDO      { return self::get(); }
    public static function select(string $sql, array $p = []): array   { return self::query($sql, $p); }
    public static function selectOne(string $sql, array $p = []): ?array { return self::queryOne($sql, $p); }
    public static function insert(string $sql, array $p = []): int
    {
        self::execute($sql, $p);
        return (int)self::lastId();
    }
}
