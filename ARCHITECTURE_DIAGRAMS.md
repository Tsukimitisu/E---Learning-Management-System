# Student Enrollment System - Architecture Diagrams

## System Architecture Overview

```
┌─────────────────────────────────────────────────────────────────────┐
│                         ELMS SYSTEM                                 │
│                                                                     │
│  ┌──────────────────┐  ┌──────────────────┐  ┌──────────────────┐ │
│  │  SCHOOL ADMIN    │  │   REGISTRAR      │  │  BRANCH ADMIN    │ │
│  │                  │  │                  │  │                  │ │
│  │ • Create Program │→ │ • Add Student    │→ │ • Assign Section │ │
│  │ • Add Year Level │  │ • Select Program │  │ • Bulk Assign    │ │
│  │ • Add Subjects   │  │ • Record Enroll. │  │ • Verify Roster  │ │
│  └────────────────┬─┘  └────────┬─────────┘  └────────┬─────────┘ │
│                   │             │                      │            │
│         Curriculum│         Enrollment│        Assignment│           │
│                   │             │                      │            │
│                   └─────────────┴──────────────────────┘            │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

---

## Data Flow Diagram

```
┌─────────────────────────┐
│   SCHOOL ADMIN          │
│   Creates Programs      │
│   Programs Table        │
│   (BSIT, BS CSPE, etc)  │
└────────────┬────────────┘
             │
             │ Queries programs
             │ for dropdown
             ▼
┌─────────────────────────────────────────┐
│          REGISTRAR DASHBOARD            │
│   Student Management → Add Student      │
│                                         │
│   Form:                                 │
│   ├─ Name                               │
│   ├─ Email                              │
│   ├─ Program (from programs table) ◄─┐ │
│   └─ Year Level                     │ │
│                                     │ │
│   Upon Submit:                      │ │
│   ├─ Create users entry             │ │
│   ├─ Create user_profiles entry     │ │
│   ├─ Create students entry (course_id = program.id)
│   └─ Create user_roles entry        │ │
└────────────┬────────────────────────┼─┘
             │                        │
             │ Creates               │
             │ student records       Queries
             │ with course_id        programs
             ▼
┌─────────────────────────────────────────┐
│      DATABASE TABLES                    │
│                                         │
│  users:           users (id, email,... )
│                                         │
│  user_profiles:   user_profiles        │
│                   (user_id, first_name,
│                    last_name, branch_id)
│                                         │
│  students:        students             │
│  ├─ user_id ─────────────┐             │
│  ├─ student_no           │ References   │
│  └─ course_id ──────┬────┤ users       │
│                     │    │             │
│  user_roles:        │    └─ references │
│  (user_id, role_id) │      user_profile│
│                     │                  │
│  programs:          │   References     │
│  (id, code, name)   │   programs or    │
│                     └─► shs_strands    │
│                                         │
│  shs_strands:                           │
│  (id, code, name)                       │
└────────────┬────────────────────────────┘
             │
             │ Student now enrolled
             │ in program
             │ Ready for assignment
             ▼
┌──────────────────────────────────────────┐
│       BRANCH ADMIN DASHBOARD             │
│   Student Assignment                     │
│                                          │
│   ├─ View Students list (all active)     │
│   │  ├─ Name                             │
│   │  ├─ Student No                       │
│   │  ├─ Program (from course_id lookup) │
│   │  └─ Section Count                    │
│   │                                      │
│   ├─ Select Student                      │
│   ├─ Select Section                      │
│   └─ Confirm Assignment                  │
│                                          │
│   Upon Assignment:                       │
│   ├─ Verify capacity                     │
│   ├─ Create section_students record      │
│   ├─ Log audit trail                     │
│   └─ Success message                     │
└──────────────┬───────────────────────────┘
               │
               │ Inserts into section_students
               │ (section_id, student_id)
               ▼
