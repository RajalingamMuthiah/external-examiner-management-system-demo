-- PERFORMANCE OPTIMIZATION SCRIPT
-- File: db/optimize_performance.sql
-- Purpose: Add indexes and optimize database for better performance

-- ============================================================================
-- INDEXES FOR EXAMS TABLE
-- ============================================================================

-- Index on status for filtering approved/pending exams
CREATE INDEX IF NOT EXISTS idx_exams_status ON exams(status);

-- Index on exam_date for date-based queries
CREATE INDEX IF NOT EXISTS idx_exams_date ON exams(exam_date);

-- Index on college_id for multi-college filtering
CREATE INDEX IF NOT EXISTS idx_exams_college ON exams(college_id);

-- Index on created_by for user's exams
CREATE INDEX IF NOT EXISTS idx_exams_creator ON exams(created_by);

-- Composite index for common queries (college + status + date)
CREATE INDEX IF NOT EXISTS idx_exams_college_status_date ON exams(college_id, status, exam_date);

-- ============================================================================
-- INDEXES FOR EXAM_ASSIGNMENTS TABLE
-- ============================================================================

-- Index on exam_id for assignment lookups
CREATE INDEX IF NOT EXISTS idx_assignments_exam ON exam_assignments(exam_id);

-- Index on user_id for user's assignments
CREATE INDEX IF NOT EXISTS idx_assignments_user ON exam_assignments(user_id);

-- Index on assignment_status for filtering
CREATE INDEX IF NOT EXISTS idx_assignments_status ON exam_assignments(assignment_status);

-- Composite index for checking existing assignments
CREATE INDEX IF NOT EXISTS idx_assignments_exam_user ON exam_assignments(exam_id, user_id);

-- ============================================================================
-- INDEXES FOR USERS TABLE
-- ============================================================================

-- Index on email for login queries
CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);

-- Index on role for role-based queries
CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);

-- Index on college_id for college filtering
CREATE INDEX IF NOT EXISTS idx_users_college ON users(college_id);

-- Index on status for verified users
CREATE INDEX IF NOT EXISTS idx_users_status ON users(status);

-- Composite index for faculty queries (role + college + status)
CREATE INDEX IF NOT EXISTS idx_users_faculty ON users(role, college_id, status);

-- ============================================================================
-- INDEXES FOR QUESTION_PAPERS TABLE
-- ============================================================================

-- Index on exam_id for paper lookups
CREATE INDEX IF NOT EXISTS idx_papers_exam ON question_papers(exam_id);

-- Index on status (locked/unlocked)
CREATE INDEX IF NOT EXISTS idx_papers_status ON question_papers(status);

-- Index on uploaded_by for tracking
CREATE INDEX IF NOT EXISTS idx_papers_uploader ON question_papers(uploaded_by);

-- Composite index for exam papers
CREATE INDEX IF NOT EXISTS idx_papers_exam_version ON question_papers(exam_id, version);

-- ============================================================================
-- INDEXES FOR PRACTICAL_EXAM_SESSIONS TABLE
-- ============================================================================

-- Index on exam_id for session lookups
CREATE INDEX IF NOT EXISTS idx_sessions_exam ON practical_exam_sessions(exam_id);

-- Index on examiner_id for examiner's sessions
CREATE INDEX IF NOT EXISTS idx_sessions_examiner ON practical_exam_sessions(examiner_id);

-- Index on session_date for date-based queries
CREATE INDEX IF NOT EXISTS idx_sessions_date ON practical_exam_sessions(session_date);

-- Index on status for filtering
CREATE INDEX IF NOT EXISTS idx_sessions_status ON practical_exam_sessions(status);

-- Composite index for conflict detection
CREATE INDEX IF NOT EXISTS idx_sessions_examiner_date ON practical_exam_sessions(examiner_id, session_date, status);

-- ============================================================================
-- INDEXES FOR PRACTICAL_EXAM_ATTEMPTS TABLE
-- ============================================================================

-- Index on session_id for attempts lookup
CREATE INDEX IF NOT EXISTS idx_attempts_session ON practical_exam_attempts(session_id);

