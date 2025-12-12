# College Assignment System - User Guide

## Overview
The EEMS now includes an automatic college and department assignment system for all users. When a new user registers or an existing user hasn't selected their college/department, they'll be guided through a profile completion page.

---

## How It Works

### For New Users (Registration Flow)
1. **User registers** via `register.php`
2. **Auto-login** happens after successful registration
3. **Redirected to** `user_profile.php?new_user=1`
4. **User selects:**
   - Their college from dropdown (dynamically loaded via API)
   - Their department from dropdown (filtered by selected college)
   - Updates phone number (optional)
5. **Profile saved** → User redirected to their role-specific dashboard

### For Existing Users (Missing College/Department)
1. **User logs in** via `login.php`
2. **Profile check middleware** detects missing college_id or department_id
3. **Auto-redirected to** `user_profile.php?incomplete=1`
4. **User completes** college and department selection
5. **Access granted** to their dashboard

---

## Files Added/Modified

### New Files Created
1. **`api/colleges.php`** - College & Department API
   - `GET ?action=get_colleges` - Fetch all colleges with user/dept counts
   - `GET ?action=get_departments&college_id=X` - Fetch departments for a college
   - `POST ?action=add_college` - Add new college (admin only)
   - `POST ?action=add_department` - Add new department (admin/principal only)

2. **`user_profile.php`** - Personal Information Page
   - Profile completion form
   - College/department dropdowns (AJAX-loaded)
   - Auto-redirect for incomplete profiles
   - Displays current assignment info

