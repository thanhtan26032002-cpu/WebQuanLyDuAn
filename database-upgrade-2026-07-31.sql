-- Bo sung chu so huu cho nhiem vu de phan quyen nhiem vu doc lap.
-- Script co the chay tren database MySQL hien co va giu nguyen du lieu.

SET @task_created_by_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'tasks'
    AND COLUMN_NAME = 'task_created_by'
);
SET @task_created_by_sql = IF(
  @task_created_by_exists = 0,
  'ALTER TABLE `tasks` ADD COLUMN `task_created_by` varchar(50) NULL AFTER `task_assignee_code`, ADD INDEX `tasks_task_created_by_index` (`task_created_by`)',
  'SELECT ''tasks.task_created_by already exists'' AS message'
);
PREPARE task_created_by_statement FROM @task_created_by_sql;
EXECUTE task_created_by_statement;
DEALLOCATE PREPARE task_created_by_statement;

SET @fallback_task_owner = (
  SELECT `user_code`
  FROM `users`
  WHERE `user_role` IN ('admin', 'project_manager')
  ORDER BY CASE WHEN `user_role` = 'admin' THEN 0 ELSE 1 END, `user_created_at`
  LIMIT 1
);

UPDATE `tasks` AS task
LEFT JOIN `projects` AS project
  ON project.`project_code` = task.`task_project_code`
LEFT JOIN `users` AS assignee
  ON assignee.`user_code` = task.`task_assignee_code`
SET task.`task_created_by` = COALESCE(
  project.`project_created_by`,
  CASE
    WHEN assignee.`user_role` IN ('admin', 'project_manager') THEN assignee.`user_code`
    ELSE NULL
  END,
  @fallback_task_owner
)
WHERE task.`task_created_by` IS NULL;

SET @task_created_by_fk_exists = (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'tasks'
    AND CONSTRAINT_NAME = 'tasks_task_created_by_foreign'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @task_created_by_fk_sql = IF(
  @task_created_by_fk_exists = 0,
  'ALTER TABLE `tasks` ADD CONSTRAINT `tasks_task_created_by_foreign` FOREIGN KEY (`task_created_by`) REFERENCES `users` (`user_code`) ON DELETE SET NULL',
  'SELECT ''tasks_task_created_by_foreign already exists'' AS message'
);
PREPARE task_created_by_fk_statement FROM @task_created_by_fk_sql;
EXECUTE task_created_by_fk_statement;
DEALLOCATE PREPARE task_created_by_fk_statement;

-- Gan truc tiep moi nhat ky voi du an de luu du lich su cua ca nhiem vu.
SET @activity_project_code_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'activities'
    AND COLUMN_NAME = 'activity_project_code'
);
SET @activity_project_code_sql = IF(
  @activity_project_code_exists = 0,
  'ALTER TABLE `activities` ADD COLUMN `activity_project_code` varchar(50) NULL AFTER `activity_user_code`, ADD INDEX `activities_project_created_index` (`activity_project_code`, `activity_created_at`)',
  'SELECT ''activities.activity_project_code already exists'' AS message'
);
PREPARE activity_project_code_statement FROM @activity_project_code_sql;
EXECUTE activity_project_code_statement;
DEALLOCATE PREPARE activity_project_code_statement;

UPDATE `activities` AS activity
INNER JOIN `projects` AS project
  ON project.`project_code` = activity.`activity_target_code`
SET activity.`activity_project_code` = project.`project_code`
WHERE activity.`activity_project_code` IS NULL
  AND activity.`activity_target_type` = 'Project';

UPDATE `activities` AS activity
INNER JOIN `tasks` AS task
  ON task.`task_code` = activity.`activity_target_code`
SET activity.`activity_project_code` = task.`task_project_code`
WHERE activity.`activity_project_code` IS NULL
  AND activity.`activity_target_type` IN ('Task', 'TaskComment')
  AND task.`task_project_code` IS NOT NULL;

SET @activity_project_fk_exists = (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'activities'
    AND CONSTRAINT_NAME = 'activities_activity_project_code_foreign'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @activity_project_fk_sql = IF(
  @activity_project_fk_exists = 0,
  'ALTER TABLE `activities` ADD CONSTRAINT `activities_activity_project_code_foreign` FOREIGN KEY (`activity_project_code`) REFERENCES `projects` (`project_code`) ON DELETE SET NULL',
  'SELECT ''activities_activity_project_code_foreign already exists'' AS message'
);
PREPARE activity_project_fk_statement FROM @activity_project_fk_sql;
EXECUTE activity_project_fk_statement;
DEALLOCATE PREPARE activity_project_fk_statement;

-- Quan tri qua han, ke hoach khac phuc va lich su gia han.
SET @project_delay_reason_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = 'project_delay_reason'
);
SET @project_delay_reason_sql = IF(
  @project_delay_reason_exists = 0,
  'ALTER TABLE `projects` ADD COLUMN `project_delay_reason` text NULL AFTER `project_due_date`',
  'SELECT ''projects.project_delay_reason already exists'' AS message'
);
PREPARE project_delay_reason_statement FROM @project_delay_reason_sql;
EXECUTE project_delay_reason_statement;
DEALLOCATE PREPARE project_delay_reason_statement;

