# Multi-College Privacy Enforcement Audit

## Overview
This document audits all database queries across the EEMS system to ensure proper multi-college privacy isolation.

## Privacy Rules

### Role-Based Access
1. **Teacher**: Can only see exams from their own college
2. **HOD**: Can only see exams from their own college  
3. **Vice-Principal**: Can see exams from ALL colleges (coordinator role)
4. **Principal**: Can only see exams from their own college
5. **Admin**: Can see all data (system administrator)

### Table Privacy Requirements

#### `exams` table
- Filter by `college_id` for Teacher/HOD/Principal
- No filter for VP/Admin

#### `exam_assignments` table  
- Filter by user's college through JOIN with exams table
- VP can see all assignments across colleges

#### `practical_exam_sessions` table
- Filter by examiner's college
- VP can coordinate across all colleges

#### `question_papers` table
- Filter by exam's college_id
- Only exam stakeholders can access

## Files Requiring Privacy Enforcement

### Dashboard Files
- [x] `dashboard.php` - Main redirect (no queries)
- [ ] `teacher_dashboard.php` - Needs audit
- [ ] `hod_dashboard.php` - Needs audit  
- [ ] `VP.php` - Should see ALL colleges
- [ ] `admin_dashboard.php` - Should see ALL colleges

### Exam Management
- [ ] `create_exam.php` - Ensure user can only create for their college
- [ ] `view_exam_details.php` - Privacy filter on exam access
- [ ] `view_other_college_exams.php` - Should respect role permissions

### Assignment & Invitation
- [ ] `manage_faculty.php` - Needs college filter
- [ ] `apply_for_exam.php` - Cross-college visibility rules

### Question Papers
- [x] `question_papers.php` - Already enforces via exam assignments

### Practical Exams
- [x] `practical_exams.php` - Already enforces via exam assignments

### Rating System
- [x] `rate_examiner.php` - Already filters by user's assignments

## Implementation Strategy

1. Add college_id JOIN clauses to all queries
2. Implement role-based WHERE conditions
3. Create helper function: `getCollegeFilterSQL($role, $collegeId)`
4. Test cross-college isolation
5. Verify VP can coordinate globally

## Status: IN PROGRESS
Next: Audit teacher_dashboard.php
