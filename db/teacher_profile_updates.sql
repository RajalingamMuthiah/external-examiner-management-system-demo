-- Teacher Profile and Assignment Tracking Tables
-- Run this to add required columns and tables

-- Add profile columns to users table
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS profile_data TEXT COMMENT 'JSON data for teacher profile',
ADD COLUMN IF NOT EXISTS profile_completed TINYINT(1) DEFAULT 0 COMMENT 'Whether teacher completed onboarding',
ADD COLUMN IF NOT EXISTS phone VARCHAR(20) COMMENT 'Primary phone number';

-- Add assignment tracking columns to assignments table
ALTER TABLE assignments 
ADD COLUMN IF NOT EXISTS assigned_by INT COMMENT 'User ID who made the assignment (for HOD nominations)',
ADD COLUMN IF NOT EXISTS status VARCHAR(50) DEFAULT 'Assigned' COMMENT 'Assignment status',
ADD COLUMN IF NOT EXISTS notes TEXT COMMENT 'Additional notes about assignment';

-- Create indexes for faster queries
CREATE INDEX IF NOT EXISTS idx_faculty_assignments ON assignments(faculty_id, exam_id);
CREATE INDEX IF NOT EXISTS idx_exam_assignments ON assignments(exam_id);
CREATE INDEX IF NOT EXISTS idx_assigned_by ON assignments(assigned_by);