┌──────────────────────────────────────────┐
│  section_students TABLE                  │
│  ├─ section_id ──┬─►  References         │
│  ├─ student_id ──┼─►  sections table     │
│  └─ status      │    References users    │
│                 │    via students table  │
│                 ▼                        │
│         ✅ Assignment Complete           │
│         Student now in teacher's         │
│         class roster                     │
└──────────────────────────────────────────┘
```

---

## User Journey Flowchart

```
                        ┌─────────────────────┐
                        │   START: Student    │
                        │   Needs to Join     │
                        │   College/SHS       │
                        └──────────┬──────────┘
                                   │
                                   ▼
                        ┌─────────────────────┐
                        │  SCHOOL ADMIN       │
                        │  Creates Program    │
                        │  (BSIT, STEM, etc) │
                        └──────────┬──────────┘
                                   │
                                   ▼
                        ┌─────────────────────┐
                        │  REGISTRAR          │
                        │  Adds Student       │
                        │  Form:              │
                        │  - Name             │
                        │  - Program ◄────────┼─ From School Admin
                        │  - Year Level       │
                        └──────────┬──────────┘
                                   │
                                   ▼
                        ┌─────────────────────┐
        ┌───────────────►│  DATABASE INSERT    │
        │               │  - User account     │
        │               │  - Profile info     │
        │               │  - Student record   │
        │               │  - Student role     │
        │               └────────┬────────────┘
        │                        │
        │ Course ID             ▼
        │ populated     ┌─────────────────────┐
        │ auto.        │  ✅ STUDENT CREATED  │
        │              │  Auto Student#:      │
        │              │  STU-2025-00001      │
        │              │  Status: ACTIVE      │
        │              │  Program: BSIT       │
        │              │  Ready: YES          │
        │              └────────┬─────────────┘
        │                       │
        │                       ▼
        │              ┌──────────────────────┐
        │              │  BRANCH ADMIN        │
        │              │  Views Student List  │
        │              │  - Sees new student  │
        │              │  - Sees program      │
        │              │  - Clicks student    │
        │              └────────┬─────────────┘
        │                       │
        │                       ▼
        │              ┌──────────────────────┐
        │              │  SELECT SECTION      │
        │              │  (Filters by:        │
        │              │   - Program          │
        │              │   - Year Level       │
        │              │   - Capacity)        │
        │              └────────┬─────────────┘
        │                       │
        │                       ▼
        │              ┌──────────────────────┐
        │              │  CONFIRM ASSIGN      │
        │              │  Validation:         │
        │              │  ✓ Capacity OK       │
        │              │  ✓ Student not in    │
        │              │    section already   │
        │              │  ✓ Section exists    │
        │              └────────┬─────────────┘
        │                       │
        │                       ▼
        └──────────────┌──────────────────────┐
                       │  DATABASE UPDATE     │
                       │  section_students:   │
                       │  INSERT              │
                       │  (section_id,        │
                       │   student_id)        │
                       └────────┬─────────────┘
                                │
                                ▼
                       ┌──────────────────────┐
                       │  ✅ ASSIGNMENT OK    │
                       │  Student now in:     │
                       │  - Teacher roster    │
                       │  - Attendance        │
                       │  - Grades            │
                       │  - Materials         │
                       └────────┬─────────────┘
                                │
                                ▼
                       ┌──────────────────────┐
                       │  TEACHER             │
                       │  - Takes attendance  │
                       │  - Records grades    │
                       │  - Uploads materials │
                       │  - Creates tests     │
                       └────────┬─────────────┘
                                │
                                ▼
                       ┌──────────────────────┐
                       │  STUDENT             │
                       │  - Views grades      │
                       │  - Checks attendance │
                       │  - Downloads         │
                       │    materials         │
                       │  - Takes tests       │
                       └──────────────────────┘
```

---

## Database Relationship Diagram

```
┌─────────────────────┐
│      USERS          │
├─────────────────────┤
│ id (PK)             │
│ email (UNIQUE)      │
│ password            │
│ status              │
│ created_at          │
└──────────┬──────────┘
           │
           │ 1:1
           │
           ▼
