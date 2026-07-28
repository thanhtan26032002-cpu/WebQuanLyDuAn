-- Chạy một lần trên database đã được tạo từ database.sql của phiên bản cũ.
-- Script có thể chạy lại an toàn trên MySQL/MariaDB.

SET @project_color_exists = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'projects'
    AND COLUMN_NAME = 'project_color'
);

SET @project_color_sql = IF(
  @project_color_exists = 0,
  'ALTER TABLE `projects` ADD COLUMN `project_color` varchar(30) NOT NULL DEFAULT ''indigo'' AFTER `project_description`',
  'SELECT ''projects.project_color already exists'' AS message'
);

PREPARE project_color_statement FROM @project_color_sql;
EXECUTE project_color_statement;
DEALLOCATE PREPARE project_color_statement;
