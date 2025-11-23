# Granular Permissions Control - Complete Guide

## Overview

The enhanced Permissions Control module now provides **granular, tab-level access control** for each user. Instead of just granting broad dashboard access, administrators can now control access to individual tabs and modules within the system.

---

## Key Features

### 1. **Expandable User Cards**
- Click on any user to expand their permissions panel
- Each user card shows:
  - User name and email
  - Current role badge
  - Quick save button
  - Expand/collapse indicator

### 2. **Four Permission Categories**

#### A. Dashboard Access (4 options)
Controls which role-based dashboards the user can access:
- ✅ **Principal Dashboard** - Full principal dashboard access
- ✅ **Vice Principal Dashboard** - VP dashboard access
- ✅ **HOD Dashboard** - Department head access
- ✅ **Teacher Dashboard** - Teacher dashboard access

#### B. Admin Modules (5 options)
Controls access to core admin functionality:
- 📊 **Overview** - Dashboard overview/statistics
- 👥 **User Management** - Create/edit/verify users
- 📅 **Exam Management** - Create/edit/assign exams
- ✓ **Approvals** - Approve/reject users and exams
- 📚 **Available Exams** - Browse and select exams

#### C. Settings & Analytics (4 options)
Controls access to administrative tools:
- 🔒 **Permissions Control** - Manage user permissions
- 📈 **Analytics** - View reports and statistics
- 🕐 **Activity Logs** - View audit trail
- ⚙️ **System Settings** - Configure system parameters

#### D. Role Dashboards (4 options)
Controls visibility of role-specific tabs:
- 🏆 **Principal Tab** - Principal dashboard tab
- 💼 **Vice Principal Tab** - VP dashboard tab
- 🏢 **HOD Tab** - HOD dashboard tab
- 👨‍🏫 **Teacher Tab** - Teacher dashboard tab

---

## How to Use

### Setting Individual Permissions

1. **Navigate to Permissions Control**
   - Click "Permissions Control" in the admin sidebar

2. **Find the User**
   - Use the search box to filter by name or email
   - Or use the role filter to show specific roles only

3. **Expand User Card**
   - Click anywhere on the user's card header to expand

4. **Toggle Permissions**
   - Click toggle switches to enable/disable specific permissions
   - Green = Enabled, Gray = Disabled

5. **Save Changes**
   - Click the blue "Save" button on the user's card
   - Or click "Save All Changes" to update all visible users

---

## Quick Templates

To speed up permission assignment, use the quick templates:

### **Full Admin Template**
- Grants access to ALL modules and dashboards
- Use for: System administrators

### **Principal Template**
- Principal dashboard access
- Overview, User Management, Exam Management, Approvals
- Available Exams, Analytics
- Principal dashboard tab
- Use for: College principals

### **Teacher Only Template**
- Teacher dashboard access
- Overview, Available Exams
- Teacher dashboard tab
- Use for: Regular teaching staff

### **Clear All Template**
- Removes all permissions
- Use for: Revoking access or starting fresh

---

## Search and Filtering

### Search Box
- Type any part of the user's name or email
- Results filter in real-time
- Case-insensitive search

### Role Filter
- Filter by specific role:
  - Admin
  - Principal
  - Vice Principal
  - HOD
  - Teacher
  - Faculty

### Clear Filters
- Click "Clear Filters" button to reset both search and filter

---

## Database Structure

### New Columns in `permissions` Table

```sql
-- Dashboard Access
principal_access TINYINT(1) DEFAULT 0
vice_access TINYINT(1) DEFAULT 0
hod_access TINYINT(1) DEFAULT 0
teacher_access TINYINT(1) DEFAULT 0

-- Admin Modules
module_overview TINYINT(1) DEFAULT 1
module_user_management TINYINT(1) DEFAULT 0
module_exam_management TINYINT(1) DEFAULT 0
module_approvals TINYINT(1) DEFAULT 0
module_available_exams TINYINT(1) DEFAULT 1

-- Settings & Analytics
module_permissions TINYINT(1) DEFAULT 0
module_analytics TINYINT(1) DEFAULT 0
module_audit_logs TINYINT(1) DEFAULT 0
module_settings TINYINT(1) DEFAULT 0

-- Role Dashboards
module_principal_dash TINYINT(1) DEFAULT 0
module_vice_dash TINYINT(1) DEFAULT 0
module_hod_dash TINYINT(1) DEFAULT 0
module_teacher_dash TINYINT(1) DEFAULT 1
```

