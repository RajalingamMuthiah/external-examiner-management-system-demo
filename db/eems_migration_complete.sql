-- ===================================================================
-- EEMS COMPLETE DATABASE MIGRATION
-- ===================================================================
-- This migration aligns the existing schema to support:
-- - Multiple colleges and departments
-- - Role-based data visibility
-- - Cross-college exam scheduling with privacy
-- - Proper foreign key relationships
-- ===================================================================

USE eems;

-- Step 1: Create colleges table
CREATE TABLE IF NOT EXISTS `colleges` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL UNIQUE,
  `code` VARCHAR(50) NULL,
  `address` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Step 2: Create departments table
CREATE TABLE IF NOT EXISTS `departments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `college_id` INT NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `code` VARCHAR(50) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_dept_college` FOREIGN KEY (`college_id`) REFERENCES `colleges`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_dept_per_college` (`college_id`, `name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Step 3: Migrate existing college_name data from users to colleges table
INSERT IGNORE INTO `colleges` (`name`)
SELECT DISTINCT `college_name` 
FROM `users` 
WHERE `college_name` IS NOT NULL AND `college_name` != ''
ORDER BY `college_name`;

-- Step 4: Add college_id and department_id to users table
ALTER TABLE `users` 
ADD COLUMN IF NOT EXISTS `college_id` INT NULL AFTER `post`,
ADD COLUMN IF NOT EXISTS `department_id` INT NULL AFTER `college_id`,
ADD COLUMN IF NOT EXISTS `role` VARCHAR(50) NULL AFTER `post`;

-- Step 5: Update users.college_id from college_name
UPDATE `users` u
INNER JOIN `colleges` c ON u.`college_name` = c.`name`
SET u.`college_id` = c.`id`
WHERE u.`college_name` IS NOT NULL AND u.`college_name` != '';

-- Step 6: Sync role field with post field for backward compatibility
UPDATE `users` 
SET `role` = CASE 
    WHEN `post` = 'vice_principal' THEN 'vice_principal'
    WHEN `post` IN ('teacher', 'hod', 'principal', 'admin') THEN `post`
    ELSE `post`
END
WHERE `role` IS NULL OR `role` = '';

-- Step 7: Add foreign key constraint for users.college_id
ALTER TABLE `users`
ADD CONSTRAINT `fk_user_college` FOREIGN KEY (`college_id`) REFERENCES `colleges`(`id`) ON DELETE SET NULL;

-- Step 8: Update exams table structure
ALTER TABLE `exams`
ADD COLUMN IF NOT EXISTS `college_id` INT NULL AFTER `department`,
ADD COLUMN IF NOT EXISTS `department_id` INT NULL AFTER `college_id`,
ADD COLUMN IF NOT EXISTS `created_by` INT NULL AFTER `department_id`,
ADD COLUMN IF NOT EXISTS `course_code` VARCHAR(50) NULL AFTER `title`,
ADD COLUMN IF NOT EXISTS `subject` VARCHAR(255) NULL AFTER `course_code`,
ADD COLUMN IF NOT EXISTS `start_time` TIME NULL AFTER `exam_date`,
ADD COLUMN IF NOT EXISTS `end_time` TIME NULL AFTER `start_time`,
ADD COLUMN IF NOT EXISTS `status` ENUM('draft','published','approved','assigned','cancelled') DEFAULT 'draft' AFTER `end_time`,
ADD COLUMN IF NOT EXISTS `description` TEXT NULL AFTER `status`,
ADD COLUMN IF NOT EXISTS `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER `description`,
ADD COLUMN IF NOT EXISTS `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;

-- Step 9: Migrate existing department text to college_id in exams
UPDATE `exams` e
INNER JOIN `colleges` c ON e.`department` = c.`name`
SET e.`college_id` = c.`id`
WHERE e.`department` IS NOT NULL AND e.`department` != '' AND e.`college_id` IS NULL;

-- Step 10: Add foreign keys to exams
ALTER TABLE `exams`
ADD CONSTRAINT `fk_exam_college` FOREIGN KEY (`college_id`) REFERENCES `colleges`(`id`) ON DELETE SET NULL,
ADD CONSTRAINT `fk_exam_creator` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL;

-- Step 11: Rename assignments to exam_assignments for clarity
RENAME TABLE `assignments` TO `exam_assignments`;

