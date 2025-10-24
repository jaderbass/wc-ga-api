-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Server-Version:               8.4.3 - MySQL Community Server - GPL
-- Server-Betriebssystem:        Win64
-- HeidiSQL Version:             12.8.0.6908
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Exportiere Datenbank-Struktur für geoalp_woo_api
DROP DATABASE IF EXISTS `geoalp_woo_api`;
CREATE DATABASE IF NOT EXISTS `geoalp_woo_api` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `geoalp_woo_api`;

-- Exportiere Struktur von Tabelle geoalp_woo_api.cache
DROP TABLE IF EXISTS `cache`;
CREATE TABLE IF NOT EXISTS `cache` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exportiere Daten aus Tabelle geoalp_woo_api.cache: ~3 rows (ungefähr)
INSERT INTO `cache` (`key`, `value`, `expiration`) VALUES
	('geoalpin_woocommerce_api_cache_livewire-rate-limiter:a17961fa74e9275d529f489537f179c05d50c2f3', 'i:1;', 1761238804),
	('geoalpin_woocommerce_api_cache_livewire-rate-limiter:a17961fa74e9275d529f489537f179c05d50c2f3:timer', 'i:1761238804;', 1761238804),
	('geoalpin_woocommerce_api_cache_spatie.permission.cache', 'a:3:{s:5:"alias";a:0:{}s:11:"permissions";a:0:{}s:5:"roles";a:0:{}}', 1761325145);

-- Exportiere Struktur von Tabelle geoalp_woo_api.cache_locks
DROP TABLE IF EXISTS `cache_locks`;
CREATE TABLE IF NOT EXISTS `cache_locks` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exportiere Daten aus Tabelle geoalp_woo_api.cache_locks: ~0 rows (ungefähr)

-- Exportiere Struktur von Tabelle geoalp_woo_api.exports
DROP TABLE IF EXISTS `exports`;
CREATE TABLE IF NOT EXISTS `exports` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `completed_at` timestamp NULL DEFAULT NULL,
  `file_disk` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `exporter` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `processed_rows` int unsigned NOT NULL DEFAULT '0',
  `total_rows` int unsigned NOT NULL,
  `successful_rows` int unsigned NOT NULL DEFAULT '0',
  `user_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `exports_user_id_foreign` (`user_id`),
  CONSTRAINT `exports_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exportiere Daten aus Tabelle geoalp_woo_api.exports: ~0 rows (ungefähr)

-- Exportiere Struktur von Tabelle geoalp_woo_api.failed_import_rows
DROP TABLE IF EXISTS `failed_import_rows`;
CREATE TABLE IF NOT EXISTS `failed_import_rows` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `data` json NOT NULL,
  `import_id` bigint unsigned NOT NULL,
  `validation_error` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `failed_import_rows_import_id_foreign` (`import_id`),
  CONSTRAINT `failed_import_rows_import_id_foreign` FOREIGN KEY (`import_id`) REFERENCES `imports` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exportiere Daten aus Tabelle geoalp_woo_api.failed_import_rows: ~0 rows (ungefähr)

-- Exportiere Struktur von Tabelle geoalp_woo_api.failed_jobs
DROP TABLE IF EXISTS `failed_jobs`;
CREATE TABLE IF NOT EXISTS `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exportiere Daten aus Tabelle geoalp_woo_api.failed_jobs: ~0 rows (ungefähr)

-- Exportiere Struktur von Tabelle geoalp_woo_api.imports
DROP TABLE IF EXISTS `imports`;
CREATE TABLE IF NOT EXISTS `imports` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `completed_at` timestamp NULL DEFAULT NULL,
  `file_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `importer` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `processed_rows` int unsigned NOT NULL DEFAULT '0',
  `total_rows` int unsigned NOT NULL,
  `successful_rows` int unsigned NOT NULL DEFAULT '0',
  `user_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `imports_user_id_foreign` (`user_id`),
  CONSTRAINT `imports_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exportiere Daten aus Tabelle geoalp_woo_api.imports: ~0 rows (ungefähr)

