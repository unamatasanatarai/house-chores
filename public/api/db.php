<?php

class Database {
    private static $pdo = null;

    public static function getConnection() {
        if (self::$pdo === null) {
            $host = 'db';
            $db   = 'family_chores';
            $user = 'root';
            $pass = 'root_password';
            $charset = 'utf8mb4';

            $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                 self::$pdo = new PDO($dsn, $user, $pass, $options);
            } catch (\PDOException $e) {
                 Response::error('500_INTERNAL_ERROR', 'Database connection failed', [], 500);
            }
        }
        return self::$pdo;
    }
}
