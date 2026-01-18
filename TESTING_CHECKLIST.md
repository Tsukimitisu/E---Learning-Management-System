# Student Enrollment System - Testing Checklist

## Pre-Testing Setup

- [ ] Database backup taken
- [ ] Access to test accounts:
  - [ ] School Admin account
  - [ ] Registrar account
  - [ ] Branch Admin account
  - [ ] Teacher account
- [ ] Test environment verified
- [ ] Browser console open (to check for JS errors)

---

## Phase 1: School Admin - Program Creation

### Create College Program
- [ ] Log in as School Admin
- [ ] Navigate to: Curriculum Management → College Curriculum
- [ ] Create new program:
  - [ ] Program Code: `BSIT`
  - [ ] Program Name: `Bachelor of Science in Information Technology`
  - [ ] Mark as Active
  - [ ] Save successfully
- [ ] Add Year Levels to program:
  - [ ] Year Level 1: `1st Year`
  - [ ] Year Level 2: `2nd Year`
  - [ ] Year Level 3: `3rd Year`
  - [ ] Year Level 4: `4th Year`
  - [ ] All saved successfully
- [ ] Add Subjects to at least 1st Year (for validation)

### Create SHS Strand
- [ ] Log in as School Admin
- [ ] Navigate to: Curriculum Management → SHS Curriculum
- [ ] Create new strand:
  - [ ] Strand Code: `STEM`
  - [ ] Strand Name: `Science, Technology, Engineering, Mathematics`
  - [ ] Mark as Active
  - [ ] Save successfully
- [ ] Add Grade Levels to strand:
  - [ ] Grade Level 11: `Grade 11`
  - [ ] Grade Level 12: `Grade 12`
  - [ ] All saved successfully
- [ ] Add Subjects to at least Grade 11

---

## Phase 2: Registrar - Student Creation

### Verify Programs Display Correctly
- [ ] Log in as Registrar
- [ ] Navigate to: Student Management → Add Student
- [ ] In modal:
  - [ ] "Program Type" dropdown exists
  - [ ] "College" option appears
  - [ ] "SHS" option appears
- [ ] Select "College":
  - [ ] Program dropdown populates
  - [ ] `BSIT` appears in list ✓
  - [ ] Year Level dropdown populates
  - [ ] `1st Year`, `2nd Year` etc. appear ✓
- [ ] Select "SHS":
  - [ ] Program dropdown hides
  - [ ] SHS Strand dropdown appears
  - [ ] `STEM` appears in list ✓
  - [ ] Grade Level dropdown populates
  - [ ] `Grade 11`, `Grade 12` appear ✓

### Create College Student
- [ ] Fill form:
  - [ ] First Name: `Juan`
  - [ ] Last Name: `Dela Cruz`
  - [ ] Email: `juan@test.com` (unique)
  - [ ] Contact: `09123456789`
  - [ ] Address: `Manila`
  - [ ] Program Type: `College`
  - [ ] Program: `BSIT`
  - [ ] Year Level: `1st Year`
  - [ ] Password: `student123`
- [ ] Submit form
- [ ] Success message appears: ✓
  - [ ] "Student account created successfully"
  - [ ] Mentions "Branch Admin will assign..."
- [ ] Student number displayed/generated
- [ ] Page reloads and shows new student in list

### Create SHS Student
- [ ] Fill form:
  - [ ] First Name: `Maria`
  - [ ] Last Name: `Santos`
  - [ ] Email: `maria@test.com` (unique)
  - [ ] Contact: `09987654321`
  - [ ] Address: `Quezon City`
  - [ ] Program Type: `SHS`
  - [ ] SHS Strand: `STEM`
  - [ ] Grade Level: `Grade 11`
  - [ ] Password: `student123`
- [ ] Submit form
- [ ] Success message appears ✓
- [ ] Student number generated ✓
- [ ] New student appears in list ✓

### Verify Student List Display
- [ ] Go to: All Students
- [ ] Both new students appear:
  - [ ] Juan Dela Cruz - `STU-2025-00001` (or similar)
  - [ ] Maria Santos - `STU-2025-00002`
- [ ] Student information displays:
  - [ ] Full name ✓
  - [ ] Student number ✓
  - [ ] Email ✓
  - [ ] Course/Program information ✓
  - [ ] Status (Active) ✓

