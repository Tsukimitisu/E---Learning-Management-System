<?php
require_once __DIR__ . '/../config/init.php';

// Simulate what ensureIrregularSupportSchema does
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS program_type ENUM('college','shs') DEFAULT NULL AFTER course_id");
$conn->query("
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
        KEY idx_student_status (student_id, status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// Test: HUMSS student (239, course_id=3) should be eligible for HUMSS section (10, shs_strand_id=3, shs_grade_level_id=5)
$student_id = 239;
$section_id = 10;

// Get section info
$section_stmt = $conn->prepare("SELECT id, program_id, year_level_id, shs_strand_id, shs_grade_level_id, semester FROM sections WHERE id = ?");
$section_stmt->bind_param("i", $section_id);
$section_stmt->execute();
$section = $section_stmt->get_result()->fetch_assoc();
echo "Section: " . json_encode($section) . PHP_EOL;

// Get student info
$student_stmt = $conn->prepare("SELECT course_id, program_type FROM students WHERE user_id = ?");
$student_stmt->bind_param("i", $student_id);
$student_stmt->execute();
$student = $student_stmt->get_result()->fetch_assoc();
echo "Student: " . json_encode($student) . PHP_EOL;

// Check course_id vs shs_strand_id
echo "course_id(" . $student['course_id'] . ") == shs_strand_id(" . $section['shs_strand_id'] . "): " . ((int)$student['course_id'] === (int)$section['shs_strand_id'] ? 'YES' : 'NO') . PHP_EOL;

// Resolve grade level number
$gl_stmt = $conn->prepare("SELECT grade_level FROM shs_grade_levels WHERE id = ?");
$gl_stmt->bind_param("i", $section['shs_grade_level_id']);
$gl_stmt->execute();
$gl_row = $gl_stmt->get_result()->fetch_assoc();
$grade_level_num = $gl_row['grade_level'] ?? 0;
echo "Section grade_level_id=" . $section['shs_grade_level_id'] . " resolves to grade_level=" . $grade_level_num . PHP_EOL;

// Check curriculum subjects matching by grade_level NUMBER
$semester_num = 1;
$stmt = $conn->prepare("
    SELECT COUNT(*) as cnt FROM curriculum_subjects cs
    INNER JOIN shs_grade_levels sgl ON cs.shs_grade_level_id = sgl.id
    WHERE cs.shs_strand_id = ? AND sgl.grade_level = ? AND cs.is_active = 1
      AND (cs.semester = ? OR ? = 3)
");
$stmt->bind_param("iiii", $section['shs_strand_id'], $grade_level_num, $semester_num, $semester_num);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
echo "Matching curriculum subjects: " . $result['cnt'] . PHP_EOL;
echo "Eligible: " . (((int)$result['cnt']) > 0 ? 'YES' : 'NO') . PHP_EOL;

// Also test getSubjects-style query
echo PHP_EOL . "=== Subjects for section (strand_id=" . $section['shs_strand_id'] . ", grade_level=" . $grade_level_num . ", semester=1) ===" . PHP_EOL;
$stmt2 = $conn->prepare("
    SELECT cs.id, cs.subject_code, cs.subject_title 
    FROM curriculum_subjects cs
    INNER JOIN shs_grade_levels sgl ON cs.shs_grade_level_id = sgl.id
    WHERE cs.shs_strand_id = ? AND sgl.grade_level = ? AND cs.semester = ? AND cs.is_active = 1
    ORDER BY cs.subject_code
");
$stmt2->bind_param("iii", $section['shs_strand_id'], $grade_level_num, $semester_num);
$stmt2->execute();
$r = $stmt2->get_result();
while ($row = $r->fetch_assoc()) {
    echo "  " . $row['subject_code'] . " - " . $row['subject_title'] . PHP_EOL;
}
echo "Total: " . $r->num_rows . " subjects" . PHP_EOL;
