<?php
$saved = false;
$passChanged = false;
$passError = '';
$mailTestResult = null;
$mailSendResult = null;
$telegramTestResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_telegram'])) {
    if (trim(setting('telegram', 'telegram_bot_token') ?? '') && trim(setting('telegram', 'telegram_chat_id') ?? '')) {
        $telegramTestResult = \Repair\Telegram::send('Тест — Реестр поломок. Если это сообщение пришло, уведомления в Telegram настроены верно.')
            ? ['ok' => true, 'message' => 'Тестовое сообщение отправлено в Telegram.']
            : ['ok' => false, 'error' => 'Не удалось отправить. Проверьте токен и chat_id.'];
    } else {
        $telegramTestResult = ['ok' => false, 'error' => 'Укажите токен бота и chat_id и сохраните настройки.'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_mail'])) {
    $mailTestResult = \Repair\Mailer::testConnection();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_test_mail'])) {
    $to = trim((string) ($_POST['test_email'] ?? ''));
    if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $mailSendResult = ['ok' => false, 'error' => 'Укажите корректный email для тестовой отправки.'];
    } else {
        $sent = \Repair\Mailer::send(
            $to,
            'Тестовое письмо — Реестр поломок',
            '<p>Это тестовое письмо.</p><p>Если вы его получили, настройки почты работают корректно.</p><p>— Реестр поломок</p>'
        );
        $mailSendResult = $sent
            ? ['ok' => true, 'message' => 'Тестовое письмо отправлено на ' . $to . '. Проверьте папку «Входящие» и «Спам».']
            : ['ok' => false, 'error' => 'Отправка не удалась. Проверьте настройки и нажмите «Проверить настройки».'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    if ($new && strlen($new) >= 4) {
        $pdo = \Repair\Db::get();
        $u = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
        $u->execute([\Repair\Auth::userId()]);
        $u = $u->fetch();
        if ($u && password_verify($current, $u['password_hash'])) {
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), \Repair\Auth::userId()]);
            $passChanged = true;
        } else {
            $passError = 'Неверный текущий пароль.';
        }
    } else {
        $passError = 'Новый пароль не менее 4 символов.';
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    foreach (['smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_secure', 'from_email', 'from_name'] as $k) {
        if (array_key_exists($k, $_POST)) {
            setting_set('mail', $k, (string) $_POST[$k]);
        }
    }
    foreach (['emails_new_breakdown', 'emails_repair_done', 'emails_reopened'] as $k) {
        if (array_key_exists($k, $_POST)) {
            setting_set('notify', $k, (string) $_POST[$k]);
        }
    }
    if (array_key_exists('telegram_bot_token', $_POST)) {
        setting_set('telegram', 'telegram_bot_token', (string) $_POST['telegram_bot_token']);
    }
    if (array_key_exists('telegram_chat_id', $_POST)) {
        setting_set('telegram', 'telegram_chat_id', (string) $_POST['telegram_chat_id']);
    }
    $saved = true;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_templates'])) {
    $tplKeys = [
        'new_breakdown_subject', 'new_breakdown_body',
        'repair_done_subject', 'repair_done_body',
        'closed_no_repair_subject', 'closed_no_repair_body',
        'reopened_subject', 'reopened_body',
        'pin_sent_subject', 'pin_sent_body',
    ];
    foreach ($tplKeys as $k) {
        if (array_key_exists($k, $_POST)) {
            setting_set('mail_tpl', $k, (string) $_POST[$k]);
        }
    }
    $saved = true;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_telegram_templates'])) {
    foreach (['new_breakdown', 'repair_done', 'closed_no_repair', 'reopened'] as $k) {
        if (array_key_exists('tg_' . $k, $_POST)) {
            setting_set('telegram_tpl', $k, (string) $_POST['tg_' . $k]);
        }
    }
    $saved = true;
}
?>
<div class="card">
    <h3 style="margin-top:0;">Почта (SMTP)</h3>
    <?php if ($saved): ?><p style="color: var(--success);">Настройки сохранены.</p><?php endif; ?>
    <form method="post" action="?tab=settings">
        <input type="hidden" name="save_settings" value="1">
        <div class="form-row">
            <div class="form-group">
                <label>SMTP хост</label>
                <input type="text" name="smtp_host" value="<?= e(setting('mail', 'smtp_host')) ?>" placeholder="smtp.gmail.com">
                <div class="hint" style="margin-top: 4px;">Gmail: <strong>smtp.gmail.com</strong> (не smtp.google.com). Порт 587 (TLS) или 465 (SSL). Обязательно используйте пароль приложения (Google → Безопасность → Пароли приложений).</div>
            </div>
            <div class="form-group">
                <label>Порт</label>
                <input type="text" name="smtp_port" value="<?= e(setting('mail', 'smtp_port') ?: '587') ?>">
            </div>
            <div class="form-group">
                <label>Логин</label>
                <input type="text" name="smtp_user" value="<?= e(setting('mail', 'smtp_user')) ?>">
            </div>
            <div class="form-group">
                <label>Пароль</label>
                <input type="password" name="smtp_pass" value="<?= e(setting('mail', 'smtp_pass')) ?>" placeholder="••••••">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Шифрование</label>
                <select name="smtp_secure">
                    <option value="" <?= setting('mail', 'smtp_secure') === '' ? 'selected' : '' ?>>Нет</option>
                    <option value="tls" <?= setting('mail', 'smtp_secure') === 'tls' ? 'selected' : '' ?>>TLS</option>
                    <option value="ssl" <?= setting('mail', 'smtp_secure') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                </select>
            </div>
            <div class="form-group">
                <label>От кого (email)</label>
                <input type="email" name="from_email" value="<?= e(setting('mail', 'from_email')) ?>">
            </div>
            <div class="form-group">
                <label>Имя отправителя</label>
                <input type="text" name="from_name" value="<?= e(setting('mail', 'from_name')) ?>">
            </div>
        </div>
        <h4 class="mt-1">Уведомления</h4>
        <div class="form-group">
            <label>Почты при новой поломке (через запятую)</label>
            <input type="text" name="emails_new_breakdown" value="<?= e(setting('notify', 'emails_new_breakdown')) ?>" placeholder="admin@example.com, boss@example.com">
        </div>
        <div class="form-group">
            <label>Почты при завершении ремонта</label>
            <input type="text" name="emails_repair_done" value="<?= e(setting('notify', 'emails_repair_done')) ?>" placeholder="admin@example.com">
        </div>
        <div class="form-group">
            <label>Почты при повторном открытии задачи</label>
            <input type="text" name="emails_reopened" value="<?= e(setting('notify', 'emails_reopened')) ?>" placeholder="admin@example.com">
        </div>
        <h4 class="mt-1" style="margin-top: 20px;">Telegram</h4>
        <p class="text-muted" style="font-size: 0.875rem;">Уведомления дублируются в Telegram (группа или канал). Создайте бота через @BotFather, добавьте его в группу, укажите токен и chat_id группы.</p>
        <div class="form-row">
            <div class="form-group">
                <label>Токен бота</label>
                <input type="text" name="telegram_bot_token" value="<?= e(setting('telegram', 'telegram_bot_token')) ?>" placeholder="123456:ABC-DEF..." autocomplete="off">
            </div>
            <div class="form-group">
                <label>Chat ID (группы/канала)</label>
                <input type="text" name="telegram_chat_id" value="<?= e(setting('telegram', 'telegram_chat_id')) ?>" placeholder="-1001234567890">
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Сохранить</button>
    </form>

    <h4 class="mt-1" style="margin-top: 24px;">Проверка почты</h4>
    <p class="text-muted">Сначала сохраните настройки выше, затем проверьте подключение или отправьте тест.</p>
    <div style="display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-end;">
        <form method="post" action="?tab=settings" style="display: inline;">
            <input type="hidden" name="test_mail" value="1">
            <button type="submit" class="btn btn-secondary">Проверить настройки</button>
        </form>
        <form method="post" action="?tab=settings" style="display: inline; flex: 1; min-width: 200px;">
            <input type="hidden" name="send_test_mail" value="1">
            <input type="email" name="test_email" placeholder="Куда отправить тест (email)" value="<?= e($_POST['test_email'] ?? '') ?>" style="max-width: 280px; margin-right: 8px;">
            <button type="submit" class="btn btn-primary">Отправить тестовое письмо</button>
        </form>
    </div>
    <?php if ($mailTestResult !== null): ?>
        <div class="mail-test-result <?= $mailTestResult['ok'] ? 'success' : 'error' ?>" style="margin-top: 12px; padding: 12px; border-radius: 8px; <?= $mailTestResult['ok'] ? 'background: var(--success-bg); color: var(--success);' : 'background: var(--danger-bg); color: var(--danger);' ?>">
            <?= $mailTestResult['ok'] ? e($mailTestResult['message']) : e($mailTestResult['error']) ?>
        </div>
    <?php endif; ?>
    <?php if ($mailSendResult !== null): ?>
        <div class="mail-send-result <?= $mailSendResult['ok'] ? 'success' : 'error' ?>" style="margin-top: 12px; padding: 12px; border-radius: 8px; <?= $mailSendResult['ok'] ? 'background: var(--success-bg); color: var(--success);' : 'background: var(--danger-bg); color: var(--danger);' ?>">
            <?= $mailSendResult['ok'] ? e($mailSendResult['message']) : e($mailSendResult['error']) ?>
        </div>
    <?php endif; ?>
