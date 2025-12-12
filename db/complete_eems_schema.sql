-- ================================================================
-- EEMS Complete Schema Migration
-- Adds all missing tables and columns for full functionality
-- Run this after the initial migration_align_schema.sql
-- ================================================================

USE eems;

-- ================================================================
-- STEP 1: Create exam_roles table (defines roles needed for each exam)
-- ================================================================
CREATE TABLE IF NOT EXISTS `exam_roles` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `exam_id` INT(11) NOT NULL,
  `role_type` ENUM('moderator','evaluator','invigilator','paper_setter','external_examiner') NOT NULL,
  `required_count` INT(11) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_exam` (`exam_id`),
  CONSTRAINT `fk_exam_roles_exam` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ================================================================
-- STEP 2: Create exam_invites table (tracks invitations to examiners)
-- ================================================================
CREATE TABLE IF NOT EXISTS `exam_invites` (
  `invite_id` INT(11) NOT NULL AUTO_INCREMENT,
  `exam_id` INT(11) NOT NULL,
  `invitee_user_id` INT(11) NULL COMMENT 'NULL if external email only',
  `invitee_email` VARCHAR(255) NOT NULL,
  `invitee_name` VARCHAR(255) NULL,
  `role` ENUM('moderator','evaluator','invigilator','paper_setter','external_examiner') NOT NULL,
  `token` VARCHAR(64) NOT NULL UNIQUE,
  `status` ENUM('pending','accepted','declined','expired') DEFAULT 'pending',
  `invited_on` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `responded_on` TIMESTAMP NULL,
  `response` TEXT NULL COMMENT 'Accept/Decline message from invitee',
  `response_comment` TEXT NULL,
  `availability_dates` JSON NULL COMMENT 'Available date ranges from invitee',
  `created_by` INT(11) NULL,
  PRIMARY KEY (`invite_id`),
  KEY `idx_exam` (`exam_id`),
  KEY `idx_invitee` (`invitee_user_id`),
  KEY `idx_token` (`token`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_exam_invites_exam` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_exam_invites_user` FOREIGN KEY (`invitee_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_exam_invites_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ================================================================
-- STEP 3: Add missing columns to exams table
-- ================================================================
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_SCHEMA = 'eems' AND TABLE_NAME = 'exams' AND COLUMN_NAME = 'attachments_meta');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `exams` ADD COLUMN `attachments_meta` JSON NULL AFTER `description`', 'SELECT "Column attachments_meta exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Update exams status ENUM to include all required states
ALTER TABLE `exams` 
  MODIFY COLUMN `status` ENUM('draft','submitted','approved','rejected','assigned','in_progress','completed','cancelled') DEFAULT 'draft';

-- ================================================================
-- STEP 4: Create ratings table (for external examiner feedback)
-- ================================================================
CREATE TABLE IF NOT EXISTS `ratings` (
  `rating_id` INT(11) NOT NULL AUTO_INCREMENT,
  `examiner_id` INT(11) NOT NULL COMMENT 'References external_examiners or users',
  `exam_id` INT(11) NULL COMMENT 'Optional: link to specific exam',
  `rated_by_user_id` INT(11) NOT NULL,
  `rated_by_role` VARCHAR(50) NOT NULL,
  `college_id` INT(11) NULL,
  `score` DECIMAL(3,2) NOT NULL CHECK (score >= 1.0 AND score <= 5.0),
  `comments` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`rating_id`),
  KEY `idx_examiner` (`examiner_id`),
  KEY `idx_exam` (`exam_id`),
  KEY `idx_rated_by` (`rated_by_user_id`),
  KEY `idx_college` (`college_id`),
  CONSTRAINT `fk_ratings_exam` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ratings_rater` FOREIGN KEY (`rated_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ================================================================
