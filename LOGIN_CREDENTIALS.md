# EEMS - Login Credentials & Dashboard Access Guide

## 🔐 Admin Login
**URL:** http://localhost/external/eems/login.php (or admin_login.php)

**Credentials:**
- Email: `arjun@gmail.com`
- Password: `1234`
- **Dashboard Redirects To:** `admin_dashboard.php` (Unified admin panel with all role views)

**Note:** Admin can now login from the regular login page! No need for separate admin login page.

---

## 👥 Test User Accounts (From Database)

### Principal Account
- Email: `priya@mec.edu`
- Password: `1234`
- Role: Principal
- College: Mumbai Engineering College
- Status: Verified ✅
- **Dashboard:** `dashboard.php` (Shows as "Principal Dashboard" in sidebar)
- **Direct URL:** http://localhost/external/eems/dashboard.php

### Vice Principal Account
- Email: `[CREATE NEW USER OR USE EXISTING]`
- Password: `1234`
- Role: Vice Principal (vice_principal / VP)
- **Dashboard:** `VP.php` (Dedicated Vice Principal Dashboard)
- **Direct URL:** http://localhost/external/eems/VP.php

### HOD Account
- Email: `anjali@psc.edu`
- Password: `1234`
- Role: HOD
- College: Pune Science College
- Status: Verified ✅
- **Dashboard:** `hod_dashboard.php` (Department management & faculty assignments)
- **Direct URL:** http://localhost/external/eems/hod_dashboard.php

### Teacher Account
- Email: `rajesh@mec.edu`
- Password: `1234`
- Role: Teacher
- College: Mumbai Engineering College
- Status: Verified ✅
- **Dashboard:** `dashboard.php` (General faculty dashboard)
- **Direct URL:** http://localhost/external/eems/dashboard.php

### Pending Account
- Email: `pending@tac.edu`
- Password: (No password set)
- Role: Teacher
- College: Thane Arts College
- Status: Pending ⏳ (Cannot login until verified)

---

## � Dashboard Overview & Access

### 1. **Admin Dashboard** (`admin_dashboard.php`)
- **Who can access:** Admin only (`arjun@gmail.com`)
- **URL:** http://localhost/external/eems/admin_dashboard.php
- **Features:** 
  - Unified control panel with tabs for all roles
  - User verification management
  - Permission management (assign role access to users)
  - View Principal/VP/HOD/Teacher dashboards from admin view
  - System-wide analytics

### 2. **Principal Dashboard** (`dashboard.php`)
- **Who can access:** Principal role (`priya@mec.edu`)
- **URL:** http://localhost/external/eems/dashboard.php
- **Features:**
  - Institution-wide overview
  - Faculty verification
  - Manage faculty members
  - System analytics
  - Link to HOD dashboard (if user is HOD)

### 3. **Vice Principal Dashboard** (`VP.php`)
- **Who can access:** Vice Principal role (vice_principal/VP)
- **URL:** http://localhost/external/eems/VP.php
- **Features:**
  - Department-wise exam assignments
  - HOD request approvals
  - Examiner management
  - Communication & scheduling tools
- **Note:** No vice principal in current test data - create one via register or SQL

### 4. **HOD Dashboard** (`hod_dashboard.php`)
- **Who can access:** HOD role (`anjali@psc.edu`)
- **URL:** http://localhost/external/eems/hod_dashboard.php
- **Features:**
  - Department faculty management
  - Faculty availability tracking
  - Exam duty nominations
  - Conflict checker for exam schedules

### 5. **Teacher/Faculty Dashboard** (`dashboard.php`)
- **Who can access:** Teacher role (`rajesh@mec.edu`)
- **URL:** http://localhost/external/eems/dashboard.php
- **Features:**
  - Personal exam assignments
  - Availability marking
  - Calendar view of duties
  - Same as Principal dashboard but with limited permissions

---

## �🚀 Quick Test Steps

### 1. Test Admin Login
```
1. Go to: http://localhost/external/eems/admin_login.php
2. Enter: arjun@gmail.com / 1234
3. Should login to admin dashboard
```

