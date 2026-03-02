<?php
ob_start();
require_once '../../../config/init.php';
require_once '../../../vendor/autoload.php';
require_once '../../../config/tcpdf_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_REGISTRAR) {
    header('Location: ../../../index.php');
    exit();
}

$student_id = (int)($_GET['student_id'] ?? 0);
if ($student_id <= 0) {
    echo '<h3 style="color:red;text-align:center;margin-top:40px;">Invalid student ID</h3>';
    exit();
}

// Fetch student with program/strand (covers both college and SHS)
$student = $conn->query("
    SELECT s.student_no, s.course_id, s.lrn,
           CONCAT(up.first_name, ' ', up.last_name) as full_name,
           p.program_code, p.program_name,
           ss.strand_code, ss.strand_name,
           CASE 
               WHEN p.id IS NOT NULL THEN 'college'
               WHEN ss.id IS NOT NULL THEN 'shs'
               ELSE 'unknown'
           END as program_type
    FROM students s
    INNER JOIN user_profiles up ON s.user_id = up.user_id
    LEFT JOIN programs p ON s.course_id = p.id
    LEFT JOIN shs_strands ss ON s.course_id = ss.id AND p.id IS NULL
    WHERE s.user_id = $student_id
")->fetch_assoc();

if (!$student) {
    echo '<h3 style="color:red;text-align:center;margin-top:40px;">Student not found.</h3>';
    exit();
}

$program_code = $student['program_code'] ?? $student['strand_code'] ?? 'N/A';
$program_name = $student['program_name'] ?? $student['strand_name'] ?? 'N/A';
$program_display = $program_code . ' - ' . $program_name;

$is_shs = ($student['program_type'] === 'shs');

// Fetch ALL grades with year level info using LEFT JOINs so no grades are dropped
$grades_result = $conn->query("
    SELECT 
        cs.subject_code,
        cs.subject_title,
        cs.units,
        cs.semester,
        g.final_grade,
        g.remarks,
        COALESCE(pyl.year_level, sgl.grade_level, 0) as year_level_num,
        COALESCE(pyl.year_name, CONCAT('Grade ', sgl.grade_level), 'Unclassified') as year_level_name,
        ay.year_name as academic_year
    FROM grades g
    LEFT JOIN classes cl ON g.class_id = cl.id
    LEFT JOIN curriculum_subjects cs ON COALESCE(cl.curriculum_subject_id, cl.subject_id, g.subject_id) = cs.id
    LEFT JOIN program_year_levels pyl ON cs.year_level_id = pyl.id
    LEFT JOIN shs_grade_levels sgl ON cs.shs_grade_level_id = sgl.id
    LEFT JOIN academic_years ay ON COALESCE(cl.academic_year_id, g.academic_year_id) = ay.id
    WHERE g.student_id = $student_id
    ORDER BY year_level_num ASC, cs.semester ASC, cs.subject_code ASC
");

$records = [];
if ($grades_result) {
    while ($row = $grades_result->fetch_assoc()) {
        $records[] = $row;
    }
}

// Group records by year level + semester
$grouped = [];
foreach ($records as $row) {
    $yl = $row['year_level_name'] ?? 'Unclassified';
    $sem = (int)($row['semester'] ?? 1);
    $key = $yl . '|' . $sem;
    if (!isset($grouped[$key])) {
        $grouped[$key] = [
            'year_level_name' => $yl,
            'year_level_num' => (int)($row['year_level_num'] ?? 0),
            'semester' => $sem,
            'academic_year' => $row['academic_year'] ?? '',
            'subjects' => []
        ];
    }
    $grouped[$key]['subjects'][] = $row;
}

// Semester display labels
$semester_labels = [1 => '1st Semester', 2 => '2nd Semester', 3 => 'Summer'];

// --- PDF Generation ---
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8');
$pdf->SetCreator('ELMS - Datamex');
$pdf->SetAuthor('Registrar Office');
$pdf->SetTitle('Transcript of Records');
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(20, 15, 20);
$pdf->SetAutoPageBreak(true, 25);
$pdf->AddPage();

// Logo
$logo_path = realpath(__DIR__ . '/../../../assets/image/datamexlogo.png');
if ($logo_path && file_exists($logo_path)) {
    $pdf->Image($logo_path, 85, 10, 40);
}
$pdf->SetY(52);

// School name
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 8, PDF_HEADER_TITLE, 0, 1, 'C');
$pdf->SetFont('helvetica', 'I', 10);
$pdf->Cell(0, 5, 'Office of the Registrar', 0, 1, 'C');

// Decorative line
$pdf->SetDrawColor(128, 0, 0);
$pdf->SetLineWidth(0.8);
$pdf->Line(20, $pdf->GetY() + 2, 190, $pdf->GetY() + 2);
$pdf->Ln(6);

// Title
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 8, 'OFFICIAL TRANSCRIPT OF RECORDS', 0, 1, 'C');
$pdf->Ln(4);

