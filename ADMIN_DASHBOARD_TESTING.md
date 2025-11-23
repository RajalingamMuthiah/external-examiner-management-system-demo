# Admin Dashboard Testing Guide

## Quick Start Testing

### Prerequisites
1. XAMPP running with Apache and MySQL
2. Database `eems` created and seeded
3. Admin user account available
4. Browser with JavaScript enabled

---

## Testing Steps

### 1. Login as Admin

```
URL: http://localhost/external/eems/admin_login.php
Credentials: Check LOGIN_CREDENTIALS.md
```

**Expected Result:**
- Successful login
- Redirect to admin dashboard
- Sidebar visible with all menu items

---

### 2. Test Permissions Control Tab

**Steps:**
1. Click "Permissions Control" in sidebar
2. Wait for module to load

**Expected Result:**
- Table showing all users
- Four permission columns with toggle switches:
  - Principal Access
  - Vice Principal Access
  - HOD Access
  - Teacher Access
- Search box at top right
- "Save All Changes" button visible

**Test Actions:**
1. Toggle a permission switch for a user
2. Click the individual "Save" button (disk icon)
3. Look for success toast notification (top-right corner)
4. Refresh the module (click Permissions Control again)
5. Verify toggle switch state persisted

**Test Search:**
1. Type a user name in search box
2. Verify table filters to show only matching users
3. Clear search box
4. Verify all users visible again

**Test Bulk Save:**
1. Toggle multiple permissions for different users
2. Click "Save All Changes" button
3. Wait for completion message
4. Refresh module
5. Verify all changes persisted

---

### 3. Test Analytics & Reports Tab

**Steps:**
1. Click "Analytics & Reports" in sidebar
2. Wait for module to load

**Expected Result:**
- Four colored statistics cards at top:
  - Total Users (purple gradient)
  - Total Colleges (pink gradient)
  - Total Exams (blue gradient)
  - Pending Items (orange gradient)
- Four charts below:
  - Users by Role (doughnut chart)
  - User Registrations (line chart)
  - Exams by Status (bar chart)
  - Verification Status (pie chart)
- Recent Admin Activity table at bottom

**Test Charts:**
1. Verify all four charts render without errors
2. Hover over chart segments to see tooltips
3. Check browser console for any Chart.js errors

**Test Export:**
1. Click "Export Report" button
2. Verify CSV download starts
3. Open CSV file and check data

---

### 4. Test Activity Logs Tab

**Steps:**
1. Click "Activity Logs" in sidebar (may show as "Audit Logs")
2. Wait for module to load

**Expected Result:**
- Filter controls at top:
  - Search Action input
  - Admin User dropdown
  - Date From/To inputs
- Table with columns:
  - Timestamp
  - Admin
  - Action (colored badges)
  - Details
  - IP Address
- Export Logs button
- Refresh button

**Test Filters:**
1. Type "verify" in Search Action
2. Verify table filters to show only verification actions
3. Clear search
4. Select an admin from Admin User dropdown
5. Verify table shows only that admin's actions
6. Select a date range
7. Verify table shows only logs within range

**Test Export:**
1. Click "Export Logs" button
2. Verify CSV download starts
3. Open CSV and verify log data

---

### 5. Test System Settings Tab

**Steps:**
1. Click "System Settings" in sidebar
2. Wait for module to load

**Expected Result:**
- Four cards:
  1. General Settings (blue header)
     - System Name input
     - System Email input
     - Session Timeout input
  2. User Management (green header)
     - Default Password input
     - Max Exam Assignments input
     - Auto-verify Users checkbox
  3. System Features (cyan header)
     - Email Notifications checkbox
     - Maintenance Mode checkbox
  4. Database & Logs (yellow header)
     - Backup Database button
     - Clear Old Logs button
     - Clear All Logs button

**Test Save Settings:**
1. Change System Name to "Test EEMS"
2. Change Default Password to "NewPass123"
3. Toggle Auto-verify Users checkbox
4. Click "Save Settings" button
5. Look for success message
6. Click "Reset" button to reload
7. Verify changes persisted

**Test Backup Database:**
1. Click "Backup Database" button
2. Verify SQL file downloads
3. Open SQL file and verify it contains INSERT statements

**Test Clear Old Logs:**
1. Click "Clear Old Logs (>30 days)" button
2. Confirm in popup
3. Verify success message
4. Check Activity Logs - old logs should be gone

