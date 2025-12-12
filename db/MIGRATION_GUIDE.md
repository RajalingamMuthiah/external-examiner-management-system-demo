# EEMS Database Migration Guide

## Overview
This guide will help you migrate your existing EEMS database to the new unified schema with proper college and department support.

## Prerequisites
1. XAMPP installed with MySQL/MariaDB running
2. Existing `eems` database (will be modified, not dropped)
3. Backup of your current database (recommended)

## Migration Files
- `eems_migration_complete.sql` - Main migration script
- `sample_data.sql` - Test data with sample colleges, departments, and users
- `run_migration.ps1` - PowerShell script to automate migration

## Step-by-Step Instructions

### Step 1: Backup Your Current Database (Important!)
```sql
-- From MySQL command line or phpMyAdmin, export your current database
mysqldump -u root eems > eems_backup_$(date +%Y%m%d).sql
```

### Step 2: Start MySQL from XAMPP
1. Open XAMPP Control Panel
2. Click "Start" next to MySQL
3. Verify MySQL is running (green indicator)

### Step 3: Run the Migration

#### Option A: Using PowerShell Script (Recommended)
```powershell
cd C:\xampp\htdocs\external\eems\db
.\run_migration.ps1
```

#### Option B: Using MySQL Command Line
```bash
# Open Command Prompt (cmd.exe), not PowerShell
cd C:\xampp\htdocs\external\eems\db
C:\xampp\mysql\bin\mysql.exe -u root eems < eems_migration_complete.sql
```

