-- Реестр поломок/ремонта - схема БД
-- MySQL 5.7+ / MariaDB

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Роли: admin, operator, engineer
CREATE TABLE IF NOT EXISTS `roles` (
  `id` tinyint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(32) NOT NULL,
  `name` varchar(64) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `roles` (`id`, `code`, `name`) VALUES
(1, 'admin', 'Администратор'),
(2, 'operator', 'Оператор'),
(3, 'engineer', 'Инженер');

-- Пользователи
CREATE TABLE IF NOT EXISTS `users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `role_id` tinyint unsigned NOT NULL DEFAULT 2,
  `login` varchar(64) DEFAULT NULL COMMENT 'для admin/engineer',
  `password_hash` varchar(255) DEFAULT NULL COMMENT 'для admin/engineer',
  `pin` varchar(6) DEFAULT NULL COMMENT 'только для операторов, уникальный 6 цифр',
  `name` varchar(255) NOT NULL COMMENT 'ФИО',
  `email` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '0 = заблокирован',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `login` (`login`),
  UNIQUE KEY `pin` (`pin`),
  KEY `role_id` (`role_id`),
  CONSTRAINT `users_role_fk` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- admin / admin (пароль: admin)
INSERT INTO `users` (`id`, `role_id`, `login`, `password_hash`, `pin`, `name`, `email`) VALUES
(1, 1, 'admin', '$2y$12$GondRqK3J6nvQJZ7DsRBP.2p4dwMq.TRgn5YQ5ba.ysu.fRHMcqmO', NULL, 'Администратор', NULL);

-- Настройки (ключ-значение)
CREATE TABLE IF NOT EXISTS `settings` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `group_name` varchar(64) NOT NULL,
  `key_name` varchar(64) NOT NULL,
  `value` text,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `group_key` (`group_name`,`key_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Примеры настроек
INSERT INTO `settings` (`group_name`, `key_name`, `value`) VALUES
('mail', 'smtp_host', ''),
('mail', 'smtp_port', '587'),
('mail', 'smtp_user', ''),
('mail', 'smtp_pass', ''),
('mail', 'smtp_secure', 'tls'),
('mail', 'from_email', ''),
('mail', 'from_name', 'Реестр поломок'),
('notify', 'emails_new_breakdown', ''),
('notify', 'emails_repair_done', ''),
('notify', 'emails_reopened', '');

-- Номенклатура (оборудование)
CREATE TABLE IF NOT EXISTS `nomenclature` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `inventory_number` varchar(128) NOT NULL COMMENT 'ШК/QR/EAN и т.д.',
  `name` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `inventory_number` (`inventory_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Статусы поломок
CREATE TABLE IF NOT EXISTS `breakdown_statuses` (
  `id` tinyint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(32) NOT NULL,
  `name` varchar(64) NOT NULL,
  `is_completed` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = завершённая (серая в реестре)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `breakdown_statuses` (`id`, `code`, `name`, `is_completed`) VALUES
(1, 'new', 'Новая поломка', 0),
(2, 'in_work', 'В работе', 0),
(3, 'repaired', 'Выполнен ремонт', 1),
(4, 'closed_no_repair', 'Закрыто без ремонта', 1);

-- Поломки
CREATE TABLE IF NOT EXISTS `breakdowns` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `reported_at` datetime NOT NULL COMMENT 'дата внесения поломки',
  `reported_by_user_id` int unsigned NOT NULL,
  `inventory_number` varchar(128) NOT NULL,
  `nomenclature_id` int unsigned DEFAULT NULL COMMENT 'сопоставленная номенклатура',
  `place_type` enum('warehouse','site','other') NOT NULL COMMENT 'Склад/Площадка/Другое',
  `place_site_project` varchar(255) DEFAULT NULL COMMENT 'название/номер проекта при place=site',
  `place_other_text` varchar(255) DEFAULT NULL COMMENT 'при place=other',
  `description` text NOT NULL,
  `reproduction_method` text,
  `parent_breakdown_id` int unsigned DEFAULT NULL COMMENT 'элемент комплекта к заявке',
  `status_id` tinyint unsigned NOT NULL DEFAULT 1,
  `completed_at` datetime DEFAULT NULL COMMENT 'дата выполнения работ',
  `completion_notes` text COMMENT 'что делалось, что было сломано (при Выполнен ремонт)',
  `closed_without_repair_action` varchar(64) DEFAULT NULL COMMENT 'Списано/Доноры/Иное',
  `closed_without_repair_other` varchar(255) DEFAULT NULL,
  `reopened_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `reported_by_user_id` (`reported_by_user_id`),
  KEY `nomenclature_id` (`nomenclature_id`),
  KEY `status_id` (`status_id`),
  KEY `reported_at` (`reported_at`),
  KEY `completed_at` (`completed_at`),
  KEY `parent_breakdown_id` (`parent_breakdown_id`),
  CONSTRAINT `breakdowns_user_fk` FOREIGN KEY (`reported_by_user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `breakdowns_nomenclature_fk` FOREIGN KEY (`nomenclature_id`) REFERENCES `nomenclature` (`id`) ON DELETE SET NULL,
  CONSTRAINT `breakdowns_status_fk` FOREIGN KEY (`status_id`) REFERENCES `breakdown_statuses` (`id`),
  CONSTRAINT `breakdowns_parent_fk` FOREIGN KEY (`parent_breakdown_id`) REFERENCES `breakdowns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Фото к поломкам
CREATE TABLE IF NOT EXISTS `breakdown_photos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `breakdown_id` int unsigned NOT NULL,
  `filename` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `breakdown_id` (`breakdown_id`),
  CONSTRAINT `breakdown_photos_fk` FOREIGN KEY (`breakdown_id`) REFERENCES `breakdowns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
