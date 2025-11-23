# Quick Start Testing Guide

## Prerequisites
1. **Login to the system first** - Your session expired
2. **Database is ready** - Schema updated with exam management columns

## Test Users
You need to create/use these test users to test the complete workflow:

### Admin User
- Email: `admin@eems.com`
- Password: `Admin@123`
- Role: `admin`
- Purpose: Approve exams, manage system

### Principal User  
- Email: `principal@college1.com`
- Password: `Welcome@123`
- College: `St. Joseph's College`
- Role: `principal`
- Purpose: Create exams for their college

### Teacher User
- Email: `teacher@college2.com`
- Password: `Welcome@123`
- College: `Christ University` (DIFFERENT from principal's college)
- Role: `teacher`
- Purpose: Select exams from other colleges

---

## Step-by-Step Testing

### STEP 1: Login as Admin First

**URL:** `http://localhost/external/eems/login.php`

**Credentials:**
- Email: `admin@eems.com`
- Password: `Admin@123`

**Verify:**
- Dashboard loads successfully
- See "Admin Dashboard" title
- Navigation sidebar appears

---

### STEP 2: Create Test Exam (as Principal or Admin)

**Option A: Login as Principal**
1. Logout admin
2. Login as principal user
3. Navigate to "Exam Management"
4. Click "Add Exam" button

**Option B: Use Admin Account**
1. Stay logged in as admin
2. Navigate to "Exam Management"  
3. Click "Add Exam" button

**Fill Form:**
```
Exam Name: Mathematics Final Examination
Subject: Mathematics
College: St. Joseph's College
Exam Date: 2025-12-15 (or any future date)
Description: Final semester examination for 3rd year students
```

**Submit → Result:**
- Success message appears
- Exam created with status "Pending"
- Shows in exam table with yellow "Pending" badge

---

### STEP 3: Approve Exam (as Admin)

**If not logged in as admin, login now:**
- Email: `admin@eems.com`
- Password: `Admin@123`

**Actions:**
1. Go to "Exam Management" module
2. Find the exam you just created
3. Click the green "Approve" (✅) button
4. Confirm approval

**Result:**
- Status changes from "Pending" to "Approved"
- Badge color changes from yellow to green
- Exam is now available for teacher selection

---

### STEP 4: Teacher Self-Assignment

**Create/Login as Teacher:**

**If teacher doesn't exist, create one:**
1. Go to `http://localhost/external/eems/register.php`
2. Fill in:
   - Name: `Dr. Ramesh Kumar`
   - Email: `teacher@college2.com`
   - Password: `Welcome@123`
   - Post: `teacher`
   - College: `Christ University` (IMPORTANT: Different from exam's college)
   - Phone: `9876543210`
3. Register → Wait for admin to verify

**Verify Teacher (as Admin):**
1. Login as admin
2. Go to "User Management"
3. Find the teacher
4. Click "Approve" button
5. Password will be set to `Welcome@123`

**Now Login as Teacher:**
1. Logout
2. Login with `teacher@college2.com` / `Welcome@123`

**Select Exam:**
1. Dashboard opens → See "Available Exams" link in sidebar (has green "Teacher" badge)
2. Click "Available Exams"
3. See list of approved exams from OTHER colleges
4. Find "Mathematics Final Examination"
5. Click "Select" button
6. Confirm in popup
7. Success message: "Successfully scheduled for this exam!"
8. Exam moves to "My Assignments" tab

**Verify:**
- Exam disappears from "Available Exams" tab
- Exam appears in "My Assignments" tab
- Shows assignment date and role

---

### STEP 5: Verify Assignment (as Admin)

**Login as Admin again:**
1. Logout teacher
2. Login as admin

**Check Assignment:**
1. Go to "Exam Management"
2. Find the exam (status should be "Assigned" now - blue badge)
3. Click "View" (👁️) button
4. Modal opens showing:
   - Exam details
   - List of assigned faculty (should show the teacher)
   - Assignment date/time

**Result:**
- Teacher is listed in "Assigned Faculty" section
- Shows teacher name, college, email
- Assignment role: "Examiner"

---

## Quick Verification Checklist

### ✅ Exam Creation
- [ ] Principal/Admin can create exams
- [ ] All required fields work
- [ ] Future date validation works
- [ ] Exam appears with "Pending" status

### ✅ Exam Approval  
- [ ] Admin can see pending exams
- [ ] Approve button changes status to "Approved"
- [ ] Badge color changes (yellow → green)
- [ ] Approved exams show in teacher view

### ✅ Teacher Self-Assignment
- [ ] Teacher sees "Available Exams" menu item
- [ ] Only exams from OTHER colleges appear
- [ ] Select button works
- [ ] Assignment confirmation appears
- [ ] Exam moves to "My Assignments"

### ✅ Conflict of Interest Prevention
- [ ] Teacher does NOT see exams from their own college
- [ ] Teacher cannot select same exam twice
- [ ] System shows appropriate error messages

### ✅ Admin Management
- [ ] Admin can view all exams
- [ ] Admin can see assignments
- [ ] Admin can manually assign faculty
- [ ] Admin can update/delete exams
- [ ] Filters and search work

---

## Troubleshooting Common Issues

### Issue: "You must be logged in"
**Solution:** Your session expired. Login first at `http://localhost/external/eems/login.php`

### Issue: "Available Exams" not showing in sidebar
**Reason:** You're not logged in as a teacher
**Solution:** Login with teacher account (role must be 'teacher', 'faculty', or 'hod')

### Issue: No exams showing in "Available Exams"
**Reasons:**
1. No exams have been created yet → Create one as admin
2. No exams are approved yet → Approve pending exams
3. All exams are from your own college → Create exam with different college
4. You're already assigned to all exams → Create new exam

**Solution:** Check "Exam Management" as admin to see all exams and their status

### Issue: Cannot create exam
**Reasons:**
1. Not logged in as Principal/VP/Admin
2. Missing required fields
3. Exam date is in the past

**Solution:** 
- Verify your role with: Check session_check.php
- Ensure all required fields filled
- Use future date

### Issue: "Conflict of Interest" error
**This is expected!** The system prevents teachers from selecting exams from their own college.
**Solution:** This is correct behavior. Test with exam from different college.

---

## Database Queries for Verification

### Check Exam Status
```sql
SELECT id, title, department, status, created_by 
FROM exams 
ORDER BY created_at DESC 
LIMIT 5;
```

### Check Assignments
```sql
SELECT 
    a.id,
    e.title AS exam_name,
    u.name AS teacher_name,
    u.college_name AS teacher_college,
    e.department AS exam_college,
    a.assigned_at
FROM assignments a
JOIN exams e ON a.exam_id = e.id
JOIN users u ON a.faculty_id = u.id
ORDER BY a.assigned_at DESC;
```

### Check User Roles
```sql
SELECT id, name, email, post, college_name, status 
FROM users 
WHERE email LIKE '%@%';
```

---

## Expected Behavior Summary

### For Principals/VPs:
- ✅ CAN create exams for their college
- ✅ CAN see exam management dashboard
- ❌ CANNOT approve exams
- ❌ CANNOT assign faculty

### For Teachers:
- ✅ CAN see "Available Exams" menu
- ✅ CAN select exams from OTHER colleges
- ✅ CAN view their assignments
- ❌ CANNOT see exams from own college
- ❌ CANNOT create or approve exams
- ❌ CANNOT select same exam twice

### For Admin:
- ✅ CAN do everything
- ✅ CAN create, approve, edit, delete exams
- ✅ CAN manually assign faculty
- ✅ CAN view all assignments
- ✅ CAN see all colleges and users

---

## Next Steps After Testing

1. **Create more test data:**
   - Multiple exams from different colleges
   - Multiple teachers from different colleges
   - Test filtering and search

2. **Test edge cases:**
   - Past date validation
   - Duplicate assignments
   - Missing required fields
   - Role permissions

3. **Review audit logs:**
   - Go to "Activity Logs" module
   - Verify all actions are logged

4. **Export functionality:**
   - Test CSV export
   - Verify data accuracy

---

**Happy Testing! 🎉**

If you encounter any issues not covered here, check:
1. Browser console for JavaScript errors (F12)
2. Apache error log for PHP errors
3. Database for data integrity
4. EXAM_WORKFLOW_GUIDE.md for detailed documentation
