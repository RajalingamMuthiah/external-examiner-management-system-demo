# Admin Dashboard Quick Reference

## Navigation

### Sidebar Menu Items

| Icon | Menu Item | Description | Status |
|------|-----------|-------------|--------|
| 📊 | Overview | Dashboard statistics and summary | ✅ Working |
| 👥 | User Management | View, edit, verify users | ✅ Working |
| 📅 | Exam Management | Create, edit, approve exams | ✅ Working |
| ✓ | Approvals & Verifications | Pending user/exam approvals | ✅ Working |
| 📚 | Available Exams | Browse all available exams | ✅ Working |
| 🔒 | Permissions Control | **NEW** - Manage dashboard access | ✅ Working |
| 📈 | Analytics & Reports | **NEW** - Statistics and charts | ✅ Working |
| 🕐 | Activity Logs | **ENHANCED** - Complete audit trail | ✅ Working |
| ⚙️ | System Settings | **NEW** - Configure system | ✅ Working |

### Quick Actions (Sidebar Bottom)

| Button | Action | Keyboard |
|--------|--------|----------|
| 🟢 Quick Add Exam | Opens Add Exam modal | - |
| 📥 Export Reports | Shows export options menu | - |

---

## Feature Overview

### 🔒 Permissions Control

**What it does:** Manage which users can access different dashboards

**Quick Actions:**
- Toggle switches to grant/revoke access
- Click 💾 to save individual user
- Click "Save All Changes" to bulk update
- Use search box to filter users

**Permission Types:**
- **Principal Access**: Access principal dashboard
- **Vice Principal Access**: Access VP dashboard
- **HOD Access**: Access HOD dashboard
- **Teacher Access**: Access teacher dashboard

**Note:** Users can have multiple permissions simultaneously

---

### 📈 Analytics & Reports

**What it does:** View system-wide statistics and visualizations

**Statistics Cards:**
- Total Users (with weekly growth)
- Total Colleges
- Total Exams
- Pending Items

**Charts:**
1. **Users by Role** - Doughnut chart showing role distribution
2. **User Registrations** - Line chart of last 6 months
3. **Exams by Status** - Bar chart of exam statuses
4. **Verification Status** - Pie chart of user verification

**Actions:**
- Click "Export Report" to download analytics CSV
- Click "Refresh" to update data

---

### 🕐 Activity Logs (Audit Trail)

**What it does:** Track all admin actions for accountability

**Filters:**
- **Search Action**: Filter by action keyword
- **Admin User**: Filter by admin name
- **Date Range**: Filter by date from/to

**Action Color Codes:**
- 🟢 Green: Verify/Approve actions
- 🔴 Red: Reject/Delete actions
- 🟡 Yellow: Update/Change actions
- 🔵 Blue: Other actions

**Actions:**
- Click "Export Logs" to download CSV
- Click "Refresh" to reload logs

---

### ⚙️ System Settings

**What it does:** Configure system-wide parameters

**Settings Categories:**

#### 🔵 General Settings
- **System Name**: Displayed in headers and emails
- **System Email**: Default sender address
- **Session Timeout**: Auto-logout time (5-120 minutes)

#### 🟢 User Management
- **Default Password**: For newly verified users (default: Welcome@123)
- **Max Exam Assignments**: Per teacher limit (default: 10)
- **Auto-verify Users**: ⬜ Skip manual verification

#### 🔷 System Features
- **Email Notifications**: ✅ Send email alerts
- **Maintenance Mode**: ⬜ Restrict to admins only

#### 🟡 Database & Logs
- **Backup Database**: Download SQL backup file
- **Clear Old Logs**: Delete logs older than 30 days
- **Clear All Logs**: ⚠️ Delete ALL audit logs (use with caution!)

---

## Common Tasks

### Grant Dashboard Access to User

1. Click **Permissions Control**
2. Search for user name
3. Toggle desired permission switch(es)
4. Click 💾 Save button
5. Verify success notification

### View System Statistics

1. Click **Analytics & Reports**
2. Review statistics cards at top
3. Scroll down to view charts
4. Click "Export Report" if needed

