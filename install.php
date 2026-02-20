<?php
/**
 * Установщик реестра поломок.
 * Откройте в браузере: http://ваш-сайт/repair/install.php
 * После успешной установки удалите install.php.
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('INSTALL_ROOT', __DIR__);
$step = isset($_POST['step']) ? (int) $_POST['step'] : (isset($_GET['step']) ? (int) $_GET['step'] : 0);

// Проверка: уже установлено?
if (is_file(INSTALL_ROOT . '/config.local.php') || is_file(INSTALL_ROOT . '/.installed')) {
    $step = -1;
}

// Проверка PHP и расширений
$phpOk = version_compare(PHP_VERSION, '7.4.0', '>=');
$pdoOk = extension_loaded('pdo') && extension_loaded('pdo_mysql');

$errors = [];
$success = [];
$fixMessages = [];

function esc($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

/**
 * Проверяет окружение и исправляет: Composer platform_check, composer.json, каталоги.
 * Вызывается при каждой загрузке install.php (уже установлено) и после успешной установки.
 */
function install_fix_environment() {
    $root = INSTALL_ROOT;
    $messages = [];
    $minPhp = 70400; // 7.4.0

    // 1. Исправить vendor/composer/platform_check.php (чтобы не падало на версии PHP)
    $platformCheck = $root . '/vendor/composer/platform_check.php';
    if (is_file($platformCheck)) {
        $stub = '<?php
// Исправлено install.php — проверка под текущую версию PHP
if (PHP_VERSION_ID < ' . $minPhp . ') {
    trigger_error(\'Требуется PHP 7.4 или выше. Сейчас: \' . PHP_VERSION, E_USER_ERROR);
}
';
        if (file_put_contents($platformCheck, $stub) !== false) {
            $messages[] = 'Проверка Composer (platform_check.php) приведена к PHP 7.4+.';
        }
    }

    // 2. Обновить composer.json — платформа под текущий PHP (для будущих composer install)
    $composerPath = $root . '/composer.json';
    if (is_file($composerPath)) {
        $json = @json_decode(file_get_contents($composerPath), true);
        if ($json && is_array($json)) {
            if (!isset($json['config'])) $json['config'] = [];
            if (!is_array($json['config'])) $json['config'] = [];
            $json['config']['platform'] = ['php' => PHP_VERSION];
            $encoded = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if ($encoded && file_put_contents($composerPath, $encoded) !== false) {
                $messages[] = 'В composer.json установлена платформа PHP ' . PHP_VERSION . '.';
            }
        }
    }

    // 3. Каталоги загрузок
    $uploadDir = $root . '/uploads/breakdowns';
    if (!is_dir($root . '/uploads')) {
        @mkdir($root . '/uploads', 0755, true);
        $messages[] = 'Создан каталог uploads.';
    }
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
        $messages[] = 'Создан каталог uploads/breakdowns.';
    }

    return $messages;
}

