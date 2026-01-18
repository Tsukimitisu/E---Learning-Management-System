-- Migration: Restructure Sections System
-- Sections can have many subjects (based on year level + semester from curriculum)
-- Teachers are assigned to subjects, not sections

-- Create sections table (independent of subjects)
CREATE TABLE IF NOT EXISTS sections (
    id INT PRIMARY KEY AUTO_INCREMENT,
    section_name VARCHAR(50) NOT NULL,
    program_id INT NULL,
    year_level_id INT NULL,
    shs_strand_id INT NULL,
    shs_grade_level_id INT NULL,
    semester ENUM('1st', '2nd', 'summer') DEFAULT '1st',
    academic_year_id INT NOT NULL,
    branch_id INT NOT NULL,
    max_capacity INT DEFAULT 40,
    room VARCHAR(50) NULL,
    adviser_id INT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_program (program_id),
    INDEX idx_year_level (year_level_id),
    INDEX idx_strand (shs_strand_id),
    INDEX idx_grade_level (shs_grade_level_id),
    INDEX idx_academic_year (academic_year_id),
    INDEX idx_branch (branch_id)
);

-- Create section_students table (students enrolled in sections)
CREATE TABLE IF NOT EXISTS section_students (
    id INT PRIMARY KEY AUTO_INCREMENT,
    section_id INT NOT NULL,
    student_id INT NOT NULL,
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('active', 'dropped', 'transferred') DEFAULT 'active',
    INDEX idx_section (section_id),
    INDEX idx_student (student_id),
    UNIQUE KEY unique_enrollment (section_id, student_id)
);

-- Create teacher_subject_assignments table (teachers assigned to subjects per branch)
CREATE TABLE IF NOT EXISTS teacher_subject_assignments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    teacher_id INT NOT NULL,
    curriculum_subject_id INT NOT NULL,
    branch_id INT NOT NULL,
    academic_year_id INT NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_teacher (teacher_id),
    INDEX idx_subject (curriculum_subject_id),
    INDEX idx_branch (branch_id),
    INDEX idx_ay (academic_year_id),
    UNIQUE KEY unique_assignment (teacher_id, curriculum_subject_id, branch_id, academic_year_id)
);
