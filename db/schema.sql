-- Drop existing tables if they exist to start fresh
DROP TABLE IF EXISTS `assignments`, `approvals`, `permissions`, `exams`, `users`;

-- Main users table for registration, login, and faculty data
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `post` enum('teacher','hod','vice_principal','principal','admin') NOT NULL,
  `college_name` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `status` enum('pending','verified','rejected') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Permissions for dashboard access (linked to the users table)
CREATE TABLE IF NOT EXISTS `permissions` (
  `user_id` int(11) NOT NULL,
  `principal_access` tinyint(1) NOT NULL DEFAULT 0,
  `vice_access` tinyint(1) NOT NULL DEFAULT 0,
  `hod_access` tinyint(1) NOT NULL DEFAULT 0,
  `teacher_access` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `permissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Exams table for dashboard data
CREATE TABLE IF NOT EXISTS `exams` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `exam_date` date NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Assignments table to link users (faculty) to exams
CREATE TABLE IF NOT EXISTS `assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `faculty_id` int(11) NOT NULL,
  `exam_id` int(11) NOT NULL,
  `role` varchar(100) DEFAULT 'Invigilator',
  PRIMARY KEY (`id`),
  KEY `faculty_id` (`faculty_id`),
  KEY `exam_id` (`exam_id`),
  CONSTRAINT `assignments_ibfk_1` FOREIGN KEY (`faculty_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `assignments_ibfk_2` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Approvals table for handling various requests
CREATE TABLE IF NOT EXISTS `approvals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `requester_id` int(11) NOT NULL,
  `type` varchar(100) NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `processed_by` int(11) DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `requester_id` (`requester_id`),
  CONSTRAINT `approvals_ibfk_1` FOREIGN KEY (`requester_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