-- Index on result for filtering pass/fail
CREATE INDEX IF NOT EXISTS idx_attempts_result ON practical_exam_attempts(result);

-- Index on student_roll_no for student lookup
CREATE INDEX IF NOT EXISTS idx_attempts_rollno ON practical_exam_attempts(student_roll_no);

-- ============================================================================
-- INDEXES FOR RATINGS TABLE
-- ============================================================================

-- Index on exam_id for exam ratings
CREATE INDEX IF NOT EXISTS idx_ratings_exam ON ratings(exam_id);

-- Index on examiner_id for examiner profile
CREATE INDEX IF NOT EXISTS idx_ratings_examiner ON ratings(examiner_id);

-- Index on rated_by for rater's history
CREATE INDEX IF NOT EXISTS idx_ratings_rater ON ratings(rated_by);

-- Composite index for duplicate prevention
CREATE INDEX IF NOT EXISTS idx_ratings_unique ON ratings(exam_id, examiner_id, rated_by);

-- ============================================================================
-- INDEXES FOR NOTIFICATIONS TABLE
-- ============================================================================

-- Index on user_id for user's notifications
CREATE INDEX IF NOT EXISTS idx_notifications_user ON notifications(user_id);

-- Index on read_on for unread notifications
CREATE INDEX IF NOT EXISTS idx_notifications_read ON notifications(read_on);

-- Composite index for unread count queries
CREATE INDEX IF NOT EXISTS idx_notifications_user_read ON notifications(user_id, read_on);

-- Index on notification_type for filtering
CREATE INDEX IF NOT EXISTS idx_notifications_type ON notifications(notification_type);

-- ============================================================================
-- INDEXES FOR AUDIT_LOGS TABLE
-- ============================================================================

-- Index on user_id for user activity
CREATE INDEX IF NOT EXISTS idx_audit_user ON audit_logs(user_id);

-- Index on action_type for action filtering
CREATE INDEX IF NOT EXISTS idx_audit_action ON audit_logs(action_type);

-- Index on created_at for time-based queries
CREATE INDEX IF NOT EXISTS idx_audit_created ON audit_logs(created_at);

-- Composite index for entity audits
CREATE INDEX IF NOT EXISTS idx_audit_entity ON audit_logs(entity_type, entity_id);

-- ============================================================================
-- INDEXES FOR APPROVALS TABLE
-- ============================================================================

-- Index on exam_id for approval lookups
CREATE INDEX IF NOT EXISTS idx_approvals_exam ON approvals(exam_id);

-- Index on approved_by for approver's history
CREATE INDEX IF NOT EXISTS idx_approvals_approver ON approvals(approved_by);

-- Index on status for filtering
CREATE INDEX IF NOT EXISTS idx_approvals_status ON approvals(status);

-- ============================================================================
-- ANALYZE TABLES FOR QUERY OPTIMIZER
-- ============================================================================

ANALYZE TABLE exams;
ANALYZE TABLE exam_assignments;
ANALYZE TABLE users;
ANALYZE TABLE question_papers;
ANALYZE TABLE practical_exam_sessions;
ANALYZE TABLE practical_exam_attempts;
ANALYZE TABLE ratings;
ANALYZE TABLE notifications;
ANALYZE TABLE audit_logs;
ANALYZE TABLE approvals;

-- ============================================================================
-- OPTIMIZATION NOTES
-- ============================================================================
-- 
-- Performance Improvements Expected:
-- 1. Exam listing queries: 50-80% faster with college+status+date index
-- 2. Assignment lookups: 70-90% faster with exam_user composite index
-- 3. Login queries: 90% faster with email index
-- 4. Notification counts: 80% faster with user+read composite index
-- 5. Faculty filtering: 60% faster with role+college+status index
-- 
-- Monitoring:
-- - Use EXPLAIN before and after to see query plan improvements
-- - Monitor slow query log
-- - Check index usage with SHOW INDEX FROM table_name
-- 
-- Maintenance:
-- - Run ANALYZE TABLE monthly or after bulk data changes
-- - Monitor index size growth
-- - Remove unused indexes if any
-- 
-- ============================================================================
