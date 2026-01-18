# Student Enrollment System - Quick Reference Guide

## Role Responsibilities

### 🏫 School Admin
```
RESPONSIBILITY: Curriculum Management
├─ Create College Programs
│  └─ Add Year Levels (1st, 2nd, 3rd, 4th)
│     └─ Add Subjects per Year Level
├─ Create SHS Strands
│  └─ Add Grade Levels (11, 12)
│     └─ Add Subjects per Grade Level
└─ Manage all curriculum aspects
```

**Used By:** Registrar when adding students

---

### 📋 Registrar  
```
RESPONSIBILITY: Student Enrollment
├─ Student Management
│  └─ Add New Student
│     ├─ Fill Personal Info (Name, Email, Contact)
│     ├─ SELECT Program/Strand (from School Admin's list)
│     ├─ SELECT Year Level/Grade Level
│     ├─ System auto-creates:
│     │  ├─ User Account (login: email)
│     │  ├─ User Profile (name, contact)
│     │  ├─ Student Record (student_no, course_id)
│     │  └─ Student Role Assignment
│     └─ Student Ready for Section Assignment
├─ Program Enrollment (bulk operations)
├─ Record Payments
├─ View Academic Records
└─ Generate Certificates
```

**Key Action:** "Add Student" → Automatically creates student record with program

**Output:** Student can now be assigned to sections by Branch Admin

---

### 👥 Branch Admin
```
RESPONSIBILITY: Student Section Assignment
├─ Student Section Assignment (Individual)
│  ├─ View all students (with programs)
│  ├─ Select student
│  ├─ Choose section (filtered by program)
│  └─ Confirm assignment
├─ Bulk Assign to Sections
│  ├─ Select section
│  ├─ Filter students (by program, status)
│  ├─ Select multiple students
│  ├─ Confirm bulk operation
│  └─ All students assigned at once
├─ Monitor section capacity
├─ Generate reports
└─ Manage students per section
```

**Key Action:** "Assign to Section" → Creates link between student and class

**Output:** Student now appears in teacher's roster

---

### 👨‍🏫 Teacher
```
RESPONSIBILITY: Teaching & Grading
├─ View Class Roster (populated by Branch Admin)
├─ Record Attendance
├─ Assign Grades
├─ Post Class Materials
├─ Create Assessments
└─ Communicate with Students
```

**Input:** Students assigned by Branch Admin

---

## Student Lifecycle

```
┌─────────────────────────────────────────────────────┐
│ 1. SCHOOL ADMIN CREATES PROGRAM                     │
│    • BS Computer Science (College)                  │
│    • OR STEM Strand (SHS)                           │
└──────────────┬──────────────────────────────────────┘
               │
┌──────────────▼──────────────────────────────────────┐
│ 2. REGISTRAR ADDS STUDENT                          │
│    • Name: Juan Dela Cruz                           │
│    • Email: juan@email.com                          │
│    • Program: BS Computer Science ← from step 1     │
│    • Year Level: 1st Year ← from step 1             │
│    ✓ Student created                                │
│    ✓ Auto student number: STU-2025-00001           │
│    ✓ Enrolled in program: BS Computer Science      │
└──────────────┬──────────────────────────────────────┘
               │
┌──────────────▼──────────────────────────────────────┐
│ 3. BRANCH ADMIN ASSIGNS TO SECTION                 │
│    • Student: Juan Dela Cruz                        │
│    • Program: BS Computer Science (confirmed)       │
│    • Section: BSCS-1A (1st year section)           │
│    • Teacher: Prof. Santos                          │
│    ✓ Assigned to section                            │
│    ✓ Added to class roster                          │
└──────────────┬──────────────────────────────────────┘
               │
┌──────────────▼──────────────────────────────────────┐
│ 4. TEACHER MANAGES STUDENT                         │
│    • Views Juan in class roster                     │
│    • Takes attendance                               │
│    • Posts grades                                   │
│    • Shares materials                               │
│    ✓ Student is active in class                     │
└─────────────────────────────────────────────────────┘
```

---

## Database Changes Summary

### Students Table
```sql
-- What's stored:
user_id         -- Links to the user account
student_no      -- Auto-generated: STU-2025-00001
course_id       -- Program ID (from programs) OR Strand ID (from shs_strands)
created_at      -- When student was created

-- Example Records:
┌────────┬──────────────────┬───────────┐
│ user_id│ student_no       │ course_id │
├────────┼──────────────────┼───────────┤
│ 200    │ STU-2025-0001    │ 1         │ ← College (program_id=1)
│ 201    │ STU-2025-0002    │ 1         │ ← College (program_id=1)
│ 202    │ STU-2025-0003    │ 3         │ ← SHS (strand_id=3)
└────────┴──────────────────┴───────────┘
```

### Program Enrollment
- Happens in **registrar/create_student.php**
- Automatically sets `course_id` when student is created
- No manual enrollment process needed

### Section Assignment
- Happens in **branch_admin/student_assignment_api.php**
- Inserts records into `section_students` table
- Happens AFTER student is created

---

## Key Queries

