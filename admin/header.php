<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ?? 'Админка') ?> — Реестр поломок</title>
    <link rel="stylesheet" href="<?= e(WEB_ROOT) ?>/assets/css/common.css">
</head>
<body>
    <div class="app-wrap">
        <header class="page-header" style="border-bottom: 1px solid var(--border); padding-bottom: 16px;">
            <h1><?= e($pageTitle ?? 'Админка') ?></h1>
            <div style="display: flex; align-items: center; gap: 12px;">
                <span class="text-muted"><?= e(Repair\Auth::userName()) ?></span>
                <a href="<?= e(WEB_ROOT) ?>/admin/?tab=settings" class="btn btn-secondary btn-sm">Настройки</a>
                <a href="<?= e(WEB_ROOT) ?>/?logout=1" class="btn btn-secondary btn-sm">Выход</a>
            </div>
        </header>
        <nav class="tabs">
            <a href="?tab=registry" class="<?= ($tab ?? '') === 'registry' ? 'active' : '' ?>">Реестр</a>
            <a href="?tab=nomenclature" class="<?= ($tab ?? '') === 'nomenclature' ? 'active' : '' ?>">Номенклатура</a>
            <a href="?tab=metrics" class="<?= ($tab ?? '') === 'metrics' ? 'active' : '' ?>">Метрики</a>
            <a href="?tab=calendar" class="<?= ($tab ?? '') === 'calendar' ? 'active' : '' ?>">Календарь</a>
            <?php if (Repair\Auth::isAdmin()): ?>
            <a href="?tab=users" class="<?= ($tab ?? '') === 'users' ? 'active' : '' ?>">Пользователи</a>
            <a href="?tab=settings" class="<?= ($tab ?? '') === 'settings' ? 'active' : '' ?>">Настройки</a>
            <?php endif; ?>
        </nav>
