<?php
require_once dirname(__DIR__) . '/config.php';
\Repair\Auth::requireLogin();
header('Content-Type: application/json; charset=utf-8');

$code = trim((string) ($_GET['code'] ?? $_POST['code'] ?? ''));
if ($code === '') {
    json_response(['found' => false, 'error' => 'Пустой код']);
}

$pdo = \Repair\Db::get();
$stmt = $pdo->prepare('SELECT id, inventory_number, name FROM nomenclature WHERE inventory_number = ?');
$stmt->execute([$code]);
$row = $stmt->fetch();
if ($row) {
    json_response(['found' => true, 'id' => (int) $row['id'], 'inventory_number' => $row['inventory_number'], 'name' => $row['name']]);
}
json_response(['found' => false]);