---

## Permission Logic

### How Permissions Work

1. **Dashboard Access** determines if user can enter a specific dashboard
2. **Module Access** determines which tabs are visible within that dashboard
3. **Both must be enabled** for a tab to appear

### Example Scenarios

#### Scenario 1: Principal with Limited Access
```
Dashboard Access:
✅ Principal Dashboard

Module Access:
✅ Overview
✅ Exam Management
❌ User Management
❌ Permissions Control

Result: User sees Principal Dashboard with only Overview and Exam Management tabs
```

#### Scenario 2: Teacher with Exam Selection
```
Dashboard Access:
✅ Teacher Dashboard

Module Access:
✅ Overview
✅ Available Exams
❌ Exam Management

Result: User can view their dashboard and select exams, but cannot create/edit exams
```

#### Scenario 3: HOD with Approvals
```
Dashboard Access:
✅ HOD Dashboard

Module Access:
✅ Overview
✅ Exam Management
✅ Approvals
✅ User Management

Result: HOD can manage department exams, approve users, and manage staff
```

---

## Best Practices

### Security Guidelines

1. **Principle of Least Privilege**
   - Grant only necessary permissions
   - Start with minimal access
   - Add permissions as needed

2. **Regular Audits**
   - Review permissions quarterly
   - Remove access for inactive users
   - Check activity logs for unauthorized attempts

3. **Role-Based Templates**
   - Use templates for common roles
   - Customize after applying template
   - Document custom permission sets

4. **Protect Sensitive Modules**
   - Limit "Permissions Control" access to admins only
   - Restrict "System Settings" to senior admins
   - Limit "Activity Logs" to audit staff

### Operational Tips

1. **Test Before Deploying**
   - Test permission changes with test user accounts
   - Verify tabs appear/disappear correctly
   - Check functionality within restricted tabs

2. **Communication**
   - Inform users before changing permissions
   - Provide clear role descriptions
   - Document expected access levels

3. **Backup Before Bulk Changes**
   - Export permissions before mass updates
   - Keep record of original settings
   - Use "Save All" cautiously

---

## Troubleshooting

### User Can't See Expected Tabs

**Check:**
1. Is Dashboard Access enabled for that role?
2. Is Module Access enabled for that specific tab?
3. Is user status "verified"? (Only verified users shown)
4. Has user logged out and back in?

**Solution:**
- Enable both Dashboard Access AND Module Access
- Refresh browser cache
- Check browser console for errors

### Permissions Not Saving

**Check:**
1. Are you logged in as admin?
2. Is CSRF token valid? (refresh page)
3. Check browser console for errors
4. Verify database connection

**Solution:**
- Refresh page to get new CSRF token
- Check PHP error logs
- Verify MySQL is running

### Template Not Applying

**Check:**
1. Did you click the correct template button?
2. Are checkboxes responding?
3. JavaScript errors in console?

**Solution:**
- Click "Save" after applying template
- Refresh page if checkboxes are stuck
- Clear browser cache

---

## API Reference

### Update Permissions Endpoint

**URL:** `?action=update_permissions`

**Method:** POST

**Parameters:**
```javascript
{
  user_id: 123,
  csrf_token: "abc123...",
  
  // Dashboard Access
  principal_access: 1,
  vice_access: 0,
  hod_access: 0,
  teacher_access: 1,
  
  // Module Access
  module_overview: 1,
  module_user_management: 1,
  module_exam_management: 1,
  module_approvals: 0,
  module_available_exams: 1,
  module_permissions: 0,
  module_analytics: 1,
  module_audit_logs: 0,
  module_settings: 0,
  module_principal_dash: 1,
  module_vice_dash: 0,
  module_hod_dash: 0,
  module_teacher_dash: 1
}
```

**Response:**
```json
{
  "success": true,
  "message": "Permissions updated"
}
```

---

## Migration from Old System

### Automatic Migration

