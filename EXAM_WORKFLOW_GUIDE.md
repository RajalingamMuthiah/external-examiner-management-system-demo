# College Exam Management - Complete Workflow Guide

## Overview
This system implements a complete, role-based exam management workflow where:
- **Principals/Vice Principals** post exam requirements for their college
- **Teachers** self-assign to eligible exams from other colleges
- **Admins** approve exams and manage the entire system

---

## User Roles & Permissions

### 1. Principal / Vice Principal
**Can:**
- Post new exam requirements for their college
- Specify exam details (subject, date, description)
- View their posted exams

**Cannot:**
- Approve their own exams
- Assign faculty directly
- See exams from other colleges (unless through admin dashboard)

### 2. Teacher / Faculty / HOD
**Can:**
- View available exams from OTHER colleges
- Self-assign to eligible exams
- View their own assignments
- Track exam dates and details

**Cannot:**
- Select exams from their own college (Conflict of Interest prevention)
- Select exams they're already assigned to
- Create or approve exams

### 3. Admin
**Can:**
- View ALL exams from all colleges
- Approve or reject pending exams
- Manually assign/remove faculty to/from exams
- Update exam details
- Delete exams
- Export exam data
- Monitor system statistics

---

## Complete Workflow

### Step 1: Exam Creation (Principal/VP)
1. **Login** as Principal or Vice Principal
2. **Navigate** to Admin Dashboard → "Exam Management"
3. **Click** "Add Exam" button
4. **Fill in details:**
   - Exam Name (e.g., "Advanced Mathematics Final Exam")
   - Subject (e.g., "Mathematics")
   - College/Department (auto-filled from your profile)
   - Exam Date (must be future date)
   - Description (optional details)
5. **Submit** the exam
6. **Status:** Exam is created with status "Pending" (requires admin approval)

**Example:**
```
Exam: "Physics Practical Examination"
Subject: "Physics"
College: "St. Joseph's College"
Date: 2025-12-15
Status: Pending ⏳
```

---

### Step 2: Exam Approval (Admin)
1. **Login** as Admin
2. **Navigate** to Admin Dashboard → "Exam Management"
3. **View** all pending exams (yellow badge shows count)
4. **Review** exam details by clicking "View" (👁️) button
5. **Approve** eligible exams by clicking "Approve" (✅) button
6. **Status:** Exam changes from "Pending" to "Approved"

**Approval Criteria:**
- Valid exam date (future)
- Complete information
- Legitimate college request
- No conflicts with other exams

**After Approval:**
- Exam becomes visible to teachers for self-assignment
- Teachers from OTHER colleges can now select this exam

---

### Step 3: Teacher Self-Assignment
1. **Login** as Teacher/Faculty
2. **Navigate** to Admin Dashboard → "Available Exams"
3. **View** list of approved exams from OTHER colleges
4. **Filter** by:
   - Subject (dropdown)
   - Exam Date (calendar)
   - Search (exam name/description)
5. **Review** exam details:
   - Exam name and description
   - College requiring examiner
   - Subject and date
   - Number of faculty already assigned
6. **Click** "Select" button to self-assign
7. **Confirm** assignment in popup dialog
8. **Status:** 
   - Assignment created in database
   - Exam appears in "My Assignments" tab
   - Exam removed from "Available Exams" for this teacher

**Automatic Restrictions:**
- ❌ Cannot select exams from your own college
- ❌ Cannot select exams you're already assigned to
- ❌ Only approved exams are shown
- ❌ Only future exams are shown

**Example:**
```
Teacher: Dr. Sharma (ABC College)
Can Select: Exams from XYZ College, PQR College, etc.
Cannot Select: Exams from ABC College (own college)
```

---

### Step 4: Assignment Confirmation (Automatic)
When a teacher selects an exam:
1. **Database Record Created:**
   - `assignments` table entry
   - Links teacher ID to exam ID
   - Records assignment timestamp
   - Sets role as "Examiner"
2. **Exam Status Updated:**
   - First assignment: Status changes to "Assigned"
   - Subsequent assignments: Status remains "Assigned"
3. **Notification:**
   - Success message shown to teacher
   - Assignment appears in "My Assignments"

---

### Step 5: Admin Management & Oversight
Admin can monitor and manage all aspects:

#### View Assignments
- See which teachers are assigned to which exams
- Click "View" on any exam to see assignment details
- Monitor assignment counts