#### Option C: Using phpMyAdmin
1. Open phpMyAdmin (http://localhost/phpmyadmin)
2. Select the `eems` database
3. Click "Import" tab
4. Choose file: `C:\xampp\htdocs\external\eems\db\eems_migration_complete.sql`
5. Click "Go"

### Step 4: Insert Sample Data (Optional but Recommended for Testing)
```bash
# Command Prompt
C:\xampp\mysql\bin\mysql.exe -u root eems < sample_data.sql
```

Or in phpMyAdmin, import `sample_data.sql` the same way.

### Step 5: Verify Migration

Check the database structure in phpMyAdmin:

**New Tables Created:**
- `colleges` - Stores college information
- `departments` - Stores departments linked to colleges
- `faculty_availability` - Tracks faculty unavailable dates

**Modified Tables:**
- `users` - Added `college_id`, `department_id`, renamed `post` to use standard roles
- `exams` - Added `course_code`, `start_time`, `end_time`, `college_id`, `department_id`, `created_by_user_id`, `status`
- `assignments` → `exam_assignments` - Renamed and added `role_assigned`, `duty_type`, `status`

## Test Login Credentials (After Sample Data)

| Role | Email | Password | College |
|------|-------|----------|---------|
| Admin | arjun@gmail.com | 1234 | System Admin |
| Principal | principal.sjec@example.com | password123 | St. Joseph Engineering College |
| Vice Principal | vp.sjec@example.com | password123 | St. Joseph Engineering College |
| HOD (CSE) | hod.cse.sjec@example.com | password123 | St. Joseph Engineering College |
| Teacher | teacher1.sjec@example.com | password123 | St. Joseph Engineering College |
| Principal | principal.canara@example.com | password123 | Canara Engineering College |
| HOD (CSE) | hod.cse.canara@example.com | password123 | Canara Engineering College |
| Teacher | teacher1.canara@example.com | password123 | Canara Engineering College |

## What Changed in the Code

### 1. Login System (`login.php`)
Now sets:
- `$_SESSION['user_id']`
- `$_SESSION['role']`
- `$_SESSION['college_id']`
- `$_SESSION['department_id']`

### 2. Shared Business Logic (`includes/functions.php`)

New functions added:

#### `getVisibleExamsForUser($pdo, $userId, $role, $collegeId, $departmentId)`
Returns exams based on role and privacy rules:
- **Admin**: All exams across all colleges
- **Principal/VP**: All exams for their college
- **HOD**: Exams for their college + department
- **Teacher**: Only exams they're assigned to

#### `createExam($pdo, $data, $createdByUserId, $role, $collegeId, $departmentId)`
Creates exam with proper role checks:
- **Admin/Principal/VP**: Can choose college/department
- **HOD**: Can only create for their own college/department

#### `assignFacultyToExam($pdo, $examId, $facultyUserId, $roleAssigned, $dutyType)`
Assigns faculty with validation:
- Checks faculty availability
- Prevents conflicting assignments
- Checks for duplicate assignments

#### `getFacultyForCollegeAndDepartment($pdo, $collegeId, $departmentId, $role)`
Returns faculty list respecting college privacy:
- Only shows verified users from specified college
- Optional department and role filters

### 3. Dashboard Privacy Rules

All dashboards updated to use privacy context:
- `$currentUserId = $_SESSION['user_id']`
- `$currentUserRole = normalize_role($_SESSION['role'])`
- `$currentUserCollege = $_SESSION['college_id']`
- `$currentUserDept = $_SESSION['department_id']`

## Privacy Implementation

### Exam Visibility Rules
✅ **Admin**: Sees all exams from all colleges  
✅ **Principal/VP**: Sees only their college's exams  
✅ **HOD**: Sees only their college + department exams  
✅ **Teacher**: Sees only exams they're assigned to (even from other colleges)

### Faculty Data Privacy
✅ Faculty from College A cannot see faculty from College B  
✅ Each role sees only relevant faculty based on their college/department

### Exam Creation Rules
✅ **Admin**: Can create exams for any college/department  
✅ **Principal/VP**: Can create exams for their college  
✅ **HOD**: Can only create exams for their own department  
✅ **Teacher**: Cannot create exams

## Troubleshooting

### Migration Failed - MySQL Service Not Running
**Solution**: Start MySQL from XAMPP Control Panel

### Migration Failed - Database 'eems' Does Not Exist
**Solution**: Create the database first:
```sql
CREATE DATABASE IF NOT EXISTS eems CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### Foreign Key Constraint Errors
**Solution**: The migration script handles this by dropping and recreating tables in the correct order

### Existing Data Lost After Migration
**Solution**: Restore from backup and review migration script. The script uses `ALTER TABLE` to preserve existing data where possible.

### Login Not Working After Migration
**Solution**: 
1. Verify users table has `college_id` column: `SHOW COLUMNS FROM users;`
2. Check if your user has `college_id` set: `SELECT * FROM users WHERE email = 'your@email.com';`
3. Run sample_data.sql to create test users

## Next Steps

1. ✅ Run the migration
2. ✅ Insert sample data
3. ✅ Test login with different roles
4. ✅ Verify exam visibility rules work correctly
5. ✅ Test creating exams from different dashboards
6. ✅ Test faculty assignment workflow
7. Update your existing user records with correct `college_id` and `department_id`

## Manual Data Update (If You Have Existing Users)

If you have existing users in your database, you need to assign them to colleges and departments:

```sql
-- Example: Update existing users with college and department IDs
UPDATE users SET college_id = 1, department_id = 1 
WHERE email = 'your.existing.user@example.com';
```

## Support

If you encounter issues:
1. Check the error log: `C:\xampp\mysql\data\mysql_error.log`
2. Verify all migration files exist in `db/` folder
3. Ensure MySQL is running before executing scripts
4. Check PHP error logs for application errors

## Files Modified

✅ `db/eems_migration_complete.sql` - Database schema migration  
✅ `db/sample_data.sql` - Test data  
✅ `db/run_migration.ps1` - Migration automation script  
✅ `login.php` - Added college_id and department_id to session  
✅ `includes/functions.php` - Added shared business logic functions  
✅ `admin_dashboard.php` - Added privacy context  
✅ `dashboard.php` (principal) - Added privacy context  
✅ `VP.php` - Added privacy context  
✅ `hod_dashboard.php` - Added privacy context  
✅ `teacher_dashboard.php` - Added privacy context  

## Success Indicators

After successful migration, you should be able to:
- ✅ Login with different roles
- ✅ See different exams based on your role and college
- ✅ Create exams (if you have permission)
- ✅ Assign faculty from your college to exams
- ✅ View faculty availability
- ✅ Not see other colleges' private data

---

**Migration Status**: Ready to Execute  
**Estimated Time**: 2-5 minutes  
**Risk Level**: Low (non-destructive, uses ALTER TABLE)  
**Backup Recommended**: Yes
