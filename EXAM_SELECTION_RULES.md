# Exam Selection Rules & Restrictions

## Overview
Comprehensive system to manage exam selection rules for teachers and prevent conflicts.

## 🔒 **Selection Rules Implemented**

### **Rule 1: One Exam Per Teacher**
**Restriction**: A teacher can only select **ONE exam** in total.

**Implementation**:
- ✅ Once a teacher selects any exam, all other exams are hidden from their dashboard
- ✅ "Available Exams" tab shows success message with link to "My Assignments"
- ✅ Cannot select additional exams through UI or API
- ✅ Server-side validation prevents multiple selections

**User Experience**:
```
Before Selection:
- Teacher sees all available exams
- Can browse and select any exam

After Selection:
- "Available Exams" tab shows: "You've Already Selected an Exam"
- Display shows: ✅ Assignment Complete
- Button: "View My Assignments"
- No more exams visible
```

### **Rule 2: One Faculty Per Exam Per College**
**Restriction**: Only **ONE faculty member from each college** can be assigned to a specific exam.

**Implementation**:
- ✅ If any teacher from College A selects Exam X, Exam X is hidden from all other teachers from College A
- ✅ Teachers from College B can still see and select Exam X
- ✅ Prevents duplicate assignments from the same institution
- ✅ Database-level filtering with EXISTS clause

**Example Scenario**:
```
Exam: "Advanced Thermodynamics - MIT"

College ABC:
- Teacher 1 selects the exam ✅ (Assignment created)
- Teacher 2 cannot see the exam ❌ (Filtered out)
- Teacher 3 cannot see the exam ❌ (Filtered out)

College XYZ:
- Teacher 4 can see the exam ✅ (Different college)
- Teacher 5 can see the exam ✅ (Different college)
```

## 📊 **Database Logic**

### Query Filter (Teacher Dashboard)
```sql
SELECT e.* 
FROM exams e
WHERE e.status = 'Approved'
  AND e.department != ? -- Not from teacher's own college
  AND e.exam_date >= CURDATE() -- Future exams only
  AND NOT EXISTS (
      -- Exclude if ANY faculty from same college already assigned
      SELECT 1 FROM assignments a
      INNER JOIN users u ON a.faculty_id = u.id
      WHERE a.exam_id = e.id 
      AND u.college_name = ?
  )
```

### Assignment Validation (AJAX Handler)
```php
// Check 1: Teacher already has ANY assignment?
SELECT COUNT(*) FROM assignments WHERE faculty_id = ?

// Check 2: Same college already assigned to this exam?
SELECT u.name 
FROM assignments a
INNER JOIN users u ON a.faculty_id = u.id
WHERE a.exam_id = ? AND u.college_name = ?
```

## 🎯 **User Flow**

### **Teacher Perspective**

#### **Step 1: First Login**
1. Complete profile (if not done)
2. Go to "Available Exams" tab
3. See all approved exams from other colleges
4. Each exam shows:
   - College name
   - Subject, date, description
   - Number of faculty assigned
   - "Select This Exam" button

#### **Step 2: Selecting an Exam**
1. Click "Select This Exam"
2. **Warning Prompt**: 
   ```
   ⚠️ IMPORTANT: You can only select ONE exam.
   
   Once you select this exam, you will NOT be able to 
   select any other exams.
   
   Do you want to proceed?
   ```
3. If confirmed:
   - Button shows: "Processing..."
   - AJAX validation runs
   - Assignment created
   - Success message: "Successfully selected for exam! Redirecting..."
   - Page reloads

#### **Step 3: Post-Selection**
1. "Available Exams" tab now shows:
   ```
   ✅ You've Already Selected an Exam
   
   You have been assigned to an exam. Check your 
   "My Assignments" tab to view details.
   ```
2. Large icon and message: "Assignment Complete"
3. Button: "View My Assignments"
4. **No exams visible** - prevents confusion

### **Other Teachers from Same College**
1. Login to dashboard
2. Go to "Available Exams"
3. **Do not see** the exam selected by their colleague
4. See all other exams (that no one from their college selected)
5. Can select from remaining exams

## ⚠️ **Validation Layers**

### **Layer 1: Frontend UI**
- Hide all exams if teacher has assignment (`$hasExistingAssignment`)
- Show success message instead of exam list
- Disable selection buttons