┌──────────────────────────┐
│    USER_PROFILES         │
├──────────────────────────┤
│ user_id (PK, FK→users)   │
│ first_name               │
│ last_name                │
│ contact_no               │
│ address                  │
│ branch_id (FK→branches)  │
└──────────┬───────────────┘
           │
           │ 1:1
           │
           ▼
┌──────────────────────────┐          ┌──────────────────┐
│      STUDENTS            │          │   PROGRAMS       │
├──────────────────────────┤          ├──────────────────┤
│ user_id (PK, FK→users)   ├─────────►│ id (PK)          │
│ student_no (UNIQUE)      │ N:1      │ program_code     │
│ course_id (FK)           │          │ program_name     │
│ created_at               │          │ is_active        │
└──────────┬───────────────┘          └──────────────────┘
           │
           │ Also references
           │
           ▼
        ┌──────────────────┐
        │  SHS_STRANDS     │
        ├──────────────────┤
        │ id (PK)          │
        │ strand_code      │
        │ strand_name      │
        │ is_active        │
        └──────────────────┘


┌──────────────────────────┐
│    USER_ROLES            │
├──────────────────────────┤
│ user_id (FK→users)       │
│ role_id                  │
│ (role_id = 6 for STUDENT)│
└──────────────────────────┘


┌──────────────────────────────┐
│    SECTIONS                  │
├──────────────────────────────┤
│ id (PK)                      │
│ section_code                 │
│ section_name                 │
│ branch_id (FK→branches)      │
│ max_capacity                 │
│ academic_year_id             │
│ is_active                    │
└──────────┬───────────────────┘
           │
           │ 1:N
           │
           ▼
┌──────────────────────────────┐
│   SECTION_STUDENTS           │
├──────────────────────────────┤
│ id (PK)                      │
│ section_id (FK→sections)     │
│ student_id (FK→users)        │
│ status (active/inactive)     │
│ enrolled_date                │
└──────────────────────────────┘
        ▲
        │
        │ Creates link between
        │ student and section
        │
        └─ When Branch Admin assigns


┌──────────────────────┐
│    BRANCHES          │
├──────────────────────┤
│ id (PK)              │
│ school_id (FK)       │
│ name                 │
│ address              │
└──────────────────────┘
```

---

## API Sequence Diagram

### Create Student Flow

```
┌─────────────────┐              ┌──────────┐              ┌──────────┐
│   REGISTRAR     │              │ BROWSER  │              │  SERVER  │
│   (UI)          │              │(JavaScript)             │(PHP API) │
└────────┬────────┘              └────┬─────┘              └────┬─────┘
         │                            │                        │
         │ 1. Fill Add Student Form   │                        │
         ├───────────────────────────►│                        │
         │    first_name: "Juan"      │                        │
         │    last_name: "Dela Cruz"  │                        │
         │    email: "juan@test.com"  │                        │
         │    program_id: 1           │                        │
         │    password: "student123"  │                        │
         │                            │                        │
         │                            │ 2. POST /create_student.php
         │                            ├───────────────────────►│
         │                            │                        │
         │                            │    3. Validate input
         │                            │    ├─ Check email unique
         │                            │    ├─ Check program exists
         │                            │    └─ Check required fields
         │                            │                        │
         │                            │    4. Generate Student No
         │                            │    └─ STU-2025-00001
         │                            │                        │
         │                            │    5. DB Transaction
         │                            │    ├─ INSERT users
         │                            │    ├─ INSERT user_profiles
         │                            │    ├─ INSERT students
         │                            │    │  (course_id = program_id)
         │                            │    ├─ INSERT user_roles
         │                            │    └─ COMMIT
         │                            │                        │
         │                            │    6. Log audit action
         │                            │                        │
         │ 7. Return JSON response    │◄───────────────────────┤
         │    {                       │                        │
         │     "status": "success",   │                        │
         │     "message": "...",      │                        │
         │     "student_id": 202,     │                        │
         │     "student_no": "..."    │                        │
         │    }                       │                        │
         │                            │                        │
         │ 8. Show success message    │                        │
         ├◄───────────────────────────┤                        │
         │ 9. Reload page             │                        │
         │ 10. Display student in list│                        │
         │     with new student number│                        │
         │                            │                        │
         └────────────────────────────┴────────────────────────┘