### Export Data

**Method 1: Quick Export**
1. Click "Export Reports" button (sidebar)
2. Select report type:
   - Users Report
   - Exams Report
   - Audit Logs
   - Analytics Report
3. File downloads automatically

**Method 2: Module Export**
- From **Activity Logs**: Click "Export Logs"
- From **Analytics**: Click "Export Report"
- From **Exam Management**: Click "Export" button

### Check Recent Admin Activity

1. Click **Activity Logs**
2. Recent actions shown at top
3. Use filters to narrow down:
   - Type action name in search
   - Select admin from dropdown
   - Choose date range
4. Click "Export Logs" for CSV

### Backup Database

1. Click **System Settings**
2. Scroll to "Database & Logs" card
3. Click "Backup Database"
4. SQL file downloads with timestamp

### Change System Settings

1. Click **System Settings**
2. Modify desired settings
3. Click "Save Settings" button
4. Wait for success message
5. Click "Reset" to reload and verify

---

## Keyboard Shortcuts

| Key | Action |
|-----|--------|
| `Ctrl + F` | Search in current page |
| `F5` | Refresh current module |
| `Ctrl + Click` | Open link in new tab |
| `Esc` | Close open modals |

---

## Status Indicators

### User Status Colors
- 🟢 **Verified**: Active user
- 🟡 **Pending**: Awaiting verification
- 🔴 **Rejected**: Access denied

### Exam Status Colors
- 🟢 **Approved**: Ready for assignments
- 🟡 **Pending**: Awaiting approval
- 🔵 **Assigned**: Faculty assigned
- ⚪ **Completed**: Exam finished

---

## Data Export Formats

### Users CSV Columns:
```
ID, Name, Email, Role, College, Phone, Status, Registered
```

### Exams CSV Columns:
```
ID, Exam Name, Subject, Exam Date, Status, College, Description
```

### Audit Logs CSV Columns:
```
ID, Timestamp, Admin ID, Admin Name, Action, Details, IP Address
```

---

## Troubleshooting

### Module Won't Load

**Symptoms:** Clicking menu item shows "Loading..." forever

**Solutions:**
1. Press F5 to refresh page
2. Check internet connection (for Chart.js)
3. Clear browser cache
4. Try different browser

### Permissions Won't Save

**Symptoms:** Error message when saving permissions

**Solutions:**
1. Refresh page to get new CSRF token
2. Check if user exists in database
3. Verify you're logged in as admin

### Charts Not Displaying

**Symptoms:** Empty spaces where charts should be

**Solutions:**
1. Check internet connection (Chart.js loads from CDN)
2. Refresh the Analytics module
3. Try different browser
4. Check browser console for errors (F12)

### Export Downloads Empty File

**Symptoms:** CSV file is empty or has only headers

**Solutions:**
1. Verify database has data
2. Check if table exists
3. Try different export type
4. Check PHP error logs

---

## Best Practices

### Security
- ✅ Always logout when done
- ✅ Review Activity Logs regularly
- ✅ Grant minimal required permissions
- ✅ Backup database before bulk changes
- ✅ Use Maintenance Mode for system updates

### Data Management
- ✅ Clear old logs monthly (>30 days)
- ✅ Backup database weekly
- ✅ Verify users promptly
- ✅ Review pending approvals daily
- ✅ Export reports for record keeping

### Performance
- ✅ Use filters to limit large result sets
- ✅ Export data instead of viewing all
- ✅ Clear browser cache if slow
- ✅ Close unused browser tabs

---

## Emergency Procedures

### System Locked Out

1. Check Maintenance Mode is OFF:
   - Settings → System Features → Uncheck Maintenance Mode
2. If can't access dashboard:
   - Contact database admin
   - Set `maintenance_mode = 0` in `system_settings` table

### All Logs Deleted by Mistake

1. Don't panic - database tables still exist
2. Future actions will create new logs
3. Check browser console for JavaScript logs
4. Restore from backup if critical

### Wrong Permissions Granted

