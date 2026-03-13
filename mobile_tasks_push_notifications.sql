-- ERP mobile task + push notification tables
-- Includes done_flag support for mobile notification panel actions

CREATE TABLE IF NOT EXISTS `mobile_tasks` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_user_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(160) NOT NULL,
  `note` TEXT NULL,
  `scheduled_at` DATETIME NOT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `is_done` TINYINT(1) NOT NULL DEFAULT 0,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_mobile_tasks_admin_user_id` (`admin_user_id`),
  KEY `idx_mobile_tasks_scheduled_at` (`scheduled_at`),
  KEY `idx_mobile_tasks_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mobile_push_notifications` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_user_id` INT UNSIGNED NULL,
  `external_user_id` VARCHAR(190) NULL,
  `type` VARCHAR(40) NOT NULL,
  `reference_table` VARCHAR(100) NULL,
  `reference_id` BIGINT UNSIGNED NULL,
  `title` VARCHAR(160) NOT NULL,
  `message` TEXT NOT NULL,
  `payload_json` LONGTEXT NULL,
  `scheduled_at` DATETIME NULL,
  `sent_at` DATETIME NULL,
  `onesignal_message_id` VARCHAR(80) NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `done_flag` TINYINT(1) NOT NULL DEFAULT 0,
  `done_at` DATETIME NULL,
  `error_message` TEXT NULL,
  `response_json` LONGTEXT NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_mobile_push_notifications_admin_user_id` (`admin_user_id`),
  KEY `idx_mobile_push_notifications_scheduled_at` (`scheduled_at`),
  KEY `idx_mobile_push_notifications_status` (`status`),
  KEY `idx_mobile_push_notifications_reference` (`reference_table`, `reference_id`),
  KEY `idx_mobile_push_done_status` (`done_flag`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Run this if the table already exists and only the new fields are missing:
ALTER TABLE `mobile_push_notifications`
  ADD COLUMN IF NOT EXISTS `done_flag` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`,
  ADD COLUMN IF NOT EXISTS `done_at` DATETIME NULL AFTER `done_flag`;
