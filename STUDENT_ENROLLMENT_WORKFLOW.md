# Student Enrollment & Assignment Workflow

## Overview
The system implements a **three-tier student enrollment workflow** involving three main roles:
1. **School Admin** - Creates programs and curriculum
2. **Registrar** - Adds students and enrolls them in programs
3. **Branch Admin** - Assigns students to sections/classes

---

## Workflow Steps

### Step 1: School Admin Creates Programs (Prerequisite)
**Location:** School Admin → Curriculum Management

The **School Admin** creates:
- **College Programs** (e.g., Bachelor of Science in Information Technology)
  - Each program has **Year Levels** (1st Year, 2nd Year, 3rd Year, 4th Year)
  - Each year level contains **Subjects/Courses**
  
- **SHS Strands** (e.g., STEM, HUMSS)
  - Each strand has **Grade Levels** (11, 12)
  - Each grade level contains **Subjects**

### Step 2: Registrar Adds Students
**Location:** Registrar → Student Management → Add Student

**Form Fields:**
- Personal Information: First Name, Last Name, Email, Contact No, Address
- Program Type: Select either "College" or "SHS"
- Program/Strand: Choose from programs/strands created by School Admin
- Year Level: Choose the appropriate year level or grade level
- Temporary Password: Default is "student123" (student must change on first login)

**What Happens:**
1. Creates a **User Account** with student role
2. Creates a **Student Record** with:
   - `student_no` - Auto-generated (e.g., STU-2025-00001)
   - `course_id` - The selected program/strand ID
3. System sends confirmation to registrar
4. Student status: `active`, ready for section assignment

**Output:**
```
✅ Student created successfully
✅ Student added to Students table with program enrollment
✅ Ready for Branch Admin to assign to sections
```

### Step 3: Branch Admin Assigns to Sections
**Location:** Branch Admin → Student Assignment or Bulk Assign to Sections

**Individual Assignment:**
1. Navigate to "Student Section Assignment"
2. Select a student from the list
3. Choose a section based on:
   - The **program** the student is enrolled in
   - The **year level** the student selected
4. Confirm assignment
5. Student appears in teacher's class roster

**Bulk Assignment:**
1. Navigate to "Bulk Assign to Sections"
2. Select a section
3. Filter students:
   - By program status (enrolled vs not enrolled)
   - By enrollment status
4. Select multiple students at once
5. Confirm bulk assignment
6. All students assigned to the section at once

**What Happens:**
- Creates entries in `section_students` table
- Links student to teacher and course
- Student gains access to:
  - Class materials
  - Grades and assessments
  - Attendance records
  - Announcements

---

## Database Schema

### Students Table
```sql
CREATE TABLE students (
    user_id INT PRIMARY KEY,
    student_no VARCHAR(20) UNIQUE,
    course_id INT,  -- References either programs.id or shs_strands.id
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)
```

### Programs Table (College)
```sql
CREATE TABLE programs (
    id INT PRIMARY KEY,
    program_code VARCHAR(50),
    program_name VARCHAR(200),
    is_active BOOLEAN DEFAULT TRUE
)
```

### SHS Strands Table (Senior High School)
```sql
CREATE TABLE shs_strands (
    id INT PRIMARY KEY,
    strand_code VARCHAR(50),
    strand_name VARCHAR(200),
    is_active BOOLEAN DEFAULT TRUE
)
```

### Section Students Link Table
```sql
CREATE TABLE section_students (
    id INT PRIMARY KEY,
    section_id INT,
    student_id INT,  -- References users.id (the student user)
    status VARCHAR(50) DEFAULT 'active',
    enrolled_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)
```

---

## Key Points

✅ **Automatic Student Record Creation**
- When registrar adds a student, the student record is automatically created in the `students` table
- `course_id` is automatically populated with the selected program/strand ID

✅ **Program Enrollment Tracking**
- Students are "enrolled" in a program immediately when their account is created
- The program is stored in `students.course_id`
- Programs come from School Admin's curriculum setup

✅ **Section Assignment (Branch Admin's Role)**
- Students are NOT assigned to sections automatically
- Branch Admin manually assigns students to sections
- Multiple students can be bulk-assigned to sections
- Section assignment can happen after program enrollment

✅ **Program Filtering**
- When branch admin assigns students to sections, they can:
  - View all students regardless of program
  - Filter by program to find students in specific programs
  - Sections are filtered by year level for easy matching

✅ **Workflow Benefits**
- **Separation of Concerns**: Each role has clear responsibilities
- **Flexibility**: Students can be enrolled without immediate class assignment
- **Bulk Operations**: Efficient assignment of many students at once
- **Capacity Management**: System respects section max capacity limits
- **Audit Trail**: All actions logged for compliance

---

## API Endpoints

### Create Student
**Endpoint:** `POST /modules/registrar/process/create_student.php`

**Parameters:**
```php
first_name          // Required
last_name           // Required
email               // Required, must be unique
contact_no          // Optional
address             // Optional
program_type        // Required: 'college' or 'shs'
course_id           // Required if college (program ID)
shs_strand_id       // Required if SHS (strand ID)
password            // Required (defaults to 'student123')
```

**Response:**
```json
{
    "status": "success",
    "message": "Student account created successfully...",
    "student_id": 202,
    "student_no": "STU-2025-00002"
}
```

### Get Unenrolled Students (for section assignment)
**Endpoint:** `GET /modules/branch_admin/process/student_assignment_api.php?action=getUnenrolledStudents&section_id={id}`

**Response:**
```json
{
    "success": true,
    "students": [
        {
            "id": 200,
            "first_name": "Pedro",
            "last_name": "Garcia",
            "student_no": "STU-2025-0001"
        }
    ]
}
```

### Bulk Assign Students to Section
**Endpoint:** `POST /modules/branch_admin/process/student_assignment_api.php`

**Parameters:**
```php
action              // 'bulkAssignToSection'
section_id          // Target section
student_ids[]       // Array of student IDs
```

**Response:**
```json
{
    "success": true,
    "message": "5 students assigned to section successfully"
}
```

---

## Testing Checklist

- [ ] School Admin created at least 1 college program with year levels
- [ ] School Admin created at least 1 SHS strand with grade levels
- [ ] Registrar can see programs/strands in "Add Student" modal
- [ ] Registrar successfully creates a student with a program
- [ ] Student record appears in "All Students" list
- [ ] Student number is auto-generated (STU-YYYY-XXXXX format)
- [ ] Branch Admin can see the new student in student assignment
- [ ] Branch Admin can assign student to a section
- [ ] Student no longer appears in "unenrolled" list after assignment
- [ ] Multiple students can be bulk-assigned at once
- [ ] Teacher can see the student in their class roster

---

## Troubleshooting

**Problem:** Programs not showing in Add Student modal
- **Solution:** Ensure School Admin has created programs and marked them `is_active = 1`

**Problem:** Student appears in enrollment but not in assignment
- **Solution:** Ensure registrar assigned a program/strand to the student

**Problem:** Student shows as "no program" in assignment view
- **Solution:** Check if student's `course_id` is NULL - registrar may not have selected a program

**Problem:** Capacity limits not working
- **Solution:** Verify `max_capacity` is set on the section record

---

## Future Enhancements

- Automatic section assignment based on program/year level matching rules
- Student preferences for section selection
- Waitlist management for full sections
- Parent/Guardian enrollment management
- Multiple program enrollment per student
