<?php
namespace Repair;

class Auth {
    public static function loginByPin(string $pin): bool {
        $pdo = Db::get();
        $stmt = $pdo->prepare('SELECT id, role_id, name FROM users WHERE pin = ? AND role_id = ? AND COALESCE(is_active, 1) = 1');
        $stmt->execute([trim($pin), ROLE_OPERATOR]);
        $user = $stmt->fetch();
        if ($user) {
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['user_role'] = (int) $user['role_id'];
            $_SESSION['user_name'] = $user['name'];
            return true;
        }
        return false;
    }

    public static function loginByCredentials(string $login, string $password): bool {
        $pdo = Db::get();
        $stmt = $pdo->prepare('SELECT id, role_id, name, password_hash FROM users WHERE login = ? AND role_id IN (?, ?) AND COALESCE(is_active, 1) = 1');
        $stmt->execute([$login, ROLE_ADMIN, ROLE_ENGINEER]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['user_role'] = (int) $user['role_id'];
            $_SESSION['user_name'] = $user['name'];
            return true;
        }
        return false;
    }

    public static function logout(): void {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function isLoggedIn(): bool {
        return !empty($_SESSION['user_id']);
    }

    public static function userId(): int {
        return (int) ($_SESSION['user_id'] ?? 0);
    }

    public static function userRole(): int {
        return (int) ($_SESSION['user_role'] ?? 0);
    }

    public static function userName(): string {
        return (string) ($_SESSION['user_name'] ?? '');
    }

    public static function isAdmin(): bool {
        return self::userRole() === ROLE_ADMIN;
    }

    public static function isOperator(): bool {
        return self::userRole() === ROLE_OPERATOR;
    }

    public static function requireLogin(): void {
        if (!self::isLoggedIn()) {
            header('Location: ' . WEB_ROOT . '/');
            exit;
        }
    }

    public static function requireAdmin(): void {
        self::requireLogin();
        if (!self::isAdmin() && self::userRole() !== ROLE_ENGINEER) {
            header('Location: ' . WEB_ROOT . '/');
            exit;
        }
    }

    public static function isEngineer(): bool {
        return self::userRole() === ROLE_ENGINEER;
    }
}