// Student info
$pdf->SetFont('helvetica', '', 11);
$lrn_line = !empty($student['lrn']) ? "LRN: {$student['lrn']}\n" : '';
$pdf->MultiCell(0, 6, "Student Name: {$student['full_name']}\nStudent No: {$student['student_no']}\n{$lrn_line}Program: {$program_display}", 0, 'L');
$pdf->Ln(4);

if (empty($records)) {
    $pdf->SetFont('helvetica', 'I', 11);
    $pdf->Cell(0, 10, 'No grade records available for this student.', 0, 1, 'C');
} else {
    $total_units = 0;
    $colW = [30, 80, 15, 20, 25]; // Code, Title, Units, Grade, Remarks

    foreach ($grouped as $group) {
        $sem_label = $semester_labels[$group['semester']] ?? 'Semester ' . $group['semester'];
        $section_title = $group['year_level_name'] . ' - ' . $sem_label;
        if (!empty($group['academic_year'])) {
            $section_title .= '  (' . $group['academic_year'] . ')';
        }

        // Check page space
        if ($pdf->GetY() > 230) {
            $pdf->AddPage();
        }

        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 7, $section_title, 0, 1, 'L');

        // Table header
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetFillColor(0, 32, 96);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell($colW[0], 7, 'Code', 1, 0, 'C', true);
        $pdf->Cell($colW[1], 7, 'Subject Title', 1, 0, 'C', true);
        $pdf->Cell($colW[2], 7, 'Units', 1, 0, 'C', true);
        $pdf->Cell($colW[3], 7, 'Grade', 1, 0, 'C', true);
        $pdf->Cell($colW[4], 7, 'Remarks', 1, 1, 'C', true);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', '', 9);

        $semester_grades = [];
        $rowIndex = 0;
        foreach ($group['subjects'] as $row) {
            $rowIndex++;
            $semester_grades[] = $row;
            $total_units += (float)($row['units'] ?? 0);

            $pdf->SetFillColor(245, 245, 245);
            $fill = ($rowIndex % 2 === 0);

            $pdf->Cell($colW[0], 7, $row['subject_code'] ?? '-', 1, 0, 'L', $fill);
            $pdf->Cell($colW[1], 7, $row['subject_title'] ?? '-', 1, 0, 'L', $fill);
            $pdf->Cell($colW[2], 7, $row['units'] ?? '-', 1, 0, 'C', $fill);
            $pdf->Cell($colW[3], 7, $row['final_grade'] ?? '-', 1, 0, 'C', $fill);
            $pdf->Cell($colW[4], 7, $row['remarks'] ?? '', 1, 1, 'C', $fill);
        }

        // Semester GPA
        $gpa = calculate_gpa($semester_grades);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(array_sum($colW), 6, "Semester GPA: " . number_format($gpa, 2), 0, 1, 'R');
        $pdf->Ln(3);
    }

    // Cumulative summary
    $cumulative_gpa = calculate_gpa($records);
    $standing = get_academic_standing($cumulative_gpa);

    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 7, 'CUMULATIVE SUMMARY', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 11);
    $pdf->MultiCell(0, 6, "Total Units Earned: {$total_units}\nGeneral Weighted Average: " . number_format($cumulative_gpa, 2) . "\nAcademic Standing: {$standing}", 0, 'L');
    $pdf->Ln(3);
}

// Reference & audit
$reference_no = generate_certificate_reference('transcript', $student_id);

$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 6, "Reference No: {$reference_no}", 0, 1, 'L');
$pdf->Cell(0, 6, "Date Issued: " . date('F d, Y'), 0, 1, 'L');

// Signature
$pdf->Ln(12);
$pdf->Cell(0, 0, '', 0, 1); // spacer
$sigX = ($pdf->getPageWidth() - 60) / 2;
$pdf->SetDrawColor(0, 0, 0);
$pdf->Line($sigX, $pdf->GetY(), $sigX + 60, $pdf->GetY());
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(0, 6, 'Registrar', 0, 1, 'C');
$pdf->SetFont('helvetica', 'I', 8);
$pdf->Cell(0, 4, 'This is a system-generated document.', 0, 1, 'C');

// Log and record
log_audit($conn, $_SESSION['user_id'], "Generated transcript for {$student['full_name']} ({$student['student_no']})");

$stmt = $conn->prepare("INSERT INTO certificates_issued (student_id, certificate_type, reference_no, purpose, academic_year, semester, issued_by) VALUES (?, 'transcript', ?, 'Official Transcript', NULL, NULL, ?)");
$stmt->bind_param("isi", $student_id, $reference_no, $_SESSION['user_id']);
$stmt->execute();

if (ob_get_length()) {
    ob_end_clean();
}
$pdf->Output("transcript_{$reference_no}.pdf", 'I');
exit;
