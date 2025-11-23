-- ================================================================
-- EXAM MANAGEMENT SYSTEM - DATABASE UPDATES
-- ================================================================
-- Run these SQL commands to add College Exam Management features
-- ================================================================

-- Step 1: Add status and description columns to exams table
-- Status tracks: Pending (new), Approved (verified), Assigned (faculty assigned)
ALTER TABLE exams 
ADD COLUMN IF NOT EXISTS status ENUM('Pending','Approved','Assigned','Cancelled') DEFAULT 'Pending',
ADD COLUMN IF NOT EXISTS description TEXT NULL,
ADD COLUMN IF NOT EXISTS subject VARCHAR(255) NULL,
ADD COLUMN IF NOT EXISTS college_id INT NULL,
ADD COLUMN IF NOT EXISTS created_by INT NULL,
ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Step 2: Add foreign key for college_id if colleges table exists
-- Uncomment this if you have a separate colleges table
-- ALTER TABLE exams 
-- ADD CONSTRAINT fk_exam_college 
-- FOREIGN KEY (college_id) REFERENCES colleges(id) ON DELETE CASCADE;

-- Step 3: Add foreign key for created_by (links to users table)
ALTER TABLE exams 
ADD CONSTRAINT fk_exam_creator 
FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL;

-- Step 4: Create index for better query performance
CREATE INDEX IF NOT EXISTS idx_exam_status ON exams(status);
CREATE INDEX IF NOT EXISTS idx_exam_date ON exams(exam_date);
CREATE INDEX IF NOT EXISTS idx_exam_college ON exams(college_id);

-- ================================================================
-- EXAMPLE QUERIES FOR FETCHING EXAM DATA
-- ================================================================

-- Query 1: Fetch all exams with college details (using college_name from exams table)
-- This is useful when colleges are stored as text in exams/users tables
SELECT 
    e.id AS exam_id,
    e.title AS exam_name,
    e.subject,
    e.exam_date,
    e.status,
    e.description,
    e.department AS college_name,
    u.name AS created_by_name,
    e.created_at
FROM exams e
LEFT JOIN users u ON e.created_by = u.id
ORDER BY e.exam_date DESC, e.created_at DESC;

-- Query 2: Fetch exams with college from users table (when college_name is in users)
SELECT 
    e.id AS exam_id,
    e.title AS exam_name,
    e.subject,
    e.exam_date,
    e.status,
    e.description,
    COALESCE(e.department, u.college_name) AS college_name,
    u.name AS created_by_name,
    e.created_at
FROM exams e
LEFT JOIN users u ON e.created_by = u.id
ORDER BY e.exam_date DESC;

-- Query 3: Count exams by status
SELECT 
    status,
    COUNT(*) as count
FROM exams
GROUP BY status;

-- Query 4: Get pending exams for admin notification badge
SELECT COUNT(*) as pending_count
FROM exams
WHERE status = 'Pending';

-- Query 5: Search exams by filters (example)
SELECT 
    e.id,
    e.title,
    e.subject,
    e.exam_date,
    e.status,
    e.department AS college_name
FROM exams e
WHERE 
    (e.status = 'Pending' OR 'Pending' = 'all')
    AND e.exam_date >= CURDATE()
ORDER BY e.exam_date ASC
LIMIT 20 OFFSET 0;

-- ================================================================
-- SAMPLE DATA FOR TESTING
-- ================================================================

-- Insert sample exams (optional - for testing)
INSERT INTO exams (title, exam_date, department, subject, status, description, created_by) VALUES
('Mathematics Final Exam', '2025-12-15', 'St. Joseph College', 'Mathematics', 'Pending', 'Final semester examination for Mathematics students', NULL),
('Physics Mid-term', '2025-11-25', 'Christ University', 'Physics', 'Approved', 'Mid-semester examination covering units 1-3', NULL),
('Computer Science Practical', '2025-12-01', 'MES College', 'Computer Science', 'Assigned', 'Practical examination for CS final year students', NULL),
('Chemistry Lab Exam', '2025-11-30', 'St. Aloysius College', 'Chemistry', 'Pending', 'Laboratory practical examination', NULL);

-- ================================================================
-- CLEANUP (OPTIONAL - USE WITH CAUTION)
-- ================================================================

-- To remove the added columns (only if needed to rollback)
-- ALTER TABLE exams 
-- DROP COLUMN status,
-- DROP COLUMN description,
-- DROP COLUMN subject,
-- DROP COLUMN college_id,
-- DROP COLUMN created_by,
-- DROP COLUMN created_at,
-- DROP COLUMN updated_at;