**WARNING:** Don't test "Clear All Logs" unless you want to delete ALL audit logs!

---

### 6. Test Quick Add Exam Button

**Steps:**
1. Locate "Quick Add Exam" button in sidebar (green button)
2. Click the button

**Expected Result:**
- Exam Management module loads
- Add Exam modal opens automatically after 0.5 seconds
- Modal shows form with fields:
  - Exam Name
  - Subject
  - College/Department
  - Exam Date
  - Description

**Test Add Exam:**
1. Fill in all required fields:
   - Exam Name: "Test Exam"
   - Subject: "Mathematics"
   - College: "Test College"
   - Exam Date: Future date
   - Description: "Test description"
2. Click "Add Exam" or "Submit" button
3. Verify success message
4. Verify exam appears in exam list with "Pending" status

---

### 7. Test Export Reports Button

**Steps:**
1. Locate "Export Reports" button in sidebar
2. Click the button

**Expected Result:**
- Modal opens with title "Export Reports"
- Four export option buttons:
  - Users Report (CSV) - blue outline
  - Exams Report (CSV) - green outline
  - Audit Logs (CSV) - cyan outline
  - Analytics Report (CSV) - yellow outline

**Test Each Export Type:**

1. **Users Report:**
   - Click "Users Report" button
   - Verify CSV downloads with filename like `users_export_2024-01-15.csv`
   - Open CSV and verify columns: ID, Name, Email, Role, College, Phone, Status, Registered
   - Verify data matches users in database

2. **Exams Report:**
   - Click "Export Reports" again
   - Click "Exams Report" button
   - Verify CSV downloads
   - Open CSV and verify exam data

3. **Audit Logs:**
   - Click "Export Reports" again
   - Click "Audit Logs" button
   - Verify CSV downloads with audit log data

4. **Analytics Report:**
   - Click "Export Reports" again
   - Click "Analytics Report" button
   - Verify CSV downloads with analytics data

---

## Common Issues & Solutions

### Issue: Module Loads But Shows "Loading..." Forever

**Solution:**
1. Open browser developer console (F12)
2. Check for JavaScript errors
3. Check Network tab for failed AJAX requests
4. Verify `?action=load_module&module=X` returns HTML (not JSON)

### Issue: Permissions Don't Save

**Solution:**
1. Check browser console for CSRF token errors
2. Verify `permissions` table exists in database
3. Check PHP error logs at `/logs/` or Apache error log
4. Verify user_id exists in both users and permissions tables

### Issue: Charts Don't Display

**Solution:**
1. Check if Chart.js CDN is accessible (internet required)
2. Verify browser console for Chart.js errors
3. Check if canvas elements exist in DOM
4. Verify JSON data format is correct

### Issue: Export Reports Downloads Empty File

**Solution:**
1. Check PHP error logs
2. Verify table names in export queries
3. Check file permissions on server
4. Ensure browser allows downloads

### Issue: Settings Don't Persist

**Solution:**
1. Verify `system_settings` table was created
2. Check MySQL permissions for INSERT/UPDATE
3. Verify CSRF token is valid
4. Check admin role in session

---

## Browser Console Debugging

### Check Module Loading:
```javascript
// In browser console, watch for:
console.log('Loading module:', module);

// Expected output when clicking Permissions:
// Loading module: permissions
```

### Check AJAX Responses:
```javascript
// Network tab should show:
// Request: ?action=load_module&module=permissions
// Status: 200
// Response: HTML content (not JSON)
```

### Check CSRF Token:
```javascript
// In console:
console.log(CSRF_TOKEN);
// Should output a string like: "abc123def456..."
```

---

## Database Verification

### Check Permissions Table:
```sql
SELECT * FROM permissions LIMIT 10;
-- Should show user_id and permission flags (0 or 1)
```

### Check Audit Logs:
```sql
SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 10;
-- Should show recent admin actions
```

### Check System Settings:
```sql
SELECT * FROM system_settings;
-- Should show setting_key and setting_value pairs
```

### Check Users:
```sql
SELECT id, name, email, post, status FROM users WHERE post = 'admin';
-- Should show admin user(s)
```

---

## Performance Testing

### Large User List (Permissions):
1. If you have 100+ users, test scrolling performance
2. Test search with 1000+ users
3. Verify bulk save doesn't timeout

### Large Log Dataset (Activity Logs):
1. Generate 1000+ log entries
2. Test filtering performance
3. Test export with large dataset
4. Verify pagination if implemented

