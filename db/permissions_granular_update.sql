-- Enhanced Granular Permissions System
-- Adds individual tab/module access control for fine-grained permission management

-- Drop old permissions table if exists (backup data first if in production!)
-- CREATE TABLE permissions_backup AS SELECT * FROM permissions;

-- Create new granular permissions table
DROP TABLE IF EXISTS `user_module_permissions`;
CREATE TABLE IF NOT EXISTS `user_module_permissions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `module_name` VARCHAR(50) NOT NULL,
  `can_access` TINYINT(1) NOT NULL DEFAULT 0,
  `can_view` TINYINT(1) NOT NULL DEFAULT 1,
  `can_edit` TINYINT(1) NOT NULL DEFAULT 0,
  `can_delete` TINYINT(1) NOT NULL DEFAULT 0,
  `can_export` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `user_module_unique` (`user_id`, `module_name`),
  CONSTRAINT `user_module_permissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_module_name` (`module_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Available modules for permission control
-- Module names correspond to the modules in admin dashboard
INSERT INTO `user_module_permissions` (`user_id`, `module_name`, `can_access`, `can_view`, `can_edit`, `can_delete`, `can_export`)
SELECT DISTINCT u.id, 'overview', 1, 1, 0, 0, 0 FROM users u WHERE u.id > 0
ON DUPLICATE KEY UPDATE can_access=can_access;

-- Module list for reference:
-- 'overview' - Dashboard overview
-- 'user_management' - Manage users
-- 'exam_management' - Manage exams
-- 'approvals_verifications' - Approve/verify users and exams
-- 'available_exams' - Browse available exams
-- 'permissions' - Manage permissions (admin only)
-- 'analytics' - Analytics & reports
-- 'audit_logs' - Activity logs
-- 'settings' - System settings (admin only)
-- 'principal' - Principal dashboard
-- 'vice' - Vice Principal dashboard
-- 'hod' - HOD dashboard
-- 'teacher' - Teacher dashboard

-- Keep the old permissions table for backward compatibility with dashboard access
-- Add new columns to existing permissions table for granular module access
ALTER TABLE `permissions` 
ADD COLUMN IF NOT EXISTS `module_overview` TINYINT(1) DEFAULT 1,
ADD COLUMN IF NOT EXISTS `module_user_management` TINYINT(1) DEFAULT 0,
ADD COLUMN IF NOT EXISTS `module_exam_management` TINYINT(1) DEFAULT 0,
ADD COLUMN IF NOT EXISTS `module_approvals` TINYINT(1) DEFAULT 0,
ADD COLUMN IF NOT EXISTS `module_available_exams` TINYINT(1) DEFAULT 1,
ADD COLUMN IF NOT EXISTS `module_permissions` TINYINT(1) DEFAULT 0,
ADD COLUMN IF NOT EXISTS `module_analytics` TINYINT(1) DEFAULT 0,
ADD COLUMN IF NOT EXISTS `module_audit_logs` TINYINT(1) DEFAULT 0,
ADD COLUMN IF NOT EXISTS `module_settings` TINYINT(1) DEFAULT 0,
ADD COLUMN IF NOT EXISTS `module_principal_dash` TINYINT(1) DEFAULT 0,
ADD COLUMN IF NOT EXISTS `module_vice_dash` TINYINT(1) DEFAULT 0,
ADD COLUMN IF NOT EXISTS `module_hod_dash` TINYINT(1) DEFAULT 0,
ADD COLUMN IF NOT EXISTS `module_teacher_dash` TINYINT(1) DEFAULT 1;

-- Grant admin users full access to all modules
UPDATE permissions p
JOIN users u ON p.user_id = u.id
SET 
  p.module_overview = 1,
  p.module_user_management = 1,
  p.module_exam_management = 1,
  p.module_approvals = 1,
  p.module_available_exams = 1,
  p.module_permissions = 1,
  p.module_analytics = 1,
  p.module_audit_logs = 1,
  p.module_settings = 1,
  p.module_principal_dash = 1,
  p.module_vice_dash = 1,
  p.module_hod_dash = 1,
  p.module_teacher_dash = 1
WHERE u.post = 'admin';

-- Grant principal users appropriate default access
UPDATE permissions p
JOIN users u ON p.user_id = u.id
SET 
  p.module_overview = 1,
  p.module_user_management = 1,
  p.module_exam_management = 1,
  p.module_approvals = 1,
  p.module_available_exams = 1,
  p.module_analytics = 1,
  p.module_principal_dash = 1