#### Manual Assignment (Optional)
- Click "Assign Faculty" (👤+) button on approved exams
- Select eligible faculty (from other colleges)
- Choose role (Examiner, Chief Examiner, etc.)
- Submit assignment

#### Remove Assignments
- View exam details
- See list of assigned faculty
- Remove specific assignments if needed
- Status changes back to "Approved" if all removed

#### Update Exams
- Edit exam details (name, subject, date, description)
- Change exam status
- Cancel exams if needed

#### Delete Exams
- Remove exams (and all assignments)
- Requires confirmation

---

## Database Structure

### Tables Used

#### 1. `exams` Table
```sql
- id (Primary Key)
- title (Exam name)
- subject (Subject area)
- exam_date (Scheduled date)
- department (College name)
- description (Details)
- status (Pending/Approved/Assigned/Cancelled)
- created_by (User ID of creator)
- created_at (Timestamp)
- updated_at (Timestamp)
```

#### 2. `assignments` Table
```sql
- id (Primary Key)
- exam_id (Foreign key to exams)
- faculty_id (Foreign key to users)
- role (Examiner/Chief Examiner)
- assigned_at (Timestamp)
- assigned_by (NULL for self-assignment, User ID for admin assignment)
```

#### 3. `users` Table
```sql
- id (Primary Key)
- name (Full name)
- email (Login email)
- post (Role: teacher/hod/vice_principal/principal/admin)
- college_name (Institution)
- status (verified/pending/rejected)
```

---

## Status Workflow

### Exam Status Transitions
```
1. PENDING (Initial state after creation by Principal/VP)
   ↓ (Admin approves)
2. APPROVED (Available for teacher self-assignment)
   ↓ (Teacher selects OR admin assigns)
3. ASSIGNED (Faculty assigned to exam)
   ↓ (Exam conducted)
4. COMPLETED (Optional future state)

OR

PENDING → CANCELLED (Admin cancels)
```

---

## Security Features

### 1. Role-Based Access Control
- **Create Exams:** Only Principal, VP, Admin
- **Approve Exams:** Only Admin
- **Self-Assign:** Only Teachers/Faculty/HOD
- **View All Exams:** Only Admin
- **Manage Assignments:** Only Admin

### 2. Conflict of Interest Prevention
- Teachers CANNOT select exams from their own college
- System automatically filters out own-college exams
- Database validation on assignment

### 3. Data Integrity
- CSRF token validation on all POST requests
- Prepared statements prevent SQL injection
- Session-based authentication
- Duplicate assignment prevention
- Date validation (no past dates)

### 4. Audit Trail
- All actions logged in `audit_logs` table
- Tracks: Admin ID, Action, Details, IP Address, Timestamp
- View logs in Admin Dashboard → Activity Logs

---

## User Interface Locations

### For Principal/Vice Principal
**Dashboard:** `admin_dashboard.php`
**Module:** "Exam Management"
**Actions:**
- Click "Add Exam" button (green button in sidebar OR in module header)
- Fill form with exam details
- Submit and await admin approval

### For Teachers
**Dashboard:** `admin_dashboard.php` (same as admins, but limited access)
**Module:** "Available Exams" (visible only to teachers)
**Actions:**
- Browse available exams (filtered automatically)
- Use filters to find suitable exams
- Click "Select" to self-assign
- Check "My Assignments" tab for status

### For Admin
**Dashboard:** `admin_dashboard.php`
**Modules:**
- "Exam Management" - Full exam CRUD and approval
- "Analytics" - View statistics
- "Activity Logs" - Audit trail

**Key Actions:**
- Approve pending exams (✅ button)
- Assign faculty manually (👤+ button)
- View details (👁️ button)
- Edit exams (✏️ button)
- Delete exams (🗑️ button)

---

## API Endpoints (AJAX)

### Exam Operations
- `?action=create_exam` - POST - Create new exam (Principal/VP/Admin)
- `?action=update_exam_status` - POST - Approve/reject exam (Admin)
- `?action=update_exam` - POST - Edit exam details (Admin)
- `?action=delete_exam` - POST - Delete exam (Admin)
- `?action=get_exam_details` - GET - Fetch exam with assignments

