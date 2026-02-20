<?php
$pdo = \Repair\Db::get();
$year = isset($_GET['y']) ? (int)$_GET['y'] : (int) date('Y');
$month = isset($_GET['m']) ? (int)$_GET['m'] : (int) date('n');
if ($month < 1) { $month = 12; $year--; }
if ($month > 12) { $month = 1; $year++; }
$monthStart = sprintf('%04d-%02d-01', $year, $month);
$monthEnd = date('Y-m-t', strtotime($monthStart)) . ' 23:59:59';

$reported = $pdo->prepare("SELECT DATE(reported_at) AS d, COUNT(*) AS c FROM breakdowns WHERE reported_at >= ? AND reported_at <= ? GROUP BY DATE(reported_at)");
$reported->execute([$monthStart . ' 00:00:00', $monthEnd]);
$byDayReported = [];
while ($r = $reported->fetch()) $byDayReported[$r['d']] = (int) $r['c'];

$completed = $pdo->prepare("SELECT DATE(completed_at) AS d, COUNT(*) AS c FROM breakdowns WHERE completed_at >= ? AND completed_at <= ? AND completed_at IS NOT NULL GROUP BY DATE(completed_at)");
$completed->execute([$monthStart . ' 00:00:00', $monthEnd]);
$byDayCompleted = [];
while ($r = $completed->fetch()) $byDayCompleted[$r['d']] = (int) $r['c'];

$firstDay = (int) date('w', strtotime($monthStart));
$firstDay = $firstDay === 0 ? 6 : $firstDay - 1;
$daysInMonth = (int) date('t', strtotime($monthStart));
$weekdays = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
?>
<div class="card calendar-wrap">
    <div class="page-header" style="margin-bottom: 16px;">
        <?php
        $months = [1=>'Январь','Февраль','Март','Апрель','Май','Июнь','Июль','Август','Сентябрь','Октябрь','Ноябрь','Декабрь'];
        ?>
        <h3 style="margin:0;">Календарь — <?= $months[(int)date('n', strtotime($monthStart))] ?> <?= date('Y', strtotime($monthStart)) ?></h3>
        <div>
            <a href="?tab=calendar&y=<?= $year ?>&m=<?= $month - 1 ?>" class="btn btn-secondary btn-sm">←</a>
            <a href="?tab=calendar&y=<?= date('Y') ?>&m=<?= date('n') ?>" class="btn btn-secondary btn-sm">Сегодня</a>
            <a href="?tab=calendar&y=<?= $year ?>&m=<?= $month + 1 ?>" class="btn btn-secondary btn-sm">→</a>
        </div>
    </div>
    <p class="text-muted mb-2"><span class="red">●</span> — обнаружено поломок, <span class="green">●</span> — выполнено работ</p>
    <div class="calendar-grid">
        <?php foreach ($weekdays as $w): ?>
            <div class="cal-day head"><?= $w ?></div>
        <?php endforeach; ?>
        <?php for ($i = 0; $i < $firstDay; $i++): ?>
            <div class="cal-day empty"></div>
        <?php endfor; ?>
        <?php for ($d = 1; $d <= $daysInMonth; $d++): 
            $date = sprintf('%04d-%02d-%02d', $year, $month, $d);
            $rep = $byDayReported[$date] ?? 0;
            $com = $byDayCompleted[$date] ?? 0;
        ?>
            <div class="cal-day">
                <?= $d ?>
                <?php if ($rep > 0): ?><span class="red"><?= $rep ?></span><?php endif; ?>
                <?php if ($com > 0): ?><span class="green"><?= $com ?></span><?php endif; ?>
            </div>
        <?php endfor; ?>
    </div>
</div>
