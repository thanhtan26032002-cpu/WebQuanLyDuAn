-- Them xoa mem cho du an va nhiem vu tren database hien co.
-- Du lieu duoc giu nguyen; thoi diem xoa dung de gioi han khoi phuc 30 ngay.

SET @project_deleted_at_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'projects'
    AND COLUMN_NAME = 'project_deleted_at'
);
SET @project_deleted_at_sql = IF(
  @project_deleted_at_exists = 0,
  'ALTER TABLE `projects` ADD COLUMN `project_deleted_at` timestamp NULL DEFAULT NULL AFTER `project_updated_at`, ADD INDEX `projects_project_deleted_at_index` (`project_deleted_at`)',
  'SELECT ''projects.project_deleted_at already exists'' AS message'
);
PREPARE project_deleted_at_statement FROM @project_deleted_at_sql;
EXECUTE project_deleted_at_statement;
DEALLOCATE PREPARE project_deleted_at_statement;

SET @task_deleted_at_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'tasks'
    AND COLUMN_NAME = 'task_deleted_at'
);
SET @task_deleted_at_sql = IF(
  @task_deleted_at_exists = 0,
  'ALTER TABLE `tasks` ADD COLUMN `task_deleted_at` timestamp NULL DEFAULT NULL AFTER `task_updated_at`, ADD INDEX `tasks_task_deleted_at_index` (`task_deleted_at`)',
  'SELECT ''tasks.task_deleted_at already exists'' AS message'
);
PREPARE task_deleted_at_statement FROM @task_deleted_at_sql;
EXECUTE task_deleted_at_statement;
DEALLOCATE PREPARE task_deleted_at_statement;

CREATE TABLE IF NOT EXISTS `task_checklists` (
  `checklist_code` varchar(50) NOT NULL,
  `checklist_task_code` varchar(50) NOT NULL,
  `checklist_text` varchar(255) NOT NULL,
  `checklist_is_completed` tinyint(1) NOT NULL DEFAULT 0,
  `checklist_created_at` timestamp NULL DEFAULT NULL,
  `checklist_updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`checklist_code`),
  CONSTRAINT `task_checklists_task_fk` FOREIGN KEY (`checklist_task_code`) REFERENCES `tasks` (`task_code`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `task_work_logs` (
  `worklog_code` varchar(50) NOT NULL,
  `worklog_task_code` varchar(50) NOT NULL,
  `worklog_reporter_code` varchar(50) DEFAULT NULL,
  `worklog_time` varchar(5) NOT NULL,
  `worklog_note` text,
  `worklog_date` date NOT NULL,
  `worklog_completed_items` json DEFAULT NULL,
  `worklog_files` json DEFAULT NULL,
  `worklog_created_at` timestamp NULL DEFAULT NULL,
  `worklog_updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`worklog_code`),
  CONSTRAINT `task_work_logs_task_fk` FOREIGN KEY (`worklog_task_code`) REFERENCES `tasks` (`task_code`) ON DELETE CASCADE,
  CONSTRAINT `task_work_logs_reporter_fk` FOREIGN KEY (`worklog_reporter_code`) REFERENCES `members` (`member_code`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
