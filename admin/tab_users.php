<?php
$pdo = \Repair\Db::get();
// Миграция: добавить is_active, если колонки ещё нет (для старых установок)
try {
    $pdo->exec('ALTER TABLE users ADD COLUMN is_active tinyint(1) NOT NULL DEFAULT 1 COMMENT "0 = заблокирован"');
} catch (\Throwable $e) {
    // колонка уже есть
}

$roles = $pdo->query('SELECT * FROM roles ORDER BY id')->fetchAll();
$users = $pdo->query("SELECT u.*, r.name AS role_name, r.code AS role_code FROM users u JOIN roles r ON r.id = u.role_id ORDER BY u.id")->fetchAll();

$message = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['toggle_active']) && isset($_POST['user_id']) && \Repair\Auth::isAdmin()) {
        $uid = (int) $_POST['user_id'];
        if ($uid !== \Repair\Auth::userId()) {
            $st = $pdo->prepare('UPDATE users SET is_active = IF(COALESCE(is_active,1)=1, 0, 1) WHERE id = ?');
            $st->execute([$uid]);
            $message = 'Статус пользователя изменён.';
            header('Location: ?tab=users&msg=' . urlencode($message));
            exit;
        } else {
            $error = 'Нельзя заблокировать себя.';
        }
    }
    if (isset($_POST['delete_user']) && isset($_POST['user_id']) && \Repair\Auth::isAdmin()) {
        $uid = (int) $_POST['user_id'];
        if ($uid === \Repair\Auth::userId()) {
            $error = 'Нельзя удалить себя.';
        } else {
            $count = $pdo->prepare('SELECT COUNT(*) FROM breakdowns WHERE reported_by_user_id = ?');
            $count->execute([$uid]);
            if ((int) $count->fetchColumn() > 0) {
                $error = 'Нельзя удалить: по этому пользователю есть записи в реестре поломок.';
            } else {
                $uRole = $pdo->prepare('SELECT role_id FROM users WHERE id = ?');
                $uRole->execute([$uid]);
                $uRole = (int) ($uRole->fetchColumn() ?: 0);
                $totalAdmins = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE role_id = 1')->fetchColumn();
                if ($uRole === ROLE_ADMIN && $totalAdmins <= 1) {
                    $error = 'Нельзя удалить последнего администратора.';
                } else {
                    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$uid]);
                    $message = 'Пользователь удалён.';
                    header('Location: ?tab=users&msg=' . urlencode($message));
                    exit;
                }
            }
        }
    }
    if (isset($_POST['send_pin']) && isset($_POST['user_id'])) {
        $uid = (int) $_POST['user_id'];
        $u = $pdo->prepare('SELECT id, name, email, pin, role_id FROM users WHERE id = ?');
        $u->execute([$uid]);
        $u = $u->fetch();
        if ($u && (int)$u['role_id'] === ROLE_OPERATOR && !empty($u['pin']) && !empty($u['email']) && filter_var($u['email'], FILTER_VALIDATE_EMAIL)) {
            $vars = ['{name}' => $u['name'], '{pin}' => $u['pin'], '{email}' => $u['email']];
            $subjectTpl = setting('mail_tpl', 'pin_sent_subject') ?: 'Доступ в Реестр поломок — ваш пин-код';
            $bodyTpl = setting('mail_tpl', 'pin_sent_body') ?: '<p>Здравствуйте, {name}!</p><p>Вам предоставлен доступ к кабинету оператора Реестра поломок.</p><p>Ваш пин-код для входа: <strong>{pin}</strong></p><p>Сохраните это письмо.</p><p>— Реестр поломок</p>';
            $subject = mail_tpl_replace($subjectTpl, $vars, false);
            $body = mail_tpl_replace($bodyTpl, $vars, true);
            $sent = \Repair\Mailer::send($u['email'], $subject, $body);
            $message = $sent ? 'Пин-код отправлен на ' . $u['email'] : 'Не удалось отправить письмо. Проверьте настройки почты.';
        } else {
            $error = 'У пользователя нет email или это не оператор с пин-кодом.';
        }
    }
    if (isset($_POST['import_users']) && !empty($_FILES['import_file']['tmp_name'])) {
        $path = $_FILES['import_file']['tmp_name'];
        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines) {
            $error = 'Не удалось прочитать файл. Загрузите CSV (разделитель — запятая или точка с запятой).';
        } else {
            $roleMap = ['admin' => ROLE_ADMIN, 'администратор' => ROLE_ADMIN, 'operator' => ROLE_OPERATOR, 'оператор' => ROLE_OPERATOR, 'engineer' => ROLE_ENGINEER, 'инженер' => ROLE_ENGINEER];
            $header = null;
            $rows = [];
            foreach ($lines as $i => $line) {
                $row = str_getcsv($line, ';');
                if (count($row) < 2) $row = str_getcsv($line, ',');
                if ($i === 0 && count($row) >= 3 && (stripos($row[0], 'фио') !== false || stripos($row[0], 'name') !== false)) {
                    $header = array_map('trim', $row);
                    continue;
                }
                $name = trim((string)($row[0] ?? ''));
                $email = trim((string)($row[1] ?? ''));
                $roleStr = trim((string)($row[2] ?? ''));
                $login = isset($row[3]) ? trim((string)$row[3]) : '';
                $password = isset($row[4]) ? (string)$row[4] : '';
                if ($name === '') continue;
                $roleId = $roleMap[mb_strtolower($roleStr)] ?? null;
                if ($roleId === null) continue;
                $rows[] = ['name' => $name, 'email' => $email, 'role_id' => $roleId, 'login' => $login, 'password' => $password];
            }
            $imported = 0;
            $createdLog = [];
            $pins = $pdo->query('SELECT pin FROM users WHERE pin IS NOT NULL')->fetchAll(\PDO::FETCH_COLUMN);
            $pins = array_flip($pins);
            $logins = $pdo->query('SELECT login FROM users WHERE login IS NOT NULL')->fetchAll(\PDO::FETCH_COLUMN);
            $logins = array_flip($logins);
            foreach ($rows as $r) {
                if ($r['role_id'] === ROLE_OPERATOR) {
                    $pin = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                    while (isset($pins[$pin])) {
                        $pin = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                    }
                    $pins[$pin] = true;
                    try {
                        $pdo->prepare('INSERT INTO users (role_id, pin, name, email) VALUES (?, ?, ?, ?)')->execute([ROLE_OPERATOR, $pin, $r['name'], $r['email'] ?: null]);
                        $imported++;
                        $createdLog[] = $r['name'] . ' (оператор, пин: ' . $pin . ')';
                    } catch (\PDOException $e) {
                        if ($e->getCode() != 23000) throw $e;
                    }
                } else {
                    $login = $r['login'] !== '' ? $r['login'] : (preg_match('/^([^@]+)@/', $r['email'], $m) ? $m[1] : 'user');
                    if ($r['login'] === '') {
                        $base = $login;
                        $c = 0;
                        while (isset($logins[$login])) { $c++; $login = $base . $c; }
                    } elseif (isset($logins[$login])) {
                        continue;
                    }
                    $pass = $r['password'] !== '' ? $r['password'] : bin2hex(random_bytes(4));
                    try {
                        $pdo->prepare('INSERT INTO users (role_id, login, password_hash, name, email) VALUES (?, ?, ?, ?, ?)')->execute([$r['role_id'], $login, password_hash($pass, PASSWORD_DEFAULT), $r['name'], $r['email'] ?: null]);
                        $imported++;
                        $logins[$login] = true;
                        $createdLog[] = $r['name'] . ' — логин: ' . $login . ($r['password'] === '' ? ', пароль: ' . $pass : '');
                    } catch (\PDOException $e) {
                        if ($e->getCode() != 23000) throw $e;
                    }
                }
            }
            $message = 'Импортировано пользователей: ' . $imported . '.';
            if (!empty($createdLog)) $message .= ' ' . implode('; ', array_slice($createdLog, 0, 15)) . (count($createdLog) > 15 ? '…' : '');
            header('Location: ?tab=users&msg=' . urlencode($message));
            exit;
        }
    }
    if (isset($_POST['create_user'])) {
        $roleId = (int) $_POST['role_id'];
        $name = trim((string) $_POST['name']);
        $email = trim((string) ($_POST['email'] ?? ''));
        if ($name === '') {
            $error = 'Укажите ФИО.';
        } elseif ($roleId === ROLE_OPERATOR) {
            $pin = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $exists = $pdo->prepare('SELECT id FROM users WHERE pin = ?');
            $exists->execute([$pin]);
            while ($exists->fetch()) {
                $pin = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $exists->execute([$pin]);
            }
            $st = $pdo->prepare('INSERT INTO users (role_id, pin, name, email) VALUES (?, ?, ?, ?)');
            $st->execute([$roleId, $pin, $name, $email]);
            $message = "Оператор создан. Пин-код: $pin (сообщите его сотруднику).";
            header('Location: ?tab=users&msg=' . urlencode($message));
            exit;
        } else {
            $login = trim((string) $_POST['login']);
            $password = (string) $_POST['password'];
            if ($login === '' || $password === '') {
                $error = 'Логин и пароль обязательны.';
            } else {
                $st = $pdo->prepare('INSERT INTO users (role_id, login, password_hash, name, email) VALUES (?, ?, ?, ?, ?)');
                try {
                    $st->execute([$roleId, $login, password_hash($password, PASSWORD_DEFAULT), $name, $email]);
                    $message = 'Пользователь создан.';
                    header('Location: ?tab=users&msg=1');
                    exit;
                } catch (\PDOException $e) {
                    if ($e->getCode() == 23000) $error = 'Логин уже занят.';
                    else $error = $e->getMessage();
                }
            }
        }
    }
}
if (isset($_GET['msg'])) $message = $_GET['msg'];
?>
<div class="card">
    <h3 style="margin-top:0;">Создать пользователя</h3>
    <?php if ($message): ?><p style="color: var(--success);"><?= e($message) ?></p><?php endif; ?>
    <?php if ($error): ?><p class="error-msg"><?= e($error) ?></p><?php endif; ?>
    <form method="post">
        <input type="hidden" name="create_user" value="1">
        <div class="form-row">
            <div class="form-group">
                <label>Роль</label>
                <select name="role_id" id="user_role">
                    <?php foreach ($roles as $r): ?>
                        <option value="<?= (int)$r['id'] ?>"><?= e($r['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>ФИО</label>
                <input type="text" name="name" required>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" placeholder="необязательно">
            </div>
        </div>
        <div class="form-row" id="operator_only">
            <p class="text-muted">Для оператора будет сгенерирован уникальный 6-значный пин-код.</p>
        </div>
        <div class="form-row hidden" id="login_pass_row">
            <div class="form-group">
                <label>Логин</label>
                <input type="text" name="login" id="user_login">
            </div>
            <div class="form-group">
                <label>Пароль</label>
                <input type="password" name="password" id="user_password">
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Создать</button>
    </form>
</div>
<div class="card">
    <h3 style="margin-top:0;">Импорт пользователей</h3>
    <p class="text-muted">CSV: столбцы <strong>ФИО</strong>, <strong>Почта</strong>, <strong>Роль</strong> (оператор / инженер / администратор). Опционально: <strong>Логин</strong>, <strong>Пароль</strong> — для админа/инженера. Первая строка может быть заголовком.</p>
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="import_users" value="1">
        <div class="form-group">
            <label>Файл CSV</label>
            <input type="file" name="import_file" accept=".csv" required>
        </div>
        <button type="submit" class="btn btn-primary">Импорт</button>
    </form>
</div>
<div class="card">
    <h3 style="margin-top:0;">Пользователи</h3>
    <table class="data-table">
        <thead>
            <tr><th>ФИО</th><th>Роль</th><th>Логин / Пин</th><th>Email</th><th>Статус</th><th>Действия</th></tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u):
                $isActive = (int)($u['is_active'] ?? 1) === 1;
                $isSelf = (int)$u['id'] === \Repair\Auth::userId();
                $canManage = \Repair\Auth::isAdmin();
            ?>
            <tr>
                <td><?= e($u['name']) ?></td>
                <td><?= e($u['role_name']) ?></td>
                <td><?= $u['login'] ? e($u['login']) : ('Пин: ' . e($u['pin'] ?? '—')) ?></td>
                <td><?= e($u['email'] ?? '') ?></td>
                <td>
                    <?php if ($isActive): ?><span class="badge badge-done">Активен</span><?php else: ?><span class="badge badge-new">Заблокирован</span><?php endif; ?>
                </td>
                <td>
                    <?php if (($u['role_code'] ?? '') === 'operator' && !empty($u['pin']) && !empty($u['email'])): ?>
                    <form method="post" style="display:inline;" onsubmit="return confirm('Отправить пин-код на <?= e($u['email']) ?>?');">
                        <input type="hidden" name="send_pin" value="1">
                        <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                        <button type="submit" class="btn btn-secondary btn-sm">Отправить пин</button>
                    </form>
                    <?php endif; ?>
                    <?php if ($canManage && !$isSelf): ?>
                    <form method="post" style="display:inline; margin-left:4px;">
                        <input type="hidden" name="toggle_active" value="1">
                        <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                        <button type="submit" class="btn btn-secondary btn-sm"><?= $isActive ? 'Заблокировать' : 'Разблокировать' ?></button>
                    </form>
                    <form method="post" style="display:inline; margin-left:4px;" onsubmit="return confirm('Удалить пользователя <?= e($u['name']) ?> безвозвратно?');">
                        <input type="hidden" name="delete_user" value="1">
                        <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm">Удалить</button>
                    </form>
                    <?php endif; ?>
                    <?php if (!$canManage && !(($u['role_code'] ?? '') === 'operator' && !empty($u['pin']) && !empty($u['email']))): ?>—<?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<script>
(function(){
    var role = document.getElementById('user_role');
    var loginRow = document.getElementById('login_pass_row');
    var opOnly = document.getElementById('operator_only');
    function toggle() {
        var isOperator = role.value === '2';
        loginRow.classList.toggle('hidden', isOperator);
        opOnly.classList.toggle('hidden', !isOperator);
    }
    role.addEventListener('change', toggle);
    toggle();
})();
</script>
