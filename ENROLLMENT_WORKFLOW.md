# Student Enrollment Workflow - System Architecture

## Overview
The ELMS system now features a complete three-level enrollment workflow that connects all roles together:

### Workflow Hierarchy:
1. **Registrar** → Enroll students in Programs and Year Levels
2. **Branch Admin** → Assign students to specific Sections  
3. **Teacher** → Manage class attendance and grades

---

## Role-Based Responsibilities

### 1. REGISTRAR - Program Enrollment Manager
**Location:** `modules/registrar/program_enrollment.php`

#### Responsibilities:
- ✅ Enroll students in College Programs or SHS Strands
- ✅ Assign students to appropriate Year Levels
- ✅ Bulk enroll multiple students to programs
- ✅ Record student payments
- ✅ Manage class-level enrollments (fallback option)

#### Key Features:
- **Filter by:**
  - Students with/without programs
  - Students with/without section assignments
  - Search by name or student number

- **Statistics Dashboard:**
  - Total students in branch
  - Students with programs (enrolled)
  - Students without programs
  - Students assigned to sections

- **Bulk Operations:**
  - Bulk Program Enrollment
  - Select students without programs
  - Enroll to program + year level in one action

#### Database Tables Updated:
- `students` table:
  - `course_id` → Program/Strand ID
  - `year_level` → Year level number
  - `enrollment_status` → 'enrolled'

---

### 2. BRANCH ADMIN - Section Assignment Manager
**Locations:**
- Individual: `modules/branch_admin/student_assignment.php`
- Bulk: `modules/branch_admin/bulk_assign_sections.php`

#### Responsibilities:
- ✅ Assign students to specific Sections
- ✅ Filter students by program enrollment status
- ✅ View section capacity and availability
- ✅ Bulk assign students to sections
- ✅ Manage teacher assignments
- ✅ Monitor branch-wide compliance

#### Key Features:

**Individual Assignment:**
- Select student → Choose program → Pick section
- View current enrollments
- Enroll/Unenroll with capacity checks
- See section details (teacher, schedule, room)

**Bulk Assignment:**
- Select section first (shows capacity)
- Filter unenrolled students
- Auto-limit selection to available slots
- Bulk enroll to single section

#### Database Tables Updated:
- `section_students` table:
  - `student_id`, `section_id`, `status`, `enrolled_at`

#### Important Validations:
- ✓ Student must have program enrollment first
- ✓ Cannot exceed section capacity
- ✓ Prevents duplicate enrollments
- ✓ Validates branch ownership

---

### 3. TEACHER - Class Management
**Location:** `modules/teacher/`

#### Responsibilities:
- ✅ View assigned sections and students
- ✅ Take attendance
- ✅ Input grades
- ✅ Post materials
- ✅ Create assessments

#### Visibility:
- Can see only students assigned to their sections
- Data filtered by assigned classes

---

## Database Schema Relationships

```
users (u)
  ├── user_profiles (up)
  │   └── branch_id → branches
  ├── students (st)
  │   ├── course_id → programs OR shs_strands
  │   ├── year_level → year level number
  │   └── enrollment_status
  ├── user_roles (ur)
  │   └── role_id (REGISTRAR=3, BRANCH_ADMIN=2, TEACHER=4, etc.)
  └── section_students (ss)
      ├── section_id → sections
      └── status ('active', 'removed')

sections (s)
  ├── program_id → programs (college)
  ├── shs_strand_id → shs_strands (secondary)
  ├── year_level_id → program_year_levels (college)
  ├── shs_grade_level_id → shs_grade_levels (secondary)
  └── max_capacity, current_enrolled

classes (cl)
  ├── curriculum_subject_id → curriculum_subjects
  ├── section_id → sections
  ├── teacher_id → users
  └── branch_id → branches
```

---

## API Endpoints

### Registrar APIs
**File:** `modules/registrar/process/program_enrollment_api.php`

