<br />
<b>Warning</b>:  Undefined array key "Create Table" in <b>C:\xampp\htdocs\external\eems\backup_complete.php</b> on line <b>35</b><br />
<br />
<b>Warning</b>:  Undefined array key "Create Table" in <b>C:\xampp\htdocs\external\eems\backup_complete.php</b> on line <b>35</b><br />
<br />
<b>Warning</b>:  Undefined array key "Create Table" in <b>C:\xampp\htdocs\external\eems\backup_complete.php</b> on line <b>35</b><br />
<br />
<b>Warning</b>:  Undefined array key "Create Table" in <b>C:\xampp\htdocs\external\eems\backup_complete.php</b> on line <b>35</b><br />
-- EEMS Complete Database Backup
-- Exported: 2025-12-25 13:04:36
-- Database: eems

DROP DATABASE IF EXISTS `eems`;
CREATE DATABASE `eems` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `eems`;

CREATE TABLE `access_control` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `dashboard_access` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON array of allowed dashboards' CHECK (json_valid(`dashboard_access`)),
  `module_access` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON array of allowed modules' CHECK (json_valid(`module_access`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `idx_user_id` (`user_id`),
  CONSTRAINT `fk_access_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `access_control` (`id`, `user_id`, `dashboard_access`, `module_access`, `created_at`, `updated_at`) VALUES
('1', '1', '[\"overview\", \"principal\", \"vice\", \"hod\", \"teacher\"]', '[\"overview\", \"users\", \"exams\", \"settings\", \"audit_logs\", \"permissions\", \"analytics\"]', '2025-11-23 19:38:39', '2025-11-23 19:38:39'),
('2', '5', '[\"principal\"]', '[\"overview\", \"exams\", \"analytics\"]', '2025-11-23 19:38:39', '2025-11-23 19:38:39'),
('3', '3', '[\"hod\"]', '[\"overview\", \"exams\"]', '2025-11-23 19:38:39', '2025-11-23 19:38:39'),
('4', '2', '[\"teacher\"]', '[\"overview\"]', '2025-11-23 19:38:39', '2025-11-23 19:38:39'),
('5', '4', '[\"teacher\"]', '[\"overview\"]', '2025-11-23 19:38:39', '2025-11-23 19:38:39');

CREATE TABLE `activity_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `activity_type` varchar(100) NOT NULL,
  `activity_description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `request_url` varchar(500) DEFAULT NULL,
  `request_method` varchar(10) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_type` (`activity_type`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `activity_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Detailed activity tracking';

INSERT INTO `activity_log` (`id`, `user_id`, `activity_type`, `activity_description`, `ip_address`, `user_agent`, `request_url`, `request_method`, `created_at`) VALUES
('1', '1', 'login', 'User logged in successfully', '127.0.0.1', NULL, '/login.php', 'POST', '2025-11-22 16:07:18'),
('2', '1', 'user_management', 'Viewed user management page', '127.0.0.1', NULL, '/admin_dashboard.php?module=user_management', 'GET', '2025-11-22 16:07:18'),
('3', '1', 'exam_create', 'Created new exam: Mathematics Final', '127.0.0.1', NULL, '/admin_dashboard.php?action=add_exam', 'POST', '2025-11-22 15:07:18');

CREATE TABLE `admin_roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_name` varchar(100) NOT NULL,
  `display_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `permissions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON array of permissions' CHECK (json_valid(`permissions`)),
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_name` (`role_name`),
  KEY `idx_name` (`role_name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Admin role definitions';

INSERT INTO `admin_roles` (`id`, `role_name`, `display_name`, `description`, `permissions`, `is_active`, `created_at`) VALUES
('1', 'super_admin', 'Super Administrator', 'Full system access', '[\"all\"]', '1', '2025-11-22 16:03:32'),
('2', 'admin', 'Administrator', 'System administration', '[\"user_management\", \"exam_management\", \"settings\"]', '1', '2025-11-22 16:03:32'),
('3', 'moderator', 'Moderator', 'Content moderation', '[\"user_verification\", \"content_moderation\"]', '1', '2025-11-22 16:03:32');

CREATE TABLE `admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `admin_level` enum('super_admin','admin','moderator') DEFAULT 'admin',
  `can_delete_users` tinyint(1) DEFAULT 0,
  `can_modify_exams` tinyint(1) DEFAULT 1,
  `can_view_reports` tinyint(1) DEFAULT 1,
  `can_manage_settings` tinyint(1) DEFAULT 0,
  `last_login` timestamp NULL DEFAULT NULL,
  `login_count` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_level` (`admin_level`),
  CONSTRAINT `admins_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Admin user details';

INSERT INTO `admins` (`id`, `user_id`, `admin_level`, `can_delete_users`, `can_modify_exams`, `can_view_reports`, `can_manage_settings`, `last_login`, `login_count`, `is_active`, `created_at`, `updated_at`) VALUES
('1', '1', 'super_admin', '1', '1', '1', '1', NULL, '0', '1', '2025-11-22 16:07:18', '2025-11-22 16:07:18');

CREATE TABLE `approvals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `exam_id` int(11) DEFAULT NULL,
  `approver_id` int(11) DEFAULT NULL,
  `approver_role` varchar(50) DEFAULT NULL,
  `decision` enum('approved','rejected','changes_requested') DEFAULT NULL,
  `comments` text DEFAULT NULL,
  `requester_id` int(11) NOT NULL,
  `type` varchar(100) NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `processed_by` int(11) DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `requester_id` (`requester_id`),
  KEY `idx_exam_id` (`exam_id`),
  KEY `idx_approver` (`approver_id`),
  CONSTRAINT `approvals_ibfk_1` FOREIGN KEY (`requester_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `faculty_id` int(11) NOT NULL,
  `exam_id` int(11) NOT NULL,
  `role` varchar(100) DEFAULT 'Invigilator',
  `assigned_by` int(11) DEFAULT NULL COMMENT 'User ID who made the assignment (for HOD nominations)',
  `status` varchar(50) DEFAULT 'Assigned' COMMENT 'Assignment status',
  `notes` text DEFAULT NULL COMMENT 'Additional notes about assignment',
  PRIMARY KEY (`id`),
  KEY `faculty_id` (`faculty_id`),
  KEY `exam_id` (`exam_id`),
  KEY `idx_faculty_assignments` (`faculty_id`,`exam_id`),
  KEY `idx_exam_assignments` (`exam_id`),
  KEY `idx_assigned_by` (`assigned_by`),
  CONSTRAINT `assignments_ibfk_1` FOREIGN KEY (`faculty_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `assignments_ibfk_2` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `audit_log` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `table_name` varchar(100) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_table` (`table_name`,`record_id`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `audit_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `resource_type` varchar(50) DEFAULT NULL,
  `resource_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `admin_id` int(11) NOT NULL,
  `action` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `metadata_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata_json`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `admin_id` (`admin_id`),
  KEY `created_at` (`created_at`),
  KEY `idx_resource` (`resource_type`,`resource_id`),
  KEY `idx_user_action` (`user_id`,`action`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `audit_logs` (`id`, `resource_type`, `resource_id`, `user_id`, `admin_id`, `action`, `details`, `metadata_json`, `ip_address`, `created_at`) VALUES
('1', NULL, NULL, NULL, '999999', 'Database Backup', 'Initiated database backup', NULL, '::1', '2025-11-22 15:38:28'),
('2', NULL, NULL, NULL, '1', 'Exam Posted', 'Posted new exam: Commercial Bank Management (Banking & Finance) Makeup (ID: 8) for System Admin', NULL, '::1', '2025-11-22 18:49:18'),
('3', NULL, NULL, NULL, '1', 'Exam Posted', 'Posted new exam: fklffff (ID: 9) for dd', NULL, '::1', '2025-11-22 18:49:51'),
('4', NULL, NULL, NULL, '1', 'Exam Status Updated', 'Updated exam ID 9 to status: Approved', NULL, '::1', '2025-11-22 18:50:32'),
('5', NULL, NULL, NULL, '999999', 'Password Set', 'Set password for user: Jane Doe (jane.doe@example.com)', NULL, '::1', '2025-11-23 20:13:46'),
('6', NULL, NULL, NULL, '999999', 'Password Reset', 'Set password for user: principle (principle@gmail.com)', NULL, '::1', '2025-11-23 21:20:28'),
('7', NULL, NULL, NULL, '999999', 'Password Reset', 'Set password for user: principle (principle@gmail.com)', NULL, '::1', '2025-11-23 21:55:31'),
('8', NULL, NULL, NULL, '999999', 'Password Reset', 'Set password for user: principle (principle@gmail.com)', NULL, '::1', '2025-11-23 22:13:20'),
('9', NULL, NULL, NULL, '999999', 'User Verified', 'Verified principle (ID: 6) as principal. Staff ID: STF001', NULL, '::1', '2025-11-23 22:19:01'),
('10', NULL, NULL, NULL, '7', 'Password Reset', 'Set password for user: Jane Doe (jane.doe@example.com)', NULL, '::1', '2025-11-25 17:47:29'),
('11', NULL, NULL, NULL, '7', 'Bulk Status Update', 'Updated 1 users to status: verified', NULL, '::1', '2025-11-27 19:30:54'),
('12', NULL, NULL, NULL, '7', 'Password Reset', 'Set password for user: Arjun Admin (arjun@gmail.com)', NULL, '::1', '2025-11-29 12:25:17'),
('13', NULL, NULL, NULL, '7', 'Password Reset', 'Set password for user: principle (principle1@gmail.com)', NULL, '::1', '2025-11-29 12:25:27'),
('14', NULL, NULL, NULL, '7', 'Password Set', 'Set password for user: John Smith (john.smith@example.com)', NULL, '::1', '2025-11-29 12:25:38'),
('15', NULL, NULL, NULL, '7', 'Password Reset', 'Set password for user: Jane Doe (jane.doe@example.com)', NULL, '::1', '2025-11-29 12:29:34'),
('16', NULL, NULL, NULL, '2', 'Password Reset', 'Set password for user: Pending User (pending@example.com)', NULL, '::1', '2025-11-29 12:44:13'),
('17', NULL, NULL, NULL, '7', 'Password Reset', 'Set password for user: principle (principle1@gmail.com)', NULL, '::1', '2025-11-30 21:50:50'),
('18', NULL, NULL, NULL, '7', 'Settings Updated', 'Updated system settings', NULL, '::1', '2025-12-13 16:46:12');

CREATE TABLE `college_notices` (
  `notice_id` int(11) NOT NULL AUTO_INCREMENT,
  `college_name` varchar(255) NOT NULL,
  `posted_by` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `notice_date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `expires_at` date DEFAULT NULL,
  `priority` enum('low','normal','high','urgent') DEFAULT 'normal',
  `is_active` tinyint(1) DEFAULT 1,
  `file_path` varchar(500) DEFAULT NULL,
  `file_type` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`notice_id`),
  KEY `idx_college` (`college_name`),
  KEY `idx_date` (`notice_date`),
  KEY `posted_by` (`posted_by`),
  CONSTRAINT `college_notices_ibfk_1` FOREIGN KEY (`posted_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `college_notices` (`notice_id`, `college_name`, `posted_by`, `title`, `content`, `notice_date`, `created_at`, `updated_at`, `expires_at`, `priority`, `is_active`, `file_path`, `file_type`) VALUES
('1', 'St. Joseph College', '1', 'Mid-term Exam Schedule', 'The mid-term examinations will commence from December 1st. Students are requested to check the detailed schedule on the notice board.', '2025-11-22', '2025-11-22 16:07:18', '2025-11-22 16:07:18', NULL, 'high', '1', NULL, NULL),
('2', 'Christ University', '1', 'Faculty Meeting', 'All faculty members are requested to attend the departmental meeting on November 25th at 2:00 PM.', '2025-11-22', '2025-11-22 16:07:18', '2025-11-22 16:07:18', NULL, 'normal', '1', NULL, NULL),
('3', 'MES College', '1', 'Holiday Notice', 'The college will remain closed on November 30th due to a public holiday.', '2025-11-22', '2025-11-22 16:07:18', '2025-11-22 16:07:18', NULL, 'urgent', '1', NULL, NULL);

CREATE TABLE `college_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `college_id` int(11) NOT NULL,
  `settings_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Notification prefs, timezone, approval rules, etc.' CHECK (json_valid(`settings_json`)),
  `timezone` varchar(50) DEFAULT 'Asia/Kolkata',
  `notification_enabled` tinyint(1) DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_college` (`college_id`),
  KEY `fk_college_settings_updater` (`updated_by`),
  CONSTRAINT `fk_college_settings_college` FOREIGN KEY (`college_id`) REFERENCES `colleges` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_college_settings_updater` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `colleges` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `college_name` varchar(255) NOT NULL,
  `college_code` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `pincode` varchar(10) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `principal_name` varchar(255) DEFAULT NULL,
  `principal_id` int(11) DEFAULT NULL,
  `affiliation` varchar(255) DEFAULT NULL COMMENT 'University affiliation',
  `established_year` int(11) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `college_name` (`college_name`),
  KEY `idx_name` (`college_name`),
  KEY `idx_city` (`city`),
  KEY `idx_active` (`is_active`),
  KEY `principal_id` (`principal_id`),
  CONSTRAINT `colleges_ibfk_1` FOREIGN KEY (`principal_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Master colleges list';

INSERT INTO `colleges` (`id`, `college_name`, `college_code`, `address`, `city`, `state`, `pincode`, `phone`, `email`, `principal_name`, `principal_id`, `affiliation`, `established_year`, `latitude`, `longitude`, `is_active`, `created_at`, `updated_at`) VALUES
('1', 'St. Joseph College', 'SJC001', NULL, 'Bangalore', 'Karnataka', NULL, '080-12345678', 'info@stjoseph.edu', NULL, NULL, 'Bangalore University', '1882', NULL, NULL, '1', '2025-11-22 16:07:18', '2025-11-22 16:07:18'),
('2', 'Christ University', 'CU002', NULL, 'Bangalore', 'Karnataka', NULL, '080-23456789', 'info@christuniversity.edu', NULL, NULL, 'Autonomous', '1969', NULL, NULL, '1', '2025-11-22 16:07:18', '2025-11-22 16:07:18'),
('3', 'MES College', 'MES003', NULL, 'Bangalore', 'Karnataka', NULL, '080-34567890', 'info@mescollege.edu', NULL, NULL, 'Bangalore University', '1960', NULL, NULL, '1', '2025-11-22 16:07:18', '2025-11-22 16:07:18'),
('4', 'St. Aloysius College', 'SAC004', NULL, 'Mangalore', 'Karnataka', NULL, '0824-1234567', 'info@staloysius.edu', NULL, NULL, 'Mangalore University', '1880', NULL, NULL, '1', '2025-11-22 16:07:18', '2025-11-22 16:07:18'),
('5', 'Mount Carmel College', 'MCC005', NULL, 'Bangalore', 'Karnataka', NULL, '080-45678901', 'info@mcc.edu', NULL, NULL, 'Bangalore University', '1948', NULL, NULL, '1', '2025-11-22 16:07:18', '2025-11-22 16:07:18'),
('6', 'Arts College', 'COL001', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '1', '2025-11-23 21:36:40', '2025-11-23 21:36:40'),
('7', 'eee', 'COL002', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '1', '2025-11-23 21:36:40', '2025-11-23 21:36:40'),
('8', 'Science College', 'COL003', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '1', '2025-11-23 21:36:40', '2025-11-23 21:36:40'),
('9', 'System Admin', 'COL004', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '1', '2025-11-23 21:36:40', '2025-11-23 21:36:40'),
('10', 'Tech College', 'COL005', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '1', '2025-11-23 21:36:40', '2025-11-23 21:36:40'),
('11', '1234', 'COL214', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '1', '2025-12-10 18:10:43', '2025-12-10 18:10:43'),
('12', 'siws', 'COL854', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '1', '2025-12-10 18:10:43', '2025-12-10 18:10:43');

CREATE TABLE `delegated_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `delegator_id` int(11) NOT NULL COMMENT 'User delegating permission',
  `delegate_id` int(11) NOT NULL COMMENT 'User receiving permission',
  `permission_type` enum('admin','hod','teacher_approval') NOT NULL,
  `granted_at` timestamp NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_delegation` (`delegator_id`,`delegate_id`,`permission_type`),
  KEY `idx_delegate` (`delegate_id`),
  KEY `idx_active` (`is_active`),
  CONSTRAINT `delegated_permissions_ibfk_1` FOREIGN KEY (`delegator_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `delegated_permissions_ibfk_2` FOREIGN KEY (`delegate_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Temporary permission delegation';

CREATE TABLE `departments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `college_id` int(11) DEFAULT NULL,
  `dept_name` varchar(255) NOT NULL,
  `dept_code` varchar(50) DEFAULT NULL,
  `hod_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_college` (`college_id`),
  KEY `idx_hod` (`hod_id`),
  CONSTRAINT `departments_ibfk_1` FOREIGN KEY (`college_id`) REFERENCES `colleges` (`id`) ON DELETE CASCADE,
  CONSTRAINT `departments_ibfk_2` FOREIGN KEY (`hod_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='College departments';

INSERT INTO `departments` (`id`, `college_id`, `dept_name`, `dept_code`, `hod_id`, `description`, `is_active`, `created_at`) VALUES
('1', '1', 'Computer Science', 'CS', NULL, 'Department of Computer Science and Applications', '1', '2025-11-22 16:07:18'),
('2', '1', 'Mathematics', 'MATH', NULL, 'Department of Mathematics', '1', '2025-11-22 16:07:18'),
('3', '1', 'Physics', 'PHY', NULL, 'Department of Physics', '1', '2025-11-22 16:07:18'),
('4', '2', 'Commerce', 'COM', NULL, 'Department of Commerce and Management', '1', '2025-11-22 16:07:18'),
('5', '2', 'Arts', 'ARTS', NULL, 'Department of Arts and Humanities', '1', '2025-11-22 16:07:18'),
('6', '3', 'Engineering', 'ENG', NULL, 'Department of Engineering', '1', '2025-11-22 16:07:18'),
('7', '4', 'Chemistry', 'CHEM', NULL, 'Department of Chemistry', '1', '2025-11-22 16:07:18'),
('8', '5', 'Biology', 'BIO', NULL, 'Department of Biological Sciences', '1', '2025-11-22 16:07:18');

CREATE TABLE `email_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `recipient_email` varchar(255) NOT NULL,
  `subject` varchar(500) NOT NULL,
  `body` text DEFAULT NULL,
  `email_type` enum('verification','notification','password_reset','exam_assignment','general') DEFAULT 'general',
  `status` enum('pending','sent','failed') DEFAULT 'pending',
  `sent_at` timestamp NULL DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_type` (`email_type`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `email_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Email sending logs';

CREATE TABLE `exam_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `exam_id` int(11) NOT NULL,
  `faculty_user_id` int(11) NOT NULL,
  `role_assigned` enum('invigilator','paper_setter','evaluator','external_examiner') DEFAULT 'invigilator',
  `duty_type` varchar(100) DEFAULT NULL,
  `status` enum('assigned','accepted','completed','cancelled') DEFAULT 'assigned',
  `assigned_by` int(11) DEFAULT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_exam` (`exam_id`),
  KEY `idx_faculty` (`faculty_user_id`),
  KEY `fk_exam_assignment_assigner` (`assigned_by`),
  CONSTRAINT `fk_exam_assignment_assigner` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_exam_assignment_exam` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_exam_assignment_faculty` FOREIGN KEY (`faculty_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `exam_invites` (
  `invite_id` int(11) NOT NULL AUTO_INCREMENT,
  `exam_id` int(11) NOT NULL,
  `invitee_user_id` int(11) DEFAULT NULL COMMENT 'NULL if external email only',
  `invitee_email` varchar(255) NOT NULL,
  `invitee_name` varchar(255) DEFAULT NULL,
  `role` enum('moderator','evaluator','invigilator','paper_setter','external_examiner') NOT NULL,
  `token` varchar(64) NOT NULL,
  `status` enum('pending','accepted','declined','expired') DEFAULT 'pending',
  `invited_on` timestamp NOT NULL DEFAULT current_timestamp(),
  `responded_on` timestamp NULL DEFAULT NULL,
  `response` text DEFAULT NULL COMMENT 'Accept/Decline message from invitee',
  `response_comment` text DEFAULT NULL,
  `availability_dates` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Available date ranges from invitee' CHECK (json_valid(`availability_dates`)),
  `created_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`invite_id`),
  UNIQUE KEY `token` (`token`),
  KEY `idx_exam` (`exam_id`),
  KEY `idx_invitee` (`invitee_user_id`),
  KEY `idx_token` (`token`),
  KEY `idx_status` (`status`),
  KEY `fk_exam_invites_creator` (`created_by`),
  CONSTRAINT `fk_exam_invites_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_exam_invites_exam` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_exam_invites_user` FOREIGN KEY (`invitee_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `exam_roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `exam_id` int(11) NOT NULL,
  `role_type` enum('moderator','evaluator','invigilator','paper_setter','external_examiner') NOT NULL,
  `required_count` int(11) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_exam` (`exam_id`),
  CONSTRAINT `fk_exam_roles_exam` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `exam_schedule` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `exam_id` int(11) NOT NULL,
  `exam_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `venue` varchar(255) DEFAULT NULL,
  `room_number` varchar(50) DEFAULT NULL,
  `max_capacity` int(11) DEFAULT 0,
  `current_enrolled` int(11) DEFAULT 0,
  `invigilator_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('scheduled','ongoing','completed','cancelled') DEFAULT 'scheduled',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_exam` (`exam_id`),
  KEY `idx_date` (`exam_date`),
  KEY `idx_invigilator` (`invigilator_id`),
  CONSTRAINT `exam_schedule_ibfk_1` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `exam_schedule_ibfk_2` FOREIGN KEY (`invigilator_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Exam scheduling details';

INSERT INTO `exam_schedule` (`id`, `exam_id`, `exam_date`, `start_time`, `end_time`, `venue`, `room_number`, `max_capacity`, `current_enrolled`, `invigilator_id`, `notes`, `status`, `created_at`, `updated_at`) VALUES
('1', '1', '2024-06-01', '09:00:00', '12:00:00', 'Main Hall - Science', NULL, '0', '0', NULL, NULL, 'scheduled', '2025-11-22 16:07:18', '2025-11-22 16:07:18'),
('2', '2', '2024-06-16', '09:00:00', '12:00:00', 'Main Hall - Arts', NULL, '0', '0', NULL, NULL, 'scheduled', '2025-11-22 16:07:18', '2025-11-22 16:07:18');

CREATE TABLE `examiner_nominations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dept` varchar(128) DEFAULT NULL,
  `examiner_name` varchar(255) NOT NULL,
  `role` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `dept` (`dept`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `examiner_nominations` (`id`, `dept`, `examiner_name`, `role`, `status`, `created_by`, `created_at`) VALUES
('1', 'Computer Science', 'Dr. Kumar Sharma', 'External Examiner', 'pending', '3', '2025-11-22 16:07:18'),
('2', 'Mathematics', 'Prof. Anita Desai', 'Chief Examiner', 'approved', '3', '2025-11-22 16:07:18'),
('3', 'Physics', 'Dr. Rajesh Kumar', 'External Examiner', 'pending', '3', '2025-11-22 16:07:18');

CREATE TABLE `examiner_pool` (
  `pool_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `department` varchar(100) NOT NULL,
  `expertise_area` text DEFAULT NULL,
  `total_assignments` int(11) DEFAULT 0,
  `last_assigned_date` date DEFAULT NULL,
  `availability_status` enum('available','busy','unavailable') DEFAULT 'available',
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `college_name` varchar(255) NOT NULL,
  PRIMARY KEY (`pool_id`),
  UNIQUE KEY `unique_user` (`user_id`),
  KEY `idx_department` (`department`),
  KEY `idx_status` (`availability_status`),
  KEY `idx_location` (`latitude`,`longitude`),
  CONSTRAINT `examiner_pool_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `examiner_pool` (`pool_id`, `user_id`, `department`, `expertise_area`, `total_assignments`, `last_assigned_date`, `availability_status`, `latitude`, `longitude`, `college_name`) VALUES
('1', '2', 'General', '[\"General Education\", \"Exam Administration\"]', '0', NULL, 'available', NULL, NULL, 'Science College'),
('2', '3', 'General', '[\"General Education\", \"Exam Administration\"]', '0', NULL, 'available', NULL, NULL, 'Arts College'),
('3', '4', 'General', '[\"General Education\", \"Exam Administration\"]', '0', NULL, 'available', NULL, NULL, 'Tech College');

CREATE TABLE `examiner_ratings` (
  `rating_id` int(11) NOT NULL AUTO_INCREMENT,
  `examiner_id` int(11) NOT NULL,
  `rated_by` int(11) NOT NULL,
  `exam_id` int(11) DEFAULT NULL,
  `overall_rating` int(11) NOT NULL CHECK (`overall_rating` between 1 and 5),
  `punctuality` int(11) DEFAULT NULL CHECK (`punctuality` between 1 and 5),
  `professionalism` int(11) DEFAULT NULL CHECK (`professionalism` between 1 and 5),
  `feedback` text DEFAULT NULL,
  `rated_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`rating_id`),
  KEY `idx_examiner` (`examiner_id`),
  KEY `idx_exam` (`exam_id`),
  KEY `rated_by` (`rated_by`),
  CONSTRAINT `examiner_ratings_ibfk_1` FOREIGN KEY (`examiner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `examiner_ratings_ibfk_2` FOREIGN KEY (`rated_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `examiner_ratings_ibfk_3` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `examiner_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hod_name` varchar(255) DEFAULT NULL,
  `examiner` varchar(255) DEFAULT NULL,
  `purpose` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `examiner_requests` (`id`, `hod_name`, `examiner`, `purpose`, `status`, `created_at`) VALUES
('1', 'Dr. John Smith', 'Prof. Sharma', 'Final Year Examination', 'pending', '2025-11-22 16:07:18'),
('2', 'Dr. Jane Doe', 'Dr. Kumar', 'Mid-term Assessment', 'approved', '2025-11-22 16:07:18');

CREATE TABLE `exams` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `course_code` varchar(50) DEFAULT NULL,
  `exam_date` date NOT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `status` enum('draft','submitted','approved','rejected','assigned','in_progress','completed','cancelled') DEFAULT 'draft',
  `description` text DEFAULT NULL,
  `attachments_meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`attachments_meta`)),
  `subject` varchar(255) DEFAULT NULL,
  `college_id` int(11) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_exam_status` (`status`),
  KEY `idx_exam_date` (`exam_date`),
  KEY `idx_exam_college` (`college_id`),
  KEY `idx_college_id` (`college_id`),
  KEY `idx_status_college` (`status`,`college_id`),
  KEY `idx_exams_college` (`college_id`),
  KEY `idx_exams_dept` (`department_id`),
  KEY `idx_exams_date` (`exam_date`),
  KEY `idx_exams_status` (`status`),
  KEY `fk_exams_creator` (`created_by`),
  CONSTRAINT `fk_exam_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_exams_college` FOREIGN KEY (`college_id`) REFERENCES `colleges` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_exams_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_exams_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `exams` (`id`, `title`, `course_code`, `exam_date`, `start_time`, `end_time`, `department`, `status`, `description`, `attachments_meta`, `subject`, `college_id`, `department_id`, `created_by`, `created_at`, `updated_at`) VALUES
('1', 'Mid-Term Physics', NULL, '2024-06-01', NULL, NULL, 'Science', '', NULL, NULL, NULL, '1', NULL, NULL, '2025-11-22 15:40:23', '2025-11-23 21:49:23'),
('2', 'Final Year History', NULL, '2024-06-16', NULL, NULL, 'Arts', '', NULL, NULL, NULL, '1', '5', NULL, '2025-11-22 15:40:23', '2025-12-10 18:10:43'),
('8', 'Commercial Bank Management (Banking & Finance) Makeup', NULL, '2025-11-24', NULL, NULL, 'System Admin', '', 'ddd', NULL, 'nvhv', '9', NULL, '1', '2025-11-22 18:49:18', '2025-11-23 21:44:08'),
('10', 'International Finance makeup', NULL, '2025-11-29', NULL, NULL, 'System Admin', '', '', NULL, 'dd', NULL, NULL, '7', '2025-11-28 16:41:57', '2025-11-28 16:41:57'),
('11', 'Strategic Management Makeup', NULL, '2025-11-29', NULL, NULL, 'System Admin', '', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-28 16:53:23', '2025-11-28 16:53:23'),
('12', 'Financial Services', NULL, '2025-11-30', NULL, NULL, 'System Admin', '', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-29 12:02:19', '2025-11-29 12:02:19'),
('13', 'Direct Taxation', NULL, '2025-12-04', NULL, NULL, 'siws', '', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-29 12:17:24', '2025-11-29 12:17:24'),
('14', 'Financial Services', NULL, '2025-12-01', NULL, NULL, 'siws', '', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-29 12:19:07', '2025-11-29 12:19:07'),
('15', 'Financial Services', NULL, '2025-11-29', NULL, NULL, 'System Admin', '', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-29 12:19:30', '2025-11-29 12:19:30'),
('16', 'Direct Taxation', NULL, '2025-11-30', NULL, NULL, 'siws', '', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-29 12:22:38', '2025-11-29 12:22:38'),
('17', 'Financial Services', NULL, '2025-12-01', NULL, NULL, 'siws', '', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-29 12:23:01', '2025-11-29 12:23:01'),
('18', 'Financial Services', NULL, '2025-12-02', NULL, NULL, '1111', '', '', NULL, '1234', NULL, NULL, '7', '2025-11-29 13:29:48', '2025-11-29 13:29:48'),
('19', 'International Finance makeup', NULL, '2025-11-29', NULL, NULL, '1111', '', '', NULL, 'dd', NULL, NULL, '7', '2025-11-29 13:36:52', '2025-11-29 13:36:52'),
('20', 'Corporate Financial Accounting', NULL, '2025-11-29', NULL, NULL, '1111', '', '', NULL, 'dd', NULL, NULL, '7', '2025-11-29 13:37:46', '2025-11-29 13:37:46'),
('21', 'Direct Taxation', NULL, '2025-11-30', NULL, NULL, 'System Admin', '', '', NULL, 'dd', NULL, NULL, '7', '2025-11-29 15:49:03', '2025-11-29 15:49:03'),
('22', 'Strategic Management Makeup', NULL, '2025-11-30', NULL, NULL, 'siws', '', '', NULL, 'dd', NULL, NULL, '7', '2025-11-29 16:04:03', '2025-11-29 16:04:03'),
('23', 'Financial Services', NULL, '2025-11-30', NULL, NULL, 'System Admin', '', '', NULL, 'dd', NULL, NULL, '7', '2025-11-29 16:24:47', '2025-11-29 16:24:47'),
('24', 'hgdffbjbj', NULL, '2025-12-01', NULL, NULL, 'cc', '', '', NULL, 'hdjbj', NULL, NULL, '7', '2025-11-30 21:46:34', '2025-11-30 21:46:34');

CREATE TABLE `external_examiners` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `institution` varchar(255) DEFAULT NULL,
  `origin_college` varchar(255) DEFAULT NULL,
  `expertise` varchar(255) DEFAULT NULL,
  `dept` varchar(128) DEFAULT NULL,
  `availability` date DEFAULT NULL,
  `past_assignments` int(11) DEFAULT 0,
  `status` enum('pending','confirmed','declined') DEFAULT 'pending',
  `profile_score` decimal(3,2) DEFAULT 0.00,
  `last_active` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `faculty_availability` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `faculty_id` int(11) NOT NULL,
  `unavailable_date` date NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `faculty_id` (`faculty_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `faculty_availability` (`id`, `faculty_id`, `unavailable_date`, `created_by`, `created_at`) VALUES
('1', '2', '2025-12-07', '2', '2025-11-22 16:07:18'),
('2', '3', '2025-12-12', '3', '2025-11-22 16:07:18');

CREATE TABLE `file_uploads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_type` varchar(100) DEFAULT NULL,
  `file_size` int(11) DEFAULT 0 COMMENT 'Bytes',
  `upload_category` enum('profile_photo','document','notice','forum','exam','other') DEFAULT 'other',
  `related_id` int(11) DEFAULT NULL COMMENT 'ID of related record',
  `related_type` varchar(50) DEFAULT NULL COMMENT 'Type of related record',
  `is_public` tinyint(1) DEFAULT 0,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_category` (`upload_category`),
  KEY `idx_related` (`related_type`,`related_id`),
  CONSTRAINT `file_uploads_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='File upload tracking';

INSERT INTO `file_uploads` (`id`, `user_id`, `file_name`, `file_path`, `file_type`, `file_size`, `upload_category`, `related_id`, `related_type`, `is_public`, `uploaded_at`) VALUES
('1', '1', 'exam_schedule.pdf', '/uploads/exams/exam_schedule_2025.pdf', 'application/pdf', '245678', 'exam', NULL, NULL, '0', '2025-11-20 16:07:18'),
('2', '1', 'notice_board.jpg', '/uploads/notices/notice_2025.jpg', 'image/jpeg', '156789', 'notice', NULL, NULL, '0', '2025-11-21 16:07:18');

CREATE TABLE `forum_attachments` (
  `attachment_id` int(11) NOT NULL AUTO_INCREMENT,
  `post_id` int(11) DEFAULT NULL,
  `reply_id` int(11) DEFAULT NULL,
  `uploaded_by` int(11) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_size` int(11) NOT NULL,
  `file_type` varchar(50) NOT NULL,
  `uploaded_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`attachment_id`),
  KEY `idx_post` (`post_id`),
  KEY `idx_reply` (`reply_id`),
  KEY `uploaded_by` (`uploaded_by`),
  CONSTRAINT `forum_attachments_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `forum_posts` (`post_id`) ON DELETE CASCADE,
  CONSTRAINT `forum_attachments_ibfk_2` FOREIGN KEY (`reply_id`) REFERENCES `forum_replies` (`reply_id`) ON DELETE CASCADE,
  CONSTRAINT `forum_attachments_ibfk_3` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `forum_categories` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `display_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`category_id`),
  UNIQUE KEY `name` (`name`),
  KEY `idx_order` (`display_order`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `forum_categories` (`category_id`, `name`, `description`, `icon`, `display_order`, `is_active`, `created_at`) VALUES
('1', 'General Discussion', 'General topics and announcements', 'chat', '1', '1', '2025-11-22 15:20:19'),
('2', 'Exam Coordination', 'Discuss exam scheduling and assignments', 'calendar', '2', '1', '2025-11-22 15:20:19'),
('3', 'Technical Support', 'Help with the EEMS system', 'help', '3', '1', '2025-11-22 15:20:19');

CREATE TABLE `forum_posts` (
  `post_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) NOT NULL,
  `author_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_pinned` tinyint(1) DEFAULT 0,
  `is_locked` tinyint(1) DEFAULT 0,
  `view_count` int(11) DEFAULT 0,
  `reply_count` int(11) DEFAULT 0,
  `last_reply_at` timestamp NULL DEFAULT NULL,
  `last_reply_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`post_id`),
  KEY `idx_category` (`category_id`),
  KEY `idx_author` (`author_id`),
  KEY `idx_created` (`created_at`),
  KEY `idx_pinned` (`is_pinned`,`created_at`),
  KEY `last_reply_by` (`last_reply_by`),
  CONSTRAINT `forum_posts_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `forum_categories` (`category_id`) ON DELETE CASCADE,
  CONSTRAINT `forum_posts_ibfk_2` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `forum_posts_ibfk_3` FOREIGN KEY (`last_reply_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `forum_replies` (
  `reply_id` int(11) NOT NULL AUTO_INCREMENT,
  `post_id` int(11) NOT NULL,
  `author_id` int(11) NOT NULL,
  `content` text NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_solution` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`reply_id`),
  KEY `idx_post` (`post_id`,`created_at`),
  KEY `idx_author` (`author_id`),
  CONSTRAINT `forum_replies_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `forum_posts` (`post_id`) ON DELETE CASCADE,
  CONSTRAINT `forum_replies_ibfk_2` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `import_history` (
  `import_id` int(11) NOT NULL AUTO_INCREMENT,
  `imported_by` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_type` enum('csv','xlsx') NOT NULL,
  `records_total` int(11) NOT NULL,
  `records_success` int(11) NOT NULL,
  `records_failed` int(11) NOT NULL,
  `error_log` text DEFAULT NULL,
  `imported_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`import_id`),
  KEY `idx_imported_by` (`imported_by`),
  KEY `idx_date` (`imported_at`),
  CONSTRAINT `import_history_ibfk_1` FOREIGN KEY (`imported_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='CSV/Excel import tracking';

CREATE TABLE `notices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `content` text NOT NULL,
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `target_role` varchar(50) DEFAULT 'all',
  `posted_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `posted_by` (`posted_by`),
  CONSTRAINT `notices_ibfk_1` FOREIGN KEY (`posted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `notification_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `email_notifications` tinyint(1) DEFAULT 1,
  `sms_notifications` tinyint(1) DEFAULT 0,
  `push_notifications` tinyint(1) DEFAULT 1,
  `exam_reminders` tinyint(1) DEFAULT 1,
  `assignment_alerts` tinyint(1) DEFAULT 1,
  `approval_updates` tinyint(1) DEFAULT 1,
  `forum_replies` tinyint(1) DEFAULT 1,
  `notice_board` tinyint(1) DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `notification_settings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='User notification preferences';

INSERT INTO `notification_settings` (`id`, `user_id`, `email_notifications`, `sms_notifications`, `push_notifications`, `exam_reminders`, `assignment_alerts`, `approval_updates`, `forum_replies`, `notice_board`, `updated_at`) VALUES
('1', '1', '1', '0', '1', '1', '1', '1', '1', '1', '2025-11-22 16:07:18'),
('2', '2', '1', '0', '1', '1', '1', '1', '1', '1', '2025-11-22 16:07:18'),
('3', '3', '1', '0', '1', '1', '1', '1', '1', '1', '2025-11-22 16:07:18'),
('4', '4', '1', '0', '1', '1', '1', '1', '1', '1', '2025-11-22 16:07:18');

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` enum('assignment','approval','exam_duty','message','rating','system','forum','notice') NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `related_id` int(11) DEFAULT NULL,
  `related_type` varchar(50) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `priority` enum('low','normal','high') DEFAULT 'normal',
  `action_url` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`notification_id`),
  KEY `idx_user_unread` (`user_id`,`is_read`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `permissions` (
  `user_id` int(11) NOT NULL,
  `principal_access` tinyint(1) NOT NULL DEFAULT 0,
  `vice_access` tinyint(1) NOT NULL DEFAULT 0,
  `hod_access` tinyint(1) NOT NULL DEFAULT 0,
  `teacher_access` tinyint(1) NOT NULL DEFAULT 0,
  `module_overview` tinyint(1) DEFAULT 1,
  `module_user_management` tinyint(1) DEFAULT 0,
  `module_exam_management` tinyint(1) DEFAULT 0,
  `module_approvals` tinyint(1) DEFAULT 0,
  `module_available_exams` tinyint(1) DEFAULT 1,
  `module_permissions` tinyint(1) DEFAULT 0,
  `module_analytics` tinyint(1) DEFAULT 0,
  `module_audit_logs` tinyint(1) DEFAULT 0,
  `module_settings` tinyint(1) DEFAULT 0,
  `module_principal_dash` tinyint(1) DEFAULT 0,
  `module_vice_dash` tinyint(1) DEFAULT 0,
  `module_hod_dash` tinyint(1) DEFAULT 0,
  `module_teacher_dash` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `permissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `permissions` (`user_id`, `principal_access`, `vice_access`, `hod_access`, `teacher_access`, `module_overview`, `module_user_management`, `module_exam_management`, `module_approvals`, `module_available_exams`, `module_permissions`, `module_analytics`, `module_audit_logs`, `module_settings`, `module_principal_dash`, `module_vice_dash`, `module_hod_dash`, `module_teacher_dash`) VALUES
('1', '1', '1', '1', '1', '1', '0', '0', '0', '1', '0', '0', '0', '0', '0', '0', '0', '1'),
('2', '1', '1', '1', '1', '1', '0', '0', '0', '1', '0', '0', '0', '0', '0', '0', '0', '1'),
('3', '1', '1', '1', '1', '1', '0', '0', '0', '1', '0', '0', '0', '0', '0', '0', '0', '1'),
('4', '1', '1', '1', '1', '1', '0', '0', '0', '1', '0', '0', '0', '0', '0', '0', '0', '1'),
('9', '1', '1', '1', '1', '1', '0', '0', '0', '1', '0', '0', '0', '0', '0', '0', '0', '1');

CREATE TABLE `practical_attempts` (
  `attempt_id` int(11) NOT NULL AUTO_INCREMENT,
  `session_id` int(11) NOT NULL,
  `student_id` varchar(100) NOT NULL,
  `slip_id` varchar(100) NOT NULL COMMENT 'Unique slip identifier',
  `recording_path` varchar(500) DEFAULT NULL COMMENT 'Desktop recording file path',
  `outputs_path` varchar(500) DEFAULT NULL COMMENT 'Student outputs/code path',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `finalized_at` timestamp NULL DEFAULT NULL,
  `marks` decimal(5,2) DEFAULT NULL,
  `evaluated_by` int(11) DEFAULT NULL,
  `evaluation_comments` text DEFAULT NULL,
  PRIMARY KEY (`attempt_id`),
  UNIQUE KEY `slip_id` (`slip_id`),
  KEY `idx_session` (`session_id`),
  KEY `idx_student` (`student_id`),
  KEY `idx_slip` (`slip_id`),
  KEY `idx_evaluator` (`evaluated_by`),
  CONSTRAINT `fk_practical_attempts_evaluator` FOREIGN KEY (`evaluated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_practical_attempts_session` FOREIGN KEY (`session_id`) REFERENCES `practical_sessions` (`session_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `practical_sessions` (
  `session_id` int(11) NOT NULL AUTO_INCREMENT,
  `exam_id` int(11) NOT NULL,
  `college_id` int(11) NOT NULL,
  `lab_id` varchar(100) DEFAULT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime NOT NULL,
  `status` enum('scheduled','in_progress','completed','cancelled') DEFAULT 'scheduled',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`session_id`),
  KEY `idx_exam` (`exam_id`),
  KEY `idx_college` (`college_id`),
  KEY `idx_start_time` (`start_time`),
  CONSTRAINT `fk_practical_sessions_exam` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `profile_field_locks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `field_name` varchar(100) NOT NULL COMMENT 'email, phone, college_name, etc',
  `locked_by` int(11) NOT NULL COMMENT 'Admin who locked the field',
  `locked_at` timestamp NULL DEFAULT current_timestamp(),
  `reason` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_lock` (`user_id`,`field_name`),
  KEY `idx_user` (`user_id`),
  KEY `idx_locked_by` (`locked_by`),
  CONSTRAINT `profile_field_locks_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `profile_field_locks_ibfk_2` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Locked profile fields by admin';

CREATE TABLE `profile_update_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `field_name` varchar(100) NOT NULL,
  `current_value` text NOT NULL,
  `requested_value` text NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `requested_at` timestamp NULL DEFAULT current_timestamp(),
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `reviewed_by` (`reviewed_by`),
  CONSTRAINT `profile_update_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `profile_update_requests_ibfk_2` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Pending profile changes requiring approval';

CREATE TABLE `question_papers` (
  `paper_id` int(11) NOT NULL AUTO_INCREMENT,
  `exam_id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `status` enum('draft','submitted','approved','locked') DEFAULT 'draft',
  `content_location` varchar(500) DEFAULT NULL COMMENT 'File path or cloud URL',
  `co_po_mapping_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Course Outcome to Program Outcome mapping' CHECK (json_valid(`co_po_mapping_json`)),
  `locked_by` int(11) DEFAULT NULL,
  `locked_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`paper_id`),
  KEY `idx_exam` (`exam_id`),
  KEY `idx_creator` (`created_by`),
  KEY `idx_locker` (`locked_by`),
  CONSTRAINT `fk_question_papers_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_question_papers_exam` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_question_papers_locker` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `ratings` (
  `rating_id` int(11) NOT NULL AUTO_INCREMENT,
  `examiner_id` int(11) NOT NULL COMMENT 'References external_examiners or users',
  `exam_id` int(11) DEFAULT NULL COMMENT 'Optional: link to specific exam',
  `rated_by_user_id` int(11) NOT NULL,
  `rated_by_role` varchar(50) NOT NULL,
  `college_id` int(11) DEFAULT NULL,
  `score` decimal(3,2) NOT NULL CHECK (`score` >= 1.0 and `score` <= 5.0),
  `comments` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`rating_id`),
  KEY `idx_examiner` (`examiner_id`),
  KEY `idx_exam` (`exam_id`),
  KEY `idx_rated_by` (`rated_by_user_id`),
  KEY `idx_college` (`college_id`),
  CONSTRAINT `fk_ratings_exam` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ratings_rater` FOREIGN KEY (`rated_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `level` int(11) DEFAULT 1 COMMENT 'Role hierarchy level',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_name` (`role_name`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='System roles';

INSERT INTO `roles` (`id`, `role_name`, `description`, `level`, `created_at`) VALUES
('1', 'admin', 'System Administrator', '10', '2025-11-22 16:03:32'),
('2', 'principal', 'College Principal', '9', '2025-11-22 16:03:32'),
('3', 'vice_principal', 'Vice Principal', '8', '2025-11-22 16:03:32'),
('4', 'hod', 'Head of Department', '7', '2025-11-22 16:03:32'),
('5', 'teacher', 'Faculty Teacher', '5', '2025-11-22 16:03:32'),
('6', 'student', 'Student', '1', '2025-11-22 16:03:32');

CREATE TABLE `schema_versions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `version` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `applied_by` varchar(100) DEFAULT 'system',
  PRIMARY KEY (`id`),
  UNIQUE KEY `version` (`version`),
  KEY `idx_version` (`version`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Database schema versioning';

INSERT INTO `schema_versions` (`id`, `version`, `description`, `applied_at`, `applied_by`) VALUES
('1', '2.0.0', 'Complete dashboard tables with all modules', '2025-11-22 16:03:32', 'admin');

CREATE TABLE `session_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `session_id` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `login_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `logout_time` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_session` (`session_id`),
  KEY `idx_active` (`is_active`),
  CONSTRAINT `session_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='User session tracking';

CREATE TABLE `system_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `system_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES
('auto_verify_users', '0', '2025-12-13 16:46:12'),
('default_exam_status', 'Pending', '2025-11-22 15:41:25'),
('default_password', '1234', '2025-12-13 16:46:12'),
('email_notifications', '1', '2025-12-13 16:46:12'),
('enable_notifications', '1', '2025-11-22 15:41:25'),
('maintenance_mode', '0', '2025-12-13 16:46:12'),
('max_exam_assignments', '10', '2025-12-13 16:46:12'),
('max_upload_size', '10485760', '2025-11-22 15:41:25'),
('session_timeout', '30', '2025-12-13 16:46:12'),
('site_name', 'EEMS', '2025-11-22 15:41:25'),
('system_email', 'admin@eems.edu', '2025-12-13 16:46:12'),
('system_name', 'External Exam Management System', '2025-12-13 16:46:12');

CREATE TABLE `timeline_events` (
  `event_id` int(11) NOT NULL AUTO_INCREMENT,
  `event_type` enum('exam','assignment','notice','approval','system') NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `event_date` date NOT NULL,
  `related_id` int(11) DEFAULT NULL,
  `related_type` varchar(50) DEFAULT NULL,
  `college_name` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`event_id`),
  KEY `idx_date` (`event_date`),
  KEY `idx_type` (`event_type`),
  KEY `idx_college` (`college_name`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `timeline_events` (`event_id`, `event_type`, `title`, `description`, `event_date`, `related_id`, `related_type`, `college_name`, `created_at`) VALUES
('1', 'exam', 'Mathematics Final Exam', 'Final semester examination for Mathematics Department', '2025-12-02', NULL, 'exams', 'St. Joseph College', '2025-11-22 16:07:18'),
('2', 'assignment', 'Physics Lab Assignment', 'Laboratory assignment for Physics practical examination', '2025-11-27', NULL, 'assignments', 'Christ University', '2025-11-22 16:07:18'),
('3', 'notice', 'Library Timings Update', 'Updated library timings for examination period', '2025-11-22', NULL, 'college_notices', 'MES College', '2025-11-22 16:07:18'),
('4', 'system', 'System Maintenance', 'Scheduled system maintenance on weekend', '2025-11-29', NULL, NULL, NULL, '2025-11-22 16:07:18');

CREATE TABLE `user_module_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `module_name` varchar(50) NOT NULL,
  `can_access` tinyint(1) NOT NULL DEFAULT 0,
  `can_view` tinyint(1) NOT NULL DEFAULT 1,
  `can_edit` tinyint(1) NOT NULL DEFAULT 0,
  `can_delete` tinyint(1) NOT NULL DEFAULT 0,
  `can_export` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_module_unique` (`user_id`,`module_name`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_module_name` (`module_name`),
  CONSTRAINT `user_module_permissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `user_module_permissions` (`id`, `user_id`, `module_name`, `can_access`, `can_view`, `can_edit`, `can_delete`, `can_export`, `created_at`, `updated_at`) VALUES
('1', '1', 'overview', '1', '1', '0', '0', '0', '2025-11-22 15:40:34', '2025-11-22 15:40:34'),
('2', '2', 'overview', '1', '1', '0', '0', '0', '2025-11-22 15:40:34', '2025-11-22 15:40:34'),
('3', '3', 'overview', '1', '1', '0', '0', '0', '2025-11-22 15:40:34', '2025-11-22 15:40:34'),
('4', '4', 'overview', '1', '1', '0', '0', '0', '2025-11-22 15:40:34', '2025-11-22 15:40:34');

;

INSERT INTO `user_permissions_view` (`user_id`, `user_name`, `email`, `role`, `principal_access`, `vice_access`, `hod_access`, `teacher_access`, `module_overview`, `module_user_management`, `module_exam_management`, `module_approvals`, `module_available_exams`, `module_permissions`, `module_analytics`, `module_audit_logs`, `module_settings`, `module_principal_dash`, `module_vice_dash`, `module_hod_dash`, `module_teacher_dash`) VALUES
('1', 'Arjun', 'arjun@admin.com', 'admin', '1', '1', '1', '1', '1', '0', '0', '0', '1', '0', '0', '0', '0', '0', '0', '0', '1'),
('2', 'Jane Doe', 'jane.doe@example.com', 'teacher', '1', '1', '1', '1', '1', '0', '0', '0', '1', '0', '0', '0', '0', '0', '0', '0', '1'),
('3', 'John Smith', 'john.smith@example.com', 'hod', '1', '1', '1', '1', '1', '0', '0', '0', '1', '0', '0', '0', '0', '0', '0', '0', '1'),
('4', 'Pending User', 'pending@example.com', 'teacher', '1', '1', '1', '1', '1', '0', '0', '0', '1', '0', '0', '0', '0', '0', '0', '0', '1'),
('5', 'principle', 'principle@gmail.com', 'principal', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('6', 'principle', 'principle1@gmail.com', 'principal', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('7', 'Arjun Admin', 'arjun@gmail.com', 'admin', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('8', 'principle', 'p@gmail.con', 'principal', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('9', 'principle', 'pri@gmail.com', 'principal', '1', '1', '1', '1', '1', '0', '0', '0', '1', '0', '0', '0', '0', '0', '0', '0', '1');

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `post` enum('admin','principal','vice_principal','hod','teacher','faculty') NOT NULL,
  `designation` varchar(255) DEFAULT NULL,
  `college_name` varchar(255) NOT NULL,
  `college_id` int(11) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `qualification` varchar(255) DEFAULT NULL,
  `experience` int(11) DEFAULT 0,
  `phone` varchar(20) NOT NULL,
  `employee_id` varchar(50) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `profile_photo` varchar(500) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `status` enum('pending','verified','rejected') NOT NULL DEFAULT 'pending',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `expertise_area` text DEFAULT NULL COMMENT 'JSON array',
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `last_profile_update` timestamp NULL DEFAULT NULL,
  `profile_completion` int(11) DEFAULT 0 COMMENT 'Percentage',
  `avg_rating` decimal(3,2) DEFAULT NULL,
  `rating_count` int(11) DEFAULT 0,
  `onboarding_completed_at` timestamp NULL DEFAULT NULL,
  `onboarding_step` int(11) DEFAULT 0,
  `profile_data` text DEFAULT NULL COMMENT 'JSON data for teacher profile',
  `address` text DEFAULT NULL COMMENT 'User residential address',
  `profile_completed` tinyint(1) DEFAULT 0 COMMENT 'Whether teacher completed onboarding',
  `raw_password` varchar(255) DEFAULT 'Welcome@123' COMMENT 'Plain text password for admin reference',
  `verified_by` int(11) DEFAULT NULL COMMENT 'User ID who verified this account',
  `staff_id` varchar(50) DEFAULT NULL COMMENT 'Unique staff ID (fixed)',
  `date_of_joining` date DEFAULT NULL COMMENT 'Joining date (fixed)',
  `personal_email` varchar(255) DEFAULT NULL COMMENT 'Personal email (editable)',
  `alternate_phone` varchar(20) DEFAULT NULL COMMENT 'Alternate phone (editable)',
  `current_address` text DEFAULT NULL COMMENT 'Current address (editable)',
  `permanent_address` text DEFAULT NULL COMMENT 'Permanent address (editable)',
  `aadhar_number` varchar(12) DEFAULT NULL COMMENT 'Aadhar number',
  `specialization` varchar(255) DEFAULT NULL COMMENT 'Specialization/Subject',
  `experience_years` int(11) DEFAULT 0 COMMENT 'Years of experience',
  `emergency_contact_name` varchar(255) DEFAULT NULL COMMENT 'Emergency contact name',
  `emergency_contact_phone` varchar(20) DEFAULT NULL COMMENT 'Emergency contact phone',
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_college_id` (`college_id`),
  KEY `idx_verified_by` (`verified_by`),
  KEY `idx_staff_id` (`staff_id`),
  KEY `idx_users_college` (`college_id`),
  KEY `idx_users_dept` (`department_id`),
  KEY `idx_users_role` (`post`),
  CONSTRAINT `fk_user_college` FOREIGN KEY (`college_id`) REFERENCES `colleges` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_users_college` FOREIGN KEY (`college_id`) REFERENCES `colleges` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_users_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `users` (`id`, `name`, `gender`, `date_of_birth`, `post`, `designation`, `college_name`, `college_id`, `department_id`, `department`, `qualification`, `experience`, `phone`, `employee_id`, `email`, `profile_photo`, `password`, `status`, `is_active`, `created_at`, `last_login`, `expertise_area`, `latitude`, `longitude`, `last_profile_update`, `profile_completion`, `avg_rating`, `rating_count`, `onboarding_completed_at`, `onboarding_step`, `profile_data`, `address`, `profile_completed`, `raw_password`, `verified_by`, `staff_id`, `date_of_joining`, `personal_email`, `alternate_phone`, `current_address`, `permanent_address`, `aadhar_number`, `specialization`, `experience_years`, `emergency_contact_name`, `emergency_contact_phone`) VALUES
('1', 'Arjun', NULL, NULL, 'admin', NULL, 'System Admin', '9', NULL, NULL, NULL, '0', '0000000000', NULL, 'arjun@admin.com', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oOoZ9Hfq6KVd0yM1qzGQ5J5BZfqWVK', 'verified', '1', '2024-05-22 06:17:15', NULL, NULL, NULL, NULL, NULL, '0', NULL, '0', NULL, '0', NULL, NULL, '0', 'Welcome@123', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL),
('2', 'Jane Doe', NULL, NULL, 'teacher', NULL, 'Science College', '8', NULL, NULL, NULL, '0', '1234567891', NULL, 'jane.doe@example.com', NULL, '$2y$10$jtpRcF96BjNtLg2ncavXwOzalLQagmfoBeXgCeXHHFMutuM9Z0w4u', 'verified', '1', '2024-05-22 06:17:15', NULL, NULL, NULL, NULL, NULL, '0', NULL, '0', NULL, '0', '{\"aadhar_number\":\"123456789789\",\"phone\":\"1234567891\",\"alternate_phone\":\"1234111111\",\"date_of_birth\":\"2001-11-10\",\"gender\":\"Male\",\"address\":\"jhagrzoghiuozkjbnljkedjelrrnkllrjrojgo\",\"city\":\"jw4giojg4\",\"state\":\"ethigheri\",\"pincode\":\"444444\",\"qualification\":\"B.Tech\",\"specialization\":\"dugd\",\"experience_years\":5,\"emergency_contact_name\":\"74847874\",\"emergency_contact_phone\":\"5898956895\",\"profile_completed_at\":\"2025-11-25 13:19:20\"}', NULL, '1', 'Welcome@123', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL),
('3', 'John Smith', NULL, NULL, 'hod', NULL, 'Arts College', '6', NULL, NULL, NULL, '0', '4445556666', NULL, 'john.smith@example.com', NULL, '$2y$10$PZARGBCBvkh66BZqIdEvEOJOoCFAGS6mzCoNODqtgMlnTFSZVKyuy', 'verified', '1', '2024-05-22 06:17:15', NULL, NULL, NULL, NULL, NULL, '0', NULL, '0', NULL, '0', NULL, NULL, '0', 'Welcome@123', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL),
('4', 'Pending User', NULL, NULL, 'teacher', NULL, 'Tech College', '10', NULL, NULL, NULL, '0', '5898956895', NULL, 'pending@example.com', NULL, '$2y$10$rcvuw6r5pQE3elSBzZaw/OJggnzBOBEg7QN28Dzp6ofW2BXpKJkSe', 'verified', '1', '2024-05-22 06:17:15', NULL, NULL, NULL, NULL, NULL, '0', NULL, '0', NULL, '0', '{\"aadhar_number\":\"123456789123\",\"phone\":\"5898956895\",\"alternate_phone\":\"5898956895\",\"date_of_birth\":\"2007-11-10\",\"gender\":\"Male\",\"address\":\"jhagrzoghiuozkjbnljkedjelrrnkllrjrojgo\",\"city\":\"jw4giojg4\",\"state\":\"ethigheri\",\"pincode\":\"444444\",\"qualification\":\"M.Tech\",\"specialization\":\"dugd\",\"experience_years\":0,\"emergency_contact_name\":\"74847874\",\"emergency_contact_phone\":\"5898956895\",\"profile_completed_at\":\"2025-11-29 08:14:59\"}', NULL, '1', 'Welcome@123', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL),
('5', 'principle', NULL, NULL, 'principal', NULL, 'eee', '7', NULL, NULL, NULL, '0', '87867387837', NULL, 'principle@gmail.com', NULL, '$2y$10$VjCCfGPLnQf68FWqUMKn0eHT5etDfX1fVCJ8ZsjRCc0hOSM.7d9VS', 'verified', '1', '2025-11-22 16:26:56', NULL, NULL, NULL, NULL, NULL, '0', NULL, '0', NULL, '0', NULL, NULL, '0', 'Welcome@123', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL),
('6', 'principle', NULL, NULL, 'principal', NULL, '1234', '11', NULL, NULL, NULL, '0', '9428882827828', NULL, 'principle1@gmail.com', NULL, '$2y$10$AubhS9ljZtD6BpNp.sYSjeSNVCJgMY0bgKkBl.F9yNvShkj3/hKIi', 'verified', '1', '2025-11-23 22:17:41', NULL, NULL, NULL, NULL, NULL, '0', NULL, '0', NULL, '0', NULL, NULL, '0', 'Welcome@6627', '999999', 'STF001', '2025-11-06', NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL),
('7', 'Arjun Admin', NULL, NULL, 'admin', NULL, 'System Admin', '9', NULL, NULL, NULL, '0', '0000000000', NULL, 'arjun@gmail.com', NULL, '$2y$10$Bb1vIFlFxvKq0hitrOL9X.rSMMjjrjMGgR/cJCbZd1SJC6nKAk2ZW', 'verified', '1', '2025-11-23 22:55:41', NULL, NULL, NULL, NULL, NULL, '0', NULL, '0', NULL, '0', NULL, NULL, '0', 'Welcome@123', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL),
('8', 'principle', NULL, NULL, 'principal', NULL, 'siws', '12', NULL, NULL, NULL, '0', '+915898956895', NULL, 'p@gmail.con', NULL, '$2y$10$pKsGp5Bn2.Wflsl1e.tbiOwHmbGIs/6mRf1Y/yL6GKly.qYjKfKW.', 'verified', '1', '2025-12-10 17:31:06', NULL, NULL, NULL, NULL, NULL, '0', NULL, '0', NULL, '0', NULL, NULL, '0', 'Welcome@123', '7', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL),
('9', 'principle', NULL, NULL, 'principal', NULL, 'ssss', NULL, NULL, NULL, NULL, '0', '+915898956895', NULL, 'pri@gmail.com', NULL, '$2y$10$aQQzhtMbSYKFpBY6ofYB4eRANZSbLiGuqsuy9i1RYC/xeLK1m3Z0K', 'verified', '1', '2025-12-10 18:15:11', NULL, NULL, NULL, NULL, NULL, '0', NULL, '0', NULL, '0', NULL, NULL, '0', 'Welcome@123', '7', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL);

CREATE TABLE `verifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `verification_type` enum('email','phone','document','admin_approval') DEFAULT 'admin_approval',
  `verification_code` varchar(100) DEFAULT NULL,
  `document_type` varchar(100) DEFAULT NULL COMMENT 'ID card, certificate, etc',
  `document_path` varchar(500) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `verified_at` timestamp NULL DEFAULT NULL,
  `verified_by` int(11) DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_type` (`verification_type`),
  KEY `verified_by` (`verified_by`),
  CONSTRAINT `verifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `verifications_ibfk_2` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='User verification tracking';

;

INSERT INTO `vw_active_users` (`id`, `name`, `email`, `post`, `college_name`, `college_code`, `status`, `is_active`, `created_at`, `last_login`, `total_assignments`, `unread_notifications`) VALUES
('1', 'Arjun', 'arjun@admin.com', 'admin', 'System Admin', 'COL004', 'verified', '1', '2024-05-22 06:17:15', NULL, '0', '0'),
('2', 'Jane Doe', 'jane.doe@example.com', 'teacher', 'Science College', 'COL003', 'verified', '1', '2024-05-22 06:17:15', NULL, '0', '0'),
('3', 'John Smith', 'john.smith@example.com', 'hod', 'Arts College', 'COL001', 'verified', '1', '2024-05-22 06:17:15', NULL, '0', '0'),
('4', 'Pending User', 'pending@example.com', 'teacher', 'Tech College', 'COL005', 'verified', '1', '2024-05-22 06:17:15', NULL, '0', '0'),
('5', 'principle', 'principle@gmail.com', 'principal', 'eee', 'COL002', 'verified', '1', '2025-11-22 16:26:56', NULL, '0', '0'),
('6', 'principle', 'principle1@gmail.com', 'principal', '1234', 'COL214', 'verified', '1', '2025-11-23 22:17:41', NULL, '0', '0'),
('7', 'Arjun Admin', 'arjun@gmail.com', 'admin', 'System Admin', 'COL004', 'verified', '1', '2025-11-23 22:55:41', NULL, '0', '0'),
('8', 'principle', 'p@gmail.con', 'principal', 'siws', 'COL854', 'verified', '1', '2025-12-10 17:31:06', NULL, '0', '0'),
('9', 'principle', 'pri@gmail.com', 'principal', 'ssss', NULL, 'verified', '1', '2025-12-10 18:15:11', NULL, '0', '0');

;

INSERT INTO `vw_college_stats` (`id`, `college_name`, `college_code`, `city`, `state`, `total_faculty`, `total_teachers`, `total_hods`, `total_exams`, `is_active`, `created_at`) VALUES
('1', 'St. Joseph College', 'SJC001', 'Bangalore', 'Karnataka', '0', '0', '0', '0', '1', '2025-11-22 16:07:18'),
('2', 'Christ University', 'CU002', 'Bangalore', 'Karnataka', '0', '0', '0', '0', '1', '2025-11-22 16:07:18'),
('3', 'MES College', 'MES003', 'Bangalore', 'Karnataka', '0', '0', '0', '0', '1', '2025-11-22 16:07:18'),
('4', 'St. Aloysius College', 'SAC004', 'Mangalore', 'Karnataka', '0', '0', '0', '0', '1', '2025-11-22 16:07:18'),
('5', 'Mount Carmel College', 'MCC005', 'Bangalore', 'Karnataka', '0', '0', '0', '0', '1', '2025-11-22 16:07:18'),
('6', 'Arts College', 'COL001', NULL, NULL, '1', '0', '1', '0', '1', '2025-11-23 21:36:40'),
('7', 'eee', 'COL002', NULL, NULL, '1', '0', '0', '0', '1', '2025-11-23 21:36:40'),
('8', 'Science College', 'COL003', NULL, NULL, '1', '1', '0', '0', '1', '2025-11-23 21:36:40'),
('9', 'System Admin', 'COL004', NULL, NULL, '2', '0', '0', '7', '1', '2025-11-23 21:36:40'),
('10', 'Tech College', 'COL005', NULL, NULL, '1', '1', '0', '0', '1', '2025-11-23 21:36:40'),
('11', '1234', 'COL214', NULL, NULL, '1', '0', '0', '0', '1', '2025-12-10 18:10:43'),
('12', 'siws', 'COL854', NULL, NULL, '1', '0', '0', '5', '1', '2025-12-10 18:10:43');

;

INSERT INTO `vw_exam_stats` (`id`, `title`, `exam_date`, `status`, `department`, `subject`, `total_assignments`, `schedule_count`, `created_by_name`, `created_at`) VALUES
('1', 'Mid-Term Physics', '2024-06-01', '', 'Science', NULL, '0', '1', NULL, '2025-11-22 15:40:23'),
('2', 'Final Year History', '2024-06-16', '', 'Arts', NULL, '0', '1', NULL, '2025-11-22 15:40:23'),
('8', 'Commercial Bank Management (Banking & Finance) Makeup', '2025-11-24', '', 'System Admin', 'nvhv', '0', '0', 'Arjun', '2025-11-22 18:49:18'),
('10', 'International Finance makeup', '2025-11-29', '', 'System Admin', 'dd', '0', '0', 'Arjun Admin', '2025-11-28 16:41:57'),
('11', 'Strategic Management Makeup', '2025-11-29', '', 'System Admin', NULL, '0', '0', NULL, '2025-11-28 16:53:23'),
('12', 'Financial Services', '2025-11-30', '', 'System Admin', NULL, '0', '0', NULL, '2025-11-29 12:02:19'),
('13', 'Direct Taxation', '2025-12-04', '', 'siws', NULL, '0', '0', NULL, '2025-11-29 12:17:24'),
('14', 'Financial Services', '2025-12-01', '', 'siws', NULL, '0', '0', NULL, '2025-11-29 12:19:07'),
('15', 'Financial Services', '2025-11-29', '', 'System Admin', NULL, '0', '0', NULL, '2025-11-29 12:19:30'),
('16', 'Direct Taxation', '2025-11-30', '', 'siws', NULL, '0', '0', NULL, '2025-11-29 12:22:38'),
('17', 'Financial Services', '2025-12-01', '', 'siws', NULL, '0', '0', NULL, '2025-11-29 12:23:01'),
('18', 'Financial Services', '2025-12-02', '', '1111', '1234', '0', '0', 'Arjun Admin', '2025-11-29 13:29:48'),
('19', 'International Finance makeup', '2025-11-29', '', '1111', 'dd', '0', '0', 'Arjun Admin', '2025-11-29 13:36:52'),
('20'