CREATE DATABASE IF NOT EXISTS `web_quan_ly_du_an` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `web_quan_ly_du_an`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `notifications`, `attachments`, `task_comments`, `activities`, `tasks`, `project_members`, `projects`, `groups`, `members`, `users`;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE IF NOT EXISTS `users` (
  `user_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_avatar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_role` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'admin',
  `user_phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_department` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_email_verified_at` timestamp NULL DEFAULT NULL,
  `user_password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_api_token` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_created_at` timestamp NULL DEFAULT NULL,
  `user_updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`user_code`),
  UNIQUE KEY `users_email_unique` (`user_email`),
  UNIQUE KEY `users_api_token_unique` (`user_api_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `members` (
  `member_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `member_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `member_email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `member_avatar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `member_role` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'member',
  `member_phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `member_department` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `member_join_date` date DEFAULT NULL,
  `member_bio` text COLLATE utf8mb4_unicode_ci,
  `member_online` tinyint(1) NOT NULL DEFAULT 1,
  `member_created_at` timestamp NULL DEFAULT NULL,
  `member_updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`member_code`),
  UNIQUE KEY `members_email_unique` (`member_email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `groups` (
  `group_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `group_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `group_description` text COLLATE utf8mb4_unicode_ci,
  `group_icon` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `group_color` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'violet',
  `group_member_ids` json DEFAULT NULL,
  `group_created_at` timestamp NULL DEFAULT NULL,
  `group_updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`group_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `projects` (
  `project_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `project_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `project_description` text COLLATE utf8mb4_unicode_ci,
  `project_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'planning',
  `project_start_date` date DEFAULT NULL,
  `project_due_date` date DEFAULT NULL,
  `project_progress` int(11) DEFAULT 0,
  `project_created_by` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `project_created_at` timestamp NULL DEFAULT NULL,
  `project_updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`project_code`),
  FOREIGN KEY (`project_created_by`) REFERENCES `users` (`user_code`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `project_members` (
  `pm_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pm_project_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pm_member_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pm_role` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'member',
  `pm_created_at` timestamp NULL DEFAULT NULL,
  `pm_updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`pm_code`),
  FOREIGN KEY (`pm_project_code`) REFERENCES `projects` (`project_code`) ON DELETE CASCADE,
  FOREIGN KEY (`pm_member_code`) REFERENCES `members` (`member_code`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tasks` (
  `task_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `task_project_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `task_title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `task_description` text COLLATE utf8mb4_unicode_ci,
  `task_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'todo',
  `task_priority` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium',
  `task_due_date` date DEFAULT NULL,
  `task_progress` int(11) DEFAULT 0,
  `task_assignee_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `task_tags` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `task_created_at` timestamp NULL DEFAULT NULL,
  `task_updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`task_code`),
  FOREIGN KEY (`task_project_code`) REFERENCES `projects` (`project_code`) ON DELETE CASCADE,
  FOREIGN KEY (`task_assignee_code`) REFERENCES `members` (`member_code`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `activities` (
  `activity_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `activity_user_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `activity_action` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `activity_target_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `activity_target_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `activity_detail` text COLLATE utf8mb4_unicode_ci,
  `activity_created_at` timestamp NULL DEFAULT NULL,
  `activity_updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`activity_code`),
  FOREIGN KEY (`activity_user_code`) REFERENCES `users` (`user_code`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `task_comments` (
  `comment_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `comment_task_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `comment_user_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `comment_text` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `comment_file_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comment_file_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comment_created_at` timestamp NULL DEFAULT NULL,
  `comment_updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`comment_code`),
  FOREIGN KEY (`comment_task_code`) REFERENCES `tasks` (`task_code`) ON DELETE CASCADE,
  FOREIGN KEY (`comment_user_code`) REFERENCES `users` (`user_code`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `attachments` (
  `attachment_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `attachment_file_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `attachment_file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `attachment_mime_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attachment_size_bytes` int(11) DEFAULT NULL,
  `attachment_target_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `attachment_target_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `attachment_uploaded_by` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `attachment_created_at` timestamp NULL DEFAULT NULL,
  `attachment_updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`attachment_code`),
  FOREIGN KEY (`attachment_uploaded_by`) REFERENCES `users` (`user_code`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notifications` (
  `notif_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notif_user_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notif_title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notif_message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `notif_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'info',
  `notif_is_read` tinyint(1) NOT NULL DEFAULT 0,
  `notif_created_at` timestamp NULL DEFAULT NULL,
  `notif_updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`notif_code`),
  FOREIGN KEY (`notif_user_code`) REFERENCES `users` (`user_code`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` (`user_code`, `user_name`, `user_email`, `user_role`, `user_password`, `user_created_at`, `user_updated_at`) VALUES
('US0001', 'Quản trị viên', 'admin@example.com', 'admin', '$2y$12$zSmAfCk/CeIQ8ydoDry2yuDu2n3P0Fa3NaLOnMM6uXMlNOjnx2kxy', '2026-07-23 00:00:00', '2026-07-23 00:00:00');
