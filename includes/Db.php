<?php
namespace Repair;

class Db {
    private static $pdo = null;

    public static function get(): \PDO {
        global $db;
        if (self::$pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $db['host'],
                $db['port'],
                $db['dbname'],
                $db['charset']
            );
            self::$pdo = new \PDO($dsn, $db['user'], $db['pass'], [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]);
            $tz = $GLOBALS['timezone'] ?? date_default_timezone_get();
            self::$pdo->exec("SET time_zone = " . self::$pdo->quote($tz));
        }
        return self::$pdo;
    }
}