When you first load the Permissions Control module, it will:
1. Automatically add new columns to `permissions` table
2. Preserve existing dashboard access settings
3. Set default module permissions based on role

### Manual Migration (if needed)

Run this SQL to grant appropriate defaults:

```sql
-- Admin users get all permissions
UPDATE permissions p
JOIN users u ON p.user_id = u.id
SET 
  p.module_overview = 1,
  p.module_user_management = 1,
  p.module_exam_management = 1,
  p.module_approvals = 1,
  p.module_available_exams = 1,
  p.module_permissions = 1,
  p.module_analytics = 1,
  p.module_audit_logs = 1,
  p.module_settings = 1,
  p.module_principal_dash = 1,
  p.module_vice_dash = 1,
  p.module_hod_dash = 1,
  p.module_teacher_dash = 1
WHERE u.post = 'admin';

-- Principal users get management permissions
UPDATE permissions p
JOIN users u ON p.user_id = u.id
SET 
  p.module_overview = 1,
  p.module_user_management = 1,
  p.module_exam_management = 1,
  p.module_approvals = 1,
  p.module_available_exams = 1,
  p.module_analytics = 1,
  p.module_principal_dash = 1
WHERE u.post = 'principal' AND p.principal_access = 1;

-- Teacher users get basic permissions
UPDATE permissions p
JOIN users u ON p.user_id = u.id
SET 
  p.module_overview = 1,
  p.module_available_exams = 1,
  p.module_teacher_dash = 1
WHERE u.post IN ('teacher', 'faculty') AND p.teacher_access = 1;
```

---

## Screenshots & Examples

### Visual Layout

```
┌─────────────────────────────────────────────────────────────┐
│ [Search Box]      [Role Filter ▼]      [Clear Filters]     │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│ 👤 John Doe   john@example.com   [Admin]    [Save] [▼]     │
├─────────────────────────────────────────────────────────────┤
│ ┌─ Dashboard Access ─┬─ Admin Modules ─┬─ Settings ─────┐  │
│ │ ☑ Principal        │ ☑ Overview       │ ☑ Permissions │  │
│ │ ☐ Vice Principal   │ ☑ Users          │ ☑ Analytics   │  │
│ │ ☐ HOD              │ ☑ Exams          │ ☑ Logs        │  │
│ │ ☑ Teacher          │ ☑ Approvals      │ ☑ Settings    │  │
│ └────────────────────┴──────────────────┴───────────────┘  │
│                                                              │
│ Templates: [Full Admin] [Principal] [Teacher] [Clear All]   │
└─────────────────────────────────────────────────────────────┘
```

---

## Support & Help

### Getting Help

1. **Check Activity Logs**
   - See who made permission changes
   - Verify changes were applied

2. **Review Documentation**
   - This guide covers most scenarios
   - Check ADMIN_DASHBOARD_TESTING.md for test procedures

3. **Database Queries**
   ```sql
   -- View all permissions for a user
   SELECT * FROM permissions WHERE user_id = 123;
   
   -- View all users with specific permission
   SELECT u.name, u.email 
   FROM users u
   JOIN permissions p ON u.id = p.user_id
   WHERE p.module_user_management = 1;
   ```

---

## Future Enhancements

### Planned Features

1. **Permission Groups**
   - Create reusable permission sets
   - Apply to multiple users at once

2. **Time-Based Permissions**
   - Grant temporary access
   - Auto-revoke after date

3. **Permission History**
   - Track permission changes over time
   - Revert to previous state

4. **Bulk Import/Export**
   - Export permissions to CSV
   - Import from spreadsheet

---

## Changelog

**Version 2.0** - Granular Permissions
- Added 13 new module-level permission columns
- Implemented expandable user cards UI
- Added quick permission templates
- Enhanced search and filtering
- Added role filter dropdown
- Improved save functionality (individual and bulk)
- Auto-migration of old permissions

**Version 1.0** - Basic Permissions
- Dashboard access only (principal, vice, hod, teacher)
- Simple table view
- Basic save functionality

---

*For technical implementation details, see `db/permissions_granular_update.sql`*
*For testing procedures, see `ADMIN_DASHBOARD_TESTING.md`*
