-- Table to track issued certificates
CREATE TABLE IF NOT EXISTS certificates_issued (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    certificate_type ENUM('enrollment', 'grade_report', 'completion', 'transcript') NOT NULL,
    reference_no VARCHAR(50) UNIQUE NOT NULL,
    purpose VARCHAR(255) DEFAULT NULL,
    academic_year VARCHAR(20) DEFAULT NULL,
    semester TINYINT UNSIGNED DEFAULT NULL,
    issued_by INT UNSIGNED NOT NULL,
    issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (student_id) REFERENCES students(user_id) ON DELETE CASCADE,
    FOREIGN KEY (issued_by) REFERENCES users(id) ON DELETE RESTRICT,

    INDEX idx_student (student_id),
    INDEX idx_type (certificate_type),
    INDEX idx_reference (reference_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add index for faster GPA calculations
ALTER TABLE grades ADD INDEX idx_student_class (student_id, class_id);

-- Add index for enrollment reports
ALTER TABLE enrollments ADD INDEX idx_status_date (status, created_at);
