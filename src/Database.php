<?php

class Database
{
    private static ?PDO $pdo = null;

    public static function getInstance(array $config): PDO
    {
        if (self::$pdo === null) {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $config['db']['host'],
                $config['db']['port'],
                $config['db']['name'],
                $config['db']['charset'] ?? 'utf8mb4'
            );

            self::$pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        }

        return self::$pdo;
    }
}
