<?php
require_once __DIR__ . '/../config/db.php';

echo "=== curriculum_subjects SCHEMA ===\n";
$r = $conn->query("DESCRIBE curriculum_subjects");
while ($row = $r->fetch_assoc()) {
    echo $row['Field'] . " | " . $row['Type'] . " | " . $row['Null'] . " | default:" . $row['Default'] . "\n";
}

echo "\n=== SHS SUBJECTS (sample) ===\n";
$r = $conn->query("
    SELECT cs.id, cs.subject_code, cs.subject_title, cs.subject_type, cs.units, cs.semester,
           cs.shs_strand_id, cs.shs_grade_level_id, cs.is_active,
           ss.strand_name, ss.strand_code, sgl.grade_name, sgl.grade_level
    FROM curriculum_subjects cs
    LEFT JOIN shs_strands ss ON cs.shs_strand_id = ss.id
    LEFT JOIN shs_grade_levels sgl ON cs.shs_grade_level_id = sgl.id
    WHERE cs.subject_type IN ('shs_core', 'shs_applied', 'shs_specialized')
    ORDER BY ss.strand_name, sgl.grade_level, cs.semester, cs.subject_code
    LIMIT 30
");
while ($row = $r->fetch_assoc()) {
    echo "ID:{$row['id']} | {$row['subject_code']} | {$row['subject_title']} | type:{$row['subject_type']} | units:{$row['units']} | sem:{$row['semester']} | strand:{$row['strand_name']}({$row['shs_strand_id']}) | grade:{$row['grade_name']}({$row['shs_grade_level_id']}) | active:{$row['is_active']}\n";
}

echo "\n=== SUBJECT COUNTS BY STRAND/GRADE/SEM ===\n";
$r = $conn->query("
    SELECT ss.strand_name, ss.strand_code, sgl.grade_level, cs.semester, COUNT(*) as cnt
    FROM curriculum_subjects cs
    LEFT JOIN shs_strands ss ON cs.shs_strand_id = ss.id
    LEFT JOIN shs_grade_levels sgl ON cs.shs_grade_level_id = sgl.id
    WHERE cs.subject_type IN ('shs_core', 'shs_applied', 'shs_specialized')
    GROUP BY cs.shs_strand_id, cs.shs_grade_level_id, cs.semester
    ORDER BY ss.strand_name, sgl.grade_level, cs.semester
");
while ($row = $r->fetch_assoc()) {
    echo "{$row['strand_name']} ({$row['strand_code']}) | Grade {$row['grade_level']} | Sem {$row['semester']} | {$row['cnt']} subjects\n";
}
