-- Irregular / Transferee enrollment support
-- Adds student type metadata and subject-level enrollment tracking.

ALTER TABLE students
ADD COLUMN IF NOT EXISTS student_type ENUM('regular','irregular','transferee') NOT NULL DEFAULT 'regular' AFTER course_id,
ADD COLUMN IF NOT EXISTS previous_school VARCHAR(255) DEFAULT NULL AFTER student_type;

ALTER TABLE students
MODIFY COLUMN student_type ENUM('regular','irregular','transferee') NOT NULL DEFAULT 'regular';

CREATE TABLE IF NOT EXISTS student_completed_subjects (
    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id INT(10) UNSIGNED NOT NULL,
    subject_id INT(10) UNSIGNED NOT NULL,
    completion_source VARCHAR(255) DEFAULT NULL,
    previous_subject_name VARCHAR(255) DEFAULT NULL,
    previous_grade VARCHAR(50) DEFAULT NULL,
    remarks TEXT DEFAULT NULL,
    recorded_by INT(10) UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_student_subject (student_id, subject_id),
    KEY idx_student (student_id),
    KEY idx_subject (subject_id),
    KEY idx_recorded_by (recorded_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE student_completed_subjects
ADD COLUMN IF NOT EXISTS previous_subject_name VARCHAR(255) DEFAULT NULL AFTER completion_source,
ADD COLUMN IF NOT EXISTS previous_grade VARCHAR(50) DEFAULT NULL AFTER previous_subject_name;

CREATE TABLE IF NOT EXISTS student_subject_enrollments (
    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id INT(10) UNSIGNED NOT NULL,
    subject_id INT(10) UNSIGNED NOT NULL,
    section_id INT(11) DEFAULT NULL,
    academic_year_id INT(10) UNSIGNED NOT NULL,
    status ENUM('enrolled','completed','dropped') NOT NULL DEFAULT 'enrolled',
    enrollment_type ENUM('regular','irregular','transferee') NOT NULL DEFAULT 'regular',
    recorded_by INT(10) UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_student_subject_ay (student_id, subject_id, academic_year_id),
    KEY idx_student_status (student_id, status),
    KEY idx_subject_status (subject_id, status),
    KEY idx_section_subject_status (section_id, subject_id, status),
    KEY idx_academic_year (academic_year_id),
    KEY idx_recorded_by (recorded_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE student_subject_enrollments
MODIFY COLUMN enrollment_type ENUM('regular','irregular','transferee') NOT NULL DEFAULT 'regular';

-- Optional backfill:
-- For currently active section assignments, create subject-level enrollment rows
-- for curriculum subjects that match the student's section, year level, and semester.
INSERT IGNORE INTO student_subject_enrollments
    (student_id, subject_id, section_id, academic_year_id, status, enrollment_type, recorded_by)
SELECT
    ss.student_id,
    cs.id AS subject_id,
    sec.id AS section_id,
    sec.academic_year_id,
    'enrolled' AS status,
    'regular' AS enrollment_type,
    NULL AS recorded_by
FROM section_students ss
INNER JOIN sections sec ON ss.section_id = sec.id
INNER JOIN curriculum_subjects cs
    ON (
        (sec.program_id IS NOT NULL AND cs.program_id = sec.program_id AND cs.year_level_id = sec.year_level_id)
        OR
        (sec.shs_strand_id IS NOT NULL AND cs.shs_strand_id = sec.shs_strand_id AND cs.shs_grade_level_id = sec.shs_grade_level_id)
    )
WHERE ss.status = 'active'
  AND cs.is_active = 1
  AND (
      sec.semester = 'summer'
      OR (sec.semester = '1st' AND cs.semester = 1)
      OR (sec.semester = '2nd' AND cs.semester = 2)
  );