</div>
<div class="card">
    <h3 style="margin-top:0;">Telegram</h3>
    <p class="text-muted">После сохранения токена и chat_id выше можно отправить тестовое сообщение в чат.</p>
    <?php if ($telegramTestResult !== null): ?>
        <div class="msg <?= $telegramTestResult['ok'] ? 'success' : 'error' ?>" style="margin-bottom: 12px; padding: 12px; border-radius: 8px; <?= $telegramTestResult['ok'] ? 'background: var(--success-bg); color: var(--success);' : 'background: var(--danger-bg); color: var(--danger);' ?>">
            <?= $telegramTestResult['ok'] ? e($telegramTestResult['message']) : e($telegramTestResult['error']) ?>
        </div>
    <?php endif; ?>
    <form method="post" action="?tab=settings">
        <input type="hidden" name="test_telegram" value="1">
        <button type="submit" class="btn btn-secondary">Отправить тест в Telegram</button>
    </form>
</div>
<div class="card">
    <h3 style="margin-top:0;">Шаблоны Telegram</h3>
    <p class="text-muted">Текст сообщений в Telegram. Пустое поле — используется стандартный шаблон. Поддерживается HTML: &lt;b&gt;, &lt;i&gt;, &lt;code&gt;, &lt;pre&gt;.</p>
    <form method="post" action="?tab=settings">
        <input type="hidden" name="save_telegram_templates" value="1">

        <h4 style="margin-top: 20px;">Новая поломка</h4>
        <p class="text-muted" style="font-size: 0.875rem;">Поля: <code>{id}</code> <code>{object}</code> <code>{inventory_number}</code> <code>{place}</code> <code>{reporter}</code> <code>{description}</code> <code>{reproduction}</code> <code>{date}</code></p>
        <div class="form-group">
            <label>Текст сообщения (HTML)</label>
            <textarea name="tg_new_breakdown" rows="8" placeholder="🔔 &lt;b&gt;Новая поломка #{id}&lt;/b&gt;&#10;&lt;b&gt;Объект:&lt;/b&gt; {object}&#10;&lt;b&gt;Инв. номер:&lt;/b&gt; {inventory_number}&#10;..."><?= e(setting('telegram_tpl', 'new_breakdown')) ?></textarea>
        </div>

        <h4 style="margin-top: 20px;">Выполнен ремонт</h4>
        <p class="text-muted" style="font-size: 0.875rem;">Поля: <code>{id}</code> <code>{object}</code> <code>{inventory_number}</code> <code>{completion_notes}</code> <code>{date}</code></p>
        <div class="form-group">
            <label>Текст сообщения</label>
            <textarea name="tg_repair_done" rows="5"><?= e(setting('telegram_tpl', 'repair_done')) ?></textarea>
        </div>

        <h4 style="margin-top: 20px;">Закрыто без ремонта</h4>
        <p class="text-muted" style="font-size: 0.875rem;">Поля: <code>{id}</code> <code>{object}</code> <code>{inventory_number}</code> <code>{closed_action}</code> <code>{date}</code></p>
        <div class="form-group">
            <label>Текст сообщения</label>
            <textarea name="tg_closed_no_repair" rows="5"><?= e(setting('telegram_tpl', 'closed_no_repair')) ?></textarea>
        </div>

        <h4 style="margin-top: 20px;">Повторно открыта задача</h4>
        <p class="text-muted" style="font-size: 0.875rem;">Поля: <code>{id}</code> <code>{object}</code> <code>{inventory_number}</code> <code>{reported_at}</code> <code>{date}</code></p>
        <div class="form-group">
            <label>Текст сообщения</label>
            <textarea name="tg_reopened" rows="5"><?= e(setting('telegram_tpl', 'reopened')) ?></textarea>
        </div>

        <button type="submit" class="btn btn-primary">Сохранить шаблоны Telegram</button>
    </form>
