-- ============================================================
-- SHS Academic Features Migration
-- Adds: LRN field, SHS quarter-based grading, SHS enrollment
-- ============================================================

-- 1. Add LRN (Learner Reference Number) to students table
ALTER TABLE students ADD COLUMN IF NOT EXISTS lrn VARCHAR(12) DEFAULT NULL AFTER student_no;
ALTER TABLE students ADD UNIQUE INDEX IF NOT EXISTS idx_students_lrn (lrn);

-- 2. Create SHS Grades table (quarter-based grading)
CREATE TABLE IF NOT EXISTS shs_grades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    section_id INT NOT NULL,
    subject_id INT NOT NULL,
    academic_year_id INT NOT NULL,
    semester TINYINT NOT NULL DEFAULT 1 COMMENT '1=1st Semester, 2=2nd Semester',
    q1_grade TINYINT UNSIGNED DEFAULT NULL COMMENT 'Quarter 1 grade (whole number)',
    q2_grade TINYINT UNSIGNED DEFAULT NULL COMMENT 'Quarter 2 grade (whole number)',
    q3_grade TINYINT UNSIGNED DEFAULT NULL COMMENT 'Quarter 3 grade (whole number)',
    q4_grade TINYINT UNSIGNED DEFAULT NULL COMMENT 'Quarter 4 grade (whole number)',
    sem1_final_grade TINYINT UNSIGNED DEFAULT NULL COMMENT 'Computed: round((Q1+Q2)/2)',
    sem2_final_grade TINYINT UNSIGNED DEFAULT NULL COMMENT 'Computed: round((Q3+Q4)/2)',
    final_grade TINYINT UNSIGNED DEFAULT NULL COMMENT 'Computed: round((sem1+sem2)/2) or single semester',
    remarks ENUM('passed','failed','with_remedial','incomplete','') DEFAULT '',
    status ENUM('active','dropped','credited') DEFAULT 'active',
    notes TEXT DEFAULT NULL,
    version INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY idx_shs_grade_unique (student_id, section_id, subject_id, academic_year_id),
    INDEX idx_shs_grade_student (student_id),
    INDEX idx_shs_grade_section (section_id),
    INDEX idx_shs_grade_subject (subject_id),
    INDEX idx_shs_grade_ay (academic_year_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Add SHS enrollment fields to student_term_enrollments
ALTER TABLE student_term_enrollments ADD COLUMN IF NOT EXISTS voucher_status ENUM('yes','no') DEFAULT 'no' AFTER student_type;
ALTER TABLE student_term_enrollments ADD COLUMN IF NOT EXISTS enrollment_status ENUM('pending','approved','rejected') DEFAULT 'approved' AFTER voucher_status;

-- 4. Create SHS transferee completed subjects tracking
-- (student_completed_subjects already exists, ensure needed columns)
ALTER TABLE student_completed_subjects ADD COLUMN IF NOT EXISTS completion_type ENUM('credited','bridging','remedial') DEFAULT 'credited' AFTER completion_source;
ALTER TABLE student_completed_subjects ADD COLUMN IF NOT EXISTS semester TINYINT DEFAULT NULL AFTER completion_type;

-- 5. Create graduation requirements tracking table
CREATE TABLE IF NOT EXISTS shs_graduation_requirements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    strand_id INT NOT NULL,
    total_required_subjects INT NOT NULL DEFAULT 0,
    completed_subjects INT NOT NULL DEFAULT 0,
    missing_subjects INT NOT NULL DEFAULT 0,
    has_remedial_subjects TINYINT(1) NOT NULL DEFAULT 0,
    graduation_eligible TINYINT(1) NOT NULL DEFAULT 0,
    last_checked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY idx_shs_grad_student (student_id, strand_id),
    INDEX idx_shs_grad_eligible (graduation_eligible)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