-- Exportiere Struktur von Tabelle geoalp_woo_api.jobs
DROP TABLE IF EXISTS `jobs`;
CREATE TABLE IF NOT EXISTS `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exportiere Daten aus Tabelle geoalp_woo_api.jobs: ~0 rows (ungefähr)

-- Exportiere Struktur von Tabelle geoalp_woo_api.job_batches
DROP TABLE IF EXISTS `job_batches`;
CREATE TABLE IF NOT EXISTS `job_batches` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exportiere Daten aus Tabelle geoalp_woo_api.job_batches: ~0 rows (ungefähr)

-- Exportiere Struktur von Tabelle geoalp_woo_api.manufacturers
DROP TABLE IF EXISTS `manufacturers`;
CREATE TABLE IF NOT EXISTS `manufacturers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `manufacturer` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `manufacturercountry` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `website` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `api_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `api_user` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `api_password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `api_token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `api_password_changed_at` timestamp NULL DEFAULT NULL,
  `import_type` enum('csv','xml','api') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'csv',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `manufacturers_manufacturer_unique` (`manufacturer`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exportiere Daten aus Tabelle geoalp_woo_api.manufacturers: ~6 rows (ungefähr)
INSERT INTO `manufacturers` (`id`, `manufacturer`, `manufacturercountry`, `website`, `api_url`, `api_user`, `api_password`, `api_token`, `api_password_changed_at`, `import_type`, `notes`, `created_at`, `updated_at`) VALUES
	(1, 'Aliens', 'FR', NULL, NULL, NULL, NULL, NULL, NULL, 'csv', NULL, '2025-08-16 09:39:35', '2025-08-16 09:39:35'),
	(2, 'Kask', 'FR', NULL, NULL, NULL, NULL, NULL, NULL, 'csv', NULL, '2025-08-16 09:39:35', '2025-08-16 09:39:35'),
	(3, 'Petzl', 'IT', NULL, NULL, NULL, NULL, NULL, NULL, 'csv', NULL, '2025-08-16 09:39:35', '2025-08-16 09:39:35'),
	(4, 'Kratos Safety', 'DE', NULL, NULL, NULL, NULL, NULL, NULL, 'xml', NULL, '2025-08-16 09:39:35', '2025-08-16 09:39:35'),
	(5, 'Singing Rock', 'DE', NULL, 'https://b2b.singingrock.com/feed/products/', 'Marketing', 'eyJpdiI6Im9RL0hnd2F0QUxiZXhrdG1JMHRka3c9PSIsInZhbHVlIjoib0oxOFRwLzJBWTVEZWhXcnBpQkVkMElNRFBwTU1BazkxL2ZXc01Tb3RWcz0iLCJtYWMiOiJlYjRmNjkyMTZlZWM4ZDRhZjgxZGM3YWM2MDA3ODJhMGU5MGYyZTMwNmEzZDk4MmFiNzdlOGFkM2I0MGY4ODJlIiwidGFnIjoiIn0=', NULL, NULL, 'api', NULL, '2025-08-16 09:39:35', '2025-08-16 09:39:35'),
	(6, 'Edelrid', 'DE', NULL, NULL, NULL, NULL, NULL, NULL, 'csv', NULL, '2025-08-19 13:20:23', '2025-08-19 13:20:23');

-- Exportiere Struktur von Tabelle geoalp_woo_api.manufacturer_audits
DROP TABLE IF EXISTS `manufacturer_audits`;
CREATE TABLE IF NOT EXISTS `manufacturer_audits` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `manufacturer_id` bigint unsigned NOT NULL,
  `field` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `old_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `new_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `changed_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `manufacturer_audits_manufacturer_id_foreign` (`manufacturer_id`),
  KEY `manufacturer_audits_changed_by_foreign` (`changed_by`),
  CONSTRAINT `manufacturer_audits_changed_by_foreign` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `manufacturer_audits_manufacturer_id_foreign` FOREIGN KEY (`manufacturer_id`) REFERENCES `manufacturers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exportiere Daten aus Tabelle geoalp_woo_api.manufacturer_audits: ~0 rows (ungefähr)

-- Exportiere Struktur von Tabelle geoalp_woo_api.migrations
DROP TABLE IF EXISTS `migrations`;
CREATE TABLE IF NOT EXISTS `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exportiere Daten aus Tabelle geoalp_woo_api.migrations: ~22 rows (ungefähr)
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
	(1, '0001_01_01_000000_create_users_table', 1),
	(2, '0001_01_01_000001_create_cache_table', 1),
	(3, '0001_01_01_000002_create_jobs_table', 1),
	(4, '2025_05_05_085930_create_notifications_table', 1),
	(5, '2025_05_05_085959_create_imports_table', 1),
	(6, '2025_05_05_090000_create_exports_table', 1),
	(7, '2025_05_05_090001_create_failed_import_rows_table', 1),
	(8, '2025_05_12_065907_create_manufacturers_table', 1),
	(9, '2025_05_12_065908_create_products_table', 1),
	(10, '2025_07_28_200825_create_product_variations_table', 1),
	(11, '2025_07_28_200908_create_product_attributes_table', 1),
	(12, '2025_07_28_200951_create_product_attribute_values_table', 1),
	(13, '2025_07_28_201032_create_product_images_table', 1),
	(14, '2025_07_30_180350_create_manufacturer_audits_table', 1),
	(15, '2025_07_31_095630_create_permission_tables', 1),
	(16, '2025_08_16_115714_create_product_variation_attribute_value_table', 1),
	(17, '2025_08_17_132505_add_ean_and_weight_to_product_variations_table', 2),
	(18, '2025_08_25_000001_add_missing_columns_to_products_table', 3),
	(19, '2025_08_25_000002_remove_unused_columns_from_products_table', 3),
	(20, 'create_product_meta_table', 4),
	(21, 'update_product_variations_table_add_woo_columns', 4),
	(22, 'update_products_table_add_woo_columns', 4),
	(23, '2025_09_12_000001_create_shops_table', 5),
	(24, '2025_09_12_000002_add_woo_fields_to_products', 5),
	(25, '2025_09_12_000003_add_woo_fields_to_product_variations', 5),
	(26, '2025_09_12_000004_add_sync_fields_indexes_to_product_variations', 5),
	(27, '2025_09_23_000001_make_product_sku_nullable_for_variable_parents', 6),
	(28, '2025_09_28_000000_create_woo_links_table', 7);