### Get All Students (for registrar view)
```sql
SELECT 
    u.id,
    st.student_no,
    CONCAT(up.first_name, ' ', up.last_name) as name,
    COALESCE(p.program_code, ss.strand_code) as program,
    u.status
FROM users u
INNER JOIN user_profiles up ON u.id = up.user_id
INNER JOIN user_roles ur ON u.id = ur.user_id
LEFT JOIN students st ON u.id = st.user_id
LEFT JOIN programs p ON st.course_id = p.id
LEFT JOIN shs_strands ss ON st.course_id = ss.id
WHERE ur.role_id = 6  -- ROLE_STUDENT
```

### Get Unenrolled Students (for branch admin)
```sql
SELECT 
    u.id,
    up.first_name,
    up.last_name,
    st.student_no,
    p.program_code
FROM users u
INNER JOIN user_profiles up ON u.id = up.user_id
LEFT JOIN students st ON u.id = st.user_id
LEFT JOIN programs p ON st.course_id = p.id
WHERE u.id NOT IN (
    SELECT student_id FROM section_students 
    WHERE section_id = ? AND status = 'active'
)
```

### Assign Student to Section
```sql
INSERT INTO section_students (section_id, student_id, status)
VALUES (?, ?, 'active')
```

---

## Common Tasks

### "Create a New College Student"
1. Go: Registrar → Student Management
2. Click: "Add Student" button
3. Fill:
   - First Name, Last Name, Email, Contact
   - Program Type: "College"
   - Program: Select from dropdown (these are from School Admin)
   - Year Level: Select year level
   - Password: student123
4. Click: "Create Student"
5. ✅ Done! Now branch admin can assign to section

---

### "Create a New SHS Student"
1. Go: Registrar → Student Management
2. Click: "Add Student" button
3. Fill:
   - First Name, Last Name, Email, Contact
   - Program Type: "SHS"
   - SHS Strand: Select from dropdown (from School Admin)
   - Grade Level: Select grade (11 or 12)
   - Password: student123
4. Click: "Create Student"
5. ✅ Done! Now branch admin can assign to section

---

### "Assign Student to Section"
**Individual Assignment:**
1. Go: Branch Admin → Student Section Assignment
2. Search/Select: Student from list
3. Choose: Section to assign to
4. Click: "Assign"
5. ✅ Done! Student added to class

**Bulk Assignment:**
1. Go: Branch Admin → Bulk Assign to Sections
2. Select: Section
3. Filter: Students (optional)
4. Check: Select multiple students
5. Click: "Assign All"
6. ✅ Done! All selected students added to class

---

### "Verify Student was Created"
**Method 1: Check Student List**
- Go: Registrar → All Students
- Should appear in list with auto-generated student number

**Method 2: Check in Database**
```sql
SELECT * FROM students WHERE student_no LIKE 'STU-2025%';
SELECT * FROM users WHERE email = 'juan@email.com';
SELECT * FROM user_profiles WHERE first_name = 'Juan';
```

---

### "Check Student Program Enrollment"
```sql
SELECT 
    st.student_no,
    CONCAT(up.first_name, ' ', up.last_name) as name,
    st.course_id,
    p.program_code,
    p.program_name
FROM students st
INNER JOIN user_profiles up ON st.user_id = up.user_id
LEFT JOIN programs p ON st.course_id = p.id
WHERE st.student_no = 'STU-2025-0001';
```

---

### "Check Student Section Assignment"
```sql
SELECT 
    st.student_no,
    CONCAT(up.first_name, ' ', up.last_name) as student_name,
    sec.section_code,
    sec.section_name,
    sec.academic_year_id
FROM section_students ss
INNER JOIN students st ON ss.student_id = st.user_id
INNER JOIN user_profiles up ON st.user_id = up.user_id
INNER JOIN sections sec ON ss.section_id = sec.id
WHERE st.student_no = 'STU-2025-0001';
```

---

## Troubleshooting

| Problem | Cause | Solution |
|---------|-------|----------|
| No programs in registrar form | School admin didn't create programs | Have school admin create programs |
| Student created but can't assign to section | Student not assigned to section yet | Use branch admin to assign |
| Student shows "no program" | course_id is NULL | Registrar must select program when creating |
| Can't create student - missing program error | Didn't select program in form | Select college program or SHS strand |
| Student not in teacher's roster | Student not assigned to section | Branch admin must assign student |
| Capacity limit exceeded | Section is full | Choose different section or increase capacity |

---

## File Locations

### For Registrar
- Student Management: `/modules/registrar/students.php`
- Program Enrollment: `/modules/registrar/program_enrollment.php`
- Create API: `/modules/registrar/process/create_student.php`

### For Branch Admin
- Student Assignment: `/modules/branch_admin/student_assignment.php`
- Bulk Assignment: `/modules/branch_admin/bulk_assign_sections.php`
- Assignment API: `/modules/branch_admin/process/student_assignment_api.php`

### Documentation
- Full Workflow: `/STUDENT_ENROLLMENT_WORKFLOW.md`
- Implementation Details: `/IMPLEMENTATION_SUMMARY.md`

---

## Success Criteria

✅ School Admin creates programs  
✅ Programs appear in Registrar's "Add Student" form  
✅ Registrar creates student with program selection  
✅ Student record created in database with auto-generated number  
✅ Student enrolls in program during account creation  
✅ Student appears in Branch Admin's student list  
✅ Branch Admin assigns student to section  
✅ Section capacity limits respected  
✅ Teacher sees student in class roster  
✅ Audit logs show all actions with timestamps  

---

**Last Updated:** January 19, 2026  
**System Status:** ✅ Production Ready
