-- =====================================================
-- ACADEMIC YEAR TRANSITION & TERM-BASED GRADING SYSTEM
-- =====================================================
-- This migration adds:
-- 1. Term-based grading (Prelim, Midterm, Pre-Finals, Finals)
-- 2. Student year level promotion system
-- 3. Academic year transition utilities
-- =====================================================

-- =====================================================
-- PART 1: TERM-BASED GRADING SYSTEM
-- =====================================================

-- Add term columns to grades table
ALTER TABLE grades
ADD COLUMN prelim DECIMAL(5,2) DEFAULT NULL AFTER class_id,
ADD COLUMN prefinal DECIMAL(5,2) DEFAULT NULL AFTER midterm,
ADD COLUMN academic_year_id INT(11) DEFAULT NULL AFTER subject_id,
ADD COLUMN semester VARCHAR(10) DEFAULT NULL AFTER academic_year_id;

-- Add index for academic year filtering
ALTER TABLE grades ADD INDEX idx_grades_academic_year (academic_year_id);
ALTER TABLE grades ADD INDEX idx_grades_semester (semester);

-- Update existing grades to have academic year (use current active year)
UPDATE grades g
SET academic_year_id = (SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1),
    semester = '1st'
WHERE academic_year_id IS NULL;

-- =====================================================
-- PART 2: STUDENT ENROLLMENT HISTORY TABLE
-- =====================================================

-- Create student enrollment history to track year level progression
CREATE TABLE IF NOT EXISTS student_enrollment_history (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    student_id INT(10) UNSIGNED NOT NULL,
    academic_year_id INT(11) NOT NULL,
    program_id INT(10) UNSIGNED NULL,
    shs_strand_id INT(11) NULL,
    year_level_id INT(10) UNSIGNED NULL,
    shs_grade_level_id INT(11) NULL,
    branch_id INT(10) UNSIGNED NOT NULL,
    status ENUM('active', 'promoted', 'retained', 'graduated', 'dropped') DEFAULT 'active',
    gwa DECIMAL(4,2) DEFAULT NULL,
    promoted_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_student_enrollment (student_id, academic_year_id),
    INDEX idx_year_level (year_level_id),
    INDEX idx_status (status)
);

-- =====================================================
-- PART 3: ACADEMIC YEAR SETTINGS TABLE
-- =====================================================

-- Add academic year configuration
ALTER TABLE academic_years
ADD COLUMN start_date DATE NULL AFTER year_name,
ADD COLUMN end_date DATE NULL AFTER start_date,
ADD COLUMN enrollment_start DATE NULL AFTER end_date,
ADD COLUMN enrollment_end DATE NULL AFTER enrollment_start,
ADD COLUMN status ENUM('upcoming', 'current', 'completed') DEFAULT 'upcoming' AFTER is_active;

-- Update current year status
UPDATE academic_years SET status = 'current' WHERE is_active = 1;

-- =====================================================
-- PART 4: PROMOTION/TRANSITION LOG TABLE
-- =====================================================

CREATE TABLE IF NOT EXISTS student_promotions (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    student_id INT(10) UNSIGNED NOT NULL,
    from_academic_year_id INT(11) NOT NULL,
    to_academic_year_id INT(11) NOT NULL,
    from_year_level_id INT(10) UNSIGNED NULL,
    to_year_level_id INT(10) UNSIGNED NULL,
    from_shs_grade_level_id INT(11) NULL,
    to_shs_grade_level_id INT(11) NULL,
    program_id INT(10) UNSIGNED NULL,
    shs_strand_id INT(11) NULL,
    branch_id INT(10) UNSIGNED NOT NULL,
    promotion_type ENUM('promoted', 'retained', 'graduated', 'transferred') NOT NULL,
    gwa DECIMAL(4,2) DEFAULT NULL,
    remarks TEXT NULL,
    promoted_by INT(10) UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_student_promotion (student_id),
    INDEX idx_academic_year (from_academic_year_id, to_academic_year_id)
);

-- =====================================================
-- PART 5: GRADING TERMS CONFIGURATION TABLE
-- =====================================================

CREATE TABLE IF NOT EXISTS grading_terms (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    term_name VARCHAR(50) NOT NULL,
    term_code VARCHAR(20) NOT NULL,
    term_order TINYINT(3) NOT NULL,
    weight_percentage DECIMAL(5,2) DEFAULT 25.00,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default 4 terms
INSERT INTO grading_terms (term_name, term_code, term_order, weight_percentage) VALUES
('Prelim', 'prelim', 1, 25.00),
('Midterm', 'midterm', 2, 25.00),
('Pre-Finals', 'prefinal', 3, 25.00),
('Finals', 'final', 4, 25.00);

-- =====================================================
-- USEFUL VIEWS FOR REPORTING
-- =====================================================

-- View for student current enrollment status
CREATE OR REPLACE VIEW v_student_current_enrollment AS
SELECT 
    u.id as student_id,
    CONCAT(up.first_name, ' ', up.last_name) as student_name,
    s.student_no,
    COALESCE(p.program_name, ss.strand_name) as program_name,
    COALESCE(pyl.year_name, sgl.grade_name) as year_level,
    sec.section_name,
    b.name as branch_name,
    ay.year_name as academic_year,
    sec.semester,
    sstu.status
FROM users u
INNER JOIN user_profiles up ON u.id = up.user_id
INNER JOIN students s ON u.id = s.user_id
INNER JOIN section_students sstu ON u.id = sstu.student_id
INNER JOIN sections sec ON sstu.section_id = sec.id
INNER JOIN academic_years ay ON sec.academic_year_id = ay.id
INNER JOIN branches b ON sec.branch_id = b.id
LEFT JOIN programs p ON sec.program_id = p.id
LEFT JOIN shs_strands ss ON sec.shs_strand_id = ss.id
LEFT JOIN program_year_levels pyl ON sec.year_level_id = pyl.id
LEFT JOIN shs_grade_levels sgl ON sec.shs_grade_level_id = sgl.id
WHERE ay.is_active = 1 AND sstu.status = 'active';

-- =====================================================
-- SAMPLE: How to Add New Academic Year
-- =====================================================
-- INSERT INTO academic_years (year_name, start_date, end_date, is_active, status) 
-- VALUES ('2026-2027', '2026-06-01', '2027-05-31', 0, 'upcoming');