-- Exportiere Struktur von Tabelle geoalp_woo_api.model_has_permissions
DROP TABLE IF EXISTS `model_has_permissions`;
CREATE TABLE IF NOT EXISTS `model_has_permissions` (
  `permission_id` bigint unsigned NOT NULL,
  `model_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exportiere Daten aus Tabelle geoalp_woo_api.model_has_permissions: ~0 rows (ungefähr)

-- Exportiere Struktur von Tabelle geoalp_woo_api.model_has_roles
DROP TABLE IF EXISTS `model_has_roles`;
CREATE TABLE IF NOT EXISTS `model_has_roles` (
  `role_id` bigint unsigned NOT NULL,
  `model_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exportiere Daten aus Tabelle geoalp_woo_api.model_has_roles: ~2 rows (ungefähr)
INSERT INTO `model_has_roles` (`role_id`, `model_type`, `model_id`) VALUES
	(1, 'App\\Models\\User', 1),
	(1, 'App\\Models\\User', 2);

-- Exportiere Struktur von Tabelle geoalp_woo_api.notifications
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_id` bigint unsigned NOT NULL,
  `data` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exportiere Daten aus Tabelle geoalp_woo_api.notifications: ~0 rows (ungefähr)

-- Exportiere Struktur von Tabelle geoalp_woo_api.password_reset_tokens
DROP TABLE IF EXISTS `password_reset_tokens`;
CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exportiere Daten aus Tabelle geoalp_woo_api.password_reset_tokens: ~0 rows (ungefähr)

-- Exportiere Struktur von Tabelle geoalp_woo_api.permissions
DROP TABLE IF EXISTS `permissions`;
CREATE TABLE IF NOT EXISTS `permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exportiere Daten aus Tabelle geoalp_woo_api.permissions: ~0 rows (ungefähr)

-- Exportiere Struktur von Tabelle geoalp_woo_api.products
DROP TABLE IF EXISTS `products`;
CREATE TABLE IF NOT EXISTS `products` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `manufacturer_id` bigint unsigned DEFAULT NULL,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `external_url` varchar(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `declaration_of_compliance` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `manual_url` varchar(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `woo_product_id` bigint DEFAULT NULL,
  `stock_status` enum('in_stock','out_of_stock','on_backorder') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'in_stock',
  `product_type` enum('simple','variable') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'simple',
  `sku` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ean` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `short_description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `size` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `certification` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `author_firstname` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `author_lastname` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `author_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `author_mail` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `width` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `length` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `height` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unit` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `box_width` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `box_length` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `box_height` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `weight` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `last_synced_at` timestamp NULL DEFAULT NULL,
  `last_sync_status` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_sync_error` text COLLATE utf8mb4_unicode_ci,
  `payload_hash` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mpn` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `regular_price_cents` bigint NOT NULL DEFAULT '0',
  `sale_price_cents` bigint NOT NULL DEFAULT '0',
  `tax_class` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_status` enum('taxable','shipping','none') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'taxable',
  `weight_g` int NOT NULL DEFAULT '0',
  `length_mm` int NOT NULL DEFAULT '0',
  `width_mm` int NOT NULL DEFAULT '0',
  `height_mm` int NOT NULL DEFAULT '0',
  `stock_quantity` int NOT NULL DEFAULT '0',
  `manage_stock` tinyint(1) NOT NULL DEFAULT '0',
  `backorders` enum('no','notify','yes') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'no',
  `shipping_class` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hs_code` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country_of_origin` varchar(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `products_slug_unique` (`slug`),
  UNIQUE KEY `products_sku_unique` (`sku`),
  KEY `products_manufacturer_id_foreign` (`manufacturer_id`),
  KEY `products_size_idx` (`size`),
  KEY `products_author_name_idx` (`author_name`),
  KEY `products_author_mail_idx` (`author_mail`),
  KEY `products_mpn_index` (`mpn`),
  KEY `products_shipping_class_index` (`shipping_class`),
  KEY `products_hs_code_index` (`hs_code`),
  KEY `products_country_of_origin_index` (`country_of_origin`),
  KEY `products_woo_product_id_index` (`woo_product_id`),
  KEY `products_last_sync_status_index` (`last_sync_status`),
  CONSTRAINT `products_manufacturer_id_foreign` FOREIGN KEY (`manufacturer_id`) REFERENCES `manufacturers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exportiere Daten aus Tabelle geoalp_woo_api.products: ~9 rows (ungefähr)
INSERT INTO `products` (`id`, `manufacturer_id`, `slug`, `external_url`, `declaration_of_compliance`, `manual_url`, `woo_product_id`, `stock_status`, `product_type`, `sku`, `ean`, `product_number`, `product_name`, `description`, `short_description`, `size`, `certification`, `author_firstname`, `author_lastname`, `author_name`, `author_mail`, `width`, `length`, `height`, `unit`, `box_width`, `box_length`, `box_height`, `weight`, `created_at`, `updated_at`, `last_synced_at`, `last_sync_status`, `last_sync_error`, `payload_hash`, `mpn`, `regular_price_cents`, `sale_price_cents`, `tax_class`, `tax_status`, `weight_g`, `length_mm`, `width_mm`, `height_mm`, `stock_quantity`, `manage_stock`, `backorders`, `shipping_class`, `hs_code`, `country_of_origin`) VALUES
	(1, 6, 'bud', NULL, NULL, NULL, NULL, 'in_stock', 'variable', NULL, '4021573790743', '717620003600', 'Bud', 'Der Klassiker im neuen Design. Hier sind keine Erklärungen nötig. Universell einsetzbar zum Abseilen und Sichern.', 'Geeignet für Einfachseile von 7,8 bis 12,0 mm Durchmesser', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-19 05:25:48', '2025-09-19 05:25:48', NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, 'taxable', 0, 0, 0, 0, 0, 0, 'no', NULL, NULL, NULL),
	(2, 6, 'hannibal', NULL, NULL, NULL, NULL, 'in_stock', 'variable', NULL, '4052285295295', '720220001380', 'Hannibal', 'Die spezielle Geometrie des HANNIBAL ermöglicht verschiedene Bremsstufen. Dadurch lässt sich die Abseilgeschwindigkeit exakt dosieren.', 'Geeignet für Einfachseile von 8,5 bis 12,0 mm DurchmesserMit integriertem Gummiring zur Fixierung des Karabiners. Gefährliche Querbelastungen des Karabiners werden so vermiedenMehrere Bremsstufen durch HörnerHohe Abriebfestigkeit durch hochwertige Aluminiumlegierung', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-19 05:25:48', '2025-09-19 05:25:48', NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, 'taxable', 0, 0, 0, 0, 0, 0, 'no', NULL, NULL, NULL),
	(3, 6, 'pinch', NULL, NULL, NULL, NULL, 'in_stock', 'variable', NULL, '4028545204710', '738380008150', 'Pinch', 'PINCH – ein neues Sicherungserlebnis mit dem PINCH, einem neuartigen, vielseitigen Sicherungsgerät mit Blockierunterstützung für den Einsatz beim Sportklettern, in Mehrseillängen und in der Seilzugangstechnik. Als erstes Gerät am Markt kann das PINCH direkt in den Zentralring des Klettergurts eingehängt werden. Durch die körpernahe und tiefe Position muss das PINCH beim Seilausgeben nicht fixiert und das Bremsseil kann stets mit allen Fingern umschlossen werden. Zudem sorgt das kompakte Sicherungssystem für eine erhöhte Spannweite, mit der die sichernde Person 20 – 30 cm mehr Seil auf einmal ausgeben kann. Der lineare Seilverlauf durch die frontalen Bremsrillen aus Stahl reduziert Seilkrangel beim Ablassen und Abseilen. Dabei kann die Geschwindigkeit so zusätzlich zum Ablasshebel über den Druck der Bremshand gesteuert werden, was vor allem bei der Verwendung von dünnen, weichen Seilen zu einer erhöhten Bremsseilkontrolle beiträgt. Die Anti-Panik Funktion sorgt nicht nur bei Einsteiger*innen für ein Plus an Sicherheit. Eine Besonderheit ist die integrierte zweite Bremsstufe, welche bei der Sicherung von besonders leichten Personen oder in Systemen mit hoher Seilreibung ein kontrolliertes Ablassen durch Weiterziehen des Ablasshebels ermöglicht. Anwender*innen in der Seilzugangstechnik und Erfahrene können die Anti-Panik Funktion mittels einer mitgelieferten Schraube dauerhaft ausschalten. Ein weiteres Plus: Beim Einsatz in Mehrseillängen lässt sich das PINCH als einziges Gerät am Markt in vier verschiedenen Richtungen in 90°-Schritten am Standplatz einhängen. Dadurch kann der Ablasshebel immer in eine Position gebracht werden, in der er sich frei bedienen lässt.', 'Sicherungsgerät mit Blockierunterstützung für einen vielseitigen Einsatz beim Sportklettern, in Mehrseillängen und in der SeilzugangstechnikKörpernahe Position durch direkte Gurtanbindung erhöht die Bremsseilkontrolle und verbessert die BedienbarkeitBei direkter Gurtanbindung entfällt die Gefahr einer Querbelastung des SicherungskarabinersAusschaltbare Anti-Panik Funktion für mehr Sicherheit, indem das Gerät automatisch blockiert, wenn der Ablasshebel zu weit nach hinten gezogen wirdZweite Bremsstufe, wenn die Anti-Panik Funktion aufgrund zu geringer Last und/oder zu hoher Seilreibung zu häufig aktiviertFrontale Bremsrillen aus Stahl ermöglichen einen linearen Seilverlauf und sorgen für weniger Seilkrangel, erhöhte Langlebigkeit und verhindern unschöne Verfärbungen, die bei Seilreibung auf Aluminium auftretenEinhängen am Standplatz in vier verschiedenen Richtungen in 90°-SchrittenGleichwertige Bedienung für Rechts- und LinkshänderGeeignet fu?r Dynamikseile von 8,5 bis 10,5 mm DurchmesserGeeignet für Statikseile von 10,0 bis 10,5 mm Durchmesser (120kg max. Anwendergewicht)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-19 05:25:48', '2025-09-19 05:25:48', NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, 'taxable', 0, 0, 0, 0, 0, 0, 'no', NULL, NULL, NULL),
	(4, 6, 'safe-descent-98mm', NULL, NULL, NULL, NULL, 'in_stock', 'variable', NULL, '4028545174433', '882300150470', 'Safe Descent 9,8mm', 'Das SAFE DESCENT ist ein innovatives Abseil- und Rettungsgerät mit Hubfunktion – ideal für Höhenarbeiten und Notfälle von Adventure Parks bis Windkraftanlagen.Auf Anfrage ist das SAFE DESCENT in den hier aufgeführten Standardlängen kurzfristig verfügbar. Sonderlängen sind auf Wunsch möglich.', 'Vorkonfektioniert mit 9,8 mm Kernmantelseil mit zertifizierten Endverbindungen und farblicher Kennzeichnung an den SeilendenAutomatische Rücklaufsperre (Ratschen-System) zum sicheren AblassenZugelassen für doppelte PersonenlastRutschkupplung gegen FehlbedienungOptional mit komfortablem Rucksack zur Aufbewahrung und zum Transport (CANYONEER BAG 45)Zwei eingenähte DSG Stahl-Karabiner mit Handballensicherung in den Seilenden', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-19 05:25:48', '2025-09-19 05:25:48', NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, 'taxable', 0, 0, 0, 0, 0, 0, 'no', NULL, NULL, NULL),
	(5, 6, 'megawatt', NULL, NULL, NULL, NULL, 'in_stock', 'variable', NULL, '4028545120072', '883320000170', 'Megawatt', 'Das MEGAWATT ist ein universelles Abseilgerät für Industriekletter- und Rettungseinsätze bis zu einer Nutzlast von 230 kg. Der Ablasshebel mit seiner intelligenten Übersetzungsmechanik ist mit minimalem Kraftaufwand zu bedienen und sorgt für einen großen Bedienbereich sowie eine exakte Geschwindigkeitsdosierung.Der kurze Hebel kehrt stets automatisch in die Parkposition zurück, was das Gerät extrem kompakt macht – ein Hängenbleiben wird so effektiv verhindert. Das ergonomische Hebel-Design mit gummierten Grip-Inserts ermöglicht – aktiv wie passiv – eine intuitive Bedienung mit der linken oder der rechten Hand.Der Vier-Wege-Sicherheitsverschluss ermöglicht ein bequemes Einlegen des Seiles, ohne das Gerät vom Karabiner zu trennen. An stark beanspruchten Stellen sorgen robuste Stahleinsätze für eine lange Lebensdauer.Das Risiko einer unkontrollierten Abseilfahrt wird durch die Anti-Panikfunktion reduziert. Diese kann auch bewusst ausgelöst werden, um die Bedienrichtung des Hebels umzudrehen. Dadurch wird ein optimales Handling für verschiedenste Anwendungen und in jeder Position ermöglicht. Diese ausgeklügelten Features machen das 495 g leichte MEGAWATT zum vielseitigsten Abseilgerät auf dem Markt.', 'Integrierter RFID-Chip für eine vereinfachte EinsatzdokumentationStahleinsätze an den abriebgefährdeten Stellen erhöhen die LebensdauerExakte Bedienung des Hebels bei minimalem KraftaufwandKurzer Hebel und kompaktes Design verhindern ein HängenbleibenAutomatische Parkposition des BedienhebelsErgonomischer Hebel mit gummierten Grip-Inserts für intuitives Handling mit der linken oder rechten Hand bei aktiver und passiver AnwendungVier-Wege-Sicherheitsverschluss ermöglicht Einlegen des Seiles, ohne das Gerät vom Karabiner zu trennenGroßes Karabiner-Loch ermöglicht 360°-Drehung des KarabinersAnti-Panikfunktion reduziert Risiko unkontrollierter Abseilfahrten und schafft zusätzliche BedienmöglichkeitenIm Hebel mitgelieferte Verriegelungsschraube für den Einbau als geschlossenes SystemAbseilen von schweren Lasten bis 230 kgGeeignet für Seildurchmesser von 8,9-11,8 mmErfüllt die Standards EN 12841-C, EN 341-2A, EN 15151-1/8 und ANSI/ASSE Z359.4', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-19 05:25:48', '2025-09-19 05:25:48', NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, 'taxable', 0, 0, 0, 0, 0, 0, 'no', NULL, NULL, NULL),
	(6, 6, 'rescue-8', NULL, NULL, NULL, NULL, 'in_stock', 'variable', NULL, '4021573881182', '889200000170', 'Rescue 8', 'Großer Abseilachter aus Aluminium mit Ohren zum Sichern, Abseilen und Positionieren.', 'Für Seildurchmesser von 7,8 bis 12,0 mm', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-19 05:25:48', '2025-09-19 05:25:48', NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, 'taxable', 0, 0, 0, 0, 0, 0, 'no', NULL, NULL, NULL),
	(7, 3, 'avao-bod-european-version', NULL, NULL, NULL, 13008, 'in_stock', 'variable', NULL, '3342540822399', 'C071AA00', 'AVAO® BOD European version', 'HARNESS AVAO BOD 0 geändert', 'HARNESS AVAO BOD 0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, NULL, '2025-09-19 05:26:12', '2025-09-23 06:59:11', '2025-09-23 05:16:22', 'error', 'Woo request failed: Client error: `POST https://testshop.geoalpin.eu/wp-json/wc/v3/products` resulted in a `400 Bad Request` response:\n{"code":"woocommerce_rest_product_not_created","message":"Das Produkt mit der SKU (C071AA00), das du einf\\u00fcgen m\\u00 (truncated...)\n | body: {"code":"woocommerce_rest_product_not_created","message":"Das Produkt mit der SKU (C071AA00), das du einf\\u00fcgen m\\u00f6chtest, ist bereits in der Nachschlagetabelle vorhanden","data":{"status":400}}', 'ec6eef7b7b73cf03776f77e9b0f5f780324cf093dc9d667642d43e5851b23a20', NULL, 0, 0, NULL, 'taxable', 0, 0, 0, 0, 0, 0, 'no', NULL, NULL, NULL),
	(8, 3, 'avao-bod-fast-european-version', NULL, NULL, NULL, NULL, 'in_stock', 'variable', NULL, '3342540822429', 'C071BA00', 'AVAO® BOD FAST European Version', 'HARNESS AVAO BOD FAST 0', 'HARNESS AVAO BOD FAST 0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-19 05:26:12', '2025-09-19 05:26:12', NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, 'taxable', 0, 0, 0, 0, 0, 0, 'no', NULL, NULL, NULL),
	(9, 1, 'testprodukt-iad-2', NULL, NULL, NULL, 13000, 'in_stock', 'simple', 'SKU002001', NULL, 'T002001', 'TestProdukt IAD 2', 'TestProdukt IAD', 'TestProdukt IAD', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, NULL, '2025-09-19 05:27:34', '2025-09-19 06:20:28', '2025-09-19 06:20:28', 'synced', NULL, '50608dc60f5d63ef945330357a4b0401fea74c633afa082a3e1790cd07556b5e', NULL, 0, 0, NULL, 'taxable', 0, 0, 0, 0, 0, 0, 'no', NULL, NULL, NULL);

-- Exportiere Struktur von Tabelle geoalp_woo_api.product_attributes
DROP TABLE IF EXISTS `product_attributes`;
CREATE TABLE IF NOT EXISTS `product_attributes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `woo_attribute_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_attributes_slug_unique` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exportiere Daten aus Tabelle geoalp_woo_api.product_attributes: ~4 rows (ungefähr)
INSERT INTO `product_attributes` (`id`, `name`, `slug`, `woo_attribute_id`, `created_at`, `updated_at`) VALUES
	(1, 'Farbe', 'farbe', NULL, '2025-09-19 05:25:48', '2025-09-19 05:25:48'),
	(2, 'Größe', 'grosse', NULL, '2025-09-19 05:25:48', '2025-09-19 05:25:48'),
	(3, 'color', 'color', NULL, '2025-09-19 05:26:12', '2025-09-19 05:26:12'),
	(4, 'size', 'size', NULL, '2025-09-19 05:26:12', '2025-09-19 05:26:12');

-- Exportiere Struktur von Tabelle geoalp_woo_api.product_attribute_values
DROP TABLE IF EXISTS `product_attribute_values`;
CREATE TABLE IF NOT EXISTS `product_attribute_values` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `attribute_id` bigint unsigned NOT NULL,
  `value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `woo_term_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_attribute_values_attribute_id_slug_unique` (`attribute_id`,`slug`),
  CONSTRAINT `product_attribute_values_attribute_id_foreign` FOREIGN KEY (`attribute_id`) REFERENCES `product_attributes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exportiere Daten aus Tabelle geoalp_woo_api.product_attribute_values: ~20 rows (ungefähr)
INSERT INTO `product_attribute_values` (`id`, `attribute_id`, `value`, `slug`, `woo_term_id`, `created_at`, `updated_at`) VALUES
	(1, 1, 'royal', 'royal', NULL, '2025-09-19 05:25:48', '2025-09-19 05:25:48'),
	(2, 1, 'slate', 'slate', NULL, '2025-09-19 05:25:48', '2025-09-19 05:25:48'),
	(3, 1, 'oasis', 'oasis', NULL, '2025-09-19 05:25:48', '2025-09-19 05:25:48'),
	(4, 1, 'anthracite-oasis', 'anthracite-oasis', NULL, '2025-09-19 05:25:48', '2025-09-19 05:25:48'),
	(5, 1, 'snow', 'snow', NULL, '2025-09-19 05:25:48', '2025-09-19 05:25:48'),
	(6, 2, '15 M', '15-m', NULL, '2025-09-19 05:25:48', '2025-09-19 05:25:48'),
	(7, 2, '20 M', '20-m', NULL, '2025-09-19 05:25:48', '2025-09-19 05:25:48'),
	(8, 2, '25 M', '25-m', NULL, '2025-09-19 05:25:48', '2025-09-19 05:25:48'),
	(9, 2, '30 M', '30-m', NULL, '2025-09-19 05:25:48', '2025-09-19 05:25:48'),
	(10, 2, '40 M', '40-m', NULL, '2025-09-19 05:25:48', '2025-09-19 05:25:48'),
	(11, 2, '50 M', '50-m', NULL, '2025-09-19 05:25:48', '2025-09-19 05:25:48'),
	(12, 2, '100 M', '100-m', NULL, '2025-09-19 05:25:48', '2025-09-19 05:25:48'),
	(13, 2, '160 M', '160-m', NULL, '2025-09-19 05:25:48', '2025-09-19 05:25:48'),
	(14, 1, 'night', 'night', NULL, '2025-09-19 05:25:48', '2025-09-19 05:25:48'),
	(15, 1, 'blue', 'blue', NULL, '2025-09-19 05:25:48', '2025-09-19 05:25:48'),
	(16, 3, 'black/yellow', 'blackyellow', NULL, '2025-09-19 05:26:12', '2025-09-19 05:26:12'),
	(17, 4, '0', '0', NULL, '2025-09-19 05:26:12', '2025-09-19 05:26:12'),
	(18, 4, '1', '1', NULL, '2025-09-19 05:26:12', '2025-09-19 05:26:12'),
	(19, 4, '2', '2', NULL, '2025-09-19 05:26:12', '2025-09-19 05:26:12'),
	(20, 3, 'Black', 'black', NULL, '2025-09-19 05:26:12', '2025-09-19 05:26:12');

-- Exportiere Struktur von Tabelle geoalp_woo_api.product_images
DROP TABLE IF EXISTS `product_images`;
CREATE TABLE IF NOT EXISTS `product_images` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint unsigned NOT NULL,
  `url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_main` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `product_images_product_id_foreign` (`product_id`),
  CONSTRAINT `product_images_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exportiere Daten aus Tabelle geoalp_woo_api.product_images: ~0 rows (ungefähr)

-- Exportiere Struktur von Tabelle geoalp_woo_api.product_meta
DROP TABLE IF EXISTS `product_meta`;
CREATE TABLE IF NOT EXISTS `product_meta` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint unsigned NOT NULL,
  `variation_id` bigint unsigned DEFAULT NULL,
  `scope` enum('product','variation') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'product',
  `key` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_meta_unique_scope_key` (`product_id`,`variation_id`,`scope`,`key`),
  KEY `product_meta_product_id_index` (`product_id`),
  KEY `product_meta_variation_id_index` (`variation_id`),
  KEY `product_meta_scope_index` (`scope`),
  KEY `product_meta_key_index` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exportiere Daten aus Tabelle geoalp_woo_api.product_meta: ~0 rows (ungefähr)

-- Exportiere Struktur von Tabelle geoalp_woo_api.product_variations
DROP TABLE IF EXISTS `product_variations`;
CREATE TABLE IF NOT EXISTS `product_variations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint unsigned NOT NULL,
  `woo_variation_id` bigint DEFAULT NULL,
  `sku` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ean` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `regular_price` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sale_price` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stock_quantity` int DEFAULT NULL,
  `stock_status` enum('in_stock','out_of_stock','on_backorder') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'in_stock',
  `weight` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attributes` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `last_synced_at` timestamp NULL DEFAULT NULL,
  `last_sync_status` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_sync_error` text COLLATE utf8mb4_unicode_ci,
  `payload_hash` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `regular_price_cents` bigint NOT NULL DEFAULT '0',
  `sale_price_cents` bigint NOT NULL DEFAULT '0',
  `weight_g` int NOT NULL DEFAULT '0',
  `length_mm` int NOT NULL DEFAULT '0',
  `width_mm` int NOT NULL DEFAULT '0',
  `height_mm` int NOT NULL DEFAULT '0',
  `manage_stock` tinyint(1) NOT NULL DEFAULT '0',
  `backorders` enum('no','notify','yes') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'no',
  `attributes_json` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_variations_sku_unique` (`sku`),
  KEY `product_variations_product_id_foreign` (`product_id`),
  KEY `product_variations_woo_variation_id_index` (`woo_variation_id`),
  KEY `product_variations_last_sync_status_index` (`last_sync_status`),
  CONSTRAINT `product_variations_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exportiere Daten aus Tabelle geoalp_woo_api.product_variations: ~24 rows (ungefähr)
INSERT INTO `product_variations` (`id`, `product_id`, `woo_variation_id`, `sku`, `ean`, `regular_price`, `sale_price`, `stock_quantity`, `stock_status`, `weight`, `attributes`, `created_at`, `updated_at`, `last_synced_at`, `last_sync_status`, `last_sync_error`, `payload_hash`, `regular_price_cents`, `sale_price_cents`, `weight_g`, `length_mm`, `width_mm`, `height_mm`, `manage_stock`, `backorders`, `attributes_json`) VALUES
	(1, 1, NULL, '717620003600', NULL, NULL, NULL, NULL, 'in_stock', NULL, NULL, '2025-09-19 05:25:48', '2025-09-19 05:25:48', NULL, NULL, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 'no', NULL),
	(2, 1, NULL, '717620006630', NULL, NULL, NULL, NULL, 'in_stock', NULL, NULL, '2025-09-19 05:25:48', '2025-09-19 05:25:48', NULL, NULL, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 'no', NULL),
	(3, 2, NULL, '720220001380', NULL, NULL, NULL, NULL, 'in_stock', NULL, NULL, '2025-09-19 05:25:48', '2025-09-19 05:25:48', NULL, NULL, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 'no', NULL),
	(4, 3, NULL, '738380008150', NULL, NULL, NULL, NULL, 'in_stock', NULL, NULL, '2025-09-19 05:25:48', '2025-09-19 05:25:48', NULL, NULL, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 'no', NULL),
	(5, 4, NULL, '882300150470', NULL, NULL, NULL, NULL, 'in_stock', NULL, NULL, '2025-09-19 05:25:48', '2025-09-19 05:25:48', NULL, NULL, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 'no', NULL),
	(6, 4, NULL, '882300200470', NULL, NULL, NULL, NULL, 'in_stock', NULL, NULL, '2025-09-19 05:25:48', '2025-09-19 05:25:48', NULL, NULL, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 'no', NULL),
	(7, 4, NULL, '882300250470', NULL, NULL, NULL, NULL, 'in_stock', NULL, NULL, '2025-09-19 05:25:48', '2025-09-19 05:25:48', NULL, NULL, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 'no', NULL),
	(8, 4, NULL, '882300300470', NULL, NULL, NULL, NULL, 'in_stock', NULL, NULL, '2025-09-19 05:25:48', '2025-09-19 05:25:48', NULL, NULL, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 'no', NULL),
	(9, 4, NULL, '882300400470', NULL, NULL, NULL, NULL, 'in_stock', NULL, NULL, '2025-09-19 05:25:48', '2025-09-19 05:25:48', NULL, NULL, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 'no', NULL),
	(10, 4, NULL, '882300500470', NULL, NULL, NULL, NULL, 'in_stock', NULL, NULL, '2025-09-19 05:25:48', '2025-09-19 05:25:48', NULL, NULL, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 'no', NULL),
	(11, 4, NULL, '882301000470', NULL, NULL, NULL, NULL, 'in_stock', NULL, NULL, '2025-09-19 05:25:48', '2025-09-19 05:25:48', NULL, NULL, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 'no', NULL),
	(12, 4, NULL, '882301600470', NULL, NULL, NULL, NULL, 'in_stock', NULL, NULL, '2025-09-19 05:25:48', '2025-09-19 05:25:48', NULL, NULL, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 'no', NULL),
	(13, 5, NULL, '883320000170', NULL, NULL, NULL, NULL, 'in_stock', NULL, NULL, '2025-09-19 05:25:48', '2025-09-19 05:25:48', NULL, NULL, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 'no', NULL),
	(14, 6, NULL, '889200000170', NULL, NULL, NULL, NULL, 'in_stock', NULL, NULL, '2025-09-19 05:25:48', '2025-09-19 05:25:48', NULL, NULL, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 'no', NULL),
	(15, 6, NULL, '889200003000', NULL, NULL, NULL, NULL, 'in_stock', NULL, NULL, '2025-09-19 05:25:48', '2025-09-19 05:25:48', NULL, NULL, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 'no', NULL),
	(16, 7, NULL, 'C071AA00', NULL, NULL, NULL, NULL, 'in_stock', NULL, NULL, '2025-09-19 05:26:12', '2025-09-19 05:26:12', NULL, NULL, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 'no', NULL),
	(17, 7, NULL, 'C071AA01', NULL, NULL, NULL, NULL, 'in_stock', NULL, NULL, '2025-09-19 05:26:12', '2025-09-19 05:26:12', NULL, NULL, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 'no', NULL),
	(18, 7, NULL, 'C071AA02', NULL, NULL, NULL, NULL, 'in_stock', NULL, NULL, '2025-09-19 05:26:12', '2025-09-19 05:26:12', NULL, NULL, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 'no', NULL),
	(19, 8, NULL, 'C071BA00', NULL, NULL, NULL, NULL, 'in_stock', NULL, NULL, '2025-09-19 05:26:12', '2025-09-19 05:26:12', NULL, NULL, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 'no', NULL),
	(20, 8, NULL, 'C071BA01', NULL, NULL, NULL, NULL, 'in_stock', NULL, NULL, '2025-09-19 05:26:12', '2025-09-19 05:26:12', NULL, NULL, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 'no', NULL),
	(21, 8, NULL, 'C071BA02', NULL, NULL, NULL, NULL, 'in_stock', NULL, NULL, '2025-09-19 05:26:12', '2025-09-19 05:26:12', NULL, NULL, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 'no', NULL),
	(22, 8, NULL, 'C071BA03', NULL, NULL, NULL, NULL, 'in_stock', NULL, NULL, '2025-09-19 05:26:12', '2025-09-19 05:26:12', NULL, NULL, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 'no', NULL),
	(23, 8, NULL, 'C071BA04', NULL, NULL, NULL, NULL, 'in_stock', NULL, NULL, '2025-09-19 05:26:12', '2025-09-19 05:26:12', NULL, NULL, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 'no', NULL),
	(24, 8, NULL, 'C071BA05', NULL, NULL, NULL, NULL, 'in_stock', NULL, NULL, '2025-09-19 05:26:12', '2025-09-19 05:26:12', NULL, NULL, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 'no', NULL);

-- Exportiere Struktur von Tabelle geoalp_woo_api.product_variation_attribute_value
DROP TABLE IF EXISTS `product_variation_attribute_value`;
CREATE TABLE IF NOT EXISTS `product_variation_attribute_value` (
  `product_variation_id` bigint unsigned NOT NULL,
  `product_attribute_value_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`product_variation_id`,`product_attribute_value_id`),
  KEY `piv_var_attr_value_id_foreign` (`product_attribute_value_id`),
  CONSTRAINT `piv_var_attr_value_id_foreign` FOREIGN KEY (`product_attribute_value_id`) REFERENCES `product_attribute_values` (`id`) ON DELETE CASCADE,
  CONSTRAINT `piv_var_attr_variation_id_foreign` FOREIGN KEY (`product_variation_id`) REFERENCES `product_variations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exportiere Daten aus Tabelle geoalp_woo_api.product_variation_attribute_value: ~41 rows (ungefähr)
INSERT INTO `product_variation_attribute_value` (`product_variation_id`, `product_attribute_value_id`) VALUES
	(1, 1),
	(2, 2),
	(3, 3),
	(4, 4),
	(5, 5),
	(6, 5),
	(7, 5),
	(8, 5),
	(9, 5),
	(10, 5),
	(11, 5),
	(12, 5),
	(5, 6),
	(6, 7),
	(7, 8),
	(8, 9),
	(9, 10),
	(10, 11),
	(11, 12),
	(12, 13),
	(13, 14),
	(14, 14),
	(15, 15),
	(16, 16),
	(17, 16),
	(18, 16),
	(19, 16),
	(20, 16),
	(21, 16),
	(16, 17),
	(19, 17),
	(22, 17),
	(17, 18),
	(20, 18),
	(23, 18),
	(18, 19),
	(21, 19),
	(24, 19),
	(22, 20),
	(23, 20),
	(24, 20);

-- Exportiere Struktur von Tabelle geoalp_woo_api.roles
DROP TABLE IF EXISTS `roles`;
CREATE TABLE IF NOT EXISTS `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exportiere Daten aus Tabelle geoalp_woo_api.roles: ~3 rows (ungefähr)
INSERT INTO `roles` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES
	(1, 'Admin', 'web', '2025-08-16 09:39:35', '2025-08-16 09:39:35'),
	(2, 'Editor', 'web', '2025-08-16 09:39:35', '2025-08-16 09:39:35'),
	(3, 'User', 'web', '2025-08-16 09:39:35', '2025-08-16 09:39:35');

-- Exportiere Struktur von Tabelle geoalp_woo_api.role_has_permissions
DROP TABLE IF EXISTS `role_has_permissions`;
CREATE TABLE IF NOT EXISTS `role_has_permissions` (
  `permission_id` bigint unsigned NOT NULL,
  `role_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `role_has_permissions_role_id_foreign` (`role_id`),
  CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exportiere Daten aus Tabelle geoalp_woo_api.role_has_permissions: ~0 rows (ungefähr)

-- Exportiere Struktur von Tabelle geoalp_woo_api.sessions
DROP TABLE IF EXISTS `sessions`;
CREATE TABLE IF NOT EXISTS `sessions` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exportiere Daten aus Tabelle geoalp_woo_api.sessions: ~0 rows (ungefähr)

-- Exportiere Struktur von Tabelle geoalp_woo_api.shops
DROP TABLE IF EXISTS `shops`;
CREATE TABLE IF NOT EXISTS `shops` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `base_url` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `api_version` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'wc/v3',
  `consumer_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `consumer_secret` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `webhook_secret` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `rate_limit_json` json DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `shops_name_unique` (`name`),
  KEY `shops_created_by_foreign` (`created_by`),
  KEY `shops_base_url_api_version_index` (`base_url`,`api_version`),
  KEY `shops_is_default_index` (`is_default`),
  CONSTRAINT `shops_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exportiere Daten aus Tabelle geoalp_woo_api.shops: ~0 rows (ungefähr)
INSERT INTO `shops` (`id`, `name`, `base_url`, `api_version`, `consumer_key`, `consumer_secret`, `webhook_secret`, `is_default`, `rate_limit_json`, `created_by`, `created_at`, `updated_at`) VALUES
	(1, 'Staging', 'https://testshop.geoalpin.eu', 'wc/v3', 'ck_7020eea92a4302febbba2adb178ae5b3e6c0f86f', 'cs_94c902c3e561d522242a1ded28c37dfaf7c65028', ']-R2gP&pjI9i6lX0wka+;ew{rh3^f"va&x),.%x-KrTu431a!B', 1, '[]', NULL, '2025-09-12 08:08:05', '2025-09-16 09:45:26');

-- Exportiere Struktur von Tabelle geoalp_woo_api.users
DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exportiere Daten aus Tabelle geoalp_woo_api.users: ~1 rows (ungefähr)
INSERT INTO `users` (`id`, `name`, `email`, `email_verified_at`, `password`, `remember_token`, `created_at`, `updated_at`) VALUES
	(1, 'Administrator', 'admin@example.com', NULL, '$2y$12$8PXlXKcDVQ.F63dChg2J5.SDhFlYaJQPC7CM/4TXahDhwEYNxb1D2', NULL, '2025-08-16 09:39:35', '2025-08-16 09:39:35'),
	(2, 'Jörg Aderhold', 'joerg@jaderbass.de', NULL, '$2y$12$jO2sQXWBrakG8FPSa8KNkuzTCYmaHwMFUrgkyMEYsuGJPMsgjruoq', 'IJyV3M1jdRi5hlbSqUIWNBCTjxQfdRk0SWSPfEkEJBZKztW8tE5CYP5WvtpW', '2025-08-16 09:39:35', '2025-08-16 09:39:35');

-- Exportiere Struktur von Tabelle geoalp_woo_api.woo_links
DROP TABLE IF EXISTS `woo_links`;
CREATE TABLE IF NOT EXISTS `woo_links` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `shop_id` bigint unsigned NOT NULL,
  `local_sku` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `woo_sku` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `woo_product_id` bigint unsigned DEFAULT NULL,
  `woo_variation_id` bigint unsigned DEFAULT NULL,
  `ean` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mpn` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `protect_sku` tinyint(1) NOT NULL DEFAULT '1',
  `confidence` tinyint unsigned NOT NULL DEFAULT '100',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_shop_woo_sku` (`shop_id`,`woo_sku`),
  KEY `idx_shop_local_sku` (`shop_id`,`local_sku`),
  KEY `idx_shop_product` (`shop_id`,`woo_product_id`),
  KEY `idx_shop_variation` (`shop_id`,`woo_variation_id`),
  KEY `idx_shop_ean` (`shop_id`,`ean`),
  KEY `idx_shop_mpn` (`shop_id`,`mpn`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exportiere Daten aus Tabelle geoalp_woo_api.woo_links: ~0 rows (ungefähr)

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
