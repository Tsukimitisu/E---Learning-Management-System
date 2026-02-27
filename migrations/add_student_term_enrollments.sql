-- Student term enrollment history (year + semester tracking)

CREATE TABLE IF NOT EXISTS student_term_enrollments (
    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id INT(10) UNSIGNED NOT NULL,
    program_type ENUM('college','shs') NOT NULL DEFAULT 'college',
    program_id INT(10) UNSIGNED NOT NULL,
    year_level_id INT(10) UNSIGNED NOT NULL,
    academic_year_id INT(10) UNSIGNED NOT NULL,
    semester ENUM('1st','2nd','summer') NOT NULL DEFAULT '1st',
    student_type ENUM('regular','irregular','transferee') NOT NULL DEFAULT 'regular',
    previous_school VARCHAR(255) DEFAULT NULL,
    status ENUM('enrolled','completed','cancelled') NOT NULL DEFAULT 'enrolled',
    recorded_by INT(10) UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_student_term (student_id, academic_year_id, semester),
    KEY idx_student_ay (student_id, academic_year_id),
    KEY idx_program_level (program_id, year_level_id),
    KEY idx_recorded_by (recorded_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE student_term_enrollments
MODIFY COLUMN student_type ENUM('regular','irregular','transferee') NOT NULL DEFAULT 'regular';

