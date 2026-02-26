<?php
/**
 * Конфигурация приложения. На Ubuntu замените значения на свои.
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start(); // буфер, чтобы при redirect() не было "headers already sent"

define('ROOT', __DIR__);

// Локальная конфигурация (создаётся install.php)
if (is_file(ROOT . '/config.local.php')) {
    require ROOT . '/config.local.php';
}
if (!isset($db)) {
    $db = [
        'host' => 'localhost',
        'port' => 3306,
        'dbname' => 'repair',
        'user' => 'root',
        'pass' => 'root',
        'charset' => 'utf8mb4',
    ];
}
if (!defined('WEB_ROOT')) {
    define('WEB_ROOT', isset($webRoot) ? $webRoot : '/repair');
}

// Базовый URL сайта для ссылок в письмах (логин по пин-коду). В config.local.php: $siteUrl = 'https://repair.mysite.ru';
if (!defined('SITE_URL')) {
    if (isset($siteUrl) && $siteUrl !== '') {
        define('SITE_URL', rtrim($siteUrl, '/'));
    } else {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https' : 'http';
        define('SITE_URL', $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    }
}

// Часовой пояс (NOW(), date()). По умолчанию Москва. В config.local.php: $timezone = 'Europe/Moscow';
$appTimezone = $timezone ?? 'Europe/Moscow';
date_default_timezone_set($appTimezone);

session_start();

// Пути
define('UPLOAD_DIR', ROOT . '/uploads/breakdowns');
define('UPLOAD_URL', WEB_ROOT . '/uploads/breakdowns/');

if (is_file(ROOT . '/vendor/autoload.php')) {
    require_once ROOT . '/vendor/autoload.php';
}

// Роли
define('ROLE_ADMIN', 1);
define('ROLE_OPERATOR', 2);
define('ROLE_ENGINEER', 3);

require_once ROOT . '/includes/Db.php';
require_once ROOT . '/includes/Auth.php';
require_once ROOT . '/includes/Mailer.php';
require_once ROOT . '/includes/Telegram.php';
require_once ROOT . '/includes/helpers.php';
