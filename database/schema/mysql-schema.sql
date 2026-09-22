/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `asset_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `asset_categories` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `asset_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `asset_history` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `asset_id` int unsigned NOT NULL,
  `event_date` date DEFAULT NULL,
  `content` mediumtext NOT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_ahist_asset` (`asset_id`),
  CONSTRAINT `fk_ahist_asset` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `assets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `assets` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(200) DEFAULT NULL,
  `category_id` int unsigned DEFAULT NULL,
  `department_id` int unsigned DEFAULT NULL,
  `assigned_to` varchar(200) DEFAULT NULL,
  `vendor` varchar(200) DEFAULT NULL,
  `model_number` varchar(100) DEFAULT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `purchase_price` decimal(12,2) DEFAULT NULL,
  `purchased_on` date DEFAULT NULL,
  `sold_on` date DEFAULT NULL,
  `next_maintenance_on` date DEFAULT NULL,
  `status_code` varchar(50) DEFAULT NULL COMMENT 'Raw legacy status code; meaning not documented',
  `description` mediumtext,
  `photo_id` int unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_assets_cat` (`category_id`),
  KEY `fk_assets_dept` (`department_id`),
  KEY `fk_assets_photo` (`photo_id`),
  CONSTRAINT `fk_assets_cat` FOREIGN KEY (`category_id`) REFERENCES `asset_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_assets_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_assets_photo` FOREIGN KEY (`photo_id`) REFERENCES `media_files` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `attendance_counts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `attendance_counts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `attended_on` date NOT NULL,
  `age_group` enum('children','junior_youth','young_adult','adult') NOT NULL,
  `sex` enum('male','female') NOT NULL,
  `headcount` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_attendance` (`attended_on`,`age_group`,`sex`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_logs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned DEFAULT NULL,
  `event` varchar(60) NOT NULL,
  `subject_type` varchar(60) DEFAULT NULL,
  `subject_id` int unsigned DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `properties` json DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `audit_logs_subject_type_subject_id_index` (`subject_type`,`subject_id`),
  KEY `audit_logs_user_id_foreign` (`user_id`),
  KEY `audit_logs_event_index` (`event`),
  KEY `audit_logs_created_at_index` (`created_at`),
  CONSTRAINT `audit_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bank_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bank_accounts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `bank_name` varchar(150) NOT NULL,
  `account_number` varchar(40) NOT NULL,
  `account_name` varchar(200) DEFAULT NULL,
  `account_type` varchar(50) DEFAULT NULL,
  `branch` varchar(150) DEFAULT NULL,
  `balance` decimal(12,2) NOT NULL DEFAULT '0.00',
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `account_number` (`account_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bank_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bank_transactions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `bank_account_id` int unsigned NOT NULL,
  `transacted_on` date NOT NULL,
  `cheque_no` varchar(40) DEFAULT NULL,
  `debit` decimal(12,2) DEFAULT NULL,
  `credit` decimal(12,2) DEFAULT NULL,
  `balance` decimal(12,2) DEFAULT NULL,
  `transaction_type` varchar(50) DEFAULT NULL,
  `description` mediumtext,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_banktx` (`bank_account_id`,`transacted_on`),
  CONSTRAINT `fk_banktx_acct` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `budget_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `budget_lines` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `budget_date` date DEFAULT NULL,
  `direction` enum('inflow','outflow') NOT NULL,
  `item_code` varchar(40) NOT NULL COMMENT 'Column name in the old wide budget tables',
  `amount` decimal(12,2) NOT NULL,
  `note` varchar(400) DEFAULT NULL,
  `legacy_table` varchar(30) NOT NULL,
  `legacy_id` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_budget` (`budget_date`,`direction`,`item_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `church_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `church_settings` (
  `setting_key` varchar(60) NOT NULL,
  `setting_value` text,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contribution_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contribution_types` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(30) NOT NULL,
  `name` varchar(80) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contributions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contributions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `member_id` int unsigned DEFAULT NULL COMMENT 'NULL when the original member no longer exists (see legacy_member_id)',
  `type_id` int unsigned NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `contributed_on` date NOT NULL,
  `date_inferred` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = original date was invalid; taken from the entry timestamp',
  `invoice_no` varchar(40) DEFAULT NULL,
  `purpose` varchar(250) DEFAULT NULL,
  `recorded_by` int unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `legacy_table` varchar(30) NOT NULL,
  `legacy_id` int NOT NULL,
  `legacy_member_id` int DEFAULT NULL,
  `legacy_user_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_contrib_legacy` (`legacy_table`,`legacy_id`),
  KEY `ix_contrib_member_date` (`member_id`,`contributed_on`),
  KEY `ix_contrib_type_date` (`type_id`,`contributed_on`),
  KEY `fk_contrib_user` (`recorded_by`),
  CONSTRAINT `fk_contrib_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_contrib_type` FOREIGN KEY (`type_id`) REFERENCES `contribution_types` (`id`),
  CONSTRAINT `fk_contrib_user` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `departments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `departments` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `expenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `expenses` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `category_id` int unsigned NOT NULL,
  `spent_on` date NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `description` varchar(400) DEFAULT NULL,
  `payee_id` int unsigned DEFAULT NULL,
  `cheque_no` varchar(40) DEFAULT NULL,
  `pv_number` varchar(40) DEFAULT NULL,
  `project_id` int unsigned DEFAULT NULL,
  `recorded_by` int unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `legacy_table` varchar(30) NOT NULL,
  `legacy_id` int NOT NULL,
  `legacy_user_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_expenses_legacy` (`legacy_table`,`legacy_id`),
  KEY `ix_expenses_cat_date` (`category_id`,`spent_on`),
  KEY `fk_exp_payee` (`payee_id`),
  KEY `fk_exp_project` (`project_id`),
  KEY `fk_exp_user` (`recorded_by`),
  CONSTRAINT `fk_exp_cat` FOREIGN KEY (`category_id`) REFERENCES `ledger_categories` (`id`),
  CONSTRAINT `fk_exp_payee` FOREIGN KEY (`payee_id`) REFERENCES `payees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_exp_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_exp_user` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `income`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `income` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `category_id` int unsigned NOT NULL,
  `received_on` date NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `description` varchar(400) DEFAULT NULL,
  `paid_by` varchar(200) DEFAULT NULL,
  `invoice_no` varchar(40) DEFAULT NULL,
  `project_id` int unsigned DEFAULT NULL,
  `recorded_by` int unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `legacy_table` varchar(30) NOT NULL,
  `legacy_id` int NOT NULL,
  `legacy_user_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_income_legacy` (`legacy_table`,`legacy_id`),
  KEY `ix_income_cat_date` (`category_id`,`received_on`),
  KEY `fk_income_project` (`project_id`),
  KEY `fk_income_user` (`recorded_by`),
  CONSTRAINT `fk_income_cat` FOREIGN KEY (`category_id`) REFERENCES `ledger_categories` (`id`),
  CONSTRAINT `fk_income_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_income_user` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ledger_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ledger_categories` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `kind` enum('income','expense') NOT NULL,
  `code` varchar(40) NOT NULL,
  `name` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ledger_cat` (`kind`,`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `media_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `media_files` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `filename` varchar(255) NOT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `size_bytes` int unsigned DEFAULT NULL,
  `directory` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `member_beneficiaries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `member_beneficiaries` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `member_id` int unsigned NOT NULL,
  `slot` tinyint unsigned NOT NULL,
  `name` varchar(150) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bene_slot` (`member_id`,`slot`),
  CONSTRAINT `fk_bene_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `member_children`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `member_children` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `member_id` int unsigned NOT NULL COMMENT 'The parent',
  `name` varchar(200) NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `place_of_birth` varchar(150) DEFAULT NULL,
  `photo_id` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_mchild_member` (`member_id`),
  KEY `fk_mchild_photo` (`photo_id`),
  CONSTRAINT `fk_mchild_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mchild_photo` FOREIGN KEY (`photo_id`) REFERENCES `media_files` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `member_deaths`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `member_deaths` (
  `member_id` int unsigned NOT NULL,
  `died_on` date DEFAULT NULL,
  `buried_on` date DEFAULT NULL,
  `burial_place` varchar(200) DEFAULT NULL,
  `burial_town` varchar(200) DEFAULT NULL,
  `pastor_in_charge` varchar(200) DEFAULT NULL,
  `cause_of_death` mediumtext,
  PRIMARY KEY (`member_id`),
  CONSTRAINT `fk_mdeath_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `member_group_memberships`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `member_group_memberships` (
  `member_id` int unsigned NOT NULL,
  `member_group_id` int unsigned NOT NULL,
  PRIMARY KEY (`member_id`,`member_group_id`),
  KEY `fk_mgm_group` (`member_group_id`),
  CONSTRAINT `fk_mgm_group` FOREIGN KEY (`member_group_id`) REFERENCES `member_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mgm_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `member_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `member_groups` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `short_name` varchar(50) DEFAULT NULL,
  `photo_id` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `fk_mgroups_photo` (`photo_id`),
  CONSTRAINT `fk_mgroups_photo` FOREIGN KEY (`photo_id`) REFERENCES `media_files` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `member_next_of_kin`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `member_next_of_kin` (
  `member_id` int unsigned NOT NULL,
  `name` varchar(150) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `residential_address` tinytext,
  `postal_address` tinytext,
  PRIMARY KEY (`member_id`),
  CONSTRAINT `fk_nok_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `member_sacraments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `member_sacraments` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `member_id` int unsigned NOT NULL,
  `kind` enum('baptism','confirmation') NOT NULL,
  `sacrament_date` date DEFAULT NULL,
  `place` varchar(150) DEFAULT NULL,
  `minister` varchar(150) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sacrament` (`member_id`,`kind`),
  CONSTRAINT `fk_sacr_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `member_transfers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `member_transfers` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `member_id` int unsigned NOT NULL,
  `transferred_on` date DEFAULT NULL,
  `church_name` varchar(200) DEFAULT NULL,
  `location` varchar(200) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_mtransfer_member` (`member_id`),
  CONSTRAINT `fk_mtransfer_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `members` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `member_number` varchar(40) NOT NULL,
  `title` varchar(20) DEFAULT NULL,
  `full_name` varchar(200) NOT NULL COMMENT 'As registered: Surname Firstname Othernames',
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `maiden_name` varchar(100) DEFAULT NULL,
  `sex` enum('male','female') DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `place_of_birth` varchar(150) DEFAULT NULL,
  `marital_status` enum('single','married','divorced','widowed') DEFAULT NULL,
  `marriage_type` enum('ordinance','customary') DEFAULT NULL,
  `spouse_name` varchar(150) DEFAULT NULL,
  `hometown` varchar(150) DEFAULT NULL,
  `father_name` varchar(150) DEFAULT NULL,
  `mother_name` varchar(150) DEFAULT NULL,
  `profession_id` int unsigned DEFAULT NULL,
  `employer_details` varchar(250) DEFAULT NULL,
  `company_name` varchar(250) DEFAULT NULL,
  `mobile` varchar(30) DEFAULT NULL,
  `telephone` varchar(30) DEFAULT NULL,
  `office_phone` varchar(30) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `facebook_id` varchar(150) DEFAULT NULL,
  `joined_on` date DEFAULT NULL,
  `is_communicant` tinyint(1) DEFAULT NULL,
  `is_child` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Belongs to the children register category',
  `church_positions` text COMMENT 'Free text: offices held / committees',
  `status` enum('active','invalid','transferred','deceased','deleted') NOT NULL DEFAULT 'active',
  `is_verified` tinyint(1) NOT NULL DEFAULT '0',
  `photo_id` int unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `member_number` (`member_number`),
  KEY `ix_members_name` (`full_name`),
  KEY `ix_members_status` (`status`),
  KEY `fk_members_profession` (`profession_id`),
  KEY `fk_members_photo` (`photo_id`),
  CONSTRAINT `fk_members_photo` FOREIGN KEY (`photo_id`) REFERENCES `media_files` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_members_profession` FOREIGN KEY (`profession_id`) REFERENCES `professions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `passkeys`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `passkeys` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `credential_id` varchar(255) NOT NULL,
  `credential` json NOT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `passkeys_credential_id_unique` (`credential_id`),
  KEY `passkeys_user_id_index` (`user_id`),
  CONSTRAINT `passkeys_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payees` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `telephone` varchar(30) DEFAULT NULL,
  `location` varchar(200) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payroll_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payroll_payments` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `staff_id` int unsigned NOT NULL,
  `kind` enum('salary','allowance') NOT NULL,
  `paid_on` date NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `cheque_no` varchar(40) DEFAULT NULL,
  `pv_number` varchar(40) DEFAULT NULL,
  `recorded_by` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_payroll` (`staff_id`,`paid_on`),
  KEY `fk_payroll_user` (`recorded_by`),
  CONSTRAINT `fk_payroll_emp` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`),
  CONSTRAINT `fk_payroll_user` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `module` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_unique` (`name`),
  KEY `permissions_module_index` (`module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `positions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `positions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `positions_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `professions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `professions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `projects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `projects` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `role_permission`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_permission` (
  `role_id` int unsigned NOT NULL,
  `permission_id` int unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `role_permission_permission_id_foreign` (`permission_id`),
  CONSTRAINT `role_permission_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_permission_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(60) NOT NULL,
  `slug` varchar(60) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `is_system` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `roles_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sms_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sms_messages` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `kind` enum('single','bulk','group') NOT NULL,
  `member_id` int unsigned DEFAULT NULL,
  `recipient_phone` varchar(30) DEFAULT NULL,
  `audience` varchar(250) DEFAULT NULL COMMENT 'Group / audience label for bulk and group sends',
  `member_group_id` int unsigned DEFAULT NULL,
  `subject` varchar(250) DEFAULT NULL,
  `body` varchar(500) DEFAULT NULL,
  `status` varchar(100) DEFAULT NULL,
  `sent_by` int unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `legacy_table` varchar(30) NOT NULL,
  `legacy_id` int NOT NULL,
  `legacy_user_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sms_legacy` (`legacy_table`,`legacy_id`),
  KEY `fk_sms_member` (`member_id`),
  KEY `fk_sms_group` (`member_group_id`),
  KEY `fk_sms_user` (`sent_by`),
  CONSTRAINT `fk_sms_group` FOREIGN KEY (`member_group_id`) REFERENCES `member_groups` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_sms_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_sms_user` FOREIGN KEY (`sent_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `staff`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `staff` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `staff_number` varchar(30) DEFAULT NULL,
  `title` varchar(20) DEFAULT NULL,
  `full_name` varchar(200) NOT NULL,
  `sex` enum('male','female') DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `ssnit_number` varchar(40) DEFAULT NULL,
  `department_id` int unsigned DEFAULT NULL,
  `position_id` int unsigned DEFAULT NULL,
  `location` varchar(150) DEFAULT NULL COMMENT 'Station / congregation currently served',
  `member_id` int unsigned DEFAULT NULL COMMENT 'Set when the staff member is also on the members register',
  `status` enum('active','on_leave','suspended','terminated') NOT NULL DEFAULT 'active',
  `joined_on` date DEFAULT NULL,
  `left_on` date DEFAULT NULL,
  `emergency_contact_name` varchar(150) DEFAULT NULL,
  `emergency_contact_phone` varchar(30) DEFAULT NULL,
  `photo_id` int unsigned DEFAULT NULL,
  `notes` text,
  `telephone` varchar(30) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `address` text,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `staff_staff_number_unique` (`staff_number`),
  KEY `staff_department_id_foreign` (`department_id`),
  KEY `staff_position_id_foreign` (`position_id`),
  KEY `staff_member_id_foreign` (`member_id`),
  KEY `staff_photo_id_foreign` (`photo_id`),
  KEY `staff_full_name_index` (`full_name`),
  KEY `staff_status_index` (`status`),
  CONSTRAINT `staff_department_id_foreign` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `staff_member_id_foreign` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE SET NULL,
  CONSTRAINT `staff_photo_id_foreign` FOREIGN KEY (`photo_id`) REFERENCES `media_files` (`id`) ON DELETE SET NULL,
  CONSTRAINT `staff_position_id_foreign` FOREIGN KEY (`position_id`) REFERENCES `positions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `staff_transfers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `staff_transfers` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `staff_id` int unsigned NOT NULL,
  `from_department_id` int unsigned DEFAULT NULL,
  `to_department_id` int unsigned DEFAULT NULL,
  `from_position_id` int unsigned DEFAULT NULL,
  `to_position_id` int unsigned DEFAULT NULL,
  `from_location` varchar(150) DEFAULT NULL,
  `to_location` varchar(150) DEFAULT NULL,
  `effective_on` date NOT NULL,
  `reason` text,
  `recorded_by` int unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `staff_transfers_staff_id_effective_on_index` (`staff_id`,`effective_on`),
  KEY `staff_transfers_from_department_id_foreign` (`from_department_id`),
  KEY `staff_transfers_to_department_id_foreign` (`to_department_id`),
  KEY `staff_transfers_from_position_id_foreign` (`from_position_id`),
  KEY `staff_transfers_to_position_id_foreign` (`to_position_id`),
  KEY `staff_transfers_recorded_by_foreign` (`recorded_by`),
  CONSTRAINT `staff_transfers_from_department_id_foreign` FOREIGN KEY (`from_department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `staff_transfers_from_position_id_foreign` FOREIGN KEY (`from_position_id`) REFERENCES `positions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `staff_transfers_recorded_by_foreign` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `staff_transfers_staff_id_foreign` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE,
  CONSTRAINT `staff_transfers_to_department_id_foreign` FOREIGN KEY (`to_department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `staff_transfers_to_position_id_foreign` FOREIGN KEY (`to_position_id`) REFERENCES `positions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `role_id` int unsigned DEFAULT NULL,
  `staff_id` int unsigned DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL COMMENT 'NULL = must set a password before first login',
  `two_factor_secret` text,
  `two_factor_recovery_codes` text,
  `two_factor_confirmed_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `must_reset_password` tinyint(1) NOT NULL DEFAULT '1',
  `password_changed_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `photo_id` int unsigned DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `legacy_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `users_staff_id_unique` (`staff_id`),
  KEY `fk_users_role` (`role_id`),
  KEY `fk_users_photo` (`photo_id`),
  CONSTRAINT `fk_users_photo` FOREIGN KEY (`photo_id`) REFERENCES `media_files` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL,
  CONSTRAINT `users_staff_id_foreign` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `visitors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `visitors` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `visited_on` date DEFAULT NULL,
  `name` varchar(200) NOT NULL,
  `telephone` varchar(30) DEFAULT NULL,
  `location` varchar(200) DEFAULT NULL,
  `purpose` varchar(250) DEFAULT NULL,
  `attended_by` varchar(200) DEFAULT NULL,
  `remarks` mediumtext,
  `recorded_by` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_visitors_user` (`recorded_by`),
  CONSTRAINT `fk_visitors_user` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'0001_01_01_000000_prepare_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2,'2024_01_01_000000_create_passkeys_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (3,'2025_08_14_170933_add_two_factor_columns_to_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (4,'2026_09_22_100000_create_rbac_tables',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (5,'2026_09_22_100100_create_staff_directory',1);
