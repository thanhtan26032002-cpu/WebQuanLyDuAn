-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: web_quan_ly_du_an
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `activities`
--

DROP TABLE IF EXISTS `activities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `activities` (
  `activity_code` varchar(50) NOT NULL,
  `activity_user_code` varchar(50) NOT NULL,
  `activity_project_code` varchar(50) DEFAULT NULL,
  `activity_action` varchar(255) NOT NULL,
  `activity_target_type` varchar(255) NOT NULL,
  `activity_target_code` varchar(50) NOT NULL,
  `activity_detail` text DEFAULT NULL,
  `activity_created_at` timestamp NULL DEFAULT NULL,
  `activity_updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`activity_code`),
  KEY `activity_user_code` (`activity_user_code`),
  KEY `activities_project_created_index` (`activity_project_code`,`activity_created_at`),
  CONSTRAINT `activities_activity_project_code_foreign` FOREIGN KEY (`activity_project_code`) REFERENCES `projects` (`project_code`) ON DELETE SET NULL,
  CONSTRAINT `activities_ibfk_1` FOREIGN KEY (`activity_user_code`) REFERENCES `users` (`user_code`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activities`
--

LOCK TABLES `activities` WRITE;
/*!40000 ALTER TABLE `activities` DISABLE KEYS */;
/*!40000 ALTER TABLE `activities` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `attachments`
--

DROP TABLE IF EXISTS `attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attachments` (
  `attachment_code` varchar(50) NOT NULL,
  `attachment_file_name` varchar(255) NOT NULL,
  `attachment_file_path` varchar(255) NOT NULL,
  `attachment_mime_type` varchar(255) DEFAULT NULL,
  `attachment_size_bytes` int(11) DEFAULT NULL,
  `attachment_target_type` varchar(255) NOT NULL,
  `attachment_target_code` varchar(50) NOT NULL,
  `attachment_uploaded_by` varchar(50) NOT NULL,
  `attachment_created_at` timestamp NULL DEFAULT NULL,
  `attachment_updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`attachment_code`),
  KEY `attachment_uploaded_by` (`attachment_uploaded_by`),
  CONSTRAINT `attachments_ibfk_1` FOREIGN KEY (`attachment_uploaded_by`) REFERENCES `users` (`user_code`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attachments`
--

LOCK TABLES `attachments` WRITE;
/*!40000 ALTER TABLE `attachments` DISABLE KEYS */;
/*!40000 ALTER TABLE `attachments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` bigint(20) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` bigint(20) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_locks`
--

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `customers`
--

DROP TABLE IF EXISTS `customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customers` (
  `customer_code` varchar(50) NOT NULL,
  `customer_name` varchar(255) NOT NULL,
  `customer_company` varchar(255) DEFAULT NULL,
  `customer_email` varchar(255) DEFAULT NULL,
  `customer_phone` varchar(50) DEFAULT NULL,
  `customer_address` text DEFAULT NULL,
  `customer_notes` text DEFAULT NULL,
  `customer_created_at` timestamp NULL DEFAULT NULL,
  `customer_updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`customer_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customers`
--

LOCK TABLES `customers` WRITE;
/*!40000 ALTER TABLE `customers` DISABLE KEYS */;
INSERT INTO `customers` VALUES ('KH0001','Nguyễn Văn A','Công ty cổ phần ABC','a@gmail.com','0123456789',NULL,NULL,'2026-08-03 02:19:25','2026-08-03 02:19:25');
/*!40000 ALTER TABLE `customers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `deadline_extensions`
--

DROP TABLE IF EXISTS `deadline_extensions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `deadline_extensions` (
  `extension_code` varchar(50) NOT NULL,
  `extension_target_type` varchar(20) NOT NULL,
  `extension_target_code` varchar(50) NOT NULL,
  `extension_old_due_date` date NOT NULL,
  `extension_new_due_date` date NOT NULL,
  `extension_reason` text NOT NULL,
  `extension_created_by` varchar(50) NOT NULL,
  `extension_created_at` timestamp NULL DEFAULT NULL,
  `extension_updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`extension_code`),
  KEY `deadline_extensions_extension_created_by_foreign` (`extension_created_by`),
  KEY `deadline_extension_target_index` (`extension_target_type`,`extension_target_code`,`extension_created_at`),
  CONSTRAINT `deadline_extensions_extension_created_by_foreign` FOREIGN KEY (`extension_created_by`) REFERENCES `users` (`user_code`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `deadline_extensions`
--

LOCK TABLES `deadline_extensions` WRITE;
/*!40000 ALTER TABLE `deadline_extensions` DISABLE KEYS */;
/*!40000 ALTER TABLE `deadline_extensions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` varchar(255) NOT NULL,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`),
  KEY `failed_jobs_connection_queue_failed_at_index` (`connection`,`queue`,`failed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `groups`
--

DROP TABLE IF EXISTS `groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `groups` (
  `group_code` varchar(50) NOT NULL,
  `group_name` varchar(255) NOT NULL,
  `group_description` text DEFAULT NULL,
  `group_icon` varchar(20) DEFAULT NULL,
  `group_color` varchar(30) NOT NULL DEFAULT 'violet',
  `group_member_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`group_member_ids`)),
  `group_created_at` timestamp NULL DEFAULT NULL,
  `group_updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`group_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `groups`
--

LOCK TABLES `groups` WRITE;
/*!40000 ALTER TABLE `groups` DISABLE KEYS */;
/*!40000 ALTER TABLE `groups` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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

--
-- Dumping data for table `job_batches`
--

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` smallint(5) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'2026_01_01_000000_create_groups_table',1),(3,'2026_01_01_000000_create_members_table',1),(4,'2026_01_01_000001_create_projects_table',1),(5,'2026_01_01_000002_create_project_members_table',1),(6,'2026_01_01_000003_create_tasks_table',1),(7,'2026_01_01_000004_create_activities_table',1),(8,'2026_01_01_000005_create_task_comments_table',1),(9,'2026_01_01_000006_create_attachments_table',1),(10,'2026_01_01_000007_create_notifications_table',1),(11,'2026_07_28_000000_add_color_to_projects_table',1),(12,'2026_07_28_000001_add_color_to_members_table',1),(13,'2026_07_29_000000_add_customers_and_planning_fields',1),(14,'2026_07_29_000001_remove_project_budget',1),(15,'2026_07_30_000000_add_soft_deletes_to_projects_and_tasks',1),(16,'2026_07_30_000001_create_task_progress_tables',1),(17,'0001_01_01_000001_create_cache_table',2),(18,'0001_01_01_000002_create_jobs_table',2),(19,'2026_07_30_000002_add_professional_project_management_features',3),(20,'2026_07_30_000003_merge_members_into_users',4),(21,'2026_07_30_000004_limit_system_roles_and_default_to_member',5),(22,'2026_07_31_000001_add_task_created_by_to_tasks_table',6),(23,'2026_07_31_000002_backfill_task_created_by',7),(24,'2026_07_31_000003_add_project_code_to_activities_table',8),(25,'2026_08_03_000001_add_deadline_governance',9),(26,'2026_08_03_000002_add_targets_to_notifications_table',10),(27,'2026_08_03_000003_create_project_notes_table',11);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `notif_code` varchar(50) NOT NULL,
  `notif_user_code` varchar(50) NOT NULL,
  `notif_title` varchar(255) NOT NULL,
  `notif_message` text NOT NULL,
  `notif_type` varchar(255) NOT NULL DEFAULT 'info',
  `notif_target_type` varchar(30) DEFAULT NULL,
  `notif_target_code` varchar(50) DEFAULT NULL,
  `notif_is_read` tinyint(1) NOT NULL DEFAULT 0,
  `notif_created_at` timestamp NULL DEFAULT NULL,
  `notif_updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`notif_code`),
  KEY `notif_user_code` (`notif_user_code`),
  KEY `notifications_user_target_index` (`notif_user_code`,`notif_target_type`,`notif_target_code`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`notif_user_code`) REFERENCES `users` (`user_code`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_automations`
--

DROP TABLE IF EXISTS `project_automations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_automations` (
  `automation_code` varchar(50) NOT NULL,
  `automation_project_code` varchar(50) DEFAULT NULL,
  `automation_rule` varchar(50) NOT NULL,
  `automation_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `automation_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`automation_config`)),
  `automation_created_at` timestamp NULL DEFAULT NULL,
  `automation_updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`automation_code`),
  KEY `project_automations_automation_project_code_foreign` (`automation_project_code`),
  CONSTRAINT `project_automations_automation_project_code_foreign` FOREIGN KEY (`automation_project_code`) REFERENCES `projects` (`project_code`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_automations`
--

LOCK TABLES `project_automations` WRITE;
/*!40000 ALTER TABLE `project_automations` DISABLE KEYS */;
/*!40000 ALTER TABLE `project_automations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_members`
--

DROP TABLE IF EXISTS `project_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_members` (
  `pm_code` varchar(50) NOT NULL,
  `pm_project_code` varchar(50) NOT NULL,
  `pm_member_code` varchar(50) NOT NULL,
  `pm_role` varchar(255) NOT NULL DEFAULT 'member',
  `pm_created_at` timestamp NULL DEFAULT NULL,
  `pm_updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`pm_code`),
  KEY `pm_project_code` (`pm_project_code`),
  KEY `project_members_pm_member_code_foreign` (`pm_member_code`),
  CONSTRAINT `project_members_ibfk_1` FOREIGN KEY (`pm_project_code`) REFERENCES `projects` (`project_code`) ON DELETE CASCADE,
  CONSTRAINT `project_members_pm_member_code_foreign` FOREIGN KEY (`pm_member_code`) REFERENCES `users` (`user_code`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_members`
--

LOCK TABLES `project_members` WRITE;
/*!40000 ALTER TABLE `project_members` DISABLE KEYS */;
/*!40000 ALTER TABLE `project_members` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_milestones`
--

DROP TABLE IF EXISTS `project_milestones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_milestones` (
  `milestone_code` varchar(50) NOT NULL,
  `milestone_project_code` varchar(50) NOT NULL,
  `milestone_name` varchar(255) NOT NULL,
  `milestone_description` text DEFAULT NULL,
  `milestone_target_date` date DEFAULT NULL,
  `milestone_sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `milestone_created_at` timestamp NULL DEFAULT NULL,
  `milestone_updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`milestone_code`),
  KEY `milestone_project_date_index` (`milestone_project_code`,`milestone_target_date`),
  CONSTRAINT `project_milestones_milestone_project_code_foreign` FOREIGN KEY (`milestone_project_code`) REFERENCES `projects` (`project_code`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_milestones`
--

LOCK TABLES `project_milestones` WRITE;
/*!40000 ALTER TABLE `project_milestones` DISABLE KEYS */;
/*!40000 ALTER TABLE `project_milestones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_notes`
--

DROP TABLE IF EXISTS `project_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_notes` (
  `note_code` varchar(50) NOT NULL,
  `note_project_code` varchar(50) NOT NULL,
  `note_author_code` varchar(50) NOT NULL,
  `note_title` varchar(255) NOT NULL,
  `note_content` text NOT NULL,
  `note_is_pinned` tinyint(1) NOT NULL DEFAULT 0,
  `note_created_at` timestamp NULL DEFAULT NULL,
  `note_updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`note_code`),
  KEY `project_notes_note_author_code_foreign` (`note_author_code`),
  KEY `project_notes_project_pinned_updated_index` (`note_project_code`,`note_is_pinned`,`note_updated_at`),
  CONSTRAINT `project_notes_note_author_code_foreign` FOREIGN KEY (`note_author_code`) REFERENCES `users` (`user_code`) ON DELETE CASCADE,
  CONSTRAINT `project_notes_note_project_code_foreign` FOREIGN KEY (`note_project_code`) REFERENCES `projects` (`project_code`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_notes`
--

LOCK TABLES `project_notes` WRITE;
/*!40000 ALTER TABLE `project_notes` DISABLE KEYS */;
/*!40000 ALTER TABLE `project_notes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_updates`
--

DROP TABLE IF EXISTS `project_updates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_updates` (
  `update_code` varchar(50) NOT NULL,
  `update_project_code` varchar(50) NOT NULL,
  `update_author_code` varchar(50) NOT NULL,
  `update_health` varchar(30) NOT NULL DEFAULT 'on_track',
  `update_completed` text DEFAULT NULL,
  `update_risks` text DEFAULT NULL,
  `update_next_steps` text DEFAULT NULL,
  `update_created_at` timestamp NULL DEFAULT NULL,
  `update_updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`update_code`),
  KEY `project_updates_update_author_code_foreign` (`update_author_code`),
  KEY `project_updates_update_project_code_update_created_at_index` (`update_project_code`,`update_created_at`),
  CONSTRAINT `project_updates_update_author_code_foreign` FOREIGN KEY (`update_author_code`) REFERENCES `users` (`user_code`) ON DELETE CASCADE,
  CONSTRAINT `project_updates_update_project_code_foreign` FOREIGN KEY (`update_project_code`) REFERENCES `projects` (`project_code`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_updates`
--

LOCK TABLES `project_updates` WRITE;
/*!40000 ALTER TABLE `project_updates` DISABLE KEYS */;
/*!40000 ALTER TABLE `project_updates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `projects`
--

DROP TABLE IF EXISTS `projects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `projects` (
  `project_code` varchar(50) NOT NULL,
  `project_customer_code` varchar(50) DEFAULT NULL,
  `project_name` varchar(255) NOT NULL,
  `project_description` text DEFAULT NULL,
  `project_color` varchar(30) NOT NULL DEFAULT 'indigo',
  `project_status` varchar(255) NOT NULL DEFAULT 'planning',
  `project_health` varchar(30) NOT NULL DEFAULT 'on_track',
  `project_update_cadence` varchar(30) NOT NULL DEFAULT 'weekly',
  `project_start_date` date DEFAULT NULL,
  `project_due_date` date DEFAULT NULL,
  `project_delay_reason` text DEFAULT NULL,
  `project_recovery_plan` text DEFAULT NULL,
  `project_completed_at` timestamp NULL DEFAULT NULL,
  `project_progress` int(11) DEFAULT 0,
  `project_created_by` varchar(50) NOT NULL,
  `project_manager_code` varchar(50) DEFAULT NULL,
  `project_created_at` timestamp NULL DEFAULT NULL,
  `project_updated_at` timestamp NULL DEFAULT NULL,
  `project_deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`project_code`),
  KEY `projects_project_deleted_at_index` (`project_deleted_at`),
  KEY `project_created_by` (`project_created_by`),
  KEY `project_customer_code` (`project_customer_code`),
  KEY `projects_project_health_index` (`project_health`),
  KEY `projects_project_manager_code_foreign` (`project_manager_code`),
  KEY `projects_project_completed_at_index` (`project_completed_at`),
  CONSTRAINT `projects_ibfk_1` FOREIGN KEY (`project_created_by`) REFERENCES `users` (`user_code`) ON DELETE CASCADE,
  CONSTRAINT `projects_ibfk_2` FOREIGN KEY (`project_customer_code`) REFERENCES `customers` (`customer_code`) ON DELETE SET NULL,
  CONSTRAINT `projects_project_manager_code_foreign` FOREIGN KEY (`project_manager_code`) REFERENCES `users` (`user_code`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `projects`
--

LOCK TABLES `projects` WRITE;
/*!40000 ALTER TABLE `projects` DISABLE KEYS */;
/*!40000 ALTER TABLE `projects` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `saved_views`
--

DROP TABLE IF EXISTS `saved_views`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `saved_views` (
  `view_code` varchar(50) NOT NULL,
  `view_user_code` varchar(50) NOT NULL,
  `view_name` varchar(255) NOT NULL,
  `view_scope` varchar(30) NOT NULL DEFAULT 'tasks',
  `view_filters` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`view_filters`)),
  `view_is_favorite` tinyint(1) NOT NULL DEFAULT 0,
  `view_created_at` timestamp NULL DEFAULT NULL,
  `view_updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`view_code`),
  KEY `saved_views_view_user_code_view_scope_index` (`view_user_code`,`view_scope`),
  CONSTRAINT `saved_views_view_user_code_foreign` FOREIGN KEY (`view_user_code`) REFERENCES `users` (`user_code`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `saved_views`
--

LOCK TABLES `saved_views` WRITE;
/*!40000 ALTER TABLE `saved_views` DISABLE KEYS */;
/*!40000 ALTER TABLE `saved_views` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `task_checklists`
--

DROP TABLE IF EXISTS `task_checklists`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `task_checklists` (
  `checklist_code` varchar(50) NOT NULL,
  `checklist_task_code` varchar(50) NOT NULL,
  `checklist_text` varchar(255) NOT NULL,
  `checklist_is_completed` tinyint(1) NOT NULL DEFAULT 0,
  `checklist_created_at` timestamp NULL DEFAULT NULL,
  `checklist_updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`checklist_code`),
  KEY `checklist_task_code` (`checklist_task_code`),
  CONSTRAINT `task_checklists_ibfk_1` FOREIGN KEY (`checklist_task_code`) REFERENCES `tasks` (`task_code`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `task_checklists`
--

LOCK TABLES `task_checklists` WRITE;
/*!40000 ALTER TABLE `task_checklists` DISABLE KEYS */;
/*!40000 ALTER TABLE `task_checklists` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `task_comments`
--

DROP TABLE IF EXISTS `task_comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `task_comments` (
  `comment_code` varchar(50) NOT NULL,
  `comment_task_code` varchar(50) NOT NULL,
  `comment_user_code` varchar(50) NOT NULL,
  `comment_text` text NOT NULL,
  `comment_file_url` varchar(255) DEFAULT NULL,
  `comment_file_name` varchar(255) DEFAULT NULL,
  `comment_created_at` timestamp NULL DEFAULT NULL,
  `comment_updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`comment_code`),
  KEY `comment_task_code` (`comment_task_code`),
  KEY `comment_user_code` (`comment_user_code`),
  CONSTRAINT `task_comments_ibfk_1` FOREIGN KEY (`comment_task_code`) REFERENCES `tasks` (`task_code`) ON DELETE CASCADE,
  CONSTRAINT `task_comments_ibfk_2` FOREIGN KEY (`comment_user_code`) REFERENCES `users` (`user_code`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `task_comments`
--

LOCK TABLES `task_comments` WRITE;
/*!40000 ALTER TABLE `task_comments` DISABLE KEYS */;
/*!40000 ALTER TABLE `task_comments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `task_dependencies`
--

DROP TABLE IF EXISTS `task_dependencies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `task_dependencies` (
  `dependency_code` varchar(50) NOT NULL,
  `dependency_task_code` varchar(50) NOT NULL,
  `dependency_depends_on_code` varchar(50) NOT NULL,
  `dependency_created_at` timestamp NULL DEFAULT NULL,
  `dependency_updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`dependency_code`),
  UNIQUE KEY `task_dependency_unique` (`dependency_task_code`,`dependency_depends_on_code`),
  KEY `task_dependencies_dependency_depends_on_code_foreign` (`dependency_depends_on_code`),
  CONSTRAINT `task_dependencies_dependency_depends_on_code_foreign` FOREIGN KEY (`dependency_depends_on_code`) REFERENCES `tasks` (`task_code`) ON DELETE CASCADE,
  CONSTRAINT `task_dependencies_dependency_task_code_foreign` FOREIGN KEY (`dependency_task_code`) REFERENCES `tasks` (`task_code`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `task_dependencies`
--

LOCK TABLES `task_dependencies` WRITE;
/*!40000 ALTER TABLE `task_dependencies` DISABLE KEYS */;
/*!40000 ALTER TABLE `task_dependencies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `task_watchers`
--

DROP TABLE IF EXISTS `task_watchers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `task_watchers` (
  `watcher_code` varchar(50) NOT NULL,
  `watcher_task_code` varchar(50) NOT NULL,
  `watcher_user_code` varchar(50) NOT NULL,
  `watcher_created_at` timestamp NULL DEFAULT NULL,
  `watcher_updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`watcher_code`),
  UNIQUE KEY `task_watchers_watcher_task_code_watcher_user_code_unique` (`watcher_task_code`,`watcher_user_code`),
  KEY `task_watchers_watcher_user_code_foreign` (`watcher_user_code`),
  CONSTRAINT `task_watchers_watcher_task_code_foreign` FOREIGN KEY (`watcher_task_code`) REFERENCES `tasks` (`task_code`) ON DELETE CASCADE,
  CONSTRAINT `task_watchers_watcher_user_code_foreign` FOREIGN KEY (`watcher_user_code`) REFERENCES `users` (`user_code`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `task_watchers`
--

LOCK TABLES `task_watchers` WRITE;
/*!40000 ALTER TABLE `task_watchers` DISABLE KEYS */;
/*!40000 ALTER TABLE `task_watchers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `task_work_logs`
--

DROP TABLE IF EXISTS `task_work_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `task_work_logs` (
  `worklog_code` varchar(50) NOT NULL,
  `worklog_task_code` varchar(50) NOT NULL,
  `worklog_reporter_code` varchar(50) DEFAULT NULL,
  `worklog_time` varchar(5) NOT NULL,
  `worklog_duration_minutes` int(10) unsigned DEFAULT NULL,
  `worklog_note` text DEFAULT NULL,
  `worklog_date` date NOT NULL,
  `worklog_completed_items` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`worklog_completed_items`)),
  `worklog_files` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`worklog_files`)),
  `worklog_created_at` timestamp NULL DEFAULT NULL,
  `worklog_updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`worklog_code`),
  KEY `worklog_task_code` (`worklog_task_code`),
  KEY `task_work_logs_worklog_reporter_code_foreign` (`worklog_reporter_code`),
  CONSTRAINT `task_work_logs_ibfk_1` FOREIGN KEY (`worklog_task_code`) REFERENCES `tasks` (`task_code`) ON DELETE CASCADE,
  CONSTRAINT `task_work_logs_worklog_reporter_code_foreign` FOREIGN KEY (`worklog_reporter_code`) REFERENCES `users` (`user_code`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `task_work_logs`
--

LOCK TABLES `task_work_logs` WRITE;
/*!40000 ALTER TABLE `task_work_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `task_work_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tasks`
--

DROP TABLE IF EXISTS `tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tasks` (
  `task_code` varchar(50) NOT NULL,
  `task_project_code` varchar(50) DEFAULT NULL,
  `task_milestone_code` varchar(50) DEFAULT NULL,
  `task_title` varchar(255) NOT NULL,
  `task_description` text DEFAULT NULL,
  `task_type` varchar(30) NOT NULL DEFAULT 'task',
  `task_status` varchar(255) NOT NULL DEFAULT 'todo',
  `task_priority` varchar(255) NOT NULL DEFAULT 'medium',
  `task_start_date` date DEFAULT NULL,
  `task_due_date` date DEFAULT NULL,
  `task_delay_reason` text DEFAULT NULL,
  `task_recovery_plan` text DEFAULT NULL,
  `task_progress` int(11) DEFAULT 0,
  `task_blocked_reason` text DEFAULT NULL,
  `task_blocked_override` tinyint(1) NOT NULL DEFAULT 0,
  `task_estimated_hours` decimal(8,2) DEFAULT NULL,
  `task_assignee_code` varchar(50) DEFAULT NULL,
  `task_created_by` varchar(50) DEFAULT NULL,
  `task_tags` varchar(500) DEFAULT NULL,
  `task_recurrence` varchar(30) DEFAULT NULL,
  `task_recurrence_until` date DEFAULT NULL,
  `task_created_at` timestamp NULL DEFAULT NULL,
  `task_updated_at` timestamp NULL DEFAULT NULL,
  `task_deleted_at` timestamp NULL DEFAULT NULL,
  `task_completed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`task_code`),
  KEY `tasks_task_deleted_at_index` (`task_deleted_at`),
  KEY `task_project_code` (`task_project_code`),
  KEY `tasks_task_milestone_code_index` (`task_milestone_code`),
  KEY `tasks_task_assignee_code_foreign` (`task_assignee_code`),
  KEY `tasks_task_created_by_index` (`task_created_by`),
  CONSTRAINT `tasks_ibfk_1` FOREIGN KEY (`task_project_code`) REFERENCES `projects` (`project_code`) ON DELETE CASCADE,
  CONSTRAINT `tasks_task_assignee_code_foreign` FOREIGN KEY (`task_assignee_code`) REFERENCES `users` (`user_code`) ON DELETE SET NULL,
  CONSTRAINT `tasks_task_created_by_foreign` FOREIGN KEY (`task_created_by`) REFERENCES `users` (`user_code`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tasks`
--

LOCK TABLES `tasks` WRITE;
/*!40000 ALTER TABLE `tasks` DISABLE KEYS */;
/*!40000 ALTER TABLE `tasks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `user_code` varchar(50) NOT NULL,
  `user_name` varchar(255) NOT NULL,
  `user_email` varchar(255) NOT NULL,
  `user_avatar` varchar(255) DEFAULT NULL,
  `user_color` varchar(30) NOT NULL DEFAULT 'blue',
  `user_role` varchar(255) NOT NULL DEFAULT 'member',
  `user_job_title` varchar(255) DEFAULT NULL,
  `user_phone` varchar(50) DEFAULT NULL,
  `user_department` varchar(255) DEFAULT NULL,
  `user_join_date` date DEFAULT NULL,
  `user_bio` text DEFAULT NULL,
  `user_online` tinyint(1) NOT NULL DEFAULT 1,
  `user_weekly_capacity_hours` decimal(6,2) NOT NULL DEFAULT 40.00,
  `user_profile_completed_at` timestamp NULL DEFAULT NULL,
  `user_notification_preferences` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`user_notification_preferences`)),
  `user_email_verified_at` timestamp NULL DEFAULT NULL,
  `user_password` varchar(255) NOT NULL,
  `user_api_token` varchar(64) DEFAULT NULL,
  `user_remember_token` varchar(100) DEFAULT NULL,
  `user_created_at` timestamp NULL DEFAULT NULL,
  `user_updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`user_code`),
  UNIQUE KEY `users_email_unique` (`user_email`),
  UNIQUE KEY `users_api_token_unique` (`user_api_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES ('US0001','Quản trị viên RingNet','admin@example.com',NULL,'violet','admin','Quản trị hệ thống','0900000001','Ban điều hành','2026-07-30',NULL,1,40.00,'2026-07-30 09:49:01',NULL,NULL,'$2y$12$/JCK9UvT4p7yWCHpD4j3MOVuKOEYeLmO2QutOIRC4Cv5mkJvIhP46','37424c70b4a6c97e71a338e581b2a238e9fbc517d15812f47ec0dab106cb767a',NULL,'2026-07-30 09:49:01','2026-08-04 03:31:01'),('US0002','Quản lý dự án','manager@example.com',NULL,'indigo','project_manager','Project Manager','0900000002','Quản lý dự án','2026-07-30',NULL,1,40.00,'2026-07-30 09:49:01',NULL,NULL,'$2y$12$Ohs2Q/3u1Ngf.cqXDEMlZu0wMHRn1psYiYsn7.qxQPNRVrtgw5gVS',NULL,NULL,'2026-07-30 09:49:01','2026-08-04 02:55:48'),('US0003','Nhân viên kiểm thử','employee@example.com',NULL,'emerald','member','Nhân viên','0900000003','Phát triển sản phẩm','2026-07-30',NULL,1,40.00,'2026-07-30 09:49:01',NULL,NULL,'$2y$12$fozL2rXQbVWq0JfCNYum2ePz3V4JVnTdWzX/JjKrJH4Tqtch9bqJu',NULL,NULL,'2026-07-30 09:49:01','2026-08-04 03:30:53'),('US0004','Khách Hàng Demo','khachhang@ringnet.vn',NULL,'emerald','customer','Khách hàng','0900000099','Khách hàng','2026-08-04',NULL,1,0.00,'2026-08-04 02:47:24',NULL,NULL,'$2y$12$NimiBvT3kF4CvJYx1vk53OI/tKvBzJG5uB2j0H34ZksdVWS.Wf8Tq',NULL,NULL,'2026-08-04 02:47:24','2026-08-04 03:06:16');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-05 16:45:55
