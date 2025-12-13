# EEMS User Manual
**External Exam Management System - Version 1.0**  
**Last Updated:** December 13, 2025

---

## Table of Contents
1. [Getting Started](#getting-started)
2. [Teacher Guide](#teacher-guide)
3. [HOD Guide](#hod-guide)
4. [Principal Guide](#principal-guide)
5. [Vice-Principal Guide](#vice-principal-guide)
6. [Admin Guide](#admin-guide)
7. [Common Tasks](#common-tasks)
8. [FAQs](#faqs)

---

## Getting Started

### First Login

1. Navigate to your EEMS URL (e.g., `https://your-college.edu/eems`)
2. Click **Login** in the top navigation
3. Enter your credentials:
   - **Email:** Provided by your administrator
   - **Password:** Temporary password (change on first login)
4. Complete your profile setup if required

### Dashboard Overview

All users see a personalized dashboard with:
- **Notification Badge:** Unread notifications in top-right corner
- **Quick Stats:** Key metrics relevant to your role
- **Recent Activity:** Latest exams, assignments, approvals
- **Action Cards:** Quick access to common tasks

---

## Teacher Guide

### Your Role
As a **Teacher**, you can:
- View exams from your college
- Accept/reject examiner assignments
- Submit question papers
- Conduct practical exams
- View ratings received

### Viewing Available Exams

**Steps:**
1. Go to **Dashboard** after login
2. Scroll to **Upcoming Exams** section
3. Click any exam to view details:
   - Exam name, subject, type
   - Date, time, venue
   - Examiner requirements
   - College information

**Filters:**
- Search by exam name or subject
- Filter by exam type (Theory/Practical/Viva/Project)
- Filter by date range

### Responding to Examiner Invitations

When invited as an examiner, you'll receive a **notification** and **email**.

**To Accept:**
1. Click the notification or go to **My Assignments**
2. Click **View Details** on the invitation
3. Review:
   - Exam details (date, venue, duration)
   - Your duty type (Chief Examiner, External, Moderator)
   - Remuneration details
4. Click **Accept Assignment**
5. Confirmation message appears

**To Reject:**
1. Follow steps 1-3 above
2. Click **Reject Assignment**
3. Enter rejection reason (required):
   - Prior commitment
   - Travel constraints
   - Subject expertise mismatch
   - Other (specify)
4. Click **Submit Rejection**

**Important Notes:**
- Response deadline: 7 days from invitation
- Late responses may affect future invitations
- You can view all past assignments in **Assignment History**

### Submitting Question Papers

**For Theory Exams:**

1. Go to **My Assignments** → Select exam
2. Click **Upload Question Paper**
3. Fill details:
   - **Exam:** Auto-selected
   - **Version:** Version number (e.g., 1.0, 1.1)
   - **File:** Choose PDF (max 10 MB)
   - **Comments:** Optional notes
4. Click **Upload**
5. Wait for Principal approval/lock

**File Requirements:**
- Format: PDF only
- Size: Maximum 10 MB
- Naming: `ExamName_Version.pdf` (recommended)
- Content: Follow college guidelines

**Versioning:**
- Version 1.0: Initial submission
- Version 1.1, 1.2: Revisions (if unlocked by Principal)
- Only locked version is used for exam

### Conducting Practical Exams

**Pre-Exam Setup:**

1. Go to **Practical Exams** → **My Sessions**
2. Click **Create Session**
3. Fill session details:
   - **Exam:** Select from dropdown
   - **Date & Time:** Session schedule
   - **Lab Room:** Venue
   - **Max Students:** Per session
   - **Instructions:** Student guidelines
4. Click **Create Session**

**During Exam:**

1. Open **Active Sessions** on exam day
2. Click **Start Session** at scheduled time
3. For each student:
   - Enter **Student Name**
   - Enter **Roll Number**
   - Record **Marks Obtained** / **Total Marks**
   - Add **Performance Notes** (optional)
   - Click **Save Attempt**
4. System auto-calculates percentage and pass/fail
5. Click **End Session** when complete

**Post-Exam:**
- Review all attempts in **Session History**
- Generate **Results Report** (PDF)
- Submit marks to examination office

### Viewing Your Ratings

After completing examiner duties:

1. Go to **My Profile** → **Ratings**
2. View:
   - **Average Rating:** Overall score (1-5 stars)
   - **Total Ratings:** Number of ratings received
   - **Rating Distribution:** Breakdown by star level
   - **Recent Feedback:** Comments from HODs/Principals
3. Use feedback for continuous improvement

**Rating Criteria:**
- Punctuality
- Question paper quality
- Student interaction
- Report submission timeliness
- Overall professionalism

---

## HOD Guide

### Your Role
As a **Head of Department**, you can:
- Create exam requests for your department
- Approve/nominate examiners from your college
- Rate examiners after exam completion
- View all department exams
- Manage faculty availability

### Creating an Exam Request

**Steps:**
1. Click **Create Exam** button on dashboard
2. Fill exam details:
   - **Exam Name:** e.g., "Data Structures Final Exam"
   - **Subject:** e.g., "Computer Science"
   - **Exam Type:** Theory/Practical/Viva/Project
   - **Exam Date:** Select future date
   - **Duration:** 30-480 minutes
   - **Venue:** Room/Lab number
   - **Examiners Needed:** 1-10 examiners
   - **Description:** Additional details
3. Click **Submit Request**
4. Wait for Principal approval

**Important Notes:**
- College field auto-fills from your profile
- Exam date must be at least 7 days in future (recommended)
- Duration includes setup and closing time
- Clearly specify subject area for examiner matching

### Managing Examiner Nominations

**Viewing Nominations:**

1. Go to **Examiner Management** → **Nominations**
2. View pending nominations from your college faculty
3. Filters:
   - By subject area
   - By availability status
   - By past ratings

**Nominating Examiners:**

1. Click **Nominate** on exam card
2. Select examiner from your college faculty:
   - View their qualifications
   - Check availability calendar
   - See past ratings and feedback
3. Select **Duty Type:**
   - Chief Examiner (leads exam)
   - External Examiner (evaluation)
   - Moderator (quality check)
   - Invigilator (supervision)
4. Add **Comments** (optional)
5. Click **Submit Nomination**

**Nomination Status:**
- **Pending:** Awaiting faculty response
- **Accepted:** Faculty confirmed
- **Rejected:** Faculty declined (view reason)
- **Completed:** Duty fulfilled

### Setting Faculty Availability

**For Your Department:**

1. Go to **Availability Panel** → **Department Calendar**
2. Click **Add Unavailability** for a faculty member
3. Enter:
   - **Faculty Member:** Select from dropdown
   - **Start Date:** Beginning of unavailable period
   - **End Date:** End of unavailable period
   - **Reason:** Leave type (Medical/Conference/Personal)
4. Click **Save**

System automatically:
- Prevents nomination during unavailable periods
- Shows availability status in examiner selection
- Sends notifications for conflicting assignments

### Rating Examiners

**After Exam Completion:**

1. Go to **Completed Exams** → Select exam
2. Click **Rate Examiners**
3. For each examiner, rate on 1-5 scale:
   - **Overall Performance:** General impression
   - **Punctuality:** Timeliness
   - **Question Quality:** (If applicable) Paper quality
   - **Professionalism:** Conduct during exam
4. Add **Comments** (optional but recommended)
5. Click **Submit Ratings**

**Rating Guidelines:**
- ⭐ Poor: Significant issues, wouldn't recommend
- ⭐⭐ Below Average: Several areas need improvement
- ⭐⭐⭐ Average: Met basic expectations
- ⭐⭐⭐⭐ Good: Exceeded expectations in most areas
- ⭐⭐⭐⭐⭐ Excellent: Outstanding performance, highly recommend

Ratings help:
- Improve examiner selection
- Recognize excellent performance
- Identify training needs
- Build quality culture

---

## Principal Guide

### Your Role
As a **Principal**, you can:
- Approve/reject exam requests from HODs
- Lock/unlock question papers
- Approve examiner assignments
- View college-wide statistics
- Generate official documents

### Approving Exam Requests

**Viewing Pending Requests:**

1. Go to **Dashboard** → **Pending Approvals**
2. See all exam requests requiring your approval
3. Filter by:
   - Department
   - Exam type
   - Date range
   - Urgency (days until exam)

**Approval Process:**

1. Click **Review** on an exam request
2. Review details:
   - Exam name, subject, type
   - Requesting HOD
   - Date, venue, duration
   - Examiners needed
   - Department justification
3. Check:
   - ✓ Date conflicts with other exams
   - ✓ Venue availability
   - ✓ Sufficient notice period
   - ✓ Budget allocation
4. Decision:
   - **Approve:** Click **Approve** → Add comments → Submit
   - **Reject:** Click **Reject** → Enter reason (required) → Submit
   - **Request Changes:** Click **Request Revision** → Specify changes → Submit

**Approval Criteria:**
- Academic calendar compliance
- Resource availability (venues, staff)
- Budget constraints
- Adequate preparation time (minimum 14 days recommended)
- Compliance with university regulations

### Managing Question Papers

**Locking Question Papers:**

When question paper is ready for exam:

1. Go to **Question Papers** → **Pending Lock**
2. Click **View** on a question paper
3. Review:
   - Download and verify content
   - Check version number
   - Verify submission date
   - Review uploader's comments
4. Click **Lock Question Paper**
5. Add **Lock Comments** (optional)
6. Confirm lock

**Effect of Locking:**
- Paper cannot be modified
- Paper cannot be replaced
- Paper is ready for printing
- Uploader cannot delete file
- Locked status visible to all

**Unlocking Question Papers:**

After exam (for corrections or revisions):

1. Go to **Question Papers** → **Locked Papers**
2. Click **Unlock** on desired paper
3. Enter **Unlock Reason** (required):
   - Post-exam revision
   - Error correction
   - Version update
   - Other (specify)
4. Confirm unlock

**Best Practices:**
- Lock papers at least 48 hours before exam
- Download backup copy before locking
- Verify paper quality and content
- Never unlock during exam period
- Document unlock reasons clearly

### Approving Examiner Assignments

**Bulk Approval:**

1. Go to **Examiner Management** → **Pending Assignments**
2. Review all pending assignments
3. For each:
   - Check examiner qualifications
   - Verify no schedule conflicts
   - Review past ratings
   - Confirm college approval (if external)
4. Select multiple assignments (checkbox)
5. Click **Bulk Approve**
6. Add **Comments** for all
7. Confirm approval

**Individual Review:**

For special cases:
1. Click **Review** on assignment
2. View detailed examiner profile:
   - Educational background
   - Teaching experience
   - Previous exam duties
   - Average rating
   - Availability calendar
3. Approve or reject with comments

### Generating Official Documents

**Document Types:**

1. **Exam Schedule:**
   - Go to **Documents** → **Generate Schedule**
   - Select date range
   - Choose format (PDF/Excel)
   - Click **Generate**
   - Download complete timetable

2. **Invitation Letters:**
   - Go to **Examiner Management** → **Invitations**
   - Select examiner assignments
   - Click **Generate Invitations**
   - Letters auto-populate with exam details
   - Download PDF for printing/emailing

3. **Duty Roster:**
   - Go to **Documents** → **Duty Roster**
   - Select exam or date range
   - System generates complete roster with:
     * Examiner names
     * Duty types
     * Timings
     * Venues
   - Download PDF

4. **Exam Report:**
   - After exam completion
   - Go to **Reports** → **Exam Report**
   - Select exam
   - Report includes:
     * Exam details
     * Attendance statistics
     * Examiner feedback
     * Question paper details
     * Issues/incidents
   - Download PDF for records

**Document Features:**
- College letterhead auto-applied
- Digital signatures (if configured)
- Official formatting
- Print-ready layout
- Archive copy saved automatically

---

## Vice-Principal Guide

### Your Role
As a **Vice-Principal (Coordinator)**, you can:
- View exams from ALL colleges (inter-college coordination)
- Assign external examiners across colleges
- Manage external examiner database
- Approve inter-college examiner requests
- Monitor system-wide statistics

### Managing External Examiners

**Viewing External Examiner Pool:**

1. Go to **External Examiners** → **Database**
2. View all registered examiners across colleges
3. Filters:
   - By subject area/specialization
   - By college/university
   - By availability status
   - By rating (minimum threshold)
   - By location (for travel planning)

**Examiner Profile Includes:**
- Name, designation, college
- Qualifications and specializations
- Teaching experience
- Previous examiner duties
- Average rating
- Availability calendar
- Contact information

### Assigning External Examiners

**Assignment Process:**

1. Go to **Exam Requests** → **Requires External Examiner**
2. Review exam details:
   - Requesting college
   - Subject and level
   - Date and venue
   - Remuneration offered
3. Click **Assign Examiner**
4. Search external examiner pool:
   - Filter by specialization matching exam subject
   - Check availability on exam date
   - Review ratings and feedback
   - Consider travel distance (< 100 km preferred)
5. Select examiner(s)
6. Add **Assignment Notes**:
   - Travel arrangements
   - Accommodation (if needed)
   - Special instructions
7. Click **Send Invitation**

**System Automatically:**
- Checks examiner availability
- Prevents duplicate assignments
- Sends notification to examiner
- Notifies requesting college
- Creates remuneration record

### Coordinating Inter-College Exams

**When Multiple Colleges Collaborate:**

1. Go to **Inter-College Coordination** → **Joint Exams**
2. View collaborative exam requests
3. Click **Coordinate** on a request
4. Manage:
   - Participating colleges
   - Shared examiners
   - Common question papers
   - Unified schedules
5. Assign coordinators for each college
6. Monitor progress across all colleges

**Your Coordination Tasks:**
- Resolve scheduling conflicts
- Allocate examiners fairly
- Ensure quality standards
- Manage communications
- Handle escalations

### Approving External Requests

**When College Requests External Examiner:**

1. Go to **Requests** → **External Examiner Requests**
2. Review request:
   - Justification for external examiner
   - Budget allocation
   - Subject area requirements
3. Check:
   - Policy compliance
   - Budget availability
   - Examiner pool availability
4. Decision:
   - **Approve:** Proceed to assignment
   - **Reject:** Provide alternative solution
   - **Clarify:** Request additional information

### System-Wide Analytics

**Dashboard Metrics:**

1. Go to **VP Dashboard** → **System Analytics**
2. View metrics:
   - **Total Exams:** Across all colleges
   - **Active Examiners:** Currently engaged
   - **Pending Approvals:** Requiring attention
   - **Average Ratings:** System-wide quality
   - **College Performance:** Comparative analysis

**Reports Available:**
- Monthly examiner utilization
- College-wise exam statistics
- Quality trends (ratings over time)
- Budget utilization
- Examiner satisfaction surveys

---

## Admin Guide

### Your Role
As an **Administrator**, you have:
- Full system access (all colleges)
- User management capabilities
- System configuration control
- Backup and maintenance responsibilities
- Audit trail access

### Managing Users

**Adding New Users:**

1. Go to **Admin Panel** → **User Management**
2. Click **Add User**
3. Fill details:
   - **Name:** Full name
   - **Email:** Unique email address
   - **Role:** Teacher/HOD/Principal/VP/Admin
   - **College:** Select from dropdown
   - **Department:** Select department (if applicable)
   - **Status:** Active (default)
4. Password options:
   - **Auto-Generate:** System creates 8-character password
   - **Manual:** Set custom password (minimum 6 characters)
5. Click **Create User**
6. If auto-generated, **copy temporary password** (shown once)
7. Share credentials securely with user

**Editing Users:**

1. Go to **User Management** → **All Users**
2. Search/filter to find user
3. Click **Edit** on user row
4. Modify:
   - Basic details (name, email)
   - Role (with confirmation)
   - College/Department
   - Status (Active/Suspended)
5. Click **Save Changes**

**Suspending Users:**

When necessary:
1. Find user in **User Management**
2. Click **Suspend**
3. Enter **Suspension Reason**:
   - Disciplinary action
   - Leave of absence
   - Role change pending
   - Other (specify)
4. Confirm suspension

**Effect:**
- User cannot log in
- Existing sessions terminated
- Assignments remain visible
- Can be reactivated later

### Managing Colleges & Departments

**Adding College:**

1. Go to **Admin Panel** → **Colleges**
2. Click **Add College**
3. Enter:
   - **College Name**
   - **College Code** (unique)
   - **Address**
   - **Contact Email**
   - **Contact Phone**
4. Click **Save**

**Adding Department:**

1. Go to **Admin Panel** → **Departments**
2. Click **Add Department**
3. Select **College**
4. Enter **Department Name**
5. Click **Save**

### System Configuration

**Email Settings:**

1. Go to **Settings** → **Email Configuration**
2. Configure:
   - SMTP Server
   - SMTP Port
   - Username/Password
   - From Name
   - From Email
3. Click **Test Connection**
4. If successful, **Save Settings**

**Notification Settings:**

1. Go to **Settings** → **Notifications**
2. Enable/disable notification types:
   - Exam approvals
   - Assignment invitations
   - Question paper updates
   - Rating submissions
   - System announcements
3. Set notification frequency:
   - Immediate
   - Daily digest
   - Weekly summary
4. Save settings

**Session Settings:**

1. Go to **Settings** → **Session Management**
2. Configure:
   - **Session Timeout:** Minutes of inactivity
   - **Remember Me Duration:** Days
   - **Force Logout:** After hours
3. Save settings

### Database Backup & Maintenance

**Manual Backup:**

1. Go to **Admin Panel** → **Database**
2. Click **Create Backup**
3. Select backup type:
   - **Full Backup:** All data
   - **Incremental:** Changes since last backup
4. Click **Start Backup**
5. Download backup file when complete
6. Store securely off-site

**Scheduled Backups:**

1. Go to **Database** → **Backup Schedule**
2. Configure:
   - Frequency (Daily/Weekly/Monthly)
   - Time of day
   - Retention period (days)
   - Storage location
3. Enable auto-backup
4. Test schedule

**Performance Optimization:**

1. Go to **Database** → **Optimization**
2. View current performance metrics:
   - Query execution times
   - Index usage
   - Table sizes
3. Run optimization scripts:
   - **Analyze Tables:** Update statistics
   - **Optimize Tables:** Defragment
   - **Add Indexes:** Improve query speed
4. Click **Run Optimization**
5. Review improvement report

**To apply database indexes:**
```bash
# Via phpMyAdmin or MySQL command line
mysql -u username -p database_name < db/optimize_performance.sql
```

### Audit Trail Management

**Viewing Audit Logs:**

1. Go to **Admin Panel** → **Audit Logs**
2. Filter by:
   - **User:** Specific user actions
   - **Action Type:** Create/Update/Delete/Approve
   - **Entity Type:** Exam/User/Assignment
   - **Date Range:** Specific period
3. View detailed logs:
   - Timestamp
   - User who performed action
   - Action taken
   - Entity affected
   - Before/after values
   - IP address

**Exporting Logs:**

1. Apply desired filters
2. Click **Export**
3. Choose format (CSV/PDF)
4. Download report

**Log Retention:**

- Logs retained for 2 years (default)
- Configure in **Settings** → **Audit Logs**
- Old logs archived automatically
- Critical actions never deleted

---

## Common Tasks

### Changing Your Password

1. Click your **Profile** icon (top-right)
2. Select **Change Password**
3. Enter:
   - Current password
   - New password (minimum 6 characters)
   - Confirm new password
4. Click **Update Password**
5. You'll be logged out; log in with new password

**Password Requirements:**
- Minimum 6 characters
- Mix of letters and numbers (recommended)
- Avoid common passwords

### Updating Your Profile

1. Click **Profile** → **Edit Profile**
2. Update:
   - Contact phone
   - Department (if applicable)
   - Specialization/Subject areas
   - Availability preferences
3. Upload profile photo (optional)
4. Click **Save Changes**

### Managing Notifications

**Viewing Notifications:**

1. Click **Notification Bell** icon (top-right)
2. Badge shows unread count
3. Dropdown shows recent notifications
4. Click **View All** for complete list

**Notification Actions:**

- **Click notification:** Marks as read, navigates to related item
- **Mark as Read:** Click checkmark icon
- **Delete:** Click trash icon
- **Mark All Read:** Button at top of list

**Notification Preferences:**

1. Go to **Profile** → **Notification Settings**
2. Enable/disable:
   - Browser notifications
   - Email notifications
   - SMS notifications (if available)
3. Set frequency:
   - Immediate
   - Daily digest
   - Weekly summary
4. Save preferences

### Searching & Filtering

**Global Search:**

1. Use **Search Bar** at top of any page
2. Enter keywords:
   - Exam names
   - User names
   - Subjects
   - Colleges
3. Press Enter or click **Search**
4. Results categorized by:
   - Exams
   - Users
   - Assignments
   - Documents

**Advanced Filters:**

Most list pages have filter panel:
1. Click **Filters** button
2. Apply multiple filters:
   - Date ranges
   - Status
   - Categories
   - Colleges/Departments
3. Click **Apply Filters**
4. Click **Reset** to clear all filters

### Downloading Reports

**Available Reports:**

1. **Exam Schedule:** Complete timetable
2. **Assignment Report:** Your duties
3. **Rating Report:** Performance feedback
4. **Attendance Report:** Practical exam attendance

**To Download:**

1. Navigate to respective section
2. Click **Download** or **Export** button
3. Choose format (PDF/Excel/CSV)
4. File downloads automatically
5. Open with appropriate application

---

## FAQs

### General Questions

**Q: How do I recover my password?**  
A: Click "Forgot Password" on login page, enter your email, and follow instructions in the reset email.

**Q: Who can see my profile information?**  
A: Basic info (name, college, department) is visible to users in your college and system coordinators. Contact details are private.

**Q: How long do sessions last?**  
A: Sessions expire after 30 minutes of inactivity. Select "Remember Me" at login for extended sessions (7 days).

**Q: Can I access EEMS on mobile devices?**  
A: Yes! EEMS is responsive and works on phones/tablets. Use Chrome, Safari, or Firefox for best experience.

### Exam-Related Questions

**Q: How far in advance should exams be created?**  
A: Minimum 14 days recommended for proper examiner assignment and preparation.

**Q: Can I edit an exam after creation?**  
A: HODs/Principals can edit exams in "pending" status. After approval, only Admin can modify.

**Q: What happens if an examiner rejects an assignment?**  
A: HOD/VP receives notification and can assign another examiner. Original examiner's rejection reason is recorded.

**Q: How many examiners can be assigned to one exam?**  
A: No strict limit. Typically 2-4: Chief Examiner, External Examiner(s), Moderator, and Invigilators as needed.

### Question Paper Questions

**Q: What file formats are accepted for question papers?**  
A: PDF only. Maximum 10 MB file size.

**Q: Can I update a question paper after uploading?**  
A: Yes, until Principal locks it. After locking, Principal must unlock first (usually post-exam only).

**Q: How is question paper version determined?**  
A: You enter version number when uploading. Use 1.0 for initial, 1.1/1.2 for revisions, 2.0 for major changes.

**Q: Who can download question papers?**  
A: Assigned examiners, HOD, Principal, VP, and Admin. Privacy-enforced for other colleges.

### Rating Questions

**Q: Who can rate examiners?**  
A: HODs and Principals can rate examiners assigned to exams from their college.

**Q: Are ratings anonymous?**  
A: No. Rater name is recorded but only visible to VP/Admin. Examiner sees average rating and comments only.

**Q: When should ratings be submitted?**  
A: Within 7 days after exam completion. Late ratings may not be accepted.

**Q: Can ratings be edited after submission?**  
A: No. Ratings are final once submitted. Contact Admin if correction is needed.

### Technical Questions

**Q: Which browsers are supported?**  
A: Chrome 90+, Firefox 88+, Safari 14+, Edge 90+. IE not supported.

**Q: Why is my session expiring frequently?**  
A: Check browser settings - cookies must be enabled. Avoid "Incognito/Private" mode for persistent sessions.

**Q: Can I use EEMS offline?**  
A: No. Internet connection required for all operations.

**Q: How do I report a bug?**  
A: Click **Help** → **Report Issue**, describe problem, attach screenshots if possible.

### Privacy & Security Questions

**Q: Who can see exams from other colleges?**  
A: Only Vice-Principals and Admins (coordinators). Other roles see own college only, plus exams they're assigned to as external examiners.

**Q: Is my data secure?**  
A: Yes. All connections use HTTPS. Passwords are encrypted. Regular security audits performed.

**Q: Can I delete my account?**  
A: Contact Admin. Account deactivation preferred over deletion to maintain audit trails.

---

## Troubleshooting

### Login Issues

**Problem:** Cannot log in  
**Solutions:**
- Verify email/password (case-sensitive)
- Click "Forgot Password" to reset
- Clear browser cache and cookies
- Try different browser
- Contact Admin if account is suspended

### Page Loading Issues

**Problem:** Pages load slowly or not at all  
**Solutions:**
- Check internet connection
- Clear browser cache (Ctrl+F5)
- Disable browser extensions temporarily
- Try incognito/private mode
- Check with IT if server is down

### Upload Issues

**Problem:** Cannot upload question paper  
**Solutions:**
- Check file is PDF format
- Verify file size < 10 MB
- Ensure stable internet connection
- Try different browser
- Compress PDF if too large

### Notification Issues

**Problem:** Not receiving notifications  
**Solutions:**
- Check notification settings in profile
- Verify email address is correct
- Check spam/junk folder
- Enable browser notifications
- Contact Admin to verify email settings

---

## Contact & Support

### Technical Support
- **Email:** support@eems.edu
- **Phone:** +1-800-EEMS-HELP
- **Hours:** Monday-Friday, 9 AM - 5 PM

### Training Resources
- **Video Tutorials:** https://eems.edu/tutorials
- **User Forum:** https://forum.eems.edu
- **Knowledge Base:** https://help.eems.edu

### Feedback
We welcome your suggestions! Email feedback@eems.edu or use the **Feedback** button in the application.

---

*User Manual v1.0 - For EEMS Version 1.0 - December 13, 2025*