### Assignment Operations
- `?action=teacher_select_exam` - POST - Teacher self-assignment
- `?action=admin_assign_faculty` - POST - Admin manual assignment
- `?action=get_available_faculty` - GET - List eligible faculty
- `?action=remove_faculty_assignment` - POST - Remove assignment (Admin)

### Module Loading
- `?action=load_module&module=exam_management` - Admin exam view
- `?action=load_module&module=available_exams` - Teacher exam view

---

## Testing the Workflow

### Test Scenario 1: Complete Flow
1. **Login as Principal** (email: `principal@college.com`)
   - Create exam: "Mathematics Final Exam"
   - Subject: "Mathematics"
   - Date: Tomorrow's date
   - Save → Status: Pending

2. **Login as Admin** (email: `admin@eems.com`)
   - Go to Exam Management
   - See pending exam with yellow badge
   - Click Approve → Status: Approved

3. **Login as Teacher** (email: `teacher@othercollege.com`)
   - Go to Available Exams
   - See "Mathematics Final Exam"
   - Click Select → Assignment created
   - Check "My Assignments" tab → See exam listed

4. **Login as Admin** (verify)
   - Go to Exam Management
   - Click View on the exam
   - See teacher assigned
   - Status: Assigned

### Test Scenario 2: Conflict of Interest
1. **Login as Teacher** from College A
2. **Create test exam** (as admin) for College A
3. **Login as same teacher**
4. **Go to Available Exams**
5. **Verify:** Exam from College A is NOT shown

### Test Scenario 3: Manual Assignment
1. **Login as Admin**
2. **Create/Approve an exam**
3. **Click "Assign Faculty"**
4. **Select teacher from dropdown** (only shows teachers from OTHER colleges)
5. **Submit assignment**
6. **Verify:** Assignment created, status updated

---

## Troubleshooting

### Exam not showing in Available Exams
**Check:**
- Is exam status "Approved"? (Not Pending/Assigned)
- Is exam date in the future?
- Is exam from a DIFFERENT college than yours?
- Have you already been assigned to this exam?

### Cannot create exam
**Check:**
- Are you logged in as Principal, VP, or Admin?
- Is exam date in the future?
- Are all required fields filled?
- Is there a network/database error (check browser console)?

### Cannot approve exam
**Check:**
- Are you logged in as Admin?
- Is exam still in "Pending" status?
- Valid CSRF token? (Try refreshing page)

### Assignment not working
**Check:**
- Is exam status "Approved"?
- Are you a teacher/faculty?
- Is exam from a different college?
- Haven't you already selected this exam?

---

## Best Practices

### For Principals/VPs
1. ✅ Provide clear exam descriptions
2. ✅ Set realistic exam dates
3. ✅ Specify correct subject area
4. ✅ Include relevant details in description

### For Teachers
1. ✅ Review exam details before selecting
2. ✅ Check exam date availability
3. ✅ Select exams in your expertise area
4. ✅ Monitor "My Assignments" regularly

### For Admins
1. ✅ Approve exams promptly
2. ✅ Verify exam legitimacy before approving
3. ✅ Monitor assignment balance across faculty
4. ✅ Review audit logs regularly
5. ✅ Export reports for record-keeping

---

## Future Enhancements

### Planned Features
- Email notifications on exam approval
- SMS alerts for assignment confirmations
- Teacher availability calendar
- Workload balancing suggestions
- Automated examiner matching based on expertise
- Bulk exam import from CSV
- Advanced analytics dashboard
- Mobile app for teachers

### Database Enhancements
- Exam history tracking
- Performance ratings for examiners
- Conflict resolution tracking
- Payment/remuneration tracking

---

## Support & Contact

For technical issues or questions:
1. Check this guide first
2. Review audit logs for errors
3. Check browser console for JavaScript errors
4. Verify database connection
5. Contact system administrator

**Database Location:** `eems` database on localhost
**Application Path:** `c:\xampp\htdocs\external\eems\`
**Main File:** `admin_dashboard.php`

---

## Changelog

**Version 1.0** (Current)
- ✅ Role-based exam creation
- ✅ Admin approval workflow
- ✅ Teacher self-assignment
- ✅ Conflict of interest prevention
- ✅ Comprehensive filtering
- ✅ Real-time status updates
- ✅ Audit logging
- ✅ Security features (CSRF, prepared statements)

---

**Last Updated:** November 14, 2025
**Author:** Development Team
**System:** Education Exam Management System (EEMS)
