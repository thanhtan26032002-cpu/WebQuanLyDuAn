-- RingNet - bo sung ghi chu dung chung trong du an.
-- Co the chay lai an toan tren MySQL 8+.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `project_notes` (
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
  CONSTRAINT `project_notes_note_project_code_foreign`
    FOREIGN KEY (`note_project_code`) REFERENCES `projects` (`project_code`) ON DELETE CASCADE,
  CONSTRAINT `project_notes_note_author_code_foreign`
    FOREIGN KEY (`note_author_code`) REFERENCES `users` (`user_code`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
