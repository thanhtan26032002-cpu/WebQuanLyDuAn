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