SET @project_recovery_plan_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = 'project_recovery_plan'
);
SET @project_recovery_plan_sql = IF(
  @project_recovery_plan_exists = 0,
  'ALTER TABLE `projects` ADD COLUMN `project_recovery_plan` text NULL AFTER `project_delay_reason`',
  'SELECT ''projects.project_recovery_plan already exists'' AS message'
);
PREPARE project_recovery_plan_statement FROM @project_recovery_plan_sql;
EXECUTE project_recovery_plan_statement;
DEALLOCATE PREPARE project_recovery_plan_statement;

SET @project_completed_at_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = 'project_completed_at'
);
SET @project_completed_at_sql = IF(
  @project_completed_at_exists = 0,
  'ALTER TABLE `projects` ADD COLUMN `project_completed_at` timestamp NULL AFTER `project_recovery_plan`, ADD INDEX `projects_project_completed_at_index` (`project_completed_at`)',
  'SELECT ''projects.project_completed_at already exists'' AS message'
);
PREPARE project_completed_at_statement FROM @project_completed_at_sql;
EXECUTE project_completed_at_statement;
DEALLOCATE PREPARE project_completed_at_statement;

SET @task_delay_reason_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tasks' AND COLUMN_NAME = 'task_delay_reason'
);
SET @task_delay_reason_sql = IF(
  @task_delay_reason_exists = 0,
  'ALTER TABLE `tasks` ADD COLUMN `task_delay_reason` text NULL AFTER `task_due_date`',
  'SELECT ''tasks.task_delay_reason already exists'' AS message'
);
PREPARE task_delay_reason_statement FROM @task_delay_reason_sql;
EXECUTE task_delay_reason_statement;
DEALLOCATE PREPARE task_delay_reason_statement;

SET @task_recovery_plan_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tasks' AND COLUMN_NAME = 'task_recovery_plan'
);
SET @task_recovery_plan_sql = IF(
  @task_recovery_plan_exists = 0,
  'ALTER TABLE `tasks` ADD COLUMN `task_recovery_plan` text NULL AFTER `task_delay_reason`',
  'SELECT ''tasks.task_recovery_plan already exists'' AS message'
);
PREPARE task_recovery_plan_statement FROM @task_recovery_plan_sql;
EXECUTE task_recovery_plan_statement;
DEALLOCATE PREPARE task_recovery_plan_statement;

CREATE TABLE IF NOT EXISTS `deadline_extensions` (
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
  KEY `deadline_extension_target_index` (`extension_target_type`,`extension_target_code`,`extension_created_at`),
  KEY `deadline_extensions_extension_created_by_foreign` (`extension_created_by`),
  CONSTRAINT `deadline_extensions_extension_created_by_foreign`
    FOREIGN KEY (`extension_created_by`) REFERENCES `users` (`user_code`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE `projects`
SET `project_completed_at` = `project_updated_at`
WHERE `project_status` = 'completed'
  AND `project_completed_at` IS NULL;

-- Dinh tuyen thong bao den dung du an hoac nhiem vu.
SET @notification_target_type_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications' AND COLUMN_NAME = 'notif_target_type'
);
SET @notification_target_type_sql = IF(
  @notification_target_type_exists = 0,
  'ALTER TABLE `notifications` ADD COLUMN `notif_target_type` varchar(30) NULL AFTER `notif_type`',
  'SELECT ''notifications.notif_target_type already exists'' AS message'
);
PREPARE notification_target_type_statement FROM @notification_target_type_sql;
EXECUTE notification_target_type_statement;
DEALLOCATE PREPARE notification_target_type_statement;

SET @notification_target_code_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications' AND COLUMN_NAME = 'notif_target_code'
);
SET @notification_target_code_sql = IF(
  @notification_target_code_exists = 0,
  'ALTER TABLE `notifications` ADD COLUMN `notif_target_code` varchar(50) NULL AFTER `notif_target_type`',
  'SELECT ''notifications.notif_target_code already exists'' AS message'
);
PREPARE notification_target_code_statement FROM @notification_target_code_sql;
EXECUTE notification_target_code_statement;
DEALLOCATE PREPARE notification_target_code_statement;

SET @notification_target_index_exists = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications' AND INDEX_NAME = 'notifications_user_target_index'
);
SET @notification_target_index_sql = IF(
  @notification_target_index_exists = 0,
  'ALTER TABLE `notifications` ADD INDEX `notifications_user_target_index` (`notif_user_code`, `notif_target_type`, `notif_target_code`)',
  'SELECT ''notifications_user_target_index already exists'' AS message'
);
PREPARE notification_target_index_statement FROM @notification_target_index_sql;
EXECUTE notification_target_index_statement;
DEALLOCATE PREPARE notification_target_index_statement;
