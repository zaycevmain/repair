<?php
require_once __DIR__ . '/config.php';
use Repair\Auth;
Auth::requireLogin();
if (!Auth::isOperator()) {
    header('Location: ' . WEB_ROOT . '/');
    exit;
}

$pdo = \Repair\Db::get();
$myBreakdowns = $pdo->prepare("
    SELECT b.id, b.reported_at, b.inventory_number, b.description, bs.name AS status_name
    FROM breakdowns b
    JOIN breakdown_statuses bs ON bs.id = b.status_id
    WHERE b.reported_by_user_id = ?
    ORDER BY b.reported_at DESC
    LIMIT 50
");
$myBreakdowns->execute([Auth::userId()]);
$myBreakdowns = $myBreakdowns->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no">
    <title>Личный кабинет — Реестр поломок</title>
    <link rel="stylesheet" href="<?= e(WEB_ROOT) ?>/assets/css/common.css">
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
</head>
<body>
    <div class="app-wrap">
        <header class="page-header" style="border-bottom: 1px solid var(--border); padding-bottom: 16px;">
            <h1>Личный кабинет</h1>
            <span class="text-muted"><?= e(Auth::userName()) ?></span>
            <a href="<?= e(WEB_ROOT) ?>/?logout=1" class="btn btn-secondary btn-sm">Выход</a>
        </header>

        <div class="card">
            <button type="button" class="btn btn-primary" id="btn_add_breakdown" style="width: 100%; padding: 16px; font-size: 1.1rem;">Внести в поломки</button>
        </div>

        <div class="card">
            <h3 style="margin-top:0;">Мои поломки</h3>
            <?php if (empty($myBreakdowns)): ?>
                <p class="text-muted">Вы пока не вносили поломок.</p>
            <?php else: ?>
                <ul style="list-style: none; padding: 0; margin: 0;">
                    <?php foreach ($myBreakdowns as $b): ?>
                        <li style="padding: 12px 0; border-bottom: 1px solid var(--border);">
                            <strong><?= e($b['inventory_number']) ?></strong> — <?= e(mb_substr($b['description'], 0, 60)) ?><?= mb_strlen($b['description']) > 60 ? '…' : '' ?>
                            <br><span class="text-muted"><?= e(date('d.m.Y H:i', strtotime($b['reported_at']))) ?> · <?= e($b['status_name']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <!-- Модальное окно сканирования -->
    <div id="scan_modal" class="scan-overlay hidden">
        <div class="scan-window scan-window-scan">
            <h3>Наведите камеру на ШК/QR</h3>
            <div id="reader"></div>
            <div id="scan_result" class="scan-result hidden"></div>
            <div class="scan-actions">
                <button type="button" class="btn btn-secondary" id="scan_cancel">Отмена</button>
                <button type="button" class="btn btn-primary hidden" id="scan_confirm">Подтвердить</button>
            </div>
        </div>
    </div>

    <!-- Форма поломки (после скана) -->
    <div id="form_modal" class="scan-overlay hidden">
        <div class="scan-window form-modal-window" style="max-width: 520px; max-height: 90vh; overflow-y: auto;">
            <h3>Внести поломку</h3>
            <div class="form-content">
            <p id="form_equipment_label" style="margin: 0 0 16px; color: var(--success);"></p>
            <form id="breakdown_form">
                <input type="hidden" name="inventory_number" id="f_inventory_number">
                <div class="form-group">
                    <label>Место поломки</label>
                    <select name="place_type" id="f_place_type">
                        <option value="warehouse">Склад</option>
                        <option value="site">Площадка</option>
                        <option value="other">Другое</option>
                    </select>
                </div>
                <div class="form-group hidden" id="wrap_site">
                    <label>Название / номер проекта</label>
                    <input type="text" name="place_site_project" id="f_place_site" placeholder="Проект">
                </div>
                <div class="form-group hidden" id="wrap_other">
                    <label>Укажите место</label>
                    <input type="text" name="place_other_text" id="f_place_other" placeholder="Место">
                </div>
                <div class="form-group">
                    <label>Описание неисправности / поломки *</label>
                    <textarea name="description" id="f_description" required rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label>Метод воспроизведения</label>
                    <textarea name="reproduction_method" id="f_reproduction" rows="2"></textarea>
                </div>
                <div class="form-group">
                    <label>Фото (необязательно)</label>
                    <input type="file" name="photos[]" id="f_photos" accept="image/*" multiple>
                    <div id="photo_previews" style="display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px;"></div>
                    <div id="upload_status" class="upload-status hidden"></div>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 16px;">
                    <button type="button" class="btn btn-secondary" id="form_back">Назад к скану</button>
                    <button type="submit" class="btn btn-primary" id="form_submit_btn">Отправить</button>
                </div>
            </form>
            </div>
        </div>
    </div>

    <script>var WEB_ROOT = <?= json_encode(WEB_ROOT) ?>;</script>
    <script src="<?= e(WEB_ROOT) ?>/assets/js/operator.js"></script>
</body>
</html>