### Database Verification
```sql
-- Verify students table
SELECT * FROM students WHERE student_no LIKE 'STU-2025%';
-- Should show:
-- Juan: course_id = 1 (BSIT program id)
-- Maria: course_id = X (STEM strand id)

-- Verify user accounts
SELECT u.id, u.email, ur.role_id, up.first_name 
FROM users u
INNER JOIN user_profiles up ON u.id = up.user_id
INNER JOIN user_roles ur ON u.id = ur.user_id
WHERE u.email IN ('juan@test.com', 'maria@test.com');
-- Should show both users with role_id = 6 (STUDENT)
```

---

## Phase 3: Branch Admin - Student Assignment

### Access Student Assignment
- [ ] Log in as Branch Admin
- [ ] Navigate to: Student Section Assignment
- [ ] Page loads successfully
- [ ] No errors in console
- [ ] Student list displays

### Verify Student Display
- [ ] Both created students appear:
  - [ ] Juan Dela Cruz
  - [ ] Maria Santos
- [ ] Student information shows:
  - [ ] First name ✓
  - [ ] Last name ✓
  - [ ] Student number ✓
  - [ ] Program code (BSIT for Juan, STEM for Maria) ✓
  - [ ] Section count (0 for new students) ✓
- [ ] Search functionality works:
  - [ ] Search "Juan" → Only Juan appears ✓
  - [ ] Search "2025-0001" → Juan appears ✓
  - [ ] Clear search → All students reappear ✓

### Individual Section Assignment
- [ ] Select Juan from list
- [ ] Right panel shows assignment options
- [ ] Select a section (filter by 1st Year if available)
- [ ] Confirm assignment
- [ ] Success message appears ✓
- [ ] Juan no longer shows in list (assigned) ✓
- [ ] Section count updates (now shows 1 section) ✓

### Bulk Section Assignment
- [ ] Go to: Bulk Assign to Sections
- [ ] Page loads successfully
- [ ] Select a section:
  - [ ] Filter by: 1st Year (for Juan) OR Grade 11 (for Maria)
  - [ ] Choose appropriate section
  - [ ] "Get Unenrolled Students" button works ✓
- [ ] Student list appears:
  - [ ] Shows unenrolled students for that section
  - [ ] Shows program/strand info
  - [ ] Respects capacity limits (if set)
- [ ] Select student (Maria)
- [ ] Bulk assign
- [ ] Success message: "X students assigned"
- [ ] Maria now appears as assigned ✓

---

## Phase 4: Teacher - Roster Verification

### Verify Class Roster
- [ ] Log in as Teacher
- [ ] Navigate to: Class Records or Attendance
- [ ] Select the section that Juan and Maria were assigned to
- [ ] Class roster displays:
  - [ ] Juan Dela Cruz appears ✓
  - [ ] Maria Santos appears ✓
  - [ ] Both show correct student numbers ✓
  - [ ] Can take attendance ✓
  - [ ] Can input grades ✓

---

## Phase 5: API Validation

### Test getUnenrolledStudents API
```
GET /modules/branch_admin/process/student_assignment_api.php?action=getUnenrolledStudents&section_id=1
```
- [ ] Returns JSON response
- [ ] Status: success
- [ ] Students array contains unenrolled students
- [ ] Each student has: id, first_name, last_name, student_no
- [ ] Assigned students NOT in list
- [ ] No database errors

### Test bulkAssignToSection API
```
POST /modules/branch_admin/process/student_assignment_api.php
Parameters:
  action = bulkAssignToSection
  section_id = 1
  student_ids[] = [200, 201]
```
- [ ] Returns JSON response
- [ ] Status: success
- [ ] Message: "X students assigned"
- [ ] Records created in section_students table
- [ ] Capacity limit respected (if tested)
- [ ] Audit log created

### Test create_student API
```
POST /modules/registrar/process/create_student.php
Parameters:
  first_name = Test
  last_name = Student
  email = test@test.com
  program_type = college
  course_id = 1
  password = student123
```
- [ ] Returns JSON response
- [ ] Status: success
- [ ] Student record created
- [ ] user_profiles entry created
- [ ] user_roles entry created (STUDENT)
- [ ] course_id populated correctly
- [ ] Student number auto-generated
- [ ] Audit log created

---

## Phase 6: Error Scenarios

