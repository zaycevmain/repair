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

$parentId = (int) ($_POST['parent_id'] ?? 0);
$inventoryNumber = trim((string) ($_POST['inventory_number'] ?? ''));

if ($parentId <= 0 || $inventoryNumber === '') {
    json_response(['ok' => false, 'error' => 'Укажите заявку и инв. номер']);
}

$pdo = \Repair\Db::get();
$parent = $pdo->prepare('SELECT id, place_type, place_site_project, place_other_text, reported_by_user_id FROM breakdowns WHERE id = ? AND parent_breakdown_id IS NULL');
$parent->execute([$parentId]);
$parent = $parent->fetch();
if (!$parent || (int) $parent['reported_by_user_id'] !== \Repair\Auth::userId()) {
    json_response(['ok' => false, 'error' => 'Заявка не найдена или доступ запрещён']);
}

$stmt = $pdo->prepare('SELECT id, name FROM nomenclature WHERE inventory_number = ?');
$stmt->execute([$inventoryNumber]);
$nom = $stmt->fetch();
$nomenclatureId = $nom ? (int) $nom['id'] : null;

$description = 'Комплект к заявке №' . $parentId . ', ШК: ' . $inventoryNumber;

$ins = $pdo->prepare("
    INSERT INTO breakdowns (reported_at, reported_by_user_id, inventory_number, nomenclature_id, place_type, place_site_project, place_other_text, description, parent_breakdown_id, status_id)
    VALUES (NOW(), ?, ?, ?, ?, ?, ?, ?, ?, 1)
");
$ins->execute([
    $parent['reported_by_user_id'],
    $inventoryNumber,
    $nomenclatureId,
    $parent['place_type'],
    $parent['place_type'] === 'site' ? $parent['place_site_project'] : null,
    $parent['place_type'] === 'other' ? $parent['place_other_text'] : null,
    $description,
    $parentId,
]);
$kitId = (int) $pdo->lastInsertId();

json_response(['ok' => true, 'id' => $kitId]);
