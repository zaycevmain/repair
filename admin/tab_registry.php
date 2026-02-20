<?php
$pdo = \Repair\Db::get();
$stmt = $pdo->query("
    SELECT b.*, bs.name AS status_name, bs.code AS status_code, bs.is_completed,
           n.name AS nomenclature_name, u.name AS reporter_name
    FROM breakdowns b
    JOIN breakdown_statuses bs ON bs.id = b.status_id
    LEFT JOIN nomenclature n ON n.id = b.nomenclature_id
    JOIN users u ON u.id = b.reported_by_user_id
    ORDER BY bs.is_completed ASC, b.reported_at DESC
");
$rows = $stmt->fetchAll();
?>
<div class="card">
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Дата поломки</th>
                    <th>Инв. номер</th>
                    <th>Объект</th>
                    <th>Место</th>
                    <th>Описание</th>
                    <th>Кто обнаружил</th>
                    <th>Статус</th>
                    <th>Дата выполнения</th>
                    <th></th>
                    <?php if (Repair\Auth::isAdmin()): ?><th></th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): 
                    $place = $r['place_type'] === 'warehouse' ? 'Склад' : ($r['place_type'] === 'site' ? 'Площадка: ' . e($r['place_site_project'] ?? '') : e($r['place_other_text'] ?? ''));
                ?>
                <tr class="<?= $r['is_completed'] ? 'completed' : '' ?>">
                    <td><?= e(date('d.m.Y H:i', strtotime($r['reported_at']))) ?></td>
                    <td><?= e($r['inventory_number']) ?></td>
                    <td><?= e($r['nomenclature_name'] ?? '—') ?></td>
                    <td><?= $place ?: '—' ?></td>
                    <td style="max-width:200px;"><?= e(mb_substr($r['description'], 0, 80)) ?><?= mb_strlen($r['description']) > 80 ? '…' : '' ?></td>
                    <td><?= e($r['reporter_name']) ?></td>
                    <td><span class="badge badge-<?= $r['is_completed'] ? 'done' : 'new' ?>"><?= e($r['status_name']) ?></span></td>
                    <td><?= $r['completed_at'] ? e(date('d.m.Y', strtotime($r['completed_at']))) : '—' ?></td>
                    <td><a href="<?= e(WEB_ROOT) ?>/admin/breakdown.php?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-secondary">Открыть</a></td>
                    <?php if (Repair\Auth::isAdmin()): ?>
                    <td>
                        <form method="post" action="<?= e(WEB_ROOT) ?>/admin/breakdown.php" style="display:inline;" onsubmit="return confirm('Удалить поломку из реестра?');">
                            <input type="hidden" name="delete_breakdown" value="1">
                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Удалить</button>
                        </form>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if (empty($rows)): ?>
        <p class="text-muted">Нет записей в реестре.</p>
    <?php endif; ?>
</div>