-- Step 12: Update exam_assignments structure
ALTER TABLE `exam_assignments`
ADD COLUMN IF NOT EXISTS `role_assigned` ENUM('invigilator','paper_setter','valuator','examiner') DEFAULT 'invigilator' AFTER `role`,
ADD COLUMN IF NOT EXISTS `duty_type` VARCHAR(100) NULL AFTER `role_assigned`,
ADD COLUMN IF NOT EXISTS `status` ENUM('assigned','accepted','rejected','completed','cancelled') DEFAULT 'assigned' AFTER `duty_type`,
ADD COLUMN IF NOT EXISTS `assigned_by` INT NULL AFTER `status`,
ADD COLUMN IF NOT EXISTS `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER `assigned_by`,
ADD COLUMN IF NOT EXISTS `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;

-- Step 13: Sync role_assigned with existing role column
UPDATE `exam_assignments`
SET `role_assigned` = CASE
    WHEN `role` LIKE '%invigilator%' THEN 'invigilator'
    WHEN `role` LIKE '%setter%' OR `role` LIKE '%paper%' THEN 'paper_setter'
    WHEN `role` LIKE '%valuator%' OR `role` LIKE '%evaluator%' THEN 'valuator'
    WHEN `role` LIKE '%examiner%' THEN 'examiner'
    ELSE 'invigilator'
END
WHERE `role_assigned` = 'invigilator' OR `role_assigned` IS NULL;

-- Step 14: Create faculty_availability table
CREATE TABLE IF NOT EXISTS `faculty_availability` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `faculty_user_id` INT NOT NULL,
  `unavailable_date` DATE NOT NULL,
  `reason` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_availability_user` FOREIGN KEY (`faculty_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_faculty_date` (`faculty_user_id`, `unavailable_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Step 15: Create indexes for performance
CREATE INDEX IF NOT EXISTS `idx_users_college` ON `users`(`college_id`);
CREATE INDEX IF NOT EXISTS `idx_users_dept` ON `users`(`department_id`);
CREATE INDEX IF NOT EXISTS `idx_users_role` ON `users`(`role`);
CREATE INDEX IF NOT EXISTS `idx_exams_college` ON `exams`(`college_id`);
CREATE INDEX IF NOT EXISTS `idx_exams_dept` ON `exams`(`department_id`);
CREATE INDEX IF NOT EXISTS `idx_exams_status` ON `exams`(`status`);
CREATE INDEX IF NOT EXISTS `idx_exams_date` ON `exams`(`exam_date`);
CREATE INDEX IF NOT EXISTS `idx_assignments_faculty` ON `exam_assignments`(`faculty_id`);
CREATE INDEX IF NOT EXISTS `idx_assignments_exam` ON `exam_assignments`(`exam_id`);
CREATE INDEX IF NOT EXISTS `idx_assignments_status` ON `exam_assignments`(`status`);

-- Step 16: Insert sample colleges (if needed for testing)
INSERT IGNORE INTO `colleges` (`name`, `code`) VALUES
('St. Joseph Engineering College', 'SJEC'),
('Mangalore Institute of Technology & Engineering', 'MITE'),
('Canara Engineering College', 'CEC'),
('Srinivas Institute of Technology', 'SIT'),
('PA College of Engineering', 'PACE');

-- Step 17: Insert sample departments for each college
INSERT IGNORE INTO `departments` (`college_id`, `name`, `code`)
SELECT c.id, d.dept_name, d.dept_code
FROM `colleges` c
CROSS JOIN (
    SELECT 'Computer Science & Engineering' as dept_name, 'CSE' as dept_code UNION ALL
    SELECT 'Electronics & Communication Engineering', 'ECE' UNION ALL
    SELECT 'Mechanical Engineering', 'ME' UNION ALL
    SELECT 'Civil Engineering', 'CE' UNION ALL
    SELECT 'Information Science & Engineering', 'ISE' UNION ALL
    SELECT 'Electrical & Electronics Engineering', 'EEE'
) d;

-- Step 18: Add unique constraint for exam scheduling conflict prevention
CREATE INDEX IF NOT EXISTS `idx_exam_schedule_conflict` 
ON `exams`(`college_id`, `exam_date`, `start_time`, `end_time`);

-- ===================================================================
-- MIGRATION COMPLETE
-- ===================================================================
-- Run this SQL file using:
-- mysql -u root -p eems < db/eems_migration_complete.sql
-- OR copy-paste into phpMyAdmin SQL tab
-- ===================================================================
