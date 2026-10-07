/*M!999999\- enable the sandbox mode */ 
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `assembly_group_resync_runs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `assembly_group_resync_runs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `status` varchar(255) NOT NULL DEFAULT 'queued',
  `processed` int(10) unsigned NOT NULL DEFAULT 0,
  `updated` int(10) unsigned NOT NULL DEFAULT 0,
  `total` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `assembly_group_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `assembly_group_rules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `field` varchar(255) NOT NULL,
  `operator` varchar(255) NOT NULL,
  `value` varchar(255) NOT NULL,
  `assembly_group` int(10) unsigned NOT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `parent_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `categories_name_unique` (`name`),
  UNIQUE KEY `categories_slug_unique` (`slug`),
  KEY `categories_parent_id_foreign` (`parent_id`),
  CONSTRAINT `categories_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `category_product`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `category_product` (
  `category_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `assignment_type` varchar(255) NOT NULL DEFAULT 'auto',
  PRIMARY KEY (`category_id`,`product_id`),
  KEY `category_product_product_id_foreign` (`product_id`),
  CONSTRAINT `category_product_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `category_product_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `category_resync_runs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `category_resync_runs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `status` varchar(255) NOT NULL DEFAULT 'queued',
  `processed` int(10) unsigned NOT NULL DEFAULT 0,
  `total` int(10) unsigned NOT NULL DEFAULT 0,
  `started_at` timestamp NULL DEFAULT NULL,
  `finished_at` timestamp NULL DEFAULT NULL,
  `message` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `category_resync_runs_status_index` (`status`),
  KEY `category_resync_runs_created_at_index` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `category_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `category_rules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `category_id` bigint(20) unsigned NOT NULL,
  `keyword` varchar(255) NOT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `category_rules_category_id_keyword_unique` (`category_id`,`keyword`),
  KEY `category_rules_keyword_index` (`keyword`),
  CONSTRAINT `category_rules_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `color_translations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `color_translations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `source_value` varchar(255) NOT NULL,
  `source_slug` varchar(255) NOT NULL,
  `translated_value` varchar(255) DEFAULT NULL,
  `is_auto` tinyint(1) NOT NULL DEFAULT 0,
  `is_reviewed` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `color_translations_source_slug_unique` (`source_slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `exports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `exports` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `completed_at` timestamp NULL DEFAULT NULL,
  `file_disk` varchar(255) NOT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `exporter` varchar(255) NOT NULL,
  `processed_rows` int(10) unsigned NOT NULL DEFAULT 0,
  `total_rows` int(10) unsigned NOT NULL,
  `successful_rows` int(10) unsigned NOT NULL DEFAULT 0,
  `user_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `exports_user_id_foreign` (`user_id`),
  CONSTRAINT `exports_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_import_rows`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_import_rows` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`data`)),
  `import_id` bigint(20) unsigned NOT NULL,
  `validation_error` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `failed_import_rows_import_id_foreign` (`import_id`),
  CONSTRAINT `failed_import_rows_import_id_foreign` FOREIGN KEY (`import_id`) REFERENCES `imports` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `import_runs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `import_runs` (
  `id` char(36) NOT NULL,
  `manufacturer_id` bigint(20) unsigned NOT NULL,
  `source_type` varchar(20) NOT NULL,
  `source` text NOT NULL,
  `author_id` bigint(20) unsigned DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'queued',
  `processed_rows` int(10) unsigned NOT NULL DEFAULT 0,
  `error_message` text DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `finished_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `import_runs_manufacturer_id_status_index` (`manufacturer_id`,`status`),
  KEY `import_runs_status_created_at_index` (`status`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `imports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `imports` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `completed_at` timestamp NULL DEFAULT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `importer` varchar(255) NOT NULL,
  `processed_rows` int(10) unsigned NOT NULL DEFAULT 0,
  `total_rows` int(10) unsigned NOT NULL,
  `successful_rows` int(10) unsigned NOT NULL DEFAULT 0,
  `user_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `imports_user_id_foreign` (`user_id`),
  CONSTRAINT `imports_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `manufacturer_audits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `manufacturer_audits` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `manufacturer_id` bigint(20) unsigned NOT NULL,
  `field` varchar(255) NOT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `changed_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `manufacturer_audits_manufacturer_id_foreign` (`manufacturer_id`),
  KEY `manufacturer_audits_changed_by_foreign` (`changed_by`),
  CONSTRAINT `manufacturer_audits_changed_by_foreign` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `manufacturer_audits_manufacturer_id_foreign` FOREIGN KEY (`manufacturer_id`) REFERENCES `manufacturers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `manufacturers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `manufacturers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `manufacturer` varchar(255) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 0,
  `manufacturercountry` varchar(3) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `api_url` varchar(255) DEFAULT NULL,
  `api_user` varchar(255) DEFAULT NULL,
  `api_password` varchar(255) DEFAULT NULL,
  `api_token` varchar(255) DEFAULT NULL,
  `api_password_changed_at` timestamp NULL DEFAULT NULL,
  `import_type` enum('csv','xml','api') NOT NULL DEFAULT 'csv',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `manufacturers_manufacturer_unique` (`manufacturer`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `model_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_permissions` (
  `permission_id` bigint(20) unsigned NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `model_has_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_roles` (
  `role_id` bigint(20) unsigned NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` char(36) NOT NULL,
  `type` varchar(255) NOT NULL,
  `notifiable_type` varchar(255) NOT NULL,
  `notifiable_id` bigint(20) unsigned NOT NULL,
  `data` text NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `guard_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `petzl_category_mappings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `petzl_category_mappings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `source_category` varchar(255) NOT NULL,
  `source_subcategory` varchar(255) DEFAULT NULL,
  `translated_category` varchar(255) DEFAULT NULL,
  `translated_subcategory` varchar(255) DEFAULT NULL,
  `petzl_path` varchar(255) DEFAULT NULL,
  `is_reviewed` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `petzl_category_mappings_source_unique` (`source_category`,`source_subcategory`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `petzl_description_sync_runs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `petzl_description_sync_runs` (
  `id` char(36) NOT NULL,
  `batch_id` char(36) DEFAULT NULL,
  `import_run_id` char(36) DEFAULT NULL,
  `trigger` varchar(20) NOT NULL,
  `mode` varchar(20) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'queued',
  `author_id` bigint(20) unsigned DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `finished_at` timestamp NULL DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `petzl_description_sync_runs_author_id_foreign` (`author_id`),
  KEY `petzl_description_sync_runs_batch_id_index` (`batch_id`),
  KEY `petzl_description_sync_runs_import_run_id_index` (`import_run_id`),
  CONSTRAINT `petzl_description_sync_runs_author_id_foreign` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `petzl_translations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `petzl_translations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `source_column` varchar(255) NOT NULL,
  `source_text` text NOT NULL,
  `translated_text` text DEFAULT NULL,
  `source_lang` varchar(10) NOT NULL DEFAULT 'EN',
  `target_lang` varchar(10) NOT NULL DEFAULT 'DE',
  `provider` varchar(255) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `petzl_translation_unique` (`source_column`,`source_text`,`source_lang`,`target_lang`) USING HASH
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_attribute_values`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_attribute_values` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `attribute_id` bigint(20) unsigned NOT NULL,
  `value` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `woo_term_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_attribute_values_attribute_id_slug_unique` (`attribute_id`,`slug`),
  CONSTRAINT `product_attribute_values_attribute_id_foreign` FOREIGN KEY (`attribute_id`) REFERENCES `product_attributes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_attributes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_attributes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `woo_attribute_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_attributes_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_images` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint(20) unsigned NOT NULL,
  `url` varchar(255) NOT NULL,
  `is_main` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `product_images_product_id_foreign` (`product_id`),
  CONSTRAINT `product_images_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_meta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_meta` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint(20) unsigned NOT NULL,
  `variation_id` bigint(20) unsigned DEFAULT NULL,
  `scope` enum('product','variation') NOT NULL DEFAULT 'product',
  `key` varchar(191) NOT NULL,
  `value` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_meta_unique_scope_key` (`product_id`,`variation_id`,`scope`,`key`),
  KEY `product_meta_product_id_index` (`product_id`),
  KEY `product_meta_variation_id_index` (`variation_id`),
  KEY `product_meta_scope_index` (`scope`),
  KEY `product_meta_key_index` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_variation_attribute_value`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_variation_attribute_value` (
  `product_variation_id` bigint(20) unsigned NOT NULL,
  `product_attribute_value_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`product_variation_id`,`product_attribute_value_id`),
  KEY `piv_var_attr_value_id_foreign` (`product_attribute_value_id`),
  CONSTRAINT `piv_var_attr_value_id_foreign` FOREIGN KEY (`product_attribute_value_id`) REFERENCES `product_attribute_values` (`id`) ON DELETE CASCADE,
  CONSTRAINT `piv_var_attr_variation_id_foreign` FOREIGN KEY (`product_variation_id`) REFERENCES `product_variations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_variations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_variations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint(20) unsigned NOT NULL,
  `external_id` varchar(64) DEFAULT NULL,
  `woo_variation_id` bigint(20) DEFAULT NULL,
  `sku` varchar(255) NOT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `ean` varchar(255) DEFAULT NULL,
  `regular_price` varchar(255) DEFAULT NULL,
  `sale_price` varchar(255) DEFAULT NULL,
  `stock_quantity` int(11) DEFAULT NULL,
  `stock_status` enum('in_stock','out_of_stock','on_backorder') NOT NULL DEFAULT 'in_stock',
  `weight` varchar(255) DEFAULT NULL,
  `attributes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`attributes`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `last_synced_at` timestamp NULL DEFAULT NULL,
  `last_sync_status` varchar(32) DEFAULT NULL,
  `last_sync_error` text DEFAULT NULL,
  `payload_hash` varchar(64) DEFAULT NULL,
  `regular_price_cents` bigint(20) NOT NULL DEFAULT 0,
  `sale_price_cents` bigint(20) NOT NULL DEFAULT 0,
  `weight_g` int(11) NOT NULL DEFAULT 0,
  `length_mm` int(11) NOT NULL DEFAULT 0,
  `width_mm` int(11) NOT NULL DEFAULT 0,
  `height_mm` int(11) NOT NULL DEFAULT 0,
  `manage_stock` tinyint(1) NOT NULL DEFAULT 0,
  `backorders` enum('no','notify','yes') NOT NULL DEFAULT 'no',
  `attributes_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`attributes_json`)),
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_variations_sku_unique` (`sku`),
  UNIQUE KEY `product_variations_external_id_unique` (`external_id`),
  KEY `product_variations_product_id_foreign` (`product_id`),
  KEY `product_variations_woo_variation_id_index` (`woo_variation_id`),
  KEY `product_variations_last_sync_status_index` (`last_sync_status`),
  CONSTRAINT `product_variations_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `products` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `manufacturer_id` bigint(20) unsigned DEFAULT NULL,
  `slug` varchar(255) NOT NULL,
  `external_url` varchar(2048) DEFAULT NULL,
  `declaration_of_compliance` varchar(512) DEFAULT NULL,
  `manual_url` varchar(2048) DEFAULT NULL,
  `woo_product_id` bigint(20) DEFAULT NULL,
  `stock_status` enum('in_stock','out_of_stock','on_backorder') NOT NULL DEFAULT 'in_stock',
  `online_sellable` tinyint(1) DEFAULT NULL,
  `product_type` enum('simple','variable') NOT NULL DEFAULT 'simple',
  `sku` varchar(255) DEFAULT NULL,
  `ean` varchar(255) DEFAULT NULL,
  `product_number` varchar(255) DEFAULT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `assembly_group` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `assembly_group_source` varchar(16) NOT NULL DEFAULT 'auto',
  `original_product_name` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `petzl_description_html` longtext DEFAULT NULL,
  `petzl_short_description_html` text DEFAULT NULL,
  `petzl_description_source_url` varchar(255) DEFAULT NULL,
  `petzl_description_fetched_at` timestamp NULL DEFAULT NULL,
  `petzl_description_hash` varchar(255) DEFAULT NULL,
  `description_source` varchar(255) NOT NULL DEFAULT 'auto',
  `designation` varchar(255) DEFAULT NULL,
  `type` varchar(255) DEFAULT NULL,
  `market` varchar(255) DEFAULT NULL,
  `customs` varchar(255) DEFAULT NULL,
  `made_in` varchar(8) DEFAULT NULL,
  `short_description` text DEFAULT NULL,
  `size` varchar(128) DEFAULT NULL,
  `dimensions_raw` varchar(255) DEFAULT NULL,
  `dimension_length_mm` int(10) unsigned DEFAULT NULL,
  `dimension_width_mm` int(10) unsigned DEFAULT NULL,
  `dimension_height_mm` int(10) unsigned DEFAULT NULL,
  `image_urls` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`image_urls`)),
  `certification` varchar(255) DEFAULT NULL,
  `materials` text DEFAULT NULL,
  `norms` varchar(255) DEFAULT NULL,
  `width` varchar(255) DEFAULT NULL,
  `length` varchar(255) DEFAULT NULL,
  `height` varchar(255) DEFAULT NULL,
  `unit` varchar(255) DEFAULT NULL,
  `box_width` varchar(255) DEFAULT NULL,
  `box_length` varchar(255) DEFAULT NULL,
  `box_height` varchar(255) DEFAULT NULL,
  `weight` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `last_synced_at` timestamp NULL DEFAULT NULL,
  `last_sync_status` varchar(32) DEFAULT NULL,
  `last_sync_error` text DEFAULT NULL,
  `payload_hash` varchar(64) DEFAULT NULL,
  `mpn` varchar(64) DEFAULT NULL,
  `regular_price_cents` bigint(20) NOT NULL DEFAULT 0,
  `sale_price_cents` bigint(20) NOT NULL DEFAULT 0,
  `manufacturer_price_cents` bigint(20) unsigned DEFAULT NULL,
  `tax_class` varchar(64) DEFAULT NULL,
  `tax_status` enum('taxable','shipping','none') NOT NULL DEFAULT 'taxable',
  `weight_g` int(11) NOT NULL DEFAULT 0,
  `length_mm` int(11) NOT NULL DEFAULT 0,
  `width_mm` int(11) NOT NULL DEFAULT 0,
  `height_mm` int(11) NOT NULL DEFAULT 0,
  `stock_quantity` int(11) NOT NULL DEFAULT 0,
  `manage_stock` tinyint(1) NOT NULL DEFAULT 0,
  `backorders` enum('no','notify','yes') NOT NULL DEFAULT 'no',
  `shipping_class` varchar(64) DEFAULT NULL,
  `hs_code` varchar(32) DEFAULT NULL,
  `country_of_origin` varchar(2) DEFAULT NULL,
  `author_id` bigint(20) unsigned DEFAULT NULL,
  `petzl_source_category` varchar(255) DEFAULT NULL,
  `petzl_source_subcategory` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `products_slug_unique` (`slug`),
  UNIQUE KEY `products_sku_unique` (`sku`),
  KEY `products_manufacturer_id_foreign` (`manufacturer_id`),
  KEY `products_size_idx` (`size`),
  KEY `products_mpn_index` (`mpn`),
  KEY `products_shipping_class_index` (`shipping_class`),
  KEY `products_hs_code_index` (`hs_code`),
  KEY `products_country_of_origin_index` (`country_of_origin`),
  KEY `products_woo_product_id_index` (`woo_product_id`),
  KEY `products_last_sync_status_index` (`last_sync_status`),
  KEY `products_author_id_foreign` (`author_id`),
  KEY `products_assembly_group_index` (`assembly_group`),
  KEY `products_assembly_group_source_index` (`assembly_group_source`),
  CONSTRAINT `products_author_id_foreign` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `products_manufacturer_id_foreign` FOREIGN KEY (`manufacturer_id`) REFERENCES `manufacturers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `role_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_has_permissions` (
  `permission_id` bigint(20) unsigned NOT NULL,
  `role_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `role_has_permissions_role_id_foreign` (`role_id`),
  CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `guard_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `shops`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `shops` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `base_url` varchar(255) NOT NULL,
  `api_version` varchar(255) NOT NULL DEFAULT 'wc/v3',
  `consumer_key` varchar(255) NOT NULL,
  `consumer_secret` varchar(255) NOT NULL,
  `webhook_secret` varchar(255) DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `rate_limit_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`rate_limit_json`)),
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `shops_name_unique` (`name`),
  KEY `shops_created_by_foreign` (`created_by`),
  KEY `shops_base_url_api_version_index` (`base_url`,`api_version`),
  KEY `shops_is_default_index` (`is_default`),
  CONSTRAINT `shops_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

/*M!999999\- enable the sandbox mode */ 
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'0001_01_01_000000_create_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2,'0001_01_01_000001_create_cache_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (3,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (4,'2025_05_05_085930_create_notifications_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (5,'2025_05_05_085959_create_imports_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (6,'2025_05_05_090000_create_exports_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (7,'2025_05_05_090001_create_failed_import_rows_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (8,'2025_05_12_065907_create_manufacturers_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (9,'2025_05_12_065908_create_products_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (10,'2025_07_28_200825_create_product_variations_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (11,'2025_07_28_200908_create_product_attributes_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (12,'2025_07_28_200951_create_product_attribute_values_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (13,'2025_07_28_201032_create_product_images_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (14,'2025_07_30_180350_create_manufacturer_audits_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (15,'2025_07_31_095630_create_permission_tables',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (16,'2025_08_16_115714_create_product_variation_attribute_value_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (17,'2025_08_17_132505_add_ean_and_weight_to_product_variations_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (18,'2025_08_25_000001_add_missing_columns_to_products_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (19,'2025_08_25_000002_remove_unused_columns_from_products_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (20,'create_product_meta_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (21,'update_product_variations_table_add_woo_columns',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (22,'update_products_table_add_woo_columns',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (23,'2025_09_12_000001_create_shops_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (24,'2025_09_12_000002_add_woo_fields_to_products',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (25,'2025_09_12_000003_add_woo_fields_to_product_variations',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (26,'2025_09_12_000004_add_sync_fields_indexes_to_product_variations',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (27,'2025_10_26_190000_rename_attribute_slugs_to_german',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (28,'2025_10_26_190100_fix_variation_attribute_json_to_german',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (29,'2025_11_22_120000_add_dimensions_to_products_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (30,'2025_11_22_190000_add_image_urls_to_products_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (31,'2025_12_04_183954_add_author_fk_to_products_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (32,'2025_12_04_185236_drop_legacy_author_fields_from_products_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (33,'2025_12_09_192750_add_petzl_meta_columns_to_products_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (34,'2025_12_17_195413_add_norms_to_products_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (35,'2025_12_29_121904_create_import_runs_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (36,'2025_12_30_114831_add_processed_rows_to_import_runs_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (37,'2026_01_20_210036_add_external_id_to_product_variations_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (38,'2026_01_21_183447_add_original_product_name_to_products_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (39,'2026_02_04_205733_add_active_to_manufacturers_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (40,'2026_03_13_164003_create_categories_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (41,'2026_03_13_164011_create_category_product_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (42,'2026_03_13_164020_drop_category_and_subcategory_from_products_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (43,'2026_03_24_201149_add_baugruppe_to_products_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (44,'2026_03_30_000001_replace_baugruppe_with_assembly_group_on_products_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (45,'2026_03_30_000002_add_assembly_group_source_to_products_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (46,'2026_04_01_101030_add_assignment_type_to_category_product_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (47,'2026_04_01_112603_create_category_rules_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (48,'2026_04_02_070347_create_category_resync_runs_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (49,'2026_04_21_160752_add_manufacturer_price_cents_to_products_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (50,'2026_04_21_160804_add_manufacturer_price_cents_to_product_variations_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (51,'2026_04_29_122958_add_slug_to_product_variations_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (52,'2026_04_30_085135_create_assembly_group_rules_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (53,'2026_04_30_103354_create_assembly_group_resync_runs_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (54,'2026_05_19_183633_create_petzl_translations_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (55,'2026_05_21_145112_add_petzl_description_fields_to_products_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (56,'2026_06_03_172033_create_petzl_category_mappings_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (57,'2026_08_14_135152_add_petzl_short_description_html_to_products_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (58,'2026_08_17_040539_create_petzl_description_sync_runs_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (59,'2026_08_21_041959_add_petzl_source_categories_to_products_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (60,'2026_09_21_161822_add_online_sellable_to_products_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (61,'2026_09_24_090000_create_color_translations_table',5);
