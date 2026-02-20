<?php
function e(?string $s): string {
    return $s === null ? '' : htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url, int $code = 302): void {
    header('Location: ' . $url, true, $code);
    exit;
}

function json_response(array $data, int $code = 200): void {
    while (ob_get_level()) ob_end_clean();
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function setting(string $group, string $key, ?string $default = null): ?string {
    static $cache = [];
    $k = $group . '.' . $key;
    if (!array_key_exists($k, $cache)) {
        $pdo = \Repair\Db::get();
        $stmt = $pdo->prepare('SELECT value FROM settings WHERE group_name = ? AND key_name = ?');
        $stmt->execute([$group, $key]);
        $row = $stmt->fetch();
        $cache[$k] = $row ? $row['value'] : $default;
    }
    return $cache[$k];
}

function setting_set(string $group, string $key, string $value): void {
    $pdo = \Repair\Db::get();
    $stmt = $pdo->prepare('INSERT INTO settings (group_name, key_name, value) VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE value = VALUES(value)');
    $stmt->execute([$group, $key, $value]);
}

/** Подстановка плейсхолдеров в шаблон письма. $vars — [ '{key}' => value ]. Если $escapeBody, значения экранируются для HTML. */
function mail_tpl_replace(string $tpl, array $vars, bool $escapeBody = false): string {
    if ($escapeBody) {
        $vars = array_map(function ($v) { return nl2br(htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8')); }, $vars);
    }
    return str_replace(array_keys($vars), array_values($vars), $tpl);
}