### **Layer 2: Database Query**
- Filter out exams with assignments from same college
- SQL EXISTS clause prevents showing restricted exams
- Efficient database-level filtering

### **Layer 3: AJAX Validation**
```php
1. Check teacher has no existing assignments ✓
2. Check same college not assigned to this exam ✓
3. Check not already assigned to this specific exam ✓
4. If all pass → Create assignment ✓
5. If any fail → Return error message ✓
```

### **Layer 4: User Confirmation**
- JavaScript confirm dialog with clear warning
- Prevents accidental selections
- Emphasizes one-time selection rule

## 📱 **Error Messages**

### **Already Has Assignment**
```
❌ You have already selected an exam. 
You cannot select multiple exams.
```

### **College Already Assigned**
```
❌ A faculty member from your college (Dr. John Doe) 
has already been assigned to this exam.
```

### **Already Assigned to This Exam**
```
❌ You are already assigned to this exam
```

### **Network Error**
```
❌ Network error. Please try again.
```

## 🔍 **Where Rules Are Enforced**

### **Teacher Dashboard** (`teacher_dashboard.php`)
- Lines 73-105: Query with college-based filtering
- Lines 29-68: AJAX handler with multiple validations
- Lines 332-361: UI hiding with hasExistingAssignment check
- Lines 490-525: JavaScript confirmation and validation

### **Assignment Widget** (`includes/assignment_widget.php`)
- Shows all assignments from department
- Displays "Self-selected" vs "Nominated by [Name]"
- Helps HOD track who selected what

### **Exam Details Page** (`view_exam_details.php`)
- Shows all faculty assigned to exam
- Displays college of each faculty
- Prevents duplicate display if properly filtered

## ✅ **Benefits**

### **For Teachers**
1. **Clear Rules**: Know exactly what they can and cannot do
2. **No Confusion**: Once selected, dashboard shows clear status
3. **Fair Distribution**: Can't "hog" multiple exams
4. **Easy Navigation**: Direct link to assignments after selection

### **For Colleges**
1. **No Duplicates**: Only one faculty per exam per college
2. **Fair Representation**: Each college gets one slot per exam
3. **Clear Tracking**: See which teacher selected which exam
4. **Prevents Conflicts**: Avoid scheduling issues

### **For System Admins**
1. **Data Integrity**: Database enforces rules
2. **Clean Data**: No orphaned or duplicate assignments
3. **Easy Auditing**: Clear assignment trail
4. **Scalable**: Rules work for any number of colleges/exams

## 🧪 **Testing Scenarios**

### **Scenario 1: Single Teacher Selection**
```
✅ Teacher A from College X
   → Selects Exam 1
   → Assignment created
   → Available Exams tab shows "Already Selected"
   → Cannot see any other exams
   ✅ PASS
```

### **Scenario 2: Multiple Teachers, Same College**
```
✅ Teacher A from College X → Selects Exam 1
✅ Teacher B from College X → Cannot see Exam 1
✅ Teacher B → Can see Exam 2, 3, 4
✅ Teacher B → Selects Exam 2
✅ Teacher C from College X → Cannot see Exam 1 or Exam 2
✅ PASS
```

### **Scenario 3: Multiple Teachers, Different Colleges**
```
✅ Teacher A from College X → Selects Exam 1
✅ Teacher B from College Y → Can still see Exam 1
✅ Teacher B from College Y → Selects Exam 1
✅ Both assigned to same exam, different colleges
✅ PASS
```

### **Scenario 4: Attempt Multiple Selections**
```
✅ Teacher A → Selects Exam 1 (Success)
❌ Teacher A → Tries to select Exam 2 (Blocked by UI)
❌ Teacher A → Tries via API (Blocked by validation)
✅ Error: "You have already selected an exam"
✅ PASS
```

## 📋 **Summary**

| Rule | Description | Enforcement |
|------|-------------|-------------|
| **One exam per teacher** | Each teacher can only select ONE exam total | UI + API + DB |
| **One faculty per exam per college** | Only one teacher from each college per exam | Query filter + API |
| **No duplicate selections** | Prevent same teacher selecting same exam twice | API validation |
| **Self-selection tracking** | Track who selected (self vs nominated) | assigned_by field |
| **Clear feedback** | Users know why they can't see exams | UI messages |

---

**System Status**: ✅ FULLY OPERATIONAL

All selection rules are enforced at multiple layers for maximum security and user clarity!
