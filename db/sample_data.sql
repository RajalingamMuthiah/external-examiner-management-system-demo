-- ================================================================
-- EEMS SAMPLE DATA
-- ================================================================
-- Run this AFTER eems_migration_complete.sql
-- This creates sample colleges, departments, and users for testing
-- ================================================================

USE eems;

-- Insert sample colleges
INSERT INTO colleges (id, name) VALUES
(1, 'St. Joseph Engineering College'),
(2, 'Canara Engineering College'),
(3, 'Sahyadri College of Engineering'),
(4, 'Srinivas Institute of Technology')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Insert sample departments for each college
INSERT INTO departments (id, college_id, name) VALUES
-- St. Joseph Engineering College
(1, 1, 'Computer Science and Engineering'),
(2, 1, 'Electronics and Communication'),
(3, 1, 'Mechanical Engineering'),
(4, 1, 'Civil Engineering'),
-- Canara Engineering College
(5, 2, 'Computer Science and Engineering'),
(6, 2, 'Information Science'),
(7, 2, 'Electronics and Communication'),
(8, 2, 'Electrical Engineering'),
-- Sahyadri College of Engineering
(9, 3, 'Computer Science and Engineering'),
(10, 3, 'Electronics and Communication'),
(11, 3, 'Mechanical Engineering'),
(12, 3, 'MBA'),
-- Srinivas Institute of Technology
(13, 4, 'Computer Science and Engineering'),
(14, 4, 'Electronics and Communication'),
(15, 4, 'Civil Engineering'),
(16, 4, 'Information Science')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Update existing admin user with college_id
UPDATE users 
SET college_id = NULL, department_id = NULL, post = 'admin'
WHERE email = 'arjun@gmail.com';

-- Insert sample users for testing (if they don't exist)
-- Password for all test users: 'password123'

-- Principal for College 1
INSERT INTO users (name, email, password, post, college_id, department_id, phone, status) 
SELECT 'Dr. Rajesh Kumar', 'principal.sjec@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 
       'principal', 1, NULL, '9876543210', 'verified'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'principal.sjec@example.com');

-- Vice Principal for College 1
INSERT INTO users (name, email, password, post, college_id, department_id, phone, status) 
SELECT 'Dr. Meera Patel', 'vp.sjec@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 
       'vice_principal', 1, NULL, '9876543211', 'verified'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'vp.sjec@example.com');

-- HOD for CSE Department, College 1
INSERT INTO users (name, email, password, post, college_id, department_id, phone, status) 
SELECT 'Dr. Suresh Nair', 'hod.cse.sjec@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 
       'hod', 1, 1, '9876543212', 'verified'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'hod.cse.sjec@example.com');

-- Teachers for College 1, CSE Department
INSERT INTO users (name, email, password, post, college_id, department_id, phone, status) 
SELECT 'Prof. Anita Sharma', 'teacher1.sjec@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 
       'teacher', 1, 1, '9876543213', 'verified'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'teacher1.sjec@example.com');

INSERT INTO users (name, email, password, post, college_id, department_id, phone, status) 
SELECT 'Prof. Ramesh Verma', 'teacher2.sjec@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 
       'teacher', 1, 1, '9876543214', 'verified'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'teacher2.sjec@example.com');

-- Principal for College 2
INSERT INTO users (name, email, password, post, college_id, department_id, phone, status) 
SELECT 'Dr. Vijay Rao', 'principal.canara@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 
       'principal', 2, NULL, '9876543220', 'verified'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'principal.canara@example.com');

-- HOD for CSE Department, College 2
INSERT INTO users (name, email, password, post, college_id, department_id, phone, status) 
SELECT 'Dr. Lakshmi Menon', 'hod.cse.canara@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 
       'hod', 2, 5, '9876543221', 'verified'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'hod.cse.canara@example.com');

-- Teachers for College 2
INSERT INTO users (name, email, password, post, college_id, department_id, phone, status) 
SELECT 'Prof. Deepak Singh', 'teacher1.canara@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 
       'teacher', 2, 5, '9876543222', 'verified'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'teacher1.canara@example.com');

INSERT INTO users (name, email, password, post, college_id, department_id, phone, status) 
SELECT 'Prof. Kavita Iyer', 'teacher2.canara@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 
       'teacher', 2, 5, '9876543223', 'verified'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'teacher2.canara@example.com');

-- Insert sample exams for testing cross-college visibility
INSERT INTO exams (title, course_code, exam_date, start_time, end_time, college_id, department_id, created_by_user_id, status) 
SELECT 'Data Structures Mid Term', 'CS301', '2025-12-20', '10:00:00', '12:00:00', 1, 1, 
       (SELECT id FROM users WHERE email = 'hod.cse.sjec@example.com'), 'published'
WHERE NOT EXISTS (SELECT 1 FROM exams WHERE title = 'Data Structures Mid Term' AND exam_date = '2025-12-20');

INSERT INTO exams (title, course_code, exam_date, start_time, end_time, college_id, department_id, created_by_user_id, status) 
SELECT 'Database Management Systems Final', 'CS401', '2025-12-22', '14:00:00', '17:00:00', 1, 1, 
       (SELECT id FROM users WHERE email = 'hod.cse.sjec@example.com'), 'published'
WHERE NOT EXISTS (SELECT 1 FROM exams WHERE title = 'Database Management Systems Final' AND exam_date = '2025-12-22');

INSERT INTO exams (title, course_code, exam_date, start_time, end_time, college_id, department_id, created_by_user_id, status) 
SELECT 'Operating Systems Mid Term', 'CS302', '2025-12-25', '10:00:00', '12:00:00', 2, 5, 
       (SELECT id FROM users WHERE email = 'hod.cse.canara@example.com'), 'published'
WHERE NOT EXISTS (SELECT 1 FROM exams WHERE title = 'Operating Systems Mid Term' AND exam_date = '2025-12-25');

-- Display summary
SELECT 'Sample data inserted successfully!' as message;
SELECT COUNT(*) as total_colleges FROM colleges;
SELECT COUNT(*) as total_departments FROM departments;
SELECT COUNT(*) as total_users FROM users;
SELECT COUNT(*) as total_exams FROM exams;

SELECT '--- Test Login Credentials ---' as info;
SELECT 
    'Admin' as role, 
    'arjun@gmail.com' as email, 
    '1234' as password
UNION ALL
SELECT 'Principal (SJEC)', 'principal.sjec@example.com', 'password123'
UNION ALL
SELECT 'VP (SJEC)', 'vp.sjec@example.com', 'password123'
UNION ALL
SELECT 'HOD CSE (SJEC)', 'hod.cse.sjec@example.com', 'password123'
UNION ALL
SELECT 'Teacher (SJEC)', 'teacher1.sjec@example.com', 'password123'
UNION ALL
SELECT 'Principal (Canara)', 'principal.canara@example.com', 'password123'
UNION ALL
SELECT 'HOD CSE (Canara)', 'hod.cse.canara@example.com', 'password123'
UNION ALL
SELECT 'Teacher (Canara)', 'teacher1.canara@example.com', 'password123';
