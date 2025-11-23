# College Exam Management Module - User Guide

## Overview
The College Exam Management module provides comprehensive functionality for managing exam requirements posted by colleges within the Admin Dashboard.

## Features

### 1. **Exam Listing**
- View all exams in a paginated table format (15 exams per page)
- Display: Exam Name, College, Subject, Date, Status, Created By
- Color-coded status badges:
  - 🟡 **Pending** - Newly added exams awaiting approval
  - 🟢 **Approved** - Verified and ready for faculty assignment
  - 🔵 **Assigned** - Faculty assigned to the exam
  - 🔴 **Cancelled** - Cancelled/Rejected exams

### 2. **Search & Filtering**
- **Search**: By exam name, subject, college, or description
- **Filter by College**: Dropdown of all colleges
- **Filter by Subject**: Dropdown of all subjects
- **Filter by Status**: Pending, Approved, Assigned, Cancelled
- **Filter by Date**: Select specific exam date
- Clear filters option to reset all filters

### 3. **Exam Actions**

#### View Details
- Click 👁️ icon to view complete exam information
- Shows: Name, College, Subject, Date, Status, Description, Created By

#### Approve Exam (Pending exams only)
- Click ✅ icon to approve pending exams
- Status changes from Pending → Approved
- Logged in activity logs

#### Assign Faculty (Approved exams only)
- Click 👤+ icon to assign faculty to exam
- Select from list of verified teachers and HODs
- Specify role (Invigilator, Supervisor, etc.)
- Status automatically updates to "Assigned"
- Creates entry in assignments table

#### Edit Exam
- Click ✏️ icon to edit exam details
- Modify: Name, Subject, Date, Status, Description
- Changes saved to database
- Logged in activity logs

#### Delete Exam
- Click 🗑️ icon to delete exam
- Confirmation required
- Cascades to delete related assignments
- Logged in activity logs

### 4. **Add New Exam**
- Click "Add Exam" button in header
- Fill required fields:
  - Exam Name *
  - Subject *
  - College/Department *
  - Exam Date *
  - Description (optional)
- Auto-set status to "Pending"
- Auto-log admin as creator

### 5. **Export Exams**
- Click "Export" button to download CSV file
- Contains all exam data
- Filename format: `exams_export_YYYY-MM-DD.csv`
- Opens in Excel/Google Sheets

### 6. **Dashboard Statistics**
Four stat cards showing:
- **Total Exams**: Count of all exams
- **Pending**: Exams awaiting approval (with badge alert)
- **Approved**: Exams ready for assignment
- **Assigned**: Exams with faculty assigned

## Database Setup

### Run SQL Updates
Execute the following SQL file to add required columns:
```bash
mysql -u root eems < db/exam_management_updates.sql
```

### Manual SQL (if needed)
```sql
-- Add new columns to exams table
ALTER TABLE exams 
ADD COLUMN IF NOT EXISTS status ENUM('Pending','Approved','Assigned','Cancelled') DEFAULT 'Pending',
ADD COLUMN IF NOT EXISTS description TEXT NULL,
ADD COLUMN IF NOT EXISTS subject VARCHAR(255) NULL,
ADD COLUMN IF NOT EXISTS college_id INT NULL,
ADD COLUMN IF NOT EXISTS created_by INT NULL,
ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Add foreign keys
ALTER TABLE exams 
ADD CONSTRAINT fk_exam_creator 
FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL;

-- Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_exam_status ON exams(status);
CREATE INDEX IF NOT EXISTS idx_exam_date ON exams(exam_date);
```

## Access Control
- **Only logged-in admins** can access this module
- All actions require CSRF token validation
- All modifications logged in admin activity logs
- Session timeout redirects to login

## Navigation
1. Login as admin
2. Navigate to sidebar → **Exam Management**
3. Module loads in main content area

## Pagination
- Shows 15 exams per page
- Page numbers displayed at bottom
- Previous/Next navigation buttons
- Current page highlighted
- Filters persist across pages

## Security Features
- ✅ CSRF token validation on all POST requests
- ✅ SQL injection prevention (prepared statements)
- ✅ XSS prevention (HTML escaping)
- ✅ Admin role verification
- ✅ Activity logging for audit trail
- ✅ Session management

## Sample Data
To test the module, you can insert sample exams:

```sql
INSERT INTO exams (title, exam_date, department, subject, status, description, created_by) VALUES
('Mathematics Final Exam', '2025-12-15', 'St. Joseph College', 'Mathematics', 'Pending', 'Final semester examination for Mathematics students', NULL),
('Physics Mid-term', '2025-11-25', 'Christ University', 'Physics', 'Approved', 'Mid-semester examination covering units 1-3', NULL),
('Computer Science Practical', '2025-12-01', 'MES College', 'Computer Science', 'Assigned', 'Practical examination for CS final year students', NULL);
```

## Troubleshooting

### Issue: Columns not found error
**Solution**: Run the SQL updates to add new columns to exams table

### Issue: No exams displayed
**Solution**: 
- Check if exams table has data
- Verify database connection in config/db.php
- Clear all filters using "Clear Filters" link

### Issue: Actions not working
**Solution**:
- Check browser console for JavaScript errors
- Verify jQuery is loaded
- Ensure Bootstrap modal library is loaded

### Issue: Permission denied
**Solution**:
- Ensure logged in as admin role
- Check session is active
- Verify role in database users table

## Technical Details

### Files Modified
- `admin_dashboard.php` - Main module code and AJAX endpoints

### Files Created
- `db/exam_management_updates.sql` - Database schema updates
- `EXAM_MANAGEMENT_GUIDE.md` - This documentation

### Database Tables Used
- `exams` - Main exam storage
- `users` - Creator and faculty information
- `assignments` - Faculty-to-exam assignments
- `admin_activity_log` - Activity tracking

### AJAX Endpoints
- `get_exam_details` - Fetch single exam
- `update_exam_status` - Change exam status
- `get_available_faculty` - List assignable faculty
- `assign_faculty_to_exam` - Create assignment
- `update_exam` - Edit exam details
- `delete_exam` - Remove exam
- `add_exam` - Create new exam
- `export_exams` - Download CSV

## Future Enhancements
- [ ] Bulk actions (approve/delete multiple)
- [ ] Advanced analytics dashboard
- [ ] Email notifications to faculty on assignment
- [ ] PDF export option
- [ ] Calendar view of exams
- [ ] Conflict detection for faculty
- [ ] Faculty workload balancing
- [ ] Automated exam scheduling

## Support
For issues or questions:
1. Check error logs in browser console
2. Review PHP error logs
3. Verify database structure matches schema
4. Ensure all dependencies (Bootstrap, jQuery) are loaded

---
**Version**: 1.0  
**Last Updated**: November 14, 2025  
**Compatible with**: EEMS Admin Dashboard v3.x