3. **`includes/profile_check.php`** - Middleware
   - Checks if college_id and department_id are set
   - Redirects to user_profile.php if incomplete
   - Excludes admin role (admin doesn't need college/dept)
   - Excludes API requests and profile page itself

### Modified Files
1. **`register.php`**
   - Changed redirect from `login.php?registered=1` to `user_profile.php?new_user=1`
   - Auto-login after registration
   - Sets session variables (college_id/department_id initially null)

2. **All Dashboards** (teacher, HOD, VP, principal)
   - Added `require_once __DIR__ . '/includes/profile_check.php';` after security.php
   - Ensures users complete profile before accessing dashboard

---

## API Endpoints

### Get Colleges
```javascript
fetch('api/colleges.php?action=get_colleges')
  .then(response => response.json())
  .then(data => {
    // data.colleges = [{id, name, department_count, user_count}, ...]
  });
```

### Get Departments
```javascript
fetch('api/colleges.php?action=get_departments&college_id=1')
  .then(response => response.json())
  .then(data => {
    // data.departments = [{id, name, college_id, college_name, user_count}, ...]
  });
```

### Add College (Admin Only)
```javascript
fetch('api/colleges.php', {
  method: 'POST',
  headers: {'Content-Type': 'application/x-www-form-urlencoded'},
  body: 'action=add_college&name=New College'
})
.then(response => response.json())
.then(data => {
  // data.success, data.college_id, data.college_name
});
```

### Add Department (Admin/Principal Only)
```javascript
fetch('api/colleges.php', {
  method: 'POST',
  headers: {'Content-Type': 'application/x-www-form-urlencoded'},
  body: 'action=add_department&name=Computer Science&college_id=1'
})
.then(response => response.json());
```

---

## Database Schema

The system uses these tables for college management:

```sql
-- Colleges table
CREATE TABLE colleges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE
);

-- Departments table
CREATE TABLE departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    college_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    FOREIGN KEY (college_id) REFERENCES colleges(id) ON DELETE CASCADE,
    UNIQUE KEY unique_college_department (college_id, name)
);

-- Users table (modified)
ALTER TABLE users 
ADD COLUMN college_id INT NULL,
ADD COLUMN department_id INT NULL,
ADD FOREIGN KEY (college_id) REFERENCES colleges(id) ON DELETE SET NULL,
ADD FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL;
```

---

## User Flow Diagram

```
┌─────────────────┐
│ User Registers  │
│  register.php   │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  Auto-login +   │
│ Set session vars│
└────────┬────────┘
         │
         ▼
┌─────────────────────────┐
│  Redirect to Profile    │
│  user_profile.php       │
│  ?new_user=1            │
└────────┬────────────────┘
         │
         ▼
┌─────────────────────────┐
│  Load Colleges API      │
│  GET colleges.php       │
│  Display dropdown       │
└────────┬────────────────┘
         │
         ▼
┌─────────────────────────┐
│  User Selects College   │
│  Trigger change event   │
└────────┬────────────────┘
         │
         ▼
┌─────────────────────────┐
│  Load Departments API   │
│  GET colleges.php       │
│  ?college_id=X          │
└────────┬────────────────┘
         │
         ▼
┌─────────────────────────┐
│  User Selects Dept      │
│  & Submits Form         │
└────────┬────────────────┘
         │
         ▼
┌─────────────────────────┐
│  Update users table     │
│  SET college_id, dept_id│
│  Update session vars    │
└────────┬────────────────┘
         │
         ▼
┌─────────────────────────┐
│  Redirect to Dashboard  │
│  (role-specific)        │
└─────────────────────────┘
```

---

## Security Features

### Authentication
- All API endpoints check `$_SESSION['user_id']` before processing
- Profile page requires active session
- Middleware redirects unauthenticated users to login

### Authorization
- **Add College**: Admin only
- **Add Department**: Admin and principal (principal limited to own college)
- **View/Select College**: All authenticated users
- **View Departments**: All authenticated users (filtered by selected college)

### Data Privacy
- Users can only update their own profile
- College/department lists are public (read-only for selection)
- User counts in API response don't expose PII

### Input Validation
- College name: Required, unique, trimmed
- Department name: Required, unique per college, trimmed
- Duplicate checks before INSERT
- Foreign key constraints ensure data integrity

---

## Testing the System

### Test New User Registration
1. Go to `register.php`
2. Fill in: Name, Email, Password, Role (teacher), Phone
3. Submit → Should auto-login and redirect to `user_profile.php?new_user=1`
4. Select college from dropdown
5. Select department from dropdown (populated after college selection)
6. Click "Save Profile"
7. Should redirect to teacher_dashboard.php

### Test Existing User Without College
1. Via phpMyAdmin, set a user's `college_id` and `department_id` to NULL
2. Login as that user
3. Should auto-redirect to `user_profile.php?incomplete=1`
4. Complete profile
5. Access should be granted to dashboard

### Test College API
```bash
# Get all colleges
curl http://localhost/external/eems/api/colleges.php?action=get_colleges

# Get departments for college 1
curl http://localhost/external/eems/api/colleges.php?action=get_departments&college_id=1
```

---

## Admin Management

### Adding Colleges Programmatically
Admins can add colleges via the API:

```php
// In admin panel or via POST request
$collegeName = "New Engineering College";
// POST to api/colleges.php with action=add_college&name=$collegeName
```

### Adding Departments Programmatically
Principals/Admins can add departments:

```php
// POST to api/colleges.php
// action=add_department&name=Mechanical Engineering&college_id=2
```

---

## Privacy Implementation

### Profile Check Middleware Logic
```php
// includes/profile_check.php
$profileIncomplete = empty($_SESSION['college_id']) || empty($_SESSION['department_id']);

// Admins don't need college/dept
if ($userRole === 'admin') {
    $profileIncomplete = false;
}

// Don't redirect if on excluded pages
$excludedPages = ['user_profile.php', 'logout.php', 'login.php'];
if ($profileIncomplete && !in_array($currentPage, $excludedPages)) {
    header('Location: user_profile.php?incomplete=1');
    exit;
}
```

### Session Variables Set
After profile completion:
- `$_SESSION['college_id']` - User's college ID
- `$_SESSION['department_id']` - User's department ID
- Used by all dashboard privacy filters

---

## Troubleshooting

### Issue: Colleges dropdown is empty
**Solution:** Run the migration SQL first to populate sample colleges:
```sql
-- db/sample_data.sql contains INSERT statements
-- Import via phpMyAdmin or run via CLI
```

### Issue: Redirect loop on profile page
**Solution:** Check that user's college_id and department_id are NULL in database
```sql
SELECT id, name, college_id, department_id FROM users WHERE id = YOUR_USER_ID;
```

### Issue: Departments not loading after selecting college
**Solution:** Check browser console for API errors. Ensure `api/colleges.php` is accessible and database has departments for that college.

### Issue: "Only admins can add colleges" error
**Solution:** Verify user role in session:
```php
var_dump($_SESSION['role']); // Should be 'admin'
```

---

## Next Steps

1. **Run Migration** - Execute `db/eems_migration_complete.sql` if not done
2. **Load Sample Data** - Execute `db/sample_data.sql` for test colleges/departments
3. **Test Registration** - Create new user and verify profile completion flow
4. **Test Login** - Login as existing users and verify dashboard access
5. **Admin Panel** - Optionally create UI for adding colleges/departments in admin dashboard

---

## File Summary

| File | Purpose | Type |
|------|---------|------|
| `api/colleges.php` | College/Dept API | Backend API |
| `user_profile.php` | Profile completion form | Frontend Page |
| `includes/profile_check.php` | Middleware for redirect | Security Middleware |
| `register.php` | Modified to auto-redirect | Modified File |
| `teacher_dashboard.php` | Added profile check | Modified File |
| `hod_dashboard.php` | Added profile check | Modified File |
| `VP.php` | Added profile check | Modified File |
| `dashboard.php` | Added profile check | Modified File |

---

## Success Indicators

✅ New users redirected to profile page after registration  
✅ College dropdown loads colleges from database  
✅ Department dropdown loads after college selection  
✅ Profile saves college_id and department_id to users table  
✅ Session variables updated after save  
✅ Users with incomplete profiles redirected to profile page  
✅ Admins can access dashboards without college/dept  
✅ API endpoints return proper JSON responses  
✅ Duplicate college/department names rejected  

---

**System Status:** ✅ COMPLETE AND READY TO TEST

The college assignment system is now fully integrated with the EEMS platform. All users will be automatically assigned to their colleges during registration or first login, ensuring proper data privacy and role-based access control.