```

### Assign Student Flow

```
┌──────────────────┐              ┌──────────┐              ┌──────────┐
│  BRANCH ADMIN    │              │ BROWSER  │              │  SERVER  │
│  (UI)            │              │(JavaScript)             │(PHP API) │
└────────┬─────────┘              └────┬─────┘              └────┬─────┘
         │                            │                        │
         │ 1. Click "Student Assignment" │                   │
         ├───────────────────────────►│                        │
         │                            │ 2. GET /students page  │
         │                            ├───────────────────────►│
         │                            │◄───────────────────────┤
         │ 3. Select student          │    HTML list           │
         ├───────────────────────────►│                        │
         │                            │                        │
         │ 4. Click "Assign to Section" │                    │
         ├───────────────────────────►│                        │
         │                            │ 5. GET /getUnenrolled  │
         │                            │    ?section_id=1       │
         │                            ├───────────────────────►│
         │                            │                        │
         │                            │    Query database      │
         │                            │    SELECT students     │
         │                            │    NOT IN section      │
         │                            │                        │
         │ 6. Display available students│◄──────────────────────┤
         │◄───────────────────────────┤    JSON response       │
         │                            │                        │
         │ 7. Select student & section │                      │
         ├───────────────────────────►│                        │
         │                            │                        │
         │                            │ 8. POST /bulkAssign    │
         │                            ├───────────────────────►│
         │                            │    section_id: 1       │
         │                            │    student_ids: [202]  │
         │                            │                        │
         │                            │    9. Validate
         │                            │    ├─ Check capacity
         │                            │    ├─ Check student not
         │                            │    │  already assigned
         │                            │    └─ Check section
         │                            │       belongs to branch
         │                            │                        │
         │                            │    10. INSERT INTO
         │                            │        section_students
         │                            │                        │
         │                            │    11. Log audit
         │                            │                        │
         │ 12. Success message        │◄───────────────────────┤
         │◄───────────────────────────┤    JSON: success       │
         │ 13. Student removed from   │                        │
         │     unassigned list        │                        │
         │ 14. Assignment confirmed   │                        │
         │                            │                        │
         └────────────────────────────┴────────────────────────┘
