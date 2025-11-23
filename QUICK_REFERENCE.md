# 🎯 Admin Dashboard - Quick Reference Card

## 🚀 Quick Start

### 1. Access Dashboard
```
URL: http://localhost/external/eems/admin_dashboard.php
Login: Use admin credentials from LOGIN_CREDENTIALS.md
Default Landing: Overview Dashboard
```

### 2. Main Modules

| Module | Access | Purpose |
|--------|--------|---------|
| **Overview** | Default page | Statistics, charts, analytics |
| **Approvals & Verifications** | Sidebar link | Approve pending users/requests |
| **User Management** | Sidebar link | Search, filter, bulk operations |
| **Audit Logs** | Sidebar link | View activity history |
| **Principal/VP/HOD/Teacher** | Sidebar links | Role-specific dashboards |

---

## ⚡ Common Tasks

### Verify Multiple Users
```
1. Click "User Management" in sidebar
2. Check boxes next to users OR click "Select All"
3. Click green "Verify" button
4. Confirm action → Done!
```

### Search for a User
```
1. User Management → Search bar
2. Type name, email, or college
3. Click "Filter" → Results appear instantly
```

### Change User Role
```
1. User Management → Find user
2. Click role dropdown in their row
3. Select new role
4. Confirm change → Updates immediately + logged
```

### Export Data
```
CSV: Click "Export" → "CSV Format"
Excel/PDF: Buttons ready (need implementation)
Downloads: users_export_2024-12-25.csv
```

### View Audit Trail
```
1. Click "Audit Logs" in sidebar
2. Use filters: Search action, Admin, Date range
3. Export logs: Click "Export Logs" button
```

---

## 🎨 UI Elements Legend

### Status Badges
- 🟢 **Green** = Verified
- 🟡 **Yellow** = Pending
- 🔴 **Red** = Rejected

### Action Badges (Audit Logs)
- 🟢 **Green** = Approve/Verify actions
- 🔴 **Red** = Reject/Delete actions
- 🟡 **Yellow** = Update/Change actions
- 🔵 **Blue** = Other actions

### Icons
- ✓ = Approve
- ✗ = Reject
- ✎ = Edit
- ⬇ = Download/Export
- ⟳ = Refresh
- ⏱ = Audit/History
- 👤 = User Management

---

## 📊 Statistics Overview

### Dashboard Cards
```
┌─────────────┬─────────────┬─────────────┬─────────────┐
│ Total Users │   Colleges  │    Exams    │   Pending   │
│     120     │      15     │      45     │      8      │
│   (+12%)    │             │             │  [Click]    │
└─────────────┴─────────────┴─────────────┴─────────────┘
```

### Charts Available
1. **Users by Role** (Doughnut)
   - Admin, Principal, VP, HOD, Teacher distribution

2. **Verification Status** (Pie)
   - Verified vs Pending vs Rejected

---

## 🔍 Search & Filter Cheat Sheet

### User Management Filters
| Filter | Options |
|--------|---------|
| **Search** | Name, email, college (real-time) |
| **Role** | All, Teacher, HOD, VP, Principal, Admin |
| **College** | All colleges (from database) |
| **Status** | All, Verified, Pending, Rejected |

### Audit Logs Filters
| Filter | Options |
|--------|---------|
| **Search Action** | Any action text (real-time) |
| **Admin** | All admins who performed actions |
| **Date From** | Start date (YYYY-MM-DD) |
| **Date To** | End date (YYYY-MM-DD) |

---

## ⌨️ Keyboard Shortcuts

```
Overview Module:      (default on login)
User Management:      Click sidebar link
Audit Logs:           Click sidebar link
Refresh Current:      F5 or Ctrl+R
Export CSV:           Click Export → CSV
Close Dialogs:        ESC or click outside
```

---

## 🛠️ Troubleshooting

### Issue: Charts not showing
**Fix:** Check browser console, verify Chart.js CDN loaded

### Issue: Export downloads empty file
**Fix:** Check database has data, verify PHP error logs

### Issue: Bulk actions not working
**Fix:** Verify checkboxes are checked, check network tab for errors

### Issue: Audit logs empty
**Fix:** Perform an action (verify user, change role) to generate log

### Issue: Search returns no results
**Fix:** Clear filters, check database has matching data

---

## 📥 Export Formats

### Available Now ✅
- **CSV** - Users, Exams, Audit Logs

### Coming Soon 🚧
- **Excel** - UI ready, needs PHPSpreadsheet
- **PDF** - UI ready, needs TCPDF library

### File Naming Convention
```
users_export_YYYY-MM-DD.csv
exams_export_YYYY-MM-DD.csv
audit_logs_export_YYYY-MM-DD.csv
```