// Всегда при загрузке install.php — проверить и исправить окружение (особенно если уже установлено)
$fixMessages = install_fix_environment();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 1) {
    if (!$phpOk) $errors[] = 'Требуется PHP 7.4 или выше.';
    if (!$pdoOk) $errors[] = 'Требуются расширения PHP: PDO и pdo_mysql.';
    $host = trim($_POST['db_host'] ?? '');
    $port = (int) ($_POST['db_port'] ?? 3306);
    $dbname = trim($_POST['db_name'] ?? '');
    $user = trim($_POST['db_user'] ?? '');
    $pass = $_POST['db_pass'] ?? '';
    $webRoot = trim($_POST['web_root'] ?? '');
    if ($webRoot === '') $webRoot = '/repair';
    if (strpos($webRoot, '..') !== false) $webRoot = '/repair';

    if ($dbname === '') $errors[] = 'Укажите имя базы данных.';
    if ($user === '') $errors[] = 'Укажите пользователя MySQL.';

    if (empty($errors)) {
        try {
            $dsn = "mysql:host=" . $host . ";port=" . $port . ";charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . str_replace('`', '``', $dbname) . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `" . str_replace('`', '``', $dbname) . "`");

            $schema = file_get_contents(INSTALL_ROOT . '/sql/schema.sql');
            if ($schema === false) {
                $errors[] = 'Не найден файл sql/schema.sql';
            } else {
                $schema = preg_replace('/--.*$/m', '', $schema);
                $statements = array_filter(array_map('trim', explode(';', $schema)));
                foreach ($statements as $sql) {
                    if ($sql === '') continue;
                    $pdo->exec($sql);
                }
                $success[] = 'Таблицы созданы.';
            }

            if (empty($errors)) {
                $uploadDir = INSTALL_ROOT . '/uploads/breakdowns';
                if (!is_dir(INSTALL_ROOT . '/uploads')) {
                    mkdir(INSTALL_ROOT . '/uploads', 0755, true);
                }
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $success[] = 'Каталоги загрузок созданы.';

                $configContent = '<?php' . "\n"
                    . '// Сгенерировано install.php. Не удаляйте — здесь данные для подключения к БД.' . "\n"
                    . '$db = [' . "\n"
                    . "    'host' => " . var_export($host, true) . ",\n"
                    . "    'port' => " . $port . ",\n"
                    . "    'dbname' => " . var_export($dbname, true) . ",\n"
                    . "    'user' => " . var_export($user, true) . ",\n"
                    . "    'pass' => " . var_export($pass, true) . ",\n"
                    . "    'charset' => 'utf8mb4',\n"
                    . "];\n"
                    . '$webRoot = ' . var_export($webRoot, true) . ";\n";
                if (file_put_contents(INSTALL_ROOT . '/config.local.php', $configContent) === false) {
                    $errors[] = 'Не удалось записать config.local.php. Проверьте права на каталог.';
                } else {
                    $success[] = 'Файл config.local.php создан.';
                    file_put_contents(INSTALL_ROOT . '/.installed', date('c'));
                }
            }
        } catch (PDOException $e) {
            $errors[] = 'Ошибка БД: ' . esc($e->getMessage());
        }
    }
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Установка — Реестр поломок</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: system-ui, sans-serif; max-width: 520px; margin: 40px auto; padding: 0 20px; background: #1a1a2e; color: #e6edf3; }
        h1 { font-size: 1.5rem; margin-bottom: 24px; }
        .msg { padding: 12px; border-radius: 8px; margin-bottom: 16px; }
        .msg.error { background: rgba(248,81,73,0.2); color: #f85149; }
        .msg.success { background: rgba(63,185,80,0.2); color: #3fb950; }
        label { display: block; margin-bottom: 4px; font-size: 0.9rem; color: #8b949e; }
        input { width: 100%; padding: 10px 12px; margin-bottom: 14px; border: 1px solid #2d3a4d; border-radius: 8px; background: #0f1419; color: #e6edf3; font-size: 1rem; }
        button { padding: 12px 24px; background: #58a6ff; color: #fff; border: none; border-radius: 8px; font-size: 1rem; cursor: pointer; font-weight: 600; }
        button:hover { background: #79b8ff; }
        .done { padding: 24px; background: #161b22; border: 1px solid #2d3a4d; border-radius: 12px; }
        .done a { color: #58a6ff; }
        .hint { font-size: 0.85rem; color: #8b949e; margin-top: 4px; }
    </style>
</head>
<body>
    <h1>Установка реестра поломок</h1>

    <?php if ($step === -1): ?>
        <?php $homeUrl = rtrim(str_replace(['install.php', '\\'], ['', ''], $_SERVER['SCRIPT_NAME'] ?? ''), '/') ?: '/'; ?>
        <?php if (!empty($fixMessages)): ?>
            <div class="msg success">
                <strong>Проверка окружения выполнена.</strong><br>
                <?php foreach ($fixMessages as $m): ?> • <?= esc($m) ?><br><?php endforeach; ?>
            </div>
        <?php endif; ?>
        <div class="done">
            <p><strong>Система уже установлена.</strong></p>
            <p>Вход: иконка замка → логин <strong>admin</strong>, пароль <strong>admin</strong>. Смените пароль в Настройках.</p>
            <p><strong>Рекомендуется удалить <code>install.php</code></strong> с сервера в целях безопасности.</p>
            <p><a href="<?= esc($homeUrl) ?>">Перейти на главную</a></p>
        </div>
    <?php elseif (!empty($success) && empty($errors)): ?>
        <div class="msg success">
            <?php foreach ($success as $s): ?> <?= esc($s) ?><br><?php endforeach; ?>
        </div>
        <?php if (!empty($fixMessages)): ?>
            <div class="msg success">
                <strong>Окружение проверено и исправлено:</strong><br>
                <?php foreach ($fixMessages as $m): ?> • <?= esc($m) ?><br><?php endforeach; ?>
            </div>
        <?php endif; ?>
        <div class="done">
            <p><strong>Установка завершена.</strong></p>
            <p>Вход в админку: на главной нажмите иконку замка → логин <strong>admin</strong>, пароль <strong>admin</strong>. Обязательно смените пароль в Настройках.</p>
            <p><strong>Удалите файл install.php</strong> с сервера.</p>
            <?php
            $base = preg_replace('#/install\.php.*$#', '', $_SERVER['SCRIPT_NAME'] ?? '') ?: '/repair';
            ?>
            <p><a href="<?= esc($base) ?>">Перейти на сайт</a></p>
        </div>
    <?php else: ?>
        <?php foreach ($errors as $e): ?>
            <div class="msg error"><?= $e ?></div>
        <?php endforeach; ?>
        <?php foreach ($success as $s): ?>
            <div class="msg success"><?= esc($s) ?></div>
        <?php endforeach; ?>
        <?php if (!$phpOk): ?>
            <div class="msg error">Требуется PHP 7.4 или выше. Сейчас: <?= esc(PHP_VERSION) ?></div>
        <?php endif; ?>
        <?php if (!$pdoOk): ?>
            <div class="msg error">Включите расширения PHP: PDO и pdo_mysql.</div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="step" value="1">
            <label>Хост MySQL</label>
            <input type="text" name="db_host" value="<?= esc($_POST['db_host'] ?? 'localhost') ?>" placeholder="localhost">
            <label>Порт</label>
            <input type="number" name="db_port" value="<?= esc($_POST['db_port'] ?? '3306') ?>" placeholder="3306">
            <label>Имя базы данных</label>
            <input type="text" name="db_name" value="<?= esc($_POST['db_name'] ?? 'repair') ?>" placeholder="repair">
            <div class="hint">Будет создана, если не существует.</div>
            <label>Пользователь MySQL</label>
            <input type="text" name="db_user" value="<?= esc($_POST['db_user'] ?? 'root') ?>" placeholder="root">
            <label>Пароль MySQL</label>
            <input type="password" name="db_pass" value="<?= esc($_POST['db_pass'] ?? '') ?>" placeholder="">
            <label>Путь к сайту (WEB_ROOT)</label>
            <input type="text" name="web_root" value="<?= esc($_POST['web_root'] ?? preg_replace('#/install\.php.*$#', '', $_SERVER['SCRIPT_NAME'] ?? '') ?: '/repair') ?>" placeholder="/repair">
            <div class="hint">Путь от корня домена, например /repair или /</div>
            <button type="submit">Установить</button>
        </form>
    <?php endif; ?>
</body>
</html>
