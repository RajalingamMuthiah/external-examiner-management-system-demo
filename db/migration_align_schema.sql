
USE eems;

-- Step 1: Skip - colleges table already exists with college_name column
-- Step 2: Skip - departments table already exists with dept_name column

-- Step 3: Add college_id and department_id to users table (check if columns exist first)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_SCHEMA = 'eems' AND TABLE_NAME = 'users' AND COLUMN_NAME = 'college_id');

SET @sql = IF(@col_exists = 0, 
  'ALTER TABLE `users` ADD COLUMN `college_id` INT(11) NULL AFTER `college_name`', 
  'SELECT "Column college_id already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_SCHEMA = 'eems' AND TABLE_NAME = 'users' AND COLUMN_NAME = 'department_id');

SET @sql = IF(@col_exists = 0, 
  'ALTER TABLE `users` ADD COLUMN `department_id` INT(11) NULL AFTER `college_id`', 
  'SELECT "Column department_id already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE `users` 
MODIFY COLUMN `post` ENUM('admin','principal','vice_principal','hod','teacher','faculty') NOT NULL;

-- Step 4: Update exams table with new columns (check if columns exist first)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_SCHEMA = 'eems' AND TABLE_NAME = 'exams' AND COLUMN_NAME = 'course_code');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `exams` ADD COLUMN `course_code` VARCHAR(50) NULL AFTER `title`', 'SELECT "Column course_code exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_SCHEMA = 'eems' AND TABLE_NAME = 'exams' AND COLUMN_NAME = 'subject');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `exams` ADD COLUMN `subject` VARCHAR(255) NULL AFTER `course_code`', 'SELECT "Column subject exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_SCHEMA = 'eems' AND TABLE_NAME = 'exams' AND COLUMN_NAME = 'start_time');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `exams` ADD COLUMN `start_time` TIME NULL AFTER `exam_date`', 'SELECT "Column start_time exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_SCHEMA = 'eems' AND TABLE_NAME = 'exams' AND COLUMN_NAME = 'end_time');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `exams` ADD COLUMN `end_time` TIME NULL AFTER `start_time`', 'SELECT "Column end_time exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_SCHEMA = 'eems' AND TABLE_NAME = 'exams' AND COLUMN_NAME = 'college_id');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `exams` ADD COLUMN `college_id` INT(11) NULL AFTER `end_time`', 'SELECT "Column college_id exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_SCHEMA = 'eems' AND TABLE_NAME = 'exams' AND COLUMN_NAME = 'department_id');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `exams` ADD COLUMN `department_id` INT(11) NULL AFTER `college_id`', 'SELECT "Column department_id exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_SCHEMA = 'eems' AND TABLE_NAME = 'exams' AND COLUMN_NAME = 'created_by');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `exams` ADD COLUMN `created_by` INT(11) NULL AFTER `department_id`', 'SELECT "Column created_by exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_SCHEMA = 'eems' AND TABLE_NAME = 'exams' AND COLUMN_NAME = 'status');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `exams` ADD COLUMN `status` ENUM(''draft'',''published'',''approved'',''assigned'',''cancelled'') DEFAULT ''draft'' AFTER `created_by`', 'SELECT "Column status exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_SCHEMA = 'eems' AND TABLE_NAME = 'exams' AND COLUMN_NAME = 'description');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `exams` ADD COLUMN `description` TEXT NULL AFTER `status`', 'SELECT "Column description exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_SCHEMA = 'eems' AND TABLE_NAME = 'exams' AND COLUMN_NAME = 'created_at');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `exams` ADD COLUMN `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER `description`', 'SELECT "Column created_at exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_SCHEMA = 'eems' AND TABLE_NAME = 'exams' AND COLUMN_NAME = 'updated_at');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `exams` ADD COLUMN `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`', 'SELECT "Column updated_at exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Step 5: Rename assignments to exam_assignments and add new columns
CREATE TABLE IF NOT EXISTS `exam_assignments` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `exam_id` INT(11) NOT NULL,
  `faculty_user_id` INT(11) NOT NULL,
  `role_assigned` ENUM('invigilator','paper_setter','evaluator','external_examiner') DEFAULT 'invigilator',
  `duty_type` VARCHAR(100) NULL,
  `status` ENUM('assigned','accepted','completed','cancelled') DEFAULT 'assigned',
  `assigned_by` INT(11) NULL,
  `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `notes` TEXT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_exam` (`exam_id`),
  KEY `idx_faculty` (`faculty_user_id`),
  CONSTRAINT `fk_exam_assignment_exam` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_exam_assignment_faculty` FOREIGN KEY (`faculty_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_exam_assignment_assigner` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Step 6: Migrate data from old assignments table to exam_assignments if exists
INSERT IGNORE INTO `exam_assignments` (`exam_id`, `faculty_user_id`, `role_assigned`, `assigned_at`)
SELECT `exam_id`, `faculty_id`, 
       CASE WHEN `role` LIKE '%invig%' THEN 'invigilator' ELSE 'evaluator' END,
       NOW()
FROM `assignments`
WHERE NOT EXISTS (
  SELECT 1 FROM `exam_assignments` ea 
  WHERE ea.exam_id = `assignments`.exam_id 
  AND ea.faculty_user_id = `assignments`.faculty_id
);

-- Step 7: Create faculty_availability table
CREATE TABLE IF NOT EXISTS `faculty_availability` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `faculty_user_id` INT(11) NOT NULL,
  `unavailable_date` DATE NOT NULL,
  `reason` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_faculty_date` (`faculty_user_id`, `unavailable_date`),
  CONSTRAINT `fk_availability_faculty` FOREIGN KEY (`faculty_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Step 8: Add indexes for better query performance (check if index exists first)
SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
  WHERE TABLE_SCHEMA = 'eems' AND TABLE_NAME = 'users' AND INDEX_NAME = 'idx_users_college');
SET @sql = IF(@idx_exists = 0, 'CREATE INDEX `idx_users_college` ON `users` (`college_id`)', 'SELECT "Index idx_users_college exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
  WHERE TABLE_SCHEMA = 'eems' AND TABLE_NAME = 'users' AND INDEX_NAME = 'idx_users_dept');
SET @sql = IF(@idx_exists = 0, 'CREATE INDEX `idx_users_dept` ON `users` (`department_id`)', 'SELECT "Index idx_users_dept exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
  WHERE TABLE_SCHEMA = 'eems' AND TABLE_NAME = 'users' AND INDEX_NAME = 'idx_users_role');
SET @sql = IF(@idx_exists = 0, 'CREATE INDEX `idx_users_role` ON `users` (`post`)', 'SELECT "Index idx_users_role exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
  WHERE TABLE_SCHEMA = 'eems' AND TABLE_NAME = 'exams' AND INDEX_NAME = 'idx_exams_college');
SET @sql = IF(@idx_exists = 0, 'CREATE INDEX `idx_exams_college` ON `exams` (`college_id`)', 'SELECT "Index idx_exams_college exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
  WHERE TABLE_SCHEMA = 'eems' AND TABLE_NAME = 'exams' AND INDEX_NAME = 'idx_exams_dept');
SET @sql = IF(@idx_exists = 0, 'CREATE INDEX `idx_exams_dept` ON `exams` (`department_id`)', 'SELECT "Index idx_exams_dept exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
  WHERE TABLE_SCHEMA = 'eems' AND TABLE_NAME = 'exams' AND INDEX_NAME = 'idx_exams_date');
SET @sql = IF(@idx_exists = 0, 'CREATE INDEX `idx_exams_date` ON `exams` (`exam_date`)', 'SELECT "Index idx_exams_date exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
  WHERE TABLE_SCHEMA = 'eems' AND TABLE_NAME = 'exams' AND INDEX_NAME = 'idx_exams_status');
SET @sql = IF(@idx_exists = 0, 'CREATE INDEX `idx_exams_status` ON `exams` (`status`)', 'SELECT "Index idx_exams_status exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Step 9: Migrate college_name to college_id in users table (if college_name column exists)
UPDATE users u
INNER JOIN colleges c ON u.college_name = c.college_name
SET u.college_id = c.id
WHERE u.college_id IS NULL AND u.college_name IS NOT NULL;

-- Step 10: Create colleges for any remaining unmapped college_name values
INSERT IGNORE INTO colleges (college_name, college_code, is_active)
SELECT DISTINCT u.college_name, CONCAT('COL', LPAD(FLOOR(RAND() * 1000), 3, '0')), 1
FROM users u
LEFT JOIN colleges c ON u.college_name = c.college_name
WHERE u.college_name IS NOT NULL 
  AND u.college_name != ''
  AND c.id IS NULL;

-- Step 11: Update users again after creating missing colleges
UPDATE users u
INNER JOIN colleges c ON u.college_name = c.college_name
SET u.college_id = c.id
WHERE u.college_id IS NULL AND u.college_name IS NOT NULL;

-- Step 12: Migrate department to department_id in exams table (if department column exists)
UPDATE exams e
LEFT JOIN departments d ON e.department = d.dept_name
SET e.department_id = d.id
WHERE e.department_id IS NULL AND e.department IS NOT NULL AND d.id IS NOT NULL;

-- Step 13: Add foreign key constraints (check if not exists first)
SET @constraint_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
  WHERE CONSTRAINT_SCHEMA = 'eems' AND CONSTRAINT_NAME = 'fk_users_college' AND TABLE_NAME = 'users');

SET @sql = IF(@constraint_exists = 0, 
  'ALTER TABLE `users` ADD CONSTRAINT `fk_users_college` FOREIGN KEY (`college_id`) REFERENCES `colleges` (`id`) ON DELETE SET NULL', 
  'SELECT "Constraint fk_users_college already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @constraint_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
  WHERE CONSTRAINT_SCHEMA = 'eems' AND CONSTRAINT_NAME = 'fk_users_dept' AND TABLE_NAME = 'users');

SET @sql = IF(@constraint_exists = 0, 
  'ALTER TABLE `users` ADD CONSTRAINT `fk_users_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL', 
  'SELECT "Constraint fk_users_dept already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @constraint_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
  WHERE CONSTRAINT_SCHEMA = 'eems' AND CONSTRAINT_NAME = 'fk_exams_college' AND TABLE_NAME = 'exams');

SET @sql = IF(@constraint_exists = 0, 
  'ALTER TABLE `exams` ADD CONSTRAINT `fk_exams_college` FOREIGN KEY (`college_id`) REFERENCES `colleges` (`id`) ON DELETE SET NULL', 
  'SELECT "Constraint fk_exams_college already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @constraint_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
  WHERE CONSTRAINT_SCHEMA = 'eems' AND CONSTRAINT_NAME = 'fk_exams_dept' AND TABLE_NAME = 'exams');

SET @sql = IF(@constraint_exists = 0, 
  'ALTER TABLE `exams` ADD CONSTRAINT `fk_exams_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL', 
  'SELECT "Constraint fk_exams_dept already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @constraint_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
  WHERE CONSTRAINT_SCHEMA = 'eems' AND CONSTRAINT_NAME = 'fk_exams_creator' AND TABLE_NAME = 'exams');

SET @sql = IF(@constraint_exists = 0, 
  'ALTER TABLE `exams` ADD CONSTRAINT `fk_exams_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL', 
  'SELECT "Constraint fk_exams_creator already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ================================================================
-- MIGRATION COMPLETE
-- ================================================================
-- Your database is now aligned with the new schema
-- ================================================================
