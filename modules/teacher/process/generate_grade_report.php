<?php
require_once '../../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_TEACHER) {
    die('Unauthorized');
}

$section_id = (int)($_GET['section_id'] ?? 0);
$subject_id = (int)($_GET['subject_id'] ?? 0);
$teacher_id = $_SESSION['user_id'];

// Get current academic year
$current_ay = $conn->query("SELECT * FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$current_ay_id = $current_ay['id'] ?? 0;

if ($section_id == 0 || $subject_id == 0) {
    die('Invalid parameters');
}

// Verify teacher is assigned to this subject
$verify = $conn->prepare("SELECT id FROM teacher_subject_assignments WHERE teacher_id = ? AND curriculum_subject_id = ? AND academic_year_id = ? AND is_active = 1");
$verify->bind_param("iii", $teacher_id, $subject_id, $current_ay_id);
$verify->execute();
if ($verify->get_result()->num_rows == 0) {
    die('Unauthorized');
}

// Get section info
$section_query = $conn->prepare("
    SELECT s.*, 
           COALESCE(p.program_name, ss.strand_name) as program_name,
           COALESCE(pyl.year_name, CONCAT('Grade ', sgl.grade_level)) as year_level_name
    FROM sections s
    LEFT JOIN programs p ON s.program_id = p.id
    LEFT JOIN program_year_levels pyl ON s.year_level_id = pyl.id
    LEFT JOIN shs_strands ss ON s.shs_strand_id = ss.id
    LEFT JOIN shs_grade_levels sgl ON s.shs_grade_level_id = sgl.id
    WHERE s.id = ?
");
$section_query->bind_param("i", $section_id);
$section_query->execute();
$section_info = $section_query->get_result()->fetch_assoc();

// Get subject info
$subject_query = $conn->prepare("SELECT * FROM curriculum_subjects WHERE id = ?");
$subject_query->bind_param("i", $subject_id);
$subject_query->execute();
$subject_info = $subject_query->get_result()->fetch_assoc();

// Get grades summary from section_students
$students = $conn->prepare("
    SELECT 
        COALESCE(st.student_no, CONCAT('STU-', u.id)) as student_no,
        CONCAT(up.first_name, ' ', up.last_name) as student_name,
        g.midterm,
        g.final,
        g.final_grade,
        g.remarks
    FROM section_students ss
    INNER JOIN users u ON ss.student_id = u.id
    INNER JOIN user_profiles up ON u.id = up.user_id
    LEFT JOIN students st ON u.id = st.user_id
    LEFT JOIN grades g ON u.id = g.student_id AND g.section_id = ? AND g.subject_id = ?
    WHERE ss.section_id = ? AND ss.status = 'active'
    ORDER BY up.last_name, up.first_name
");
$students->bind_param("iii", $section_id, $subject_id, $section_id);
$students->execute();
$students_result = $students->get_result();

// Simple HTML Report (In production, use TCPDF or similar)
?>
<!DOCTYPE html>
<html>
<head>
    <title>Grade Report</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1 { color: #800000; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #003366; color: white; }
        .passed { color: green; font-weight: bold; }
        .failed { color: red; font-weight: bold; }
        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <button class="no-print" onclick="window.print()" style="padding: 10px 20px; margin-bottom: 20px; cursor: pointer;">Print Report</button>
    
    <h1>Grade Summary Report</h1>
    <p><strong>Subject:</strong> <?php echo htmlspecialchars($subject_info['subject_code'] ?? 'N/A'); ?> - <?php echo htmlspecialchars($subject_info['subject_title'] ?? 'N/A'); ?></p>
    <p><strong>Section:</strong> <?php echo htmlspecialchars($section_info['section_name'] ?? 'N/A'); ?></p>
    <p><strong>Program:</strong> <?php echo htmlspecialchars($section_info['program_name'] ?? 'N/A'); ?> - <?php echo htmlspecialchars($section_info['year_level_name'] ?? 'N/A'); ?></p>
    <p><strong>Generated:</strong> <?php echo date('F d, Y h:i A'); ?></p>
    
    <table>
        <thead>
            <tr>
                <th>Student No</th>
                <th>Student Name</th>
                <th>Midterm</th>
                <th>Final</th>
                <th>Final Grade</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($student = $students_result->fetch_assoc()): ?>
            <tr>
                <td><?php echo htmlspecialchars($student['student_no']); ?></td>
                <td><?php echo htmlspecialchars($student['student_name']); ?></td>
                <td><?php echo $student['midterm'] ? number_format($student['midterm'], 2) : '-'; ?></td>
                <td><?php echo $student['final'] ? number_format($student['final'], 2) : '-'; ?></td>
                <td><?php echo $student['final_grade'] ? number_format($student['final_grade'], 2) : '-'; ?></td>
                <td class="<?php echo ($student['remarks'] ?? '') == 'PASSED' ? 'passed' : 'failed'; ?>">
                    <?php echo htmlspecialchars($student['remarks'] ?? '-'); ?>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</body>
</html>