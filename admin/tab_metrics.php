<?php
$pdo = \Repair\Db::get();
$now = new DateTime();
$monthStart = $now->format('Y-m-01 00:00:00');
$lastMonth = (clone $now)->modify('-1 month');
$lastMonthStart = $lastMonth->format('Y-m-01 00:00:00');
$lastMonthEnd = $lastMonth->format('Y-m-t 23:59:59');

$newCount = $pdo->query("SELECT COUNT(*) FROM breakdowns b JOIN breakdown_statuses bs ON bs.id = b.status_id WHERE bs.code = 'new'")->fetchColumn();
$totalThisMonth = $pdo->query("SELECT COUNT(*) FROM breakdowns WHERE reported_at >= '$monthStart'")->fetchColumn();
$doneThisMonth = $pdo->query("SELECT COUNT(*) FROM breakdowns WHERE completed_at >= '$monthStart' AND completed_at IS NOT NULL")->fetchColumn();
$totalLastMonth = $pdo->query("SELECT COUNT(*) FROM breakdowns WHERE reported_at >= '$lastMonthStart' AND reported_at <= '$lastMonthEnd'")->fetchColumn();
$doneLastMonth = $pdo->query("SELECT COUNT(*) FROM breakdowns WHERE completed_at >= '$lastMonthStart' AND completed_at <= '$lastMonthEnd'")->fetchColumn();

$topNomenclature = $pdo->query("
    SELECT n.name, n.inventory_number, COUNT(*) AS cnt
    FROM breakdowns b
    JOIN nomenclature n ON n.id = b.nomenclature_id
    GROUP BY b.nomenclature_id
    ORDER BY cnt DESC
    LIMIT 10
")->fetchAll();

$topReporters = $pdo->query("
    SELECT u.name, COUNT(*) AS cnt
    FROM breakdowns b
    JOIN users u ON u.id = b.reported_by_user_id
    GROUP BY b.reported_by_user_id
    ORDER BY cnt DESC
    LIMIT 10
")->fetchAll();
?>
<div class="metrics-grid">
    <div class="metric-card">
        <div class="value"><?= (int)$newCount ?></div>
        <div class="label">Новых поломок</div>
    </div>
    <div class="metric-card">
        <div class="value"><?= (int)$totalThisMonth ?></div>
        <div class="label">Поломок за месяц</div>
    </div>
    <div class="metric-card">
        <div class="value"><?= (int)$doneThisMonth ?></div>
        <div class="label">Отработано за месяц</div>
    </div>
    <div class="metric-card">
        <div class="value"><?= (int)$totalLastMonth ?></div>
        <div class="label">Всего в прошлом месяце</div>
    </div>
    <div class="metric-card">
        <div class="value"><?= (int)$doneLastMonth ?></div>
        <div class="label">Отработано в прошлом месяце</div>
    </div>
</div>
<div class="card">
    <h3 style="margin-top:0;">ТОП-10 номенклатуры по поломкам</h3>
    <table class="data-table">
        <thead><tr><th>#</th><th>Номенклатура</th><th>Инв. номер</th><th>Кол-во</th></tr></thead>
        <tbody>
            <?php foreach ($topNomenclature as $i => $row): ?>
            <tr><td><?= $i + 1 ?></td><td><?= e($row['name']) ?></td><td><?= e($row['inventory_number']) ?></td><td><?= (int)$row['cnt'] ?></td></tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php if (empty($topNomenclature)): ?><p class="text-muted">Нет данных.</p><?php endif; ?>
</div>
<div class="card">
    <h3 style="margin-top:0;">ТОП сотрудников по публикации поломок</h3>
    <table class="data-table">
        <thead><tr><th>#</th><th>Сотрудник</th><th>Кол-во</th></tr></thead>
        <tbody>
            <?php foreach ($topReporters as $i => $row): ?>
            <tr><td><?= $i + 1 ?></td><td><?= e($row['name']) ?></td><td><?= (int)$row['cnt'] ?></td></tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php if (empty($topReporters)): ?><p class="text-muted">Нет данных.</p><?php endif; ?>
</div>