-- STEP 5: Add missing columns to external_examiners table
-- ================================================================
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_SCHEMA = 'eems' AND TABLE_NAME = 'external_examiners' AND COLUMN_NAME = 'email');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `external_examiners` ADD COLUMN `email` VARCHAR(255) NULL AFTER `name`', 'SELECT "Column email exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_SCHEMA = 'eems' AND TABLE_NAME = 'external_examiners' AND COLUMN_NAME = 'institution');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `external_examiners` ADD COLUMN `institution` VARCHAR(255) NULL AFTER `email`', 'SELECT "Column institution exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_SCHEMA = 'eems' AND TABLE_NAME = 'external_examiners' AND COLUMN_NAME = 'origin_college');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `external_examiners` ADD COLUMN `origin_college` VARCHAR(255) NULL AFTER `institution`', 'SELECT "Column origin_college exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ================================================================
-- STEP 6: Create college_settings table
-- ================================================================
CREATE TABLE IF NOT EXISTS `college_settings` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `college_id` INT(11) NOT NULL,
  `settings_json` JSON NULL COMMENT 'Notification prefs, timezone, approval rules, etc.',
  `timezone` VARCHAR(50) DEFAULT 'Asia/Kolkata',
  `notification_enabled` TINYINT(1) DEFAULT 1,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` INT(11) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_college` (`college_id`),
  CONSTRAINT `fk_college_settings_college` FOREIGN KEY (`college_id`) REFERENCES `colleges` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_college_settings_updater` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ================================================================
-- STEP 7: Create question_papers table
-- ================================================================
CREATE TABLE IF NOT EXISTS `question_papers` (
  `paper_id` INT(11) NOT NULL AUTO_INCREMENT,
  `exam_id` INT(11) NOT NULL,
  `created_by` INT(11) NOT NULL,
  `status` ENUM('draft','submitted','approved','locked') DEFAULT 'draft',
  `content_location` VARCHAR(500) NULL COMMENT 'File path or cloud URL',
  `co_po_mapping_json` JSON NULL COMMENT 'Course Outcome to Program Outcome mapping',
  `locked_by` INT(11) NULL,
  `locked_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`paper_id`),
  KEY `idx_exam` (`exam_id`),
  KEY `idx_creator` (`created_by`),
  KEY `idx_locker` (`locked_by`),
  CONSTRAINT `fk_question_papers_exam` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_question_papers_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_question_papers_locker` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ================================================================
-- STEP 8: Create practical_sessions table
-- ================================================================
CREATE TABLE IF NOT EXISTS `practical_sessions` (
  `session_id` INT(11) NOT NULL AUTO_INCREMENT,
  `exam_id` INT(11) NOT NULL,
  `college_id` INT(11) NOT NULL,
  `lab_id` VARCHAR(100) NULL,
  `start_time` DATETIME NOT NULL,
  `end_time` DATETIME NOT NULL,
  `status` ENUM('scheduled','in_progress','completed','cancelled') DEFAULT 'scheduled',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`session_id`),
  KEY `idx_exam` (`exam_id`),
  KEY `idx_college` (`college_id`),
  KEY `idx_start_time` (`start_time`),
  CONSTRAINT `fk_practical_sessions_exam` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ================================================================
-- STEP 9: Create practical_attempts table
-- ================================================================
CREATE TABLE IF NOT EXISTS `practical_attempts` (
  `attempt_id` INT(11) NOT NULL AUTO_INCREMENT,
  `session_id` INT(11) NOT NULL,
  `student_id` VARCHAR(100) NOT NULL,
  `slip_id` VARCHAR(100) NOT NULL UNIQUE COMMENT 'Unique slip identifier',
  `recording_path` VARCHAR(500) NULL COMMENT 'Desktop recording file path',
  `outputs_path` VARCHAR(500) NULL COMMENT 'Student outputs/code path',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `finalized_at` TIMESTAMP NULL,
  `marks` DECIMAL(5,2) NULL,
  `evaluated_by` INT(11) NULL,
  `evaluation_comments` TEXT NULL,
  PRIMARY KEY (`attempt_id`),
  KEY `idx_session` (`session_id`),
  KEY `idx_student` (`student_id`),
  KEY `idx_slip` (`slip_id`),
  KEY `idx_evaluator` (`evaluated_by`),
  CONSTRAINT `fk_practical_attempts_session` FOREIGN KEY (`session_id`) REFERENCES `practical_sessions` (`session_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_practical_attempts_evaluator` FOREIGN KEY (`evaluated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ================================================================