### 2. Test User Registration
```
1. Go to: http://localhost/external/eems/register.php
2. Fill form with:
   - Name: Your Name
   - Post: Teacher
   - College: Test College
   - Phone: 1234567890
   - Email: your@email.com
   - Password: password123
3. Submit - should show "Registration submitted for verification"
```

### 3. Test User Verification (As Admin)
```
1. Login as admin (arjun@gmail.com / 1234)
2. Go to: http://localhost/external/eems/verify_users.php
3. Click "Verify ✅" for pending users
4. User can now login
```

### 4. Test Verified User Login
```
1. Go to: http://localhost/external/eems/login.php
2. Enter verified user credentials
3. Should login to dashboard based on role
```

---

## 📱 Complete URL Reference

### Public Pages
- **Home/Landing:** http://localhost/external/eems/
- **Login:** http://localhost/external/eems/login.php
- **Admin Login:** http://localhost/external/eems/admin_login.php
- **Register:** http://localhost/external/eems/register.php

### Role-Based Dashboards
- **Admin Dashboard:** http://localhost/external/eems/admin_dashboard.php
- **Principal Dashboard:** http://localhost/external/eems/dashboard.php
- **Vice Principal Dashboard:** http://localhost/external/eems/VP.php
- **HOD Dashboard:** http://localhost/external/eems/hod_dashboard.php
- **Teacher Dashboard:** http://localhost/external/eems/dashboard.php

### Admin Functions
- **Verify Users:** http://localhost/external/eems/verify_users.php
- **Manage Faculty:** http://localhost/external/eems/manage_faculty.php
- **Manage Users:** http://localhost/external/eems/manage_users.php

---

## 🔧 Default Password for Test Accounts

All test accounts in the seed data use password: `1234`

The password hash in database is:
```
$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oOoZ9Hfq6KVd0yM1qzGQ5J5BZfqWVK
```

This is the bcrypt hash for `1234`

---

## � How Login Redirects Work

When you login, the system automatically redirects you based on your role:

| Role | Redirect Logic | Dashboard File |
|------|---------------|----------------|
| **Admin** | → `admin_dashboard.php` | Unified admin panel |
| **Principal** | → `dashboard.php` | Principal dashboard |
| **Vice Principal** | → `VP.php` | VP-specific dashboard |
| **HOD** | → `dashboard.php` (with HOD link) | General + HOD access |
| **Teacher** | → `dashboard.php` | General faculty dashboard |

### Access Any Dashboard Directly:
1. Login with your credentials at http://localhost/external/eems/login.php
2. After login, manually navigate to any dashboard URL you have permission for
3. Example: HOD can access both `dashboard.php` AND `hod_dashboard.php`

---

## 🆕 How to Create a Vice Principal Account

Since there's no VP in the test data, here's how to create one:

### Option 1: Register via UI
1. Go to: http://localhost/external/eems/register.php
2. Fill form with Post: **Vice Principal**
3. Login as admin and verify the user
4. Login as the VP user → will redirect to `VP.php`

### Option 2: SQL Insert (Quick Method)
```sql
-- Run in phpMyAdmin
INSERT INTO users (name, email, password, post, college, phone, status) 
VALUES (
    'Vice Principal Test',
    'vp@mec.edu',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oOoZ9Hfq6KVd0yM1qzGQ5J5BZfqWVK', -- password: 1234
    'vice_principal',
    'Mumbai Engineering College',
    '9876543210',
    'verified'
);
```

Then login with:
- Email: `vp@mec.edu`
- Password: `1234`
- Will redirect to: `VP.php`

---

## �💡 Tips

- If you forget admin password, it's hardcoded in `admin_login.php` as `1234`
- To add more test users, run INSERT queries in phpMyAdmin
- Use password `1234` for all test accounts for consistency
- To hash a new password: Run in PHP:
  ```php
  echo password_hash('yourpassword', PASSWORD_DEFAULT);
  ```
- **Dashboard confusion?** Remember:
  - `dashboard.php` = Principal/Teacher/HOD general dashboard
  - `hod_dashboard.php` = HOD-specific department management
  - `VP.php` = Vice Principal dashboard
  - `admin_dashboard.php` = Admin control panel
