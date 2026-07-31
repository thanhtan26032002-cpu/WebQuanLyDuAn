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
