<?php
require_once dirname(__DIR__) . '/config.php';
use Repair\Auth;
Auth::requireAdmin();

// Удаление поломки — только для администратора
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_breakdown']) && Auth::isAdmin()) {
    $delId = (int) ($_POST['id'] ?? 0);
    if ($delId) {
        $pdo = \Repair\Db::get();
        $pdo->prepare('DELETE FROM breakdowns WHERE parent_breakdown_id = ?')->execute([$delId]);
        $pdo->prepare('DELETE FROM breakdowns WHERE id = ?')->execute([$delId]);
    }
    header('Location: ' . WEB_ROOT . '/admin/?tab=registry');
    exit;
}

$id = (int) ($_GET['id'] ?? 0);
if (!$id) { header('Location: ' . WEB_ROOT . '/admin/'); exit; }

$pdo = \Repair\Db::get();
$b = $pdo->prepare("SELECT b.*, bs.name AS status_name, bs.code AS status_code, bs.is_completed,
    n.name AS nomenclature_name, u.name AS reporter_name
    FROM breakdowns b
    JOIN breakdown_statuses bs ON bs.id = b.status_id
    LEFT JOIN nomenclature n ON n.id = b.nomenclature_id
    JOIN users u ON u.id = b.reported_by_user_id
    WHERE b.id = ?");
$b->execute([$id]);
$row = $b->fetch();
if (!$row) { header('Location: ' . WEB_ROOT . '/admin/'); exit; }

$photos = $pdo->prepare('SELECT * FROM breakdown_photos WHERE breakdown_id = ? ORDER BY id');
$photos->execute([$id]);
$photos = $photos->fetchAll();

$place = $row['place_type'] === 'warehouse' ? 'Склад' : ($row['place_type'] === 'site' ? 'Площадка: ' . e($row['place_site_project'] ?? '') : e($row['place_other_text'] ?? ''));

// Смена статуса
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['status_repaired'])) {
        $notes = trim((string) $_POST['completion_notes']);
        if ($notes === '') {
            $err = 'Укажите, что делалось и что было сломано.';
        } else {
            $pdo->prepare('UPDATE breakdowns SET status_id = 3, completed_at = NOW(), completion_notes = ? WHERE id = ?')->execute([$notes, $id]);
            $vars = ['{id}' => $id, '{inventory_number}' => $row['inventory_number'], '{object}' => $row['nomenclature_name'] ?? '—', '{completion_notes}' => $notes, '{date}' => date('d.m.Y H:i')];
            $subjTpl = setting('mail_tpl', 'repair_done_subject') ?: 'Выполнен ремонт: {inventory_number}';
            $bodyTpl = setting('mail_tpl', 'repair_done_body') ?: "Поломка #{id}<br>Инв. номер: {inventory_number}<br>Объект: {object}<br>Что сделано: {completion_notes}<br><p>— Реестр поломок</p>";
            \Repair\Mailer::sendToList(setting('notify', 'emails_repair_done'), mail_tpl_replace($subjTpl, $vars, false), mail_tpl_replace($bodyTpl, $vars, true));
            if (setting('telegram', 'telegram_bot_token') && setting('telegram', 'telegram_chat_id')) {
                \Repair\Telegram::sendTemplate('repair_done', $vars);
            }
            header('Location: ' . WEB_ROOT . '/admin/breakdown.php?id=' . $id);
            exit;
        }
    }
    if (isset($_POST['status_closed_no_repair'])) {
        $action = trim((string) $_POST['closed_action']);
        $other = trim((string) ($_POST['closed_other'] ?? ''));
        if ($action === 'other' && $other === '') {
            $err = 'Укажите, что сделано с оборудованием.';
        } else {
            $pdo->prepare('UPDATE breakdowns SET status_id = 4, completed_at = NOW(), closed_without_repair_action = ?, closed_without_repair_other = ? WHERE id = ?')
                ->execute([$action === 'other' ? 'other' : $action, $action === 'other' ? $other : null, $id]);
            $closedLabel = $action === 'written_off' ? 'Списано' : ($action === 'donors' ? 'Переведено в доноры' : $other);
            $vars = ['{id}' => $id, '{inventory_number}' => $row['inventory_number'], '{object}' => $row['nomenclature_name'] ?? '—', '{closed_action}' => $closedLabel, '{date}' => date('d.m.Y H:i')];
            $subjTpl = setting('mail_tpl', 'closed_no_repair_subject') ?: 'Закрыто без ремонта: {inventory_number}';
            $bodyTpl = setting('mail_tpl', 'closed_no_repair_body') ?: "Поломка #{id}<br>Инв. номер: {inventory_number}<br>Объект: {object}<br>Действие: {closed_action}<br><p>— Реестр поломок</p>";
            \Repair\Mailer::sendToList(setting('notify', 'emails_repair_done'), mail_tpl_replace($subjTpl, $vars, false), mail_tpl_replace($bodyTpl, $vars, true));
            if (setting('telegram', 'telegram_bot_token') && setting('telegram', 'telegram_chat_id')) {
                \Repair\Telegram::sendTemplate('closed_no_repair', $vars);
            }
            header('Location: ' . WEB_ROOT . '/admin/breakdown.php?id=' . $id);
            exit;
        }
    }
    if (isset($_POST['reopen'])) {
        $pdo->prepare('UPDATE breakdowns SET status_id = 1, completed_at = NULL, completion_notes = NULL, closed_without_repair_action = NULL, closed_without_repair_other = NULL, reopened_at = NOW() WHERE id = ?')->execute([$id]);
        $vars = ['{id}' => $id, '{inventory_number}' => $row['inventory_number'], '{object}' => $row['nomenclature_name'] ?? '—', '{reported_at}' => $row['reported_at'], '{date}' => date('d.m.Y H:i')];
        $subjTpl = setting('mail_tpl', 'reopened_subject') ?: 'Повторно открыта поломка: {inventory_number}';
        $bodyTpl = setting('mail_tpl', 'reopened_body') ?: "Поломка #{id}<br>Инв. номер: {inventory_number}<br>Объект: {object}<br>Дата заявки: {reported_at}<br>Задача повторно открыта.<br><p>— Реестр поломок</p>";
        \Repair\Mailer::sendToList(setting('notify', 'emails_reopened'), mail_tpl_replace($subjTpl, $vars, false), mail_tpl_replace($bodyTpl, $vars, true));
        if (setting('telegram', 'telegram_bot_token') && setting('telegram', 'telegram_chat_id')) {
            \Repair\Telegram::sendTemplate('reopened', $vars);
        }
        header('Location: ' . WEB_ROOT . '/admin/breakdown.php?id=' . $id);
        exit;
    }
}

