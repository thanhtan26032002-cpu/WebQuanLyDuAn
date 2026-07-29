-- Nâng cấp database hiện có với khách hàng và các trường lập kế hoạch.
-- Với Laravel, ưu tiên chạy: php artisan migrate

CREATE TABLE IF NOT EXISTS `customers` (
  `customer_code` varchar(50) NOT NULL,
  `customer_name` varchar(255) NOT NULL,
  `customer_company` varchar(255) DEFAULT NULL,
  `customer_email` varchar(255) DEFAULT NULL,
  `customer_phone` varchar(50) DEFAULT NULL,
  `customer_address` text,
  `customer_notes` text,
  `customer_created_at` timestamp NULL DEFAULT NULL,
  `customer_updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`customer_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `projects`
  ADD COLUMN `project_customer_code` varchar(50) NULL AFTER `project_code`,
  ADD COLUMN `project_manager_code` varchar(50) NULL AFTER `project_created_by`,
  ADD CONSTRAINT `projects_customer_fk` FOREIGN KEY (`project_customer_code`) REFERENCES `customers` (`customer_code`) ON DELETE SET NULL,
  ADD CONSTRAINT `projects_manager_fk` FOREIGN KEY (`project_manager_code`) REFERENCES `members` (`member_code`) ON DELETE SET NULL;

-- Nếu đã chạy phiên bản trước của bản nâng cấp ngày 29/07, loại bỏ cột ngân sách.
ALTER TABLE `projects` DROP COLUMN IF EXISTS `project_budget`;

ALTER TABLE `tasks`
  ADD COLUMN `task_type` varchar(30) NOT NULL DEFAULT 'task' AFTER `task_description`,
  ADD COLUMN `task_start_date` date NULL AFTER `task_priority`,
  ADD COLUMN `task_estimated_hours` decimal(8,2) NULL AFTER `task_progress`;
