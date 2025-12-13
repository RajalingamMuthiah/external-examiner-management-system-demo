-- UAT Feedback Table
CREATE TABLE IF NOT EXISTS uat_feedback (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    category ENUM('usability', 'performance', 'functionality', 'design', 'documentation', 'other') NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    status ENUM('new', 'in-progress', 'resolved', 'closed', 'wont-fix') DEFAULT 'new',
    admin_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_category (category),
    INDEX idx_severity (severity),
    INDEX idx_status (status),
    INDEX idx_created (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- UAT Bugs Table
CREATE TABLE IF NOT EXISTS uat_bugs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    steps_to_reproduce TEXT NOT NULL,
    expected_behavior TEXT NOT NULL,
    actual_behavior TEXT NOT NULL,
    severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    status ENUM('new', 'in-progress', 'fixed', 'verified', 'closed', 'wont-fix') DEFAULT 'new',
    assigned_to INT NULL,
    fixed_in_version VARCHAR(50),
    admin_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_severity (severity),
    INDEX idx_priority (priority),
    INDEX idx_status (status),
    INDEX idx_assigned (assigned_to),
    INDEX idx_created (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- UAT Test Scenarios
CREATE TABLE IF NOT EXISTS uat_test_scenarios (
    id INT PRIMARY KEY AUTO_INCREMENT,
    role VARCHAR(50) NOT NULL,
    feature VARCHAR(100) NOT NULL,
    scenario_name VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    steps TEXT NOT NULL,
    expected_result TEXT NOT NULL,
    priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    category VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_role (role),
    INDEX idx_feature (feature),
    INDEX idx_priority (priority),
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- UAT Test Results
CREATE TABLE IF NOT EXISTS uat_test_results (
    id INT PRIMARY KEY AUTO_INCREMENT,
    scenario_id INT NOT NULL,
    user_id INT NOT NULL,
    status ENUM('pass', 'fail', 'blocked', 'skip') NOT NULL,
    notes TEXT,
    execution_time INT COMMENT 'Time in seconds',
    tested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_scenario (scenario_id),
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    INDEX idx_tested (tested_at),
    FOREIGN KEY (scenario_id) REFERENCES uat_test_scenarios(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample test scenarios
INSERT INTO uat_test_scenarios (role, feature, scenario_name, description, steps, expected_result, priority, category) VALUES
-- Teacher scenarios
('teacher', 'authentication', 'Teacher Login', 'Verify teacher can log in successfully', 
 '1. Navigate to login page\n2. Enter valid teacher credentials\n3. Click Login button',
 'User is redirected to teacher dashboard with proper role permissions',
 'critical', 'authentication'),

('teacher', 'exams', 'View Available Exams', 'Teacher can see list of available exams',
 '1. Login as teacher\n2. Navigate to dashboard\n3. View exams list',
 'All available exams are displayed with correct details',
 'high', 'functionality'),

('teacher', 'assignments', 'Accept Exam Assignment', 'Teacher can accept examination assignment',
 '1. Login as teacher\n2. View exam assignments\n3. Click Accept on an assignment\n4. Confirm acceptance',
 'Assignment status changes to Accepted, notification sent to HOD',
 'high', 'functionality'),

('teacher', 'ratings', 'Rate Examiner', 'Teacher can rate another examiner',
 '1. Login as teacher\n2. Navigate to ratings section\n3. Select examiner\n4. Submit rating and review',
 'Rating is saved and affects examiner score',
 'medium', 'functionality'),

-- HOD scenarios
('hod', 'exams', 'Create Exam', 'HOD can create new examination',
 '1. Login as HOD\n2. Navigate to Create Exam\n3. Fill exam details\n4. Select question paper type\n5. Submit',
 'Exam is created and appears in HOD dashboard',
 'critical', 'functionality'),

('hod', 'faculty', 'Nominate Teacher', 'HOD can nominate teachers for exam duty',
 '1. Login as HOD\n2. View exam details\n3. Click Nominate Teachers\n4. Select teacher and role\n5. Submit nomination',
 'Nomination is sent for approval, teacher receives notification',
 'high', 'functionality'),

('hod', 'approvals', 'Approve Request', 'HOD can approve exam requests',
 '1. Login as HOD\n2. Navigate to Pending Approvals\n3. View request details\n4. Approve or reject',
 'Request status updates, notifications sent',
 'high', 'functionality'),

-- Principal scenarios
('principal', 'approvals', 'Final Approval', 'Principal provides final approval',
 '1. Login as Principal\n2. View pending approvals\n3. Review exam details\n4. Approve',
 'Exam moves to approved status, ready for VP assignment',
 'critical', 'functionality'),

('principal', 'question-papers', 'Lock Question Paper', 'Principal can lock question papers',
 '1. Login as Principal\n2. Navigate to question papers\n3. Select paper\n4. Click Lock',
 'Paper is locked and cannot be modified',
 'high', 'security'),

-- VP scenarios
('vp', 'examiners', 'Assign External Examiner', 'VP assigns external examiners',
 '1. Login as VP\n2. View exam details\n3. Click Assign Examiner\n4. Select from available pool\n5. Confirm assignment',
 'Examiner is assigned, notifications sent',
 'critical', 'functionality'),

-- Admin scenarios
('admin', 'users', 'Manage Users', 'Admin can add/edit/delete users',
 '1. Login as Admin\n2. Navigate to User Management\n3. Add new user with role\n4. Edit existing user\n5. Deactivate user',
 'All user operations complete successfully',
 'critical', 'administration'),

('admin', 'system', 'View Logs', 'Admin can view audit logs',
 '1. Login as Admin\n2. Navigate to Audit Logs\n3. Filter by date/user/action',
 'Logs are displayed with correct filtering',
 'medium', 'administration'),

-- Performance scenarios
('all', 'performance', 'Page Load Time', 'Pages load within acceptable time',
 '1. Navigate to various pages\n2. Measure load time',
 'All pages load within 2 seconds',
 'high', 'performance'),

('all', 'performance', 'Large Dataset Handling', 'System handles large datasets',
 '1. Load page with 100+ records\n2. Apply filters\n3. Sort columns',
 'Operations complete smoothly without lag',
 'medium', 'performance'),

-- Security scenarios
('all', 'security', 'Session Timeout', 'Sessions expire after inactivity',
 '1. Login\n2. Wait 30 minutes inactive\n3. Try to perform action',
 'Session expires, user redirected to login',
 'high', 'security'),

('all', 'security', 'CSRF Protection', 'Forms protected against CSRF',
 '1. Inspect form HTML\n2. Check for CSRF token',
 'All forms have CSRF token',
 'critical', 'security');