WHERE u.post = 'principal' AND p.principal_access = 1;

-- Grant vice principal users appropriate default access
UPDATE permissions p
JOIN users u ON p.user_id = u.id
SET 
  p.module_overview = 1,
  p.module_exam_management = 1,
  p.module_available_exams = 1,
  p.module_vice_dash = 1
WHERE u.post = 'vice_principal' AND p.vice_access = 1;

-- Grant HOD users appropriate default access
UPDATE permissions p
JOIN users u ON p.user_id = u.id
SET 
  p.module_overview = 1,
  p.module_exam_management = 1,
  p.module_available_exams = 1,
  p.module_hod_dash = 1
WHERE u.post = 'hod' AND p.hod_access = 1;

-- Grant teacher users appropriate default access
UPDATE permissions p
JOIN users u ON p.user_id = u.id
SET 
  p.module_overview = 1,
  p.module_available_exams = 1,
  p.module_teacher_dash = 1
WHERE u.post IN ('teacher', 'faculty') AND p.teacher_access = 1;

-- Create view for easy permission checking
CREATE OR REPLACE VIEW user_permissions_view AS
SELECT 
  u.id AS user_id,
  u.name AS user_name,
  u.email,
  u.post AS role,
  p.principal_access,
  p.vice_access,
  p.hod_access,
  p.teacher_access,
  p.module_overview,
  p.module_user_management,
  p.module_exam_management,
  p.module_approvals,
  p.module_available_exams,
  p.module_permissions,
  p.module_analytics,
  p.module_audit_logs,
  p.module_settings,
  p.module_principal_dash,
  p.module_vice_dash,
  p.module_hod_dash,
  p.module_teacher_dash
FROM users u
LEFT JOIN permissions p ON u.id = p.user_id
WHERE u.status = 'verified';

-- Create helper function to check module access
DELIMITER $$

CREATE FUNCTION IF NOT EXISTS check_module_access(
  p_user_id INT,
  p_module_name VARCHAR(50)
) RETURNS TINYINT(1)
DETERMINISTIC
READS SQL DATA
BEGIN
  DECLARE has_access TINYINT(1) DEFAULT 0;
  
  SELECT 
    CASE p_module_name
      WHEN 'overview' THEN COALESCE(module_overview, 0)
      WHEN 'user_management' THEN COALESCE(module_user_management, 0)
      WHEN 'exam_management' THEN COALESCE(module_exam_management, 0)
      WHEN 'approvals_verifications' THEN COALESCE(module_approvals, 0)
      WHEN 'available_exams' THEN COALESCE(module_available_exams, 0)
      WHEN 'permissions' THEN COALESCE(module_permissions, 0)
      WHEN 'analytics' THEN COALESCE(module_analytics, 0)
      WHEN 'audit_logs' THEN COALESCE(module_audit_logs, 0)
      WHEN 'settings' THEN COALESCE(module_settings, 0)
      WHEN 'principal' THEN COALESCE(module_principal_dash, 0)
      WHEN 'vice' THEN COALESCE(module_vice_dash, 0)
      WHEN 'hod' THEN COALESCE(module_hod_dash, 0)
      WHEN 'teacher' THEN COALESCE(module_teacher_dash, 0)
      ELSE 0
    END INTO has_access
  FROM permissions
  WHERE user_id = p_user_id;
  
  RETURN COALESCE(has_access, 0);
END$$

DELIMITER ;

-- Sample query to get user's accessible modules
-- SELECT module_name FROM (
--   SELECT 'overview' AS module_name, module_overview AS has_access FROM permissions WHERE user_id = 1
--   UNION ALL SELECT 'user_management', module_user_management FROM permissions WHERE user_id = 1
--   UNION ALL SELECT 'exam_management', module_exam_management FROM permissions WHERE user_id = 1
--   UNION ALL SELECT 'approvals_verifications', module_approvals FROM permissions WHERE user_id = 1
--   UNION ALL SELECT 'available_exams', module_available_exams FROM permissions WHERE user_id = 1
--   UNION ALL SELECT 'permissions', module_permissions FROM permissions WHERE user_id = 1
--   UNION ALL SELECT 'analytics', module_analytics FROM permissions WHERE user_id = 1
--   UNION ALL SELECT 'audit_logs', module_audit_logs FROM permissions WHERE user_id = 1
--   UNION ALL SELECT 'settings', module_settings FROM permissions WHERE user_id = 1
-- ) AS modules WHERE has_access = 1;
