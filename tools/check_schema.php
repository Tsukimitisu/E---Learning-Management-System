<?php
require_once __DIR__.'/../config/db.php';

// curriculum_subjects columns
$r = $conn->query("DESCRIBE curriculum_subjects");
echo "curriculum_subjects columns:\n";
while ($row = $r->fetch_assoc()) echo "  " . $row['Field'] . " " . $row['Type'] . "\n";

// Check a sample grade with joins
$r = $conn->query("
    SELECT g.id, g.student_id, g.class_id, g.subject_id, g.final_grade,
           cl.curriculum_subject_id, cl.subject_id as cl_subject_id,
           cs.subject_code, cs.subject_title, cs.units
    FROM grades g
    LEFT JOIN classes cl ON g.class_id = cl.id
    LEFT JOIN curriculum_subjects cs ON COALESCE(cl.curriculum_subject_id, cl.subject_id, g.subject_id) = cs.id
    LIMIT 5
");
echo "\nSample grades with joins:\n";
if ($r && $r->num_rows > 0) {
    while ($row = $r->fetch_assoc()) {
        print_r($row);
    }
} else {
    echo "  No grades found\n";
}

// Check student_term_enrollments sample
$r = $conn->query("SELECT * FROM student_term_enrollments LIMIT 3");
echo "\nSample enrollments:\n";
if ($r && $r->num_rows > 0) {
    while ($row = $r->fetch_assoc()) print_r($row);
} else echo "  No enrollments\n";
