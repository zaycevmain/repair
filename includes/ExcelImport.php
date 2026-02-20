<?php
namespace Repair;

class ExcelImport {
    public static function importNomenclature(string $filePath, string $ext): array {
        $rows = self::readRows($filePath, $ext);
        if (isset($rows['error'])) {
            return ['error' => $rows['error'], 'imported' => 0];
        }
        if (empty($rows)) {
            return ['error' => 'Не удалось прочитать файл или нет данных.', 'imported' => 0];
        }
        $pdo = Db::get();
        $existing = $pdo->query('SELECT inventory_number FROM nomenclature')->fetchAll(\PDO::FETCH_COLUMN);
        $existing = array_flip($existing);
        $duplicatesFile = [];
        $duplicatesDb = [];
        $toInsert = [];
        $rowNum = 1;
        foreach ($rows as $row) {
            $rowNum++;
            $inv = trim((string)($row[0] ?? ''));
            $name = trim((string)($row[1] ?? ''));
            if ($inv === '' && $name === '') continue;
            if ($inv === '') continue;
            if (isset($toInsert[$inv])) {
                $duplicatesFile[] = $rowNum;
                continue;
            }
            if (isset($existing[$inv])) {
                $duplicatesDb[] = $inv;
                continue;
            }
            $toInsert[$inv] = $name;
        }
        $imported = 0;
        $stmt = $pdo->prepare('INSERT INTO nomenclature (inventory_number, name) VALUES (?, ?)');
        foreach ($toInsert as $inv => $name) {
            try {
                $stmt->execute([$inv, $name]);
                $imported++;
            } catch (\PDOException $e) {
                if ($e->getCode() != 23000) throw $e;
                $duplicatesDb[] = $inv;
            }
        }
        $msg = "Загружено позиций: $imported.";
        if (!empty($duplicatesFile)) $msg .= ' Дубли в файле: строки ' . implode(', ', $duplicatesFile);
        if (!empty($duplicatesDb)) $msg .= ' Дубли с БД: ' . implode(', ', array_slice($duplicatesDb, 0, 10)) . (count($duplicatesDb) > 10 ? '…' : '');
        return [
            'imported' => $imported,
            'message' => $msg,
            'duplicates_file' => implode(', ', $duplicatesFile),
            'duplicates_db' => implode(', ', $duplicatesDb),
        ];
    }

    private static function readRows(string $path, string $ext): array {
        if ($ext === 'csv') {
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $out = [];
            foreach ($lines as $i => $line) {
                if ($i === 0 && (strpos($line, 'inventory') !== false || strpos($line, 'номер') !== false)) continue;
                $out[] = str_getcsv($line, ';') ?: str_getcsv($line, ',');
            }
            return $out;
        }
        if ($ext === 'xlsx' || $ext === 'xls') {
            if (class_exists('PhpOffice\PhpSpreadsheet\IOFactory')) {
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
                $sheet = $spreadsheet->getActiveSheet();
                return $sheet->toArray();
            }
            return ['error' => 'Для загрузки XLSX установите PhpSpreadsheet: composer require phpoffice/phpspreadsheet'];
        }
        return [];
    }
}