1. Go to **Permissions Control**
2. Search for affected user
3. Toggle off incorrect permissions
4. Click Save
5. Verify in **Activity Logs** that change was recorded

### Database Backup Fails

1. Check disk space
2. Verify write permissions on `/backups/` folder
3. Create folder manually: `mkdir backups`
4. Set permissions: `chmod 755 backups`
5. Try export again

---

## Getting Help

### Documentation Files

| File | Purpose |
|------|---------|
| `ADMIN_DASHBOARD_IMPLEMENTATION.md` | Technical implementation details |
| `ADMIN_DASHBOARD_TESTING.md` | Complete testing guide |
| `SECURITY_IMPLEMENTATION_GUIDE.md` | Security features documentation |
| `README.md` | General system overview |
| `LOGIN_CREDENTIALS.md` | Default login credentials |

### Log Files

| Log | Location | Purpose |
|-----|----------|---------|
| PHP Errors | `/logs/error.log` | PHP runtime errors |
| Security | `/logs/security.log` | Authentication events |
| Apache | XAMPP `/apache/logs/error.log` | Server errors |
| MySQL | XAMPP `/mysql/data/*.err` | Database errors |

### Console Debugging

1. Press `F12` to open Developer Tools
2. Check **Console** tab for JavaScript errors
3. Check **Network** tab for failed AJAX requests
4. Copy error messages when reporting issues

---

## Version Information

- **Version**: 1.0
- **Last Updated**: 2024
- **Features**: 9 modules fully functional
- **Database**: MySQL with auto-table creation
- **Frontend**: Bootstrap 5 + Chart.js
- **Backend**: PHP 7.4+ with PDO

---

## Quick Command Reference

### Access Admin Dashboard
```
URL: http://localhost/external/eems/admin_dashboard.php
```

### Direct Module Access
```
Permissions: ?action=load_module&module=permissions
Analytics: ?action=load_module&module=analytics
Logs: ?action=load_module&module=audit_logs
Settings: ?action=load_module&module=settings
```

### Export Data
```
Users: ?action=export_csv&type=users
Exams: ?action=export_csv&type=exams
Logs: ?action=export_csv&type=audit_logs
Analytics: ?action=export_csv&type=analytics
```

### Backup Database
```
?action=backup_database
```

---

## Permissions Matrix

| User Role | Can Access | Typical Permissions |
|-----------|-----------|---------------------|
| **Admin** | Everything | All 4 permissions granted |
| **Principal** | Own college exams | Principal + Teacher access |
| **Vice Principal** | College operations | Vice + Teacher access |
| **HOD** | Department operations | HOD + Teacher access |
| **Teacher** | Own assignments | Teacher access only |

---

## Important Notes

⚠️ **Maintenance Mode**: When enabled, only admins can access the system

⚠️ **Clear All Logs**: Cannot be undone - backs up to file before deleting

⚠️ **Default Password**: Shared with new users - tell them to change it

⚠️ **Auto-verify Users**: Skip manual approval - use with caution

⚠️ **Bulk Save**: Saves all permission changes at once - verify before clicking

---

## Tips & Tricks

💡 **Fast User Search**: Type partial name in Permissions search box

💡 **Date Filters**: Use YYYY-MM-DD format for Activity Logs

💡 **Chart Tooltips**: Hover over chart segments for exact numbers

💡 **Bulk Operations**: Hold Ctrl while clicking for faster toggles

💡 **Export Before Delete**: Always export logs before clearing them

💡 **Mobile Access**: Sidebar collapses on mobile - tap hamburger icon

💡 **Browser Cache**: Clear cache if seeing old data

💡 **Session Timeout**: Set higher for long admin sessions

---

## Success Indicators

✅ All modules load without "Unknown module" error
✅ Toast notifications appear after actions
✅ Charts render with colorful visualizations
✅ Exports download with timestamp filenames
✅ Filters update tables in real-time
✅ Settings persist after page reload
✅ Activity logs show recent actions

---

*For detailed technical information, see ADMIN_DASHBOARD_IMPLEMENTATION.md*
*For comprehensive testing procedures, see ADMIN_DASHBOARD_TESTING.md*
