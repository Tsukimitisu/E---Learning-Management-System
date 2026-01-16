<?php
require_once '../../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_TEACHER) {
    die('Unauthorized');
}

$class_id = (int)($_GET['class_id'] ?? 0);
$teacher_id = $_SESSION['user_id'];

if ($class_id == 0) {
    die('Invalid class ID');
}

// Verify class
$verify = $conn->prepare("SELECT teacher_id FROM classes WHERE id = ?");
$verify->bind_param("i", $class_id);
$verify->execute();
$result = $verify->get_result();

if ($result->num_rows == 0 || $result->fetch_assoc()['teacher_id'] != $teacher_id) {
    die('Unauthorized');
}

// Get class info
$class_info = $conn->query("
    SELECT cl.*, s.subject_code, s.subject_title
    FROM classes cl
    LEFT JOIN subjects s ON cl.subject_id = s.id
    WHERE cl.id = $class_id
")->fetch_assoc();

// Get grades summary
$students = $conn->query("
    SELECT 
        s.student_no,
        CONCAT(up.first_name, ' ', up.last_name) as student_name,
        g.midterm,
        g.final,
        g.final_grade,
        g.remarks
    FROM enrollments e
    INNER JOIN students s ON e.student_id = s.user_id
    INNER JOIN user_profiles up ON s.user_id = up.user_id
    LEFT JOIN grades g ON s.user_id = g.student_id AND g.class_id = $class_id
    WHERE e.class_id = $class_id AND e.status = 'approved'
    ORDER BY up.last_name, up.first_name
");

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
    <button class="no-print" onclick="window.print()">Print Report</button>
    
    <h1>Grade Summary Report</h1>
    <p><strong>Subject:</strong> <?php echo htmlspecialchars($class_info['subject_code'] ?? 'N/A'); ?> - <?php echo htmlspecialchars($class_info['subject_title'] ?? 'N/A'); ?></p>
    <p><strong>Section:</strong> <?php echo htmlspecialchars($class_info['section_name'] ?? 'N/A'); ?></p>
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
            <?php while ($student = $students->fetch_assoc()): ?>
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