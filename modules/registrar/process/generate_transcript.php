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
    echo 'Invalid student ID';
    exit();
}

$student = $conn->query("
    SELECT s.student_no, CONCAT(up.first_name, ' ', up.last_name) as full_name,
           c.title as course_title
    FROM students s
    INNER JOIN user_profiles up ON s.user_id = up.user_id
    LEFT JOIN courses c ON s.course_id = c.id
    WHERE s.user_id = $student_id
")->fetch_assoc();

$grades_result = $conn->query("
    SELECT 
        ay.year_name as academic_year,
        cs.semester,
        cs.subject_code,
        cs.subject_title,
        cs.units,
        g.final_grade,
        g.remarks
    FROM grades g
    INNER JOIN classes cl ON g.class_id = cl.id
    INNER JOIN curriculum_subjects cs ON cl.curriculum_subject_id = cs.id
    LEFT JOIN academic_years ay ON cl.academic_year_id = ay.id
    WHERE g.student_id = $student_id
    ORDER BY ay.year_name, cs.semester, cs.subject_code
");

$records = [];
while ($row = $grades_result->fetch_assoc()) {
    $records[] = $row;
}

$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8');
$pdf->SetCreator('ELMS - Datamex');
$pdf->SetAuthor('Registrar Office');
$pdf->SetTitle('Transcript of Records');
$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
$pdf->SetAutoPageBreak(true, 25);
$pdf->AddPage();

$pdf->Image(PDF_HEADER_LOGO, 85, 10, 40);
$pdf->SetY(55);
$pdf->SetFont(PDF_FONT_NAME_MAIN, 'B', 16);
$pdf->Cell(0, 10, PDF_HEADER_TITLE, 0, 1, 'C');
$pdf->SetFont(PDF_FONT_NAME_MAIN, 'B', 14);
$pdf->Cell(0, 8, 'OFFICIAL TRANSCRIPT OF RECORDS', 0, 1, 'C');
$pdf->Ln(3);

$pdf->SetFont(PDF_FONT_NAME_MAIN, '', 11);
$pdf->MultiCell(0, 6, "Student Name: {$student['full_name']}\nStudent No: {$student['student_no']}\nProgram: {$student['course_title']}\n", 0, 'L');

$current_year = '';
$current_sem = '';
$semester_grades = [];
$total_units = 0;

foreach ($records as $row) {
    if ($current_year !== $row['academic_year'] || $current_sem !== $row['semester']) {
        if (!empty($semester_grades)) {
            $gpa = calculate_gpa($semester_grades);
            $pdf->Cell(0, 6, "Semester GPA: {$gpa}", 0, 1, 'R');
            $pdf->Ln(3);
        }
        $current_year = $row['academic_year'] ?? 'N/A';
        $current_sem = $row['semester'] ?? 'N/A';
        $semester_grades = [];

        $pdf->SetFont(PDF_FONT_NAME_MAIN, 'B', 12);
        $pdf->Cell(0, 7, "{$current_year} - Semester {$current_sem}", 0, 1, 'L');
        $pdf->SetFont(PDF_FONT_NAME_MAIN, 'B', 10);
        $pdf->Cell(30, 7, 'Code', 1);
        $pdf->Cell(90, 7, 'Subject Title', 1);
        $pdf->Cell(15, 7, 'Units', 1);
        $pdf->Cell(20, 7, 'Grade', 1);
        $pdf->Cell(25, 7, 'Remarks', 1);
        $pdf->Ln();
        $pdf->SetFont(PDF_FONT_NAME_MAIN, '', 10);
    }

    $semester_grades[] = $row;
    $total_units += (float)$row['units'];

    $pdf->Cell(30, 7, $row['subject_code'], 1);
    $pdf->Cell(90, 7, $row['subject_title'], 1);
    $pdf->Cell(15, 7, $row['units'], 1);
    $pdf->Cell(20, 7, $row['final_grade'], 1);
    $pdf->Cell(25, 7, $row['remarks'], 1);
    $pdf->Ln();
}

if (!empty($semester_grades)) {
    $gpa = calculate_gpa($semester_grades);
    $pdf->Cell(0, 6, "Semester GPA: {$gpa}", 0, 1, 'R');
    $pdf->Ln(3);
}

$cumulative_gpa = calculate_gpa($records);
$standing = get_academic_standing($cumulative_gpa);
$reference_no = generate_certificate_reference('transcript', $student_id);

$pdf->SetFont(PDF_FONT_NAME_MAIN, 'B', 11);
$pdf->Cell(0, 7, "CUMULATIVE SUMMARY", 0, 1, 'L');
$pdf->SetFont(PDF_FONT_NAME_MAIN, '', 11);
$pdf->MultiCell(0, 6, "Total Units Earned: {$total_units}\nGeneral Weighted Average: {$cumulative_gpa}\nAcademic Standing: {$standing}\nReference No: {$reference_no}\nDate Issued: " . date('F d, Y'), 0, 'L');

$pdf->SetY(-40);
$pdf->Cell(0, 10, '_____________________', 0, 1, 'C');
$pdf->Cell(0, 5, 'Registrar Signature', 0, 1, 'C');

log_audit($conn, $_SESSION['user_id'], "Generated transcript for {$student['full_name']} ({$student['student_no']})");

$stmt = $conn->prepare("INSERT INTO certificates_issued (student_id, certificate_type, reference_no, purpose, academic_year, semester, issued_by) VALUES (?, 'transcript', ?, 'Official Transcript', NULL, NULL, ?)");
$stmt->bind_param("isi", $student_id, $reference_no, $_SESSION['user_id']);
$stmt->execute();

if (ob_get_length()) {
    ob_end_clean();
}
$pdf->Output("transcript_{$reference_no}.pdf", 'I');
exit;