---

## 🔐 Security Features

✅ CSRF token validation on all forms
✅ Session validation required
✅ Audit logging for accountability
✅ IP address tracking
✅ Prepared statements (SQL injection prevention)
✅ Input sanitization
✅ Confirmation dialogs for destructive actions

---

## 📞 Need Help?

### Documentation Files
1. `ADMIN_DASHBOARD_ENHANCEMENTS.md` - Full feature documentation
2. `BEFORE_AFTER_COMPARISON.md` - What's new comparison
3. `AUTHENTICATION_GUIDE.md` - Login/user issues
4. `README_ADMIN.md` - Admin guide
5. `LOGIN_CREDENTIALS.md` - Test credentials

### Common Questions

**Q: How do I add a new user?**
A: Click green "+Add User" button → Register page

**Q: How do I delete a user?**
A: Feature coming soon (bulk delete has UI, needs backend)

**Q: Can I undo a bulk action?**
A: Not automatically - check audit logs to see what was changed

**Q: How long are audit logs kept?**
A: Currently unlimited - recommend archiving logs older than 1 year

**Q: Can I export filtered results only?**
A: Not yet - exports all data. Filter after export in Excel/Sheets

---

## 💡 Pro Tips

1. **Use bulk operations** for efficiency (verify 50 users at once!)
2. **Check Overview daily** for system health snapshot
3. **Review Audit Logs weekly** for security monitoring
4. **Export data regularly** for backups and reporting
5. **Filter before selecting** to target specific user groups
6. **Use real-time search** instead of scrolling through tables
7. **Check pending badge** on sidebar for urgent items
8. **Confirm actions carefully** - bulk operations affect multiple users
9. **Mobile works too** - responsive design adapts to small screens
10. **Reload after bulk actions** to see updated counts/stats

---

## 🎯 Keyboard Navigation

```
Tab         → Navigate between fields
Enter       → Submit/Confirm
Escape      → Close modals/dialogs
Ctrl+F      → Browser search (within page)
Ctrl+Click  → Open links in new tab
```

---

## 📱 Mobile Usage

### Sidebar Navigation
- Tap hamburger menu icon to open sidebar
- Tap outside sidebar to close
- Sidebar auto-closes after selection

### Tables
- Swipe left/right to scroll
- Tap rows for details
- Pinch to zoom (if needed)

### Forms
- Touch-friendly input fields
- Dropdowns work with native mobile UI
- Checkboxes sized for finger taps

---

## 🔄 Automatic Features

These happen automatically:
- ✅ Active sidebar link highlights on module load
- ✅ Pending badge updates when items approved
- ✅ Audit logs created on every admin action
- ✅ Charts refresh on data change
- ✅ User counts update after verification
- ✅ Loading spinners during AJAX calls
- ✅ Alert messages auto-dismiss after 3 seconds

---

## 📊 Data Flow Diagram

```
User Action → AJAX Call → PHP Backend → Database
     ↓            ↓             ↓            ↓
  Confirm     CSRF Check    Validate    Execute Query
     ↓            ↓             ↓            ↓
  Execute     Process      Log Action   Return JSON
     ↓            ↓             ↓            ↓
  Update UI   Show Alert   Audit Log    Refresh Data
```

---

## ✨ Feature Availability

| Feature | Status |
|---------|--------|
| Dashboard Statistics | ✅ Live |
| Chart.js Visualizations | ✅ Live |
| User Search & Filter | ✅ Live |
| Bulk Operations | ✅ Live |
| Role Editing | ✅ Live |
| Audit Logging | ✅ Live |
| CSV Export | ✅ Live |
| Excel Export | 🚧 UI Ready |
| PDF Export | 🚧 UI Ready |
| User Edit Modal | 🚧 Placeholder |
| Add Exam Modal | 🚧 Placeholder |
| Real-time Notifications | 🚧 Placeholder |

---

## 🎓 Learning Mode

### Want to understand the code?
1. Open `admin_dashboard.php`
2. Search for `// ENHANCED:` comments
3. Read inline documentation
4. Check function definitions at top of file
5. Review AJAX endpoints around line 1500+

### Code Structure
```
Lines 1-350:    Configuration & Helper Functions
Lines 350-500:  Main HTML/PHP UI Structure
Lines 500-1500: Module Cases (Overview, User Mgmt, Audit, etc.)
Lines 1500-1700: AJAX Action Handlers
Lines 1700-2150: JavaScript & Event Handlers
```

---

**Quick Reference Version:** 1.0  
**For Dashboard Version:** 2.0  
**Last Updated:** December 2024

**Print this card and keep it handy!** 📌