### Test Validation
- [ ] Try creating student without program:
  - [ ] Error message appears ✓
  - [ ] "Please select a program" ✓
- [ ] Try duplicate email:
  - [ ] Error message appears ✓
  - [ ] "Email already exists" ✓
- [ ] Try invalid email format:
  - [ ] Error message appears ✓
  - [ ] "Invalid email format" ✓
- [ ] Try assigning over capacity:
  - [ ] Error message appears ✓
  - [ ] "Section capacity exceeded" ✓
- [ ] Try assigning already-assigned student:
  - [ ] Not in list (filtered out) OR
  - [ ] Error message appears ✓

### Test Database Edge Cases
- [ ] Create student, then manually delete from students table
  - [ ] User account still exists
  - [ ] Can't assign to section without student record
- [ ] Create student with NULL course_id
  - [ ] Student account created ✓
  - [ ] Shows "No Program" in list ✓
  - [ ] Can still be assigned to section ✓
- [ ] Manually update course_id
  - [ ] List reflects change immediately (on refresh) ✓

---

## Phase 7: Data Integrity

### Cross-Table Verification
```sql
-- Check all students have users
SELECT s.student_no FROM students s
LEFT JOIN users u ON s.user_id = u.id
WHERE u.id IS NULL;
-- Should be empty

-- Check all students have profiles
SELECT s.student_no FROM students s
LEFT JOIN user_profiles up ON s.user_id = up.user_id
WHERE up.user_id IS NULL;
-- Should be empty

-- Check all students have roles
SELECT s.student_no FROM students s
LEFT JOIN user_roles ur ON s.user_id = ur.user_id
WHERE ur.user_id IS NULL;
-- Should be empty
```

### Program References
```sql
-- Check course_id references valid programs
SELECT s.student_no, s.course_id 
FROM students s
WHERE course_id IS NOT NULL
AND course_id NOT IN (
    SELECT id FROM programs 
    UNION 
    SELECT id FROM shs_strands
);
-- Should be empty (all references valid)
```

### Section References
```sql
-- Check section_students references valid students
SELECT ss.id FROM section_students ss
LEFT JOIN students s ON ss.student_id = s.user_id
WHERE s.user_id IS NULL;
-- Should be empty
```

---

## Performance Testing

- [ ] Load All Students list with:
  - [ ] 100 students - loads in < 2 seconds
  - [ ] 1000 students - loads in < 5 seconds
- [ ] Filter/search performance:
  - [ ] Search returns results immediately
  - [ ] No UI lag
- [ ] Bulk assignment of:
  - [ ] 10 students - completes instantly
  - [ ] 50 students - completes in < 2 seconds
  - [ ] 100 students - completes in < 5 seconds

---

## Security Testing

- [ ] Registrar cannot create students with high privileges
- [ ] Branch Admin cannot create students (no create API access)
- [ ] Students cannot modify enrollment
- [ ] Cannot view other branch's students (if multi-branch):
  - [ ] Registrar only sees branch's programs
  - [ ] Branch Admin only sees branch's sections
- [ ] Audit logs created for all actions
- [ ] Password hashing verified (not plaintext)
- [ ] SQL injection attempts blocked:
  - [ ] Email: `admin@test.com'); DROP TABLE--`
  - [ ] Name: `<script>alert('XSS')</script>`

---

## Rollback Plan

If issues arise:

1. [ ] Database backup exists
2. [ ] Can restore from backup
3. [ ] Known good state documented
4. [ ] Rollback procedure tested (off-peak hours)

---

## Sign-Off

### Tested By
- Name: ___________________
- Date: ___________________
- Time Spent: ___________________

### Issues Found
| Issue | Severity | Resolution | Status |
|-------|----------|-----------|--------|
| | | | |
| | | | |

### Final Status
- [ ] All tests PASSED ✅
- [ ] Ready for production deployment
- [ ] Known issues documented (if any)
- [ ] Stakeholders notified

---

## Post-Implementation

- [ ] Staff trained on new workflow
- [ ] Documentation shared with team
- [ ] Help desk briefed on common issues
- [ ] Monitor logs for first 24 hours
- [ ] Collect user feedback
- [ ] Schedule follow-up training if needed

---

**Approved for Production:** ________________  
**Date:** ________________  
**Signature:** ________________
