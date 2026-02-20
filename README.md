# Реестр поломок / ремонта
Система учёта поломок оборудования с внесением заявок через сканирование ШК/QR в браузере (в т.ч. мобильном, iOS Safari).
![Screenshot](https://github.com/zaycevmain/repair/blob/main/pic.png)

## Требования

- PHP 7.4+
- MySQL 5.7+ / MariaDB
- MAMP (локально) или Apache + PHP на Ubuntu

## Установка

1. **База данных**

   Создайте БД и выполните схему:

   ```bash
   mysql -u root -p -e "CREATE DATABASE repair CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   mysql -u root -p repair < sql/schema.sql
   ```

2. **Конфиг**

   В `config.php` при необходимости измените параметры подключения к БД (для Ubuntu — свои `host`, `user`, `pass`).

3. **Загрузка Excel (опционально)**

   Для импорта номенклатуры из XLSX установите PhpSpreadsheet:

   ```bash
   cd /path/to/repair
   composer install
   ```

   В `config.php` добавьте после `session_start()`:

   ```php
   require_once ROOT . '/vendor/autoload.php';
   ```

   Импорт CSV работает без Composer.

4. **Права на каталог загрузок**

   ```bash
   chmod 755 uploads uploads/breakdowns
   ```

5. **Вход**

   - Операторы: пин-код (создаётся в админке).
   - Администратор: по умолчанию **admin** / **admin** (обязательно смените пароль в Настройках).

## Структура

- `/` — страница входа (пин-код; иконка — вход по логину/паролю для админа).
- `/operator.php` — личный кабинет оператора: «Внести в поломки» (скан камерой), «Мои поломки».
- `/admin/` — админка: Реестр, Номенклатура, Метрики, Календарь, Пользователи, Настройки.
- `/api/check_barcode.php` — проверка кода по номенклатуре (JSON).
- `/api/save_breakdown.php` — сохранение заявки поломки (POST).
- `/api/upload_photo.php` — загрузка фото к поломке.

## Сканирование (iOS Safari и др.)

Используется библиотека [html5-qrcode](https://github.com/mebjas/html5-qrcode): поддерживаются QR, EAN-13, Code 128, Data Matrix и др. На iOS камера запрашивается с `facingMode: "environment"` (задняя камера). При первом заходе нужно разрешить доступ к камере; при проблемах — проверить настройки Safari (Камера для сайта).

## Перенос на Ubuntu

- Укажите в `config.php` корректные данные MySQL.
- Настройте виртуальный хост Apache (DocumentRoot на каталог с `index.php` и остальными файлами) или используйте существующий сайт.
- Включите `mod_rewrite` при необходимости.
- Для писем настройте SMTP в админке (Настройки → почта и уведомления).
