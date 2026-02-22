<?php
require_once dirname(__DIR__) . '/config.php';
\Repair\Auth::requireLogin();

header('Content-Type: application/json; charset=utf-8');

if (empty($_FILES['photo'])) {
    $err = 'Файл не загружен.';
    if (isset($_SERVER['CONTENT_LENGTH']) && (int) $_SERVER['CONTENT_LENGTH'] > 0) {
        $err = 'Файл слишком большой. Увеличьте upload_max_filesize и post_max_size в php.ini или выберите фото меньше.';
    }
    json_response(['ok' => false, 'error' => $err]);
}
$errCode = (int) ($_FILES['photo']['error'] ?? -1);
if ($errCode !== UPLOAD_ERR_OK) {
    $msg = [
        UPLOAD_ERR_INI_SIZE => 'Файл превышает лимит upload_max_filesize. Выберите фото меньше или увеличьте лимит в php.ini.',
        UPLOAD_ERR_FORM_SIZE => 'Файл слишком большой.',
        UPLOAD_ERR_PARTIAL => 'Файл загружен частично. Попробуйте снова.',
        UPLOAD_ERR_NO_FILE => 'Файл не выбран.',
    ];
    json_response(['ok' => false, 'error' => $msg[$errCode] ?? 'Ошибка загрузки (код ' . $errCode . ').']);
}

$allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($_FILES['photo']['tmp_name']);
if (!in_array($mime, $allowed, true)) {
    json_response(['ok' => false, 'error' => 'Допустимы только изображения JPG, PNG, GIF, WebP']);
}

$uploadDir = UPLOAD_DIR;
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$tmpPath = $_FILES['photo']['tmp_name'];
$filename = bin2hex(random_bytes(8)) . '_' . time() . '.jpg';

// Сжатие: ресайз до 960px, JPEG качество 60% (требует GD с поддержкой JPEG)
$saved = false;
if (extension_loaded('gd') && function_exists('imagecreatefromjpeg') && function_exists('imagejpeg')) {
    $src = null;
    if ($mime === 'image/jpeg') $src = @imagecreatefromjpeg($tmpPath);
    elseif ($mime === 'image/png' && function_exists('imagecreatefrompng')) $src = @imagecreatefrompng($tmpPath);
    elseif ($mime === 'image/gif' && function_exists('imagecreatefromgif')) $src = @imagecreatefromgif($tmpPath);
    elseif ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) $src = @imagecreatefromwebp($tmpPath);
    if ($src) {
        $w = imagesx($src);
        $h = imagesy($src);
        $maxSize = 960;
        if ($w > $maxSize || $h > $maxSize) {
            $ratio = min($maxSize / $w, $maxSize / $h);
            $nw = (int) round($w * $ratio);
            $nh = (int) round($h * $ratio);
            $dst = imagecreatetruecolor($nw, $nh);
            if ($dst && imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h)) {
                imagedestroy($src);
                $src = $dst;
            }
        }
        $saved = imagejpeg($src, $uploadDir . '/' . $filename, 60);
        imagedestroy($src);
    }
}
if (!$saved) {
    $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION) ?: 'jpg';
    $filename = bin2hex(random_bytes(8)) . '_' . time() . '.' . strtolower($ext);
    $saved = move_uploaded_file($tmpPath, $uploadDir . '/' . $filename);
}
if (!$saved) {
    json_response(['ok' => false, 'error' => 'Ошибка сохранения']);
}

json_response(['ok' => true, 'filename' => $filename]);
