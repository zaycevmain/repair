<?php
ob_start();
require_once dirname(__DIR__) . '/config.php';
\Repair\Auth::requireLogin();
if (!\Repair\Auth::isOperator()) {
    json_response(['ok' => false, 'error' => 'Доступ только для операторов'], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$inventoryNumber = trim((string) ($_POST['inventory_number'] ?? ''));
$placeType = $_POST['place_type'] ?? '';
$placeSiteProject = trim((string) ($_POST['place_site_project'] ?? ''));
$placeOtherText = trim((string) ($_POST['place_other_text'] ?? ''));
$description = trim((string) ($_POST['description'] ?? ''));
$reproductionMethod = trim((string) ($_POST['reproduction_method'] ?? ''));
$photoIds = isset($_POST['photo_ids']) ? (array) $_POST['photo_ids'] : [];

if ($inventoryNumber === '' || $description === '') {
    json_response(['ok' => false, 'error' => 'Укажите инв. номер и описание']);
}
if (!in_array($placeType, ['warehouse', 'site', 'other'], true)) {
    json_response(['ok' => false, 'error' => 'Неверное место поломки']);
}

$pdo = \Repair\Db::get();
$stmt = $pdo->prepare('SELECT id, name FROM nomenclature WHERE inventory_number = ?');
$stmt->execute([$inventoryNumber]);
$nom = $stmt->fetch();
$nomenclatureId = $nom ? (int) $nom['id'] : null;
$nomenclatureName = $nom ? (string) $nom['name'] : $inventoryNumber;

$stmt = $pdo->prepare("
    INSERT INTO breakdowns (reported_at, reported_by_user_id, inventory_number, nomenclature_id, place_type, place_site_project, place_other_text, description, reproduction_method, status_id)
    VALUES (NOW(), ?, ?, ?, ?, ?, ?, ?, ?, 1)
");
$stmt->execute([
    \Repair\Auth::userId(),
    $inventoryNumber,
    $nomenclatureId,
    $placeType,
    $placeType === 'site' ? $placeSiteProject : null,
    $placeType === 'other' ? $placeOtherText : null,
    $description,
    $reproductionMethod,
]);
$breakdownId = (int) $pdo->lastInsertId();

$uploadDir = UPLOAD_DIR;
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
$stmtPhoto = $pdo->prepare('INSERT INTO breakdown_photos (breakdown_id, filename) VALUES (?, ?)');
foreach ($photoIds as $fid) {
    $fid = basename(trim((string) $fid));
    if (preg_match('/^[a-f0-9]+_[0-9]+\.(jpg|jpeg|png|gif|webp)$/i', $fid) && is_file($uploadDir . '/' . $fid)) {
        $stmtPhoto->execute([$breakdownId, $fid]);
    }
}

$emails = setting('notify', 'emails_new_breakdown');
$placeLabel = $placeType === 'warehouse' ? 'Склад' : ($placeType === 'site' ? 'Площадка: ' . $placeSiteProject : $placeOtherText);
$reporterName = \Repair\Auth::userName();
$vars = [
    '{object}' => $nomenclatureName,
    '{inventory_number}' => $inventoryNumber,
    '{place}' => $placeLabel,
    '{reporter}' => $reporterName,
    '{description}' => $description,
    '{reproduction}' => $reproductionMethod,
    '{date}' => date('d.m.Y H:i'),
];
$subjectTpl = setting('mail_tpl', 'new_breakdown_subject') ?: 'Новая поломка: {inventory_number}';
$bodyTpl = setting('mail_tpl', 'new_breakdown_body') ?: "На проекте обнаружена поломка/неисправность.<br><br>Объект: {object}<br>Инв. номер: {inventory_number}<br>Место: {place}<br>Кто обнаружил: {reporter}<br><br>Описание: {description}<br>Метод воспроизведения: {reproduction}<br>";
$subject = mail_tpl_replace($subjectTpl, $vars, false);
$body = mail_tpl_replace($bodyTpl, $vars, true);
if ($emails) {
    \Repair\Mailer::sendToList($emails, $subject, $body);
}
if (setting('telegram', 'telegram_bot_token') && setting('telegram', 'telegram_chat_id')) {
    \Repair\Telegram::sendTemplate('new_breakdown', [
        '{object}' => $nomenclatureName,
        '{inventory_number}' => $inventoryNumber,
        '{place}' => $placeLabel,
        '{reporter}' => $reporterName,
        '{description}' => $description,
        '{reproduction}' => $reproductionMethod,
        '{date}' => date('d.m.Y H:i'),
    ]);
}

json_response(['ok' => true, 'id' => $breakdownId]);