```

---

## State Transition Diagram

```
                    STUDENT STATES

         ┌──────────────────────────────┐
         │                              │
         │   NOT CREATED                │
         │   (Doesn't exist in system)  │
         │                              │
         └────────────┬─────────────────┘
                      │
                      │ Registrar adds student
                      │ (create_student.php)
                      ▼
         ┌──────────────────────────────┐
         │                              │
         │   CREATED                    │
         │   • User account exists      │
         │   • User profile exists      │
         │   • Student record exists    │
         │   • course_id = program ID   │
         │   • Status: ACTIVE           │
         │   • In: Students table       │
         │                              │
         │   NOT YET ASSIGNED TO ANY    │
         │   SECTION                    │
         │                              │
         └────────────┬─────────────────┘
                      │
                      │ Branch Admin assigns
                      │ to section
                      │ (bulkAssign or assign)
                      ▼
         ┌──────────────────────────────┐
         │                              │
         │   ASSIGNED                   │
         │   • Previous state + ...     │
         │   • In: section_students     │
         │   • status = 'active'        │
         │   • In teacher's roster      │
         │   • Can access class         │
         │   • Can get attendance       │
         │   • Can get grades           │
         │                              │
         └────────────┬─────────────────┘
                      │
              ┌───────┴────────┐
              │                │
              │ Teacher        │ Admin action
              │ manages        │ unenroll
              │                │
              ▼                ▼
    ┌──────────────────┐  ┌──────────────┐
    │ ACTIVE IN CLASS  │  │ UNENROLLED   │
    │ - Takes tests    │  │ - Inactive   │
    │ - Gets grades    │  │ - No longer  │
    │ - Views materials│  │   in roster  │
    │ - Attends class  │  └──────────────┘
    │                  │
    └──────────────────┘


         Can move between sections:
         ASSIGNED SECTION A → ASSIGNED SECTION B
         (Branch Admin changes assignment)
```

---

## Role Permission Matrix

```
┌─────────────────────┬─────────────┬──────────┬────────────┬─────────┐
│ Action              │ School Admin│ Registrar│ Branch Admin│ Teacher │
├─────────────────────┼─────────────┼──────────┼────────────┼─────────┤
│ Create Program      │ ✅ YES      │ ❌ NO    │ ❌ NO      │ ❌ NO   │
│ Create Student      │ ❌ NO       │ ✅ YES   │ ❌ NO      │ ❌ NO   │
│ View All Students   │ ✅ YES      │ ✅ YES   │ ✅ YES*    │ ❌ NO   │
│ Assign to Section   │ ❌ NO       │ ❌ NO    │ ✅ YES*    │ ❌ NO   │
│ View Class Roster   │ ❌ NO       │ ❌ NO    │ ✅ YES*    │ ✅ YES* │
│ Take Attendance     │ ❌ NO       │ ❌ NO    │ ❌ NO      │ ✅ YES* │
│ Record Grades       │ ❌ NO       │ ❌ NO    │ ❌ NO      │ ✅ YES* │
│ Update Enrollment   │ ❌ NO       │ ✅ YES   │ ❌ NO      │ ❌ NO   │
│ View Reports        │ ✅ YES      │ ✅ YES   │ ✅ YES     │ ✅ YES* │
│ Export Data         │ ✅ YES      │ ✅ YES   │ ✅ YES     │ ✅ YES* │
└─────────────────────┴─────────────┴──────────┴────────────┴─────────┘

* = Limited to their own scope (branch, section, etc.)
```

---

## Implementation Timeline

```
PHASE 1: School Admin Setup (Day 1)
┌──────────────────────────────────────────┐
│ ✅ Create College Programs (BSIT, BSCS)  │
│ ✅ Add Year Levels (1st, 2nd, 3rd, 4th) │
│ ✅ Add Subjects                          │
│ ✅ Create SHS Strands (STEM, ABM)        │
│ ✅ Add Grade Levels (11, 12)              │
│ ✅ Verify all marked is_active = 1       │
└──────────────────────────────────────────┘
          ↓
PHASE 2: Registrar - Student Creation (Day 2)
┌──────────────────────────────────────────┐
│ ✅ Add 10-20 College Students            │
│ ✅ Add 10-20 SHS Students                │
│ ✅ Verify student numbers auto-generated │
│ ✅ Verify course_id populated            │
│ ✅ Verify students table populated       │
└──────────────────────────────────────────┘
          ↓
PHASE 3: Branch Admin - Section Assignment (Day 2)
┌──────────────────────────────────────────┐
│ ✅ Individual assign 5 students          │
│ ✅ Bulk assign remaining students        │
│ ✅ Verify capacity respected             │
│ ✅ Verify section_students populated     │
│ ✅ Verify student count in sections      │
└──────────────────────────────────────────┘
          ↓
PHASE 4: Verification & Testing (Day 3)
┌──────────────────────────────────────────┐
│ ✅ Teacher views class roster            │
│ ✅ Can take attendance                   │
│ ✅ Can record grades                     │
│ ✅ Students see their classes            │
│ ✅ All audit logs created                │
│ ✅ No errors in PHP/DB logs              │
└──────────────────────────────────────────┘
          ↓
PHASE 5: Production Deployment (Day 4+)
┌──────────────────────────────────────────┐
│ ✅ User training completed               │
│ ✅ Backup created                        │
│ ✅ Rollback plan documented              │
│ ✅ Go/No-go decision made                │
│ ✅ Deploy to production                  │
│ ✅ Monitor logs for 24 hours             │
│ ✅ Gather user feedback                  │
└──────────────────────────────────────────┘
```

---

**Version:** 1.0  
**Last Updated:** January 19, 2026  
**Status:** Ready for Deployment
