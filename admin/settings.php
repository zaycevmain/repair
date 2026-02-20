<?php
require_once dirname(__DIR__) . '/config.php';
use Repair\Auth;
Auth::requireLogin();
if (!Auth::isAdmin()) {
    header('Location: ' . WEB_ROOT . '/admin/');
    exit;
}

$changed = false;
if (isset($_GET['change_password']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['current'], $_POST['new_password'])) {
    $pdo = \Repair\Db::get();
    $u = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
    $u->execute([Auth::userId()]);
    $u = $u->fetch();
    if ($u && password_verify($_POST['current'], $u['password_hash'])) {
        $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($_POST['new_password'], PASSWORD_DEFAULT), Auth::userId()]);
        $changed = true;
    } else {
        $passError = 'Неверный текущий пароль.';
    }
}

header('Location: ' . WEB_ROOT . '/admin/?tab=settings' . ($changed ? '&password_changed=1' : ''));
exit;
