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
        CONCAT(up.last_name, ', ', up.first_name) as student_name,
        g.prelim,
        g.midterm,
        g.prefinal,
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

// Collect data for stats
$all_rows = [];
$total_passed = 0;
$total_failed = 0;
$grade_sum = 0;
$graded_count = 0;
while ($row = $students_result->fetch_assoc()) {
    $all_rows[] = $row;
    if ($row['final_grade'] > 0) {
        $graded_count++;
        $grade_sum += $row['final_grade'];
    }
    if ($row['remarks'] === 'PASSED') $total_passed++;
    if ($row['remarks'] === 'FAILED') $total_failed++;
}
$avg_grade = $graded_count > 0 ? $grade_sum / $graded_count : 0;

// Professional HTML Report
?>
<!DOCTYPE html>
<html>
<head>
    <title>Grade Report - <?php echo htmlspecialchars($subject_info['subject_code'] ?? ''); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; margin: 0; padding: 30px; background: #f0f2f5; color: #333; }
        
        .report-container { max-width: 900px; margin: 0 auto; background: white; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); overflow: hidden; }
        
        .report-header { background: linear-gradient(135deg, #800000 0%, #003366 100%); color: white; padding: 30px 35px; }
        .report-header h1 { font-size: 1.5rem; font-weight: 700; margin-bottom: 5px; }
        .report-header p { opacity: 0.85; font-size: 0.85rem; }
        
        .report-meta { padding: 20px 35px; background: #f8f9fa; display: flex; flex-wrap: wrap; gap: 10px; border-bottom: 1px solid #e9ecef; }
        .meta-item { flex: 1; min-width: 200px; }
        .meta-label { font-size: 0.7rem; text-transform: uppercase; color: #6c757d; letter-spacing: 0.5px; font-weight: 600; }
        .meta-value { font-size: 0.95rem; font-weight: 600; color: #333; }
        
        .report-stats { display: flex; padding: 15px 35px; gap: 15px; border-bottom: 1px solid #eee; }
        .stat-box { flex: 1; text-align: center; padding: 12px; background: #f8f9fa; border-radius: 8px; }
        .stat-num { font-size: 1.4rem; font-weight: 700; }
        .stat-label { font-size: 0.7rem; text-transform: uppercase; color: #6c757d; letter-spacing: 0.5px; }
        .stat-maroon { color: #800000; }
        .stat-green { color: #198754; }
        .stat-red { color: #dc3545; }
        .stat-blue { color: #003366; }
        
        .report-body { padding: 25px 35px; }
        
        table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        th { background: #003366; color: white; padding: 10px 8px; text-align: center; font-weight: 600; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; }
        th:nth-child(1), th:nth-child(2), th:nth-child(3) { text-align: left; }
        td { padding: 9px 8px; border-bottom: 1px solid #f0f0f0; text-align: center; }
        td:nth-child(1), td:nth-child(2), td:nth-child(3) { text-align: left; }
        tr:hover { background: #f8f9fa; }
        tr:last-child td { border-bottom: none; }
        
        .passed { color: #198754; font-weight: 700; }
        .failed { color: #dc3545; font-weight: 700; }
        .no-data { color: #adb5bd; }
        .final-grade { font-weight: 700; font-size: 0.9rem; }
        
        .report-footer { padding: 20px 35px; background: #f8f9fa; text-align: center; font-size: 0.75rem; color: #6c757d; border-top: 1px solid #eee; }
        
        .toolbar { max-width: 900px; margin: 0 auto 15px; display: flex; gap: 10px; }
        .toolbar button { padding: 10px 24px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 0.85rem; transition: 0.2s; }
        .btn-print { background: #800000; color: white; }
        .btn-print:hover { background: #600000; }
        .btn-back { background: #e9ecef; color: #333; }
        .btn-back:hover { background: #dee2e6; }
        
        @media print {
            body { background: white; padding: 0; }
            .toolbar { display: none; }
            .report-container { box-shadow: none; border-radius: 0; }
            .report-header { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            th { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .stat-box { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button class="btn-print" onclick="window.print()"><span style="margin-right:6px;">&#128438;</span> Print Report</button>
        <button class="btn-back" onclick="window.close()">Close</button>
    </div>
    
    <div class="report-container">
        <div class="report-header">
            <h1>Grade Summary Report</h1>
            <p>Academic Grade Record for Students</p>
        </div>
        
        <div class="report-meta">
            <div class="meta-item">
                <div class="meta-label">Subject</div>
                <div class="meta-value"><?php echo htmlspecialchars(($subject_info['subject_code'] ?? '') . ' - ' . ($subject_info['subject_title'] ?? '')); ?></div>
            </div>
            <div class="meta-item">
                <div class="meta-label">Section</div>
                <div class="meta-value"><?php echo htmlspecialchars($section_info['section_name'] ?? 'N/A'); ?></div>
            </div>
            <div class="meta-item">
                <div class="meta-label">Program / Year Level</div>
                <div class="meta-value"><?php echo htmlspecialchars(($section_info['program_name'] ?? '') . ' - ' . ($section_info['year_level_name'] ?? '')); ?></div>
            </div>
            <div class="meta-item">
                <div class="meta-label">Date Generated</div>
                <div class="meta-value"><?php echo date('F d, Y h:i A'); ?></div>
            </div>
        </div>
        
        <div class="report-stats">
            <div class="stat-box">
                <div class="stat-num stat-blue"><?php echo count($all_rows); ?></div>
                <div class="stat-label">Total Students</div>
            </div>
            <div class="stat-box">
                <div class="stat-num stat-green"><?php echo $total_passed; ?></div>
                <div class="stat-label">Passed</div>
            </div>
            <div class="stat-box">
                <div class="stat-num stat-red"><?php echo $total_failed; ?></div>
                <div class="stat-label">Failed</div>
            </div>
            <div class="stat-box">
                <div class="stat-num stat-maroon"><?php echo $avg_grade ? number_format($avg_grade, 2) : '-'; ?></div>
                <div class="stat-label">Class Average</div>
            </div>
        </div>
        
        <div class="report-body">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student No</th>
                        <th>Student Name</th>
                        <th>Prelim</th>
                        <th>Midterm</th>
                        <th>Pre-Final</th>
                        <th>Final</th>
                        <th>Final Grade</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_rows as $i => $student): ?>
                    <tr>
                        <td class="text-muted"><?php echo $i + 1; ?></td>
                        <td><?php echo htmlspecialchars($student['student_no']); ?></td>
                        <td style="font-weight:500;"><?php echo htmlspecialchars($student['student_name']); ?></td>
                        <td><?php echo $student['prelim'] > 0 ? number_format($student['prelim'], 2) : '<span class="no-data">-</span>'; ?></td>
                        <td><?php echo $student['midterm'] > 0 ? number_format($student['midterm'], 2) : '<span class="no-data">-</span>'; ?></td>
                        <td><?php echo $student['prefinal'] > 0 ? number_format($student['prefinal'], 2) : '<span class="no-data">-</span>'; ?></td>
                        <td><?php echo $student['final'] > 0 ? number_format($student['final'], 2) : '<span class="no-data">-</span>'; ?></td>
                        <td class="final-grade"><?php echo $student['final_grade'] > 0 ? number_format($student['final_grade'], 2) : '<span class="no-data">-</span>'; ?></td>
                        <td>
                            <?php if ($student['remarks'] === 'PASSED'): ?>
                                <span class="passed">PASSED</span>
                            <?php elseif ($student['remarks'] === 'FAILED'): ?>
                                <span class="failed">FAILED</span>
                            <?php else: ?>
                                <span class="no-data">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="report-footer">
            This report was generated by ELMS &mdash; <?php echo date('F d, Y h:i A'); ?>
        </div>
    </div>
</body>
</html>