```php
POST enroll_program
  - student_id, program_type, program_id, year_level_id, year_level

POST bulk_enroll_program
  - program_type, program_id, year_level_id, student_ids[]

GET get_student_info
  - student_id
```

### Branch Admin APIs
**File:** `modules/branch_admin/process/student_assignment_api.php`

```php
GET get_available_sections
  - student_id

GET get_student_enrollments
  - student_id

POST enroll
  - student_id, section_id

POST unenroll
  - student_id, section_id

POST bulk_enroll
  - section_id, student_ids[]

GET get_bulk_unenrolled_students
  - section_id, filter (no_program|with_program)

POST bulk_assign_to_section
  - section_id, student_ids[]
```

---

## Step-by-Step Workflow Example

### Scenario: Enroll 50 new freshmen in BSIT, Year 1

#### Step 1: Registrar - Program Enrollment
1. Go to `Program Enrollment` page
2. Select program type: "College Programs"
3. Choose program: "BSIT - Bachelor of Science in Information Technology"
4. Select year level: "1st Year"
5. Click "Bulk Enroll"
6. Select all 50 students in modal
7. Submit → Students' `course_id` set to BSIT program ID, `year_level` = 1

**Status:** Students now enrolled in program ✓

#### Step 2: Branch Admin - Section Assignment
1. Go to `Bulk Assign to Sections`
2. Select a section: "CS-1A Section 1 (20 slots available)"
3. Filter: "With Program Enrollment"
4. Select up to 20 students
5. Submit → Creates `section_students` records

**Status:** Students assigned to sections ✓

**Note:** Registrar can also assign students to classes (class enrollment) if using the old workflow via `Class Enrollment` page.

#### Step 3: Teacher
1. Goes to assigned classes
2. Sees list of enrolled students
3. Takes attendance, inputs grades

**Status:** Complete workflow ✓

---

## Key Improvements Made

### ✅ Fixed Issues:
1. **Teachers table display** - Now shows all teachers by branch, not just those with assignments
2. **Subject display in teacher assignment** - Fixed semester format mismatch (1st/1, 2nd/2)
3. **Connected workflow** - Registrar → Branch Admin → Teacher

### ✅ New Features:
1. **Program Enrollment page** for registrar with bulk enrollment
2. **Bulk Assign Sections page** for branch admin
3. **Program enrollment tracking** in students table
4. **Enhanced filtering** on all enrollment pages
5. **Capacity management** with real-time slot tracking
6. **Statistics dashboard** showing enrollment progress

### ✅ Validations:
- Students must be enrolled in program before section assignment
- Cannot assign without program enrollment
- Capacity limits enforced at all levels
- Branch isolation maintained
- Audit logging for all major operations

---

## Navigation Links

### Registrar Dashboard
- **Program Enrollment** → Enroll students in programs
- **Class Enrollment** → Fallback direct class enrollment
- **Record Payment** → Link payment to student

### Branch Admin Dashboard  
- **Assign Students to Sections** → Individual assignment
- **Bulk Assign to Sections** → Bulk assignment
- **Manage Teachers** → Subject assignment
- **Manage Students** → Student information

---

## Important Notes

1. **Program First:** Students MUST have program enrollment before section assignment
2. **Registrar Role:** Primary entry point for all student enrollments
3. **Branch Admin Role:** Controls section distribution and capacity
4. **Data Consistency:** All operations maintain referential integrity
5. **Audit Trail:** Major actions logged in `audit_logs` table

---

## Testing Checklist

- [ ] Registrar can enroll student in program
- [ ] Registrar can bulk enroll students
- [ ] Branch admin sees students with programs
- [ ] Branch admin can assign to sections
- [ ] Bulk assign respects capacity limits
- [ ] Students without programs show in registrar interface
- [ ] Section capacity tracking works
- [ ] Teachers can see their students
- [ ] Attendance/grades work for enrolled students
- [ ] All filters work correctly

---

**Last Updated:** January 19, 2026
**System Version:** ELMS v1.0+
**Database:** MySQL 5.7+