### Chart Rendering (Analytics):
1. Test with large datasets (1000+ users/exams)
2. Verify charts render within 2 seconds
3. Test responsiveness on mobile devices

---

## Mobile/Responsive Testing

### Test on Different Screen Sizes:
1. Desktop (1920x1080)
2. Tablet (768x1024)
3. Mobile (375x667)

**Expected:**
- Sidebar collapses on mobile
- Tables scroll horizontally if needed
- Charts resize properly
- Buttons stack vertically on small screens

---

## Security Testing

### CSRF Protection:
1. Open browser console
2. Try submitting permission change without CSRF token:
```javascript
$.post('?action=update_permissions', {user_id: 1}, console.log);
// Expected: {"success":false,"message":"Invalid CSRF token"}
```

### Role-Based Access:
1. Logout as admin
2. Login as teacher
3. Try accessing admin dashboard
4. Expected: Redirect or "Access Denied"

### SQL Injection:
1. Try entering `' OR '1'='1` in search boxes
2. Expected: Search fails safely, no SQL errors

---

## Load Testing (Optional)

### Simulate Multiple Users:
```bash
# Using Apache Bench (if installed)
ab -n 100 -c 10 http://localhost/external/eems/admin_dashboard.php?action=load_module&module=analytics

# Expected:
# - No 500 errors
# - Response time < 500ms
# - No database connection errors
```

---

## Acceptance Criteria

All features should meet these criteria:

✅ **Functionality:**
- All modules load without errors
- All AJAX actions complete successfully
- All export functions generate valid files
- All forms save data correctly

✅ **User Experience:**
- Loading indicators shown during AJAX calls
- Success/error messages displayed clearly
- No page reloads for module switches
- Smooth transitions and animations

✅ **Security:**
- CSRF tokens validated on all POST requests
- Role-based access enforced
- SQL injection prevented
- XSS prevented via proper escaping

✅ **Performance:**
- Page loads in < 2 seconds
- Module switches in < 500ms
- Charts render in < 1 second
- No memory leaks or console errors

✅ **Compatibility:**
- Works in Chrome, Firefox, Edge
- Responsive on mobile/tablet
- Works with JavaScript enabled
- Graceful degradation if features fail

---

## Reporting Issues

When reporting issues, include:

1. **Browser & Version**: Chrome 120, Firefox 115, etc.
2. **Steps to Reproduce**: Exact click sequence
3. **Expected Behavior**: What should happen
4. **Actual Behavior**: What actually happens
5. **Screenshots**: Error messages, console logs
6. **Console Errors**: JavaScript errors from F12 console
7. **PHP Errors**: From Apache error log or /logs/

**Example Issue Report:**
```
Title: Permissions don't save for user ID 5

Browser: Chrome 120 on Windows 11
Steps:
1. Click Permissions Control
2. Toggle "Principal Access" for user "John Doe" (ID 5)
3. Click Save button
4. See error toast "Failed to update permissions"

Console Error:
POST ?action=update_permissions 500 (Internal Server Error)

Expected: Success message and permission saved
Actual: Error message and permission not saved

Screenshot: [attached]
```

---

## Sign-Off Checklist

Before marking testing complete, verify:

- [ ] All 7 main test sections completed
- [ ] All modules load without errors
- [ ] All AJAX actions work correctly
- [ ] All export functions generate valid files
- [ ] All forms validate and save properly
- [ ] CSRF protection working
- [ ] Role-based access enforced
- [ ] No JavaScript console errors
- [ ] No PHP errors in logs
- [ ] Mobile responsive design works
- [ ] Charts render correctly
- [ ] Search/filter functions work
- [ ] Toast notifications appear
- [ ] Database updates persist
- [ ] CSV exports contain valid data

---

## Next Steps After Testing

If all tests pass:
1. Document any discovered edge cases
2. Create user training materials
3. Deploy to staging environment
4. Conduct user acceptance testing
5. Plan production deployment

If tests fail:
1. Document all failures with screenshots
2. Prioritize by severity (critical/high/medium/low)
3. Create bug tickets
4. Fix critical issues first
5. Re-test after fixes

---

## Support Contacts

- **Developer**: Check IMPLEMENTATION_SUMMARY.md
- **Database Issues**: Check db/schema.sql
- **Security Questions**: Check SECURITY_IMPLEMENTATION_GUIDE.md
- **General Help**: Check README.md
