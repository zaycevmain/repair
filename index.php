<?php
require_once __DIR__ . '/config.php';

use Repair\Auth;

$action = $_GET['action'] ?? '';
$logout = isset($_GET['logout']);

if ($logout) {
    Auth::logout();
    header('Location: ' . WEB_ROOT . '/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['pin']) && isset($_POST['login_pin'])) {
        if (Auth::loginByPin($_POST['pin'])) {
            if (Auth::isOperator()) {
                header('Location: ' . WEB_ROOT . '/operator.php');
            } else {
                header('Location: ' . WEB_ROOT . '/admin/');
            }
            exit;
        }
        $error = 'Неверный пин-код';
    } elseif (isset($_POST['login']) && isset($_POST['password']) && isset($_POST['login_admin'])) {
        if (Auth::loginByCredentials($_POST['login'], $_POST['password'])) {
            header('Location: ' . WEB_ROOT . '/admin/');
            exit;
        }
        $error = 'Неверный логин или пароль';
    }
}

if (Auth::isLoggedIn()) {
    if (Auth::isOperator()) {
        header('Location: ' . WEB_ROOT . '/operator.php');
    } else {
        header('Location: ' . WEB_ROOT . '/admin/');
    }
    exit;
}

$showAdminForm = isset($_GET['admin']);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Вход — Реестр поломок</title>
    <link rel="stylesheet" href="<?= e(WEB_ROOT) ?>/assets/css/common.css">
</head>
<body class="login-page">
    <div class="login-box">
        <h1>Реестр поломок</h1>
        <?php if (!empty($error)): ?>
            <p class="error-msg"><?= e($error) ?></p>
        <?php endif; ?>

        <?php if (!$showAdminForm): ?>
            <form method="post" action="" class="login-form">
                <input type="hidden" name="login_pin" value="1">
                <label>Пин-код</label>
                <input type="text" name="pin" inputmode="numeric" pattern="[0-9]*" maxlength="6" placeholder="Введите пин-код" autofocus autocomplete="one-time-code">
                <button type="submit">Войти</button>
            </form>
            <a href="?admin=1" class="admin-link" title="Вход для администратора">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
            </a>
        <?php else: ?>
            <form method="post" action="" class="login-form">
                <input type="hidden" name="login_admin" value="1">
                <label>Логин</label>
                <input type="text" name="login" placeholder="Логин" autofocus>
                <label>Пароль</label>
                <input type="password" name="password" placeholder="Пароль">
                <button type="submit">Войти</button>
            </form>
            <a href="<?= e(WEB_ROOT) ?>/" class="back-link">← Пин-код</a>
        <?php endif; ?>
    </div>
</body>
</html>