$place = $row['place_type'] === 'warehouse' ? 'Склад' : ($row['place_type'] === 'site' ? 'Площадка: ' . e($row['place_site_project'] ?? '') : e($row['place_other_text'] ?? ''));
$isKit = !empty($row['parent_breakdown_id']);
$kitItems = [];
if (!$isKit) {
    $kitStmt = $pdo->prepare('SELECT b.id, b.inventory_number, n.name AS nomenclature_name FROM breakdowns b LEFT JOIN nomenclature n ON n.id = b.nomenclature_id WHERE b.parent_breakdown_id = ? ORDER BY b.id');
    $kitStmt->execute([$id]);
    $kitItems = $kitStmt->fetchAll();
}
$pageTitle = 'Поломка #' . $id;
include dirname(__DIR__) . '/admin/header.php';
?>
<div class="card">
    <a href="<?= e(WEB_ROOT) ?>/admin/?tab=registry" class="btn btn-secondary btn-sm mb-2">← Реестр</a>
    <?php if ($isKit): ?>
    <p class="text-muted" style="margin-bottom: 16px;">↳ Комплект к заявке <a href="<?= e(WEB_ROOT) ?>/admin/breakdown.php?id=<?= (int)$row['parent_breakdown_id'] ?>">№<?= (int)$row['parent_breakdown_id'] ?></a></p>
    <?php endif; ?>
    <?php if (isset($err)): ?><p class="error-msg"><?= e($err) ?></p><?php endif; ?>
    <table class="data-table">
        <tr><th style="width:180px;">Дата поломки</th><td><?= e(date('d.m.Y H:i', strtotime($row['reported_at']))) ?></td></tr>
        <tr><th>Инв. номер</th><td><?= e($row['inventory_number']) ?></td></tr>
        <tr><th>Объект</th><td><?= e($row['nomenclature_name'] ?? '—') ?><?php if ($row['nomenclature_id'] === null): ?> <span class="badge badge-warn">нет в 1С</span><?php endif; ?></td></tr>
        <tr><th>Место</th><td><?= $place ?: '—' ?></td></tr>
        <tr><th>Описание</th><td><?= nl2br(e($row['description'])) ?></td></tr>
        <tr><th>Метод воспроизведения</th><td><?= nl2br(e($row['reproduction_method'] ?? '—')) ?></td></tr>
        <tr><th>Кто обнаружил</th><td><?= e($row['reporter_name']) ?></td></tr>
        <tr><th>Статус</th><td><span class="badge badge-<?= $row['is_completed'] ? 'done' : 'new' ?>"><?= e($row['status_name']) ?></span></td></tr>
        <?php if ($row['completed_at']): ?>
        <tr><th>Дата выполнения</th><td><?= e(date('d.m.Y', strtotime($row['completed_at']))) ?></td></tr>
        <?php if ($row['completion_notes']): ?><tr><th>Что сделано</th><td><?= nl2br(e($row['completion_notes'])) ?></td></tr><?php endif; ?>
        <?php if ($row['closed_without_repair_action']): ?>
        <tr><th>Закрыто без ремонта</th><td><?= e($row['closed_without_repair_action'] === 'written_off' ? 'Списано' : ($row['closed_without_repair_action'] === 'donors' ? 'Переведено в доноры' : $row['closed_without_repair_other'] ?? '—')) ?></td></tr>
        <?php endif; ?>
        <?php endif; ?>
    </table>
    <?php if (!empty($kitItems)): ?>
        <h4>Элементы комплекта</h4>
        <ul style="margin: 0; padding-left: 20px;">
            <?php foreach ($kitItems as $k): ?>
                <li><a href="<?= e(WEB_ROOT) ?>/admin/breakdown.php?id=<?= (int)$k['id'] ?>">№<?= (int)$k['id'] ?></a> — <?= e($k['inventory_number']) ?> (<?= e($k['nomenclature_name'] ?? 'нет в 1С') ?>)</li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <?php if (!empty($photos)): ?>
        <h4>Фото</h4>
        <div style="display:flex; flex-wrap: wrap; gap: 8px;">
            <?php foreach ($photos as $p): ?>
                <a href="<?= e(UPLOAD_URL . $p['filename']) ?>" target="_blank" rel="noopener"><img src="<?= e(UPLOAD_URL . $p['filename']) ?>" alt="" style="max-width: 120px; max-height: 120px; object-fit: cover; border-radius: 8px;"></a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!$row['is_completed']): ?>
        <hr style="margin: 24px 0; border-color: var(--border);">
        <h4>Изменить статус</h4>
        <div style="display: flex; gap: 12px; flex-wrap: wrap;">
            <button type="button" class="btn btn-primary" onclick="document.getElementById('form_repaired').classList.toggle('hidden'); document.getElementById('form_closed').classList.add('hidden');">Выполнен ремонт</button>
            <button type="button" class="btn btn-secondary" onclick="document.getElementById('form_closed').classList.toggle('hidden'); document.getElementById('form_repaired').classList.add('hidden');">Закрыть без ремонта</button>
        </div>
        <div id="form_repaired" class="hidden card mt-1" style="margin-top: 16px;">
            <form method="post">
                <input type="hidden" name="status_repaired" value="1">
                <div class="form-group">
                    <label>Что делалось, что было сломано (обязательно)</label>
                    <textarea name="completion_notes" required rows="4"></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Подтвердить</button>
            </form>
        </div>
        <div id="form_closed" class="hidden card mt-1" style="margin-top: 16px;">
            <form method="post">
                <input type="hidden" name="status_closed_no_repair" value="1">
                <div class="form-group">
                    <label>Что сделано с оборудованием</label>
                    <select name="closed_action" id="closed_action">
                        <option value="written_off">Списано</option>
                        <option value="donors">Переведено в доноры</option>
                        <option value="other">Иное</option>
                    </select>
                </div>
                <div class="form-group hidden" id="closed_other_wrap">
                    <label>Укажите</label>
                    <input type="text" name="closed_other" placeholder="Текст">
                </div>
                <button type="submit" class="btn btn-primary">Подтвердить</button>
            </form>
        </div>
    <?php else: ?>
        <div class="mt-1">
            <form method="post" onsubmit="return confirm('Повторно открыть задачу? Будет отправлено уведомление.');">
                <input type="hidden" name="reopen" value="1">
                <button type="submit" class="btn btn-secondary">Повторно открыть задачу</button>
            </form>
        </div>
    <?php endif; ?>

    <?php if (Auth::isAdmin()): ?>
        <hr style="margin: 24px 0; border-color: var(--border);">
        <form method="post" onsubmit="return confirm('Удалить поломку из реестра безвозвратно?');">
            <input type="hidden" name="delete_breakdown" value="1">
            <input type="hidden" name="id" value="<?= (int) $id ?>">
            <button type="submit" class="btn btn-danger">Удалить из реестра</button>
        </form>
    <?php endif; ?>
</div>
<script>
document.getElementById('closed_action') && document.getElementById('closed_action').addEventListener('change', function(){
    document.getElementById('closed_other_wrap').classList.toggle('hidden', this.value !== 'other');
});
</script>
<?php include dirname(__DIR__) . '/admin/footer.php';
