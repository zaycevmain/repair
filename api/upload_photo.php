<?php
require_once dirname(__DIR__) . '/config.php';
\Repair\Auth::requireLogin();

header('Content-Type: application/json; charset=utf-8');

if (empty($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    json_response(['ok' => false, 'error' => 'Файл не загружен']);
}

$allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($_FILES['photo']['tmp_name']);
if (!in_array($mime, $allowed, true)) {
    json_response(['ok' => false, 'error' => 'Допустимы только изображения JPG, PNG, GIF, WebP']);
}

$uploadDir = UPLOAD_DIR;
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION) ?: 'jpg';
$filename = bin2hex(random_bytes(8)) . '_' . time() . '.' . strtolower($ext);
if (!move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . '/' . $filename)) {
    json_response(['ok' => false, 'error' => 'Ошибка сохранения']);
}

json_response(['ok' => true, 'filename' => $filename]);
