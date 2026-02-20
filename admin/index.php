<?php
require_once dirname(__DIR__) . '/config.php';
use Repair\Auth;
Auth::requireAdmin();

$tab = $_GET['tab'] ?? 'registry';
$allowed = ['registry', 'nomenclature', 'settings', 'users', 'metrics', 'calendar'];
if (!in_array($tab, $allowed)) $tab = 'registry';
if (in_array($tab, ['settings', 'users']) && !Auth::isAdmin()) {
    $tab = 'registry';
    header('Location: ' . WEB_ROOT . '/admin/?tab=registry');
    exit;
}

$pageTitle = [
    'registry' => 'Реестр поломок',
    'nomenclature' => 'Номенклатура',
    'settings' => 'Настройки',
    'users' => 'Пользователи',
    'metrics' => 'Метрики',
    'calendar' => 'Календарь',
][$tab];

include dirname(__DIR__) . '/admin/header.php';
include __DIR__ . "/tab_{$tab}.php";
include dirname(__DIR__) . '/admin/footer.php';
