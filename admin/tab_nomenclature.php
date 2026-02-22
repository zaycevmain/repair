<?php
$pdo = \Repair\Db::get();
$nomenclature = $pdo->query("SELECT * FROM nomenclature ORDER BY name")->fetchAll();
$uploadError = $uploadOk = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_one']) && \Repair\Auth::isAdmin()) {
        $inv = trim((string)($_POST['inventory_number'] ?? ''));
        $name = trim((string)($_POST['name'] ?? ''));
        if ($inv === '' || $name === '') {
            $uploadError = 'Заполните оба поля.';
        } else {
            try {
                $st = $pdo->prepare('INSERT INTO nomenclature (inventory_number, name) VALUES (?, ?)');
                $st->execute([$inv, $name]);
                $uploadOk = 'Позиция добавлена.';
                redirect(WEB_ROOT . '/admin/?tab=nomenclature&ok=1');
            } catch (\PDOException $e) {
                if ($e->getCode() == 23000) $uploadError = 'Инвентарный номер уже существует: ' . e($inv);
                else $uploadError = 'Ошибка: ' . e($e->getMessage());
            }
        }
    }
    if (isset($_POST['delete_id']) && \Repair\Auth::isAdmin()) {
        $id = (int) $_POST['delete_id'];
        $pdo->prepare('DELETE FROM nomenclature WHERE id = ?')->execute([$id]);
        redirect(WEB_ROOT . '/admin/?tab=nomenclature&deleted=1');
    }
    if (!empty($_FILES['excel_file']['tmp_name']) && \Repair\Auth::isAdmin()) {
        $tmp = $_FILES['excel_file']['tmp_name'];
        $ext = strtolower(pathinfo($_FILES['excel_file']['name'], PATHINFO_EXTENSION));
        if ($ext === 'csv' || $ext === 'xlsx' || $ext === 'xls') {
            require_once dirname(__DIR__) . '/includes/ExcelImport.php';
            $result = \Repair\ExcelImport::importNomenclature($tmp, $ext);
            if (!empty($result['error'])) {
                $uploadError = $result['error'];
            } else {
                $_SESSION['nomenclature_import_message'] = $result['message'] ?? 'Загружено позиций: ' . (int)($result['imported'] ?? 0);
                redirect(WEB_ROOT . '/admin/?tab=nomenclature&ok_import=1');
            }
        } else {
            $uploadError = 'Допустимые форматы: CSV, XLSX, XLS';
        }
    }
}
if (isset($_GET['ok'])) $uploadOk = 'Позиция добавлена.';
if (isset($_GET['deleted'])) $uploadOk = 'Позиция удалена.';
if (isset($_GET['ok_import'])) {
    $uploadOk = isset($_SESSION['nomenclature_import_message']) ? $_SESSION['nomenclature_import_message'] : 'Импорт выполнен.';
    unset($_SESSION['nomenclature_import_message']);
}
?>
<?php if (\Repair\Auth::isAdmin()): ?>
<div class="card">
    <h3 style="margin-top:0;">Добавить вручную</h3>
    <?php if ($uploadError): ?><p class="error-msg"><?= $uploadError ?></p><?php endif; ?>
    <?php if ($uploadOk): ?><p style="color: var(--success);"><?= e($uploadOk) ?></p><?php endif; ?>
    <form method="post" style="max-width: 400px; display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end;">
        <input type="hidden" name="add_one" value="1">
        <div class="form-group" style="margin:0; flex: 1; min-width: 120px;">
            <label>Инвентарный номер</label>
            <input type="text" name="inventory_number" required placeholder="ШК/QR значение">
        </div>
        <div class="form-group" style="margin:0; flex: 1; min-width: 180px;">
            <label>Название</label>
            <input type="text" name="name" required placeholder="Название номенклатуры">
        </div>
        <button type="submit" class="btn btn-primary">Добавить</button>
    </form>
</div>
<div class="card">
    <h3 style="margin-top:0;">Залить из Excel/CSV</h3>
    <p class="text-muted">Колонки: инвентарный номер, название. Первая строка — заголовок (пропускается). Дубликаты инв. номеров в файле и с уже имеющимися в БД не добавляются.</p>
    <form method="post" enctype="multipart/form-data" style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
        <input type="file" name="excel_file" accept=".csv,.xlsx,.xls" required>
        <button type="submit" class="btn btn-primary">Загрузить</button>
    </form>
</div>
<?php endif; ?>
<div class="card">
    <h3 style="margin-top:0;">Список номенклатуры</h3>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr><th>Инв. номер</th><th>Название</th><th></th></tr>
            </thead>
            <tbody>
                <?php foreach ($nomenclature as $n): ?>
                <tr>
                    <td><?= e($n['inventory_number']) ?></td>
                    <td><?= e($n['name']) ?></td>
                    <td>
                        <?php if (\Repair\Auth::isAdmin()): ?>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Удалить?');">
                            <input type="hidden" name="delete_id" value="<?= (int)$n['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Удалить</button>
                        </form>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if (empty($nomenclature)): ?><p class="text-muted">Номенклатура пуста.</p><?php endif; ?>
</div>