</div>
<div class="card">
    <h3 style="margin-top:0;">Шаблоны писем</h3>
    <p class="text-muted">Тема и текст писем. Пустое поле — подставится стандартный шаблон. В тексте можно использовать подставляемые поля в фигурных скобках.</p>
    <form method="post" action="?tab=settings">
        <input type="hidden" name="save_templates" value="1">

        <h4 style="margin-top: 20px;">Новая поломка</h4>
        <p class="text-muted" style="font-size: 0.875rem;">Поля: <code>{id}</code> <code>{object}</code> <code>{inventory_number}</code> <code>{place}</code> <code>{reporter}</code> <code>{description}</code> <code>{reproduction}</code> <code>{date}</code></p>
        <div class="form-group">
            <label>Тема</label>
            <input type="text" name="new_breakdown_subject" value="<?= e(setting('mail_tpl', 'new_breakdown_subject')) ?>" placeholder="Новая поломка #{id}: {inventory_number}">
        </div>
        <div class="form-group">
            <label>Текст письма (HTML)</label>
            <textarea name="new_breakdown_body" rows="6" placeholder="Заявка №{id}&#10;Объект: {object}&#10;Инв. номер: {inventory_number}&#10;Место: {place}&#10;Кто обнаружил: {reporter}&#10;Описание: {description}"><?= e(setting('mail_tpl', 'new_breakdown_body')) ?></textarea>
        </div>

        <h4 style="margin-top: 20px;">Выполнен ремонт</h4>
        <p class="text-muted" style="font-size: 0.875rem;">Поля: <code>{id}</code> <code>{object}</code> <code>{inventory_number}</code> <code>{completion_notes}</code> <code>{date}</code></p>
        <div class="form-group">
            <label>Тема</label>
            <input type="text" name="repair_done_subject" value="<?= e(setting('mail_tpl', 'repair_done_subject')) ?>" placeholder="Выполнен ремонт: {inventory_number}">
        </div>
        <div class="form-group">
            <label>Текст письма</label>
            <textarea name="repair_done_body" rows="4"><?= e(setting('mail_tpl', 'repair_done_body')) ?></textarea>
        </div>

        <h4 style="margin-top: 20px;">Закрыто без ремонта</h4>
        <p class="text-muted" style="font-size: 0.875rem;">Поля: <code>{id}</code> <code>{object}</code> <code>{inventory_number}</code> <code>{closed_action}</code> <code>{date}</code></p>
        <div class="form-group">
            <label>Тема</label>
            <input type="text" name="closed_no_repair_subject" value="<?= e(setting('mail_tpl', 'closed_no_repair_subject')) ?>" placeholder="Закрыто без ремонта: {inventory_number}">
        </div>
        <div class="form-group">
            <label>Текст письма</label>
            <textarea name="closed_no_repair_body" rows="4"><?= e(setting('mail_tpl', 'closed_no_repair_body')) ?></textarea>
        </div>

        <h4 style="margin-top: 20px;">Повторно открыта задача</h4>
        <p class="text-muted" style="font-size: 0.875rem;">Поля: <code>{id}</code> <code>{object}</code> <code>{inventory_number}</code> <code>{reported_at}</code> <code>{date}</code></p>
        <div class="form-group">
            <label>Тема</label>
            <input type="text" name="reopened_subject" value="<?= e(setting('mail_tpl', 'reopened_subject')) ?>" placeholder="Повторно открыта поломка: {inventory_number}">
        </div>
        <div class="form-group">
            <label>Текст письма</label>
            <textarea name="reopened_body" rows="4"><?= e(setting('mail_tpl', 'reopened_body')) ?></textarea>
        </div>

        <h4 style="margin-top: 20px;">Письмо с пин-кодом оператору</h4>
        <p class="text-muted" style="font-size: 0.875rem;">Отправляется при нажатии «Отправить пин на почту». Поля: <code>{name}</code> <code>{pin}</code> <code>{email}</code> <code>{login_link}</code> — ссылка для входа по пин-коду (по клику сразу в кабинет)</p>
        <div class="form-group">
            <label>Тема</label>
            <input type="text" name="pin_sent_subject" value="<?= e(setting('mail_tpl', 'pin_sent_subject')) ?>" placeholder="Доступ в Реестр поломок — ваш пин-код">
        </div>
        <div class="form-group">
            <label>Текст письма (HTML)</label>
            <textarea name="pin_sent_body" rows="8" placeholder="Здравствуйте, {name}!&#10;&#10;Вам предоставлен доступ. Ваш пин: {pin}&#10;&#10;&lt;a href=&quot;{login_link}&quot;&gt;Войти в кабинет по ссылке&lt;/a&gt;&#10;&#10;— Реестр поломок"><?= e(setting('mail_tpl', 'pin_sent_body')) ?></textarea>
        </div>

        <button type="submit" class="btn btn-primary">Сохранить шаблоны</button>
    </form>
</div>
<div class="card">
    <h3 style="margin-top:0;">Сменить пароль</h3>
    <?php if ($passChanged): ?><p style="color: var(--success);">Пароль изменён.</p><?php endif; ?>
    <?php if ($passError): ?><p class="error-msg"><?= e($passError) ?></p><?php endif; ?>
    <form method="post" action="?tab=settings" style="max-width: 320px;">
        <input type="hidden" name="change_password" value="1">
        <div class="form-group">
            <label>Текущий пароль</label>
            <input type="password" name="current_password" required>
        </div>
        <div class="form-group">
            <label>Новый пароль</label>
            <input type="password" name="new_password" required minlength="4">
        </div>
        <button type="submit" class="btn btn-primary">Сменить пароль</button>
    </form>
</div>