-- STEP 10: Add columns to approvals table to match spec
-- ================================================================
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_SCHEMA = 'eems' AND TABLE_NAME = 'approvals' AND COLUMN_NAME = 'exam_id');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `approvals` ADD COLUMN `exam_id` INT(11) NULL AFTER `id`', 'SELECT "Column exam_id exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_SCHEMA = 'eems' AND TABLE_NAME = 'approvals' AND COLUMN_NAME = 'approver_id');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `approvals` ADD COLUMN `approver_id` INT(11) NULL AFTER `exam_id`', 'SELECT "Column approver_id exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_SCHEMA = 'eems' AND TABLE_NAME = 'approvals' AND COLUMN_NAME = 'approver_role');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `approvals` ADD COLUMN `approver_role` VARCHAR(50) NULL AFTER `approver_id`', 'SELECT "Column approver_role exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_SCHEMA = 'eems' AND TABLE_NAME = 'approvals' AND COLUMN_NAME = 'decision');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `approvals` ADD COLUMN `decision` ENUM(''approved'',''rejected'',''changes_requested'') NULL AFTER `approver_role`', 'SELECT "Column decision exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_SCHEMA = 'eems' AND TABLE_NAME = 'approvals' AND COLUMN_NAME = 'comments');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `approvals` ADD COLUMN `comments` TEXT NULL AFTER `decision`', 'SELECT "Column comments exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add indexes to approvals table
SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
  WHERE TABLE_SCHEMA = 'eems' AND TABLE_NAME = 'approvals' AND INDEX_NAME = 'idx_exam_id');
SET @sql = IF(@idx_exists = 0, 'CREATE INDEX `idx_exam_id` ON `approvals` (`exam_id`)', 'SELECT "Index idx_exam_id exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
  WHERE TABLE_SCHEMA = 'eems' AND TABLE_NAME = 'approvals' AND INDEX_NAME = 'idx_approver');
SET @sql = IF(@idx_exists = 0, 'CREATE INDEX `idx_approver` ON `approvals` (`approver_id`)', 'SELECT "Index idx_approver exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ================================================================
-- STEP 11: Add columns to audit_logs table to match spec
-- ================================================================
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_SCHEMA = 'eems' AND TABLE_NAME = 'audit_logs' AND COLUMN_NAME = 'resource_type');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `audit_logs` ADD COLUMN `resource_type` VARCHAR(50) NULL AFTER `id`', 'SELECT "Column resource_type exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_SCHEMA = 'eems' AND TABLE_NAME = 'audit_logs' AND COLUMN_NAME = 'resource_id');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `audit_logs` ADD COLUMN `resource_id` INT(11) NULL AFTER `resource_type`', 'SELECT "Column resource_id exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_SCHEMA = 'eems' AND TABLE_NAME = 'audit_logs' AND COLUMN_NAME = 'user_id');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `audit_logs` ADD COLUMN `user_id` INT(11) NULL AFTER `resource_id`', 'SELECT "Column user_id exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_SCHEMA = 'eems' AND TABLE_NAME = 'audit_logs' AND COLUMN_NAME = 'metadata_json');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `audit_logs` ADD COLUMN `metadata_json` JSON NULL AFTER `details`', 'SELECT "Column metadata_json exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add indexes to audit_logs table
SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
  WHERE TABLE_SCHEMA = 'eems' AND TABLE_NAME = 'audit_logs' AND INDEX_NAME = 'idx_resource');
SET @sql = IF(@idx_exists = 0, 'CREATE INDEX `idx_resource` ON `audit_logs` (`resource_type`, `resource_id`)', 'SELECT "Index idx_resource exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
  WHERE TABLE_SCHEMA = 'eems' AND TABLE_NAME = 'audit_logs' AND INDEX_NAME = 'idx_user_action');
SET @sql = IF(@idx_exists = 0, 'CREATE INDEX `idx_user_action` ON `audit_logs` (`user_id`, `action`)', 'SELECT "Index idx_user_action exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ================================================================
-- MIGRATION COMPLETE
-- ================================================================
-- Your database now has all tables required for the full EEMS spec
-- Next steps: Run service layer creation and wire up all UI buttons
-- ================================================================
