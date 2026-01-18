<?php
require_once '../../../config/init.php';
require_once '../../../vendor/autoload.php';
require_once '../../../config/tcpdf_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_REGISTRAR) {
    header('Location: ../../../index.php');
    exit();
}

$student_id = (int)($_GET['student_id'] ?? 0);
$certificate_type = clean_input($_GET['certificate_type'] ?? 'enrollment');
$academic_year = clean_input($_GET['academic_year'] ?? '');
$semester = clean_input($_GET['semester'] ?? '');
$purpose = clean_input($_GET['purpose'] ?? 'For Records');

if ($student_id <= 0 || !in_array($certificate_type, ['enrollment', 'grade_report', 'completion'], true)) {
    echo 'Invalid request';
    exit();
}

$student = $conn->query("
    SELECT s.student_no, CONCAT(up.first_name, ' ', up.last_name) as full_name,
           c.course_code, c.title as course_title
    FROM students s
    INNER JOIN user_profiles up ON s.user_id = up.user_id
    LEFT JOIN courses c ON s.course_id = c.id
    WHERE s.user_id = $student_id
")->fetch_assoc();

$reference_no = generate_certificate_reference($certificate_type, $student_id);

$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8');
$pdf->SetCreator('ELMS - Datamex');
$pdf->SetAuthor('Registrar Office');
$pdf->SetTitle('Certificate');
$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
$pdf->SetAutoPageBreak(true, 25);
$pdf->AddPage();

$pdf->Image(PDF_HEADER_LOGO, 85, 10, 40);
$pdf->SetY(55);
$pdf->SetFont(PDF_FONT_NAME_MAIN, 'B', 16);
$pdf->Cell(0, 10, PDF_HEADER_TITLE, 0, 1, 'C');
$pdf->SetFont(PDF_FONT_NAME_MAIN, '', 12);

if ($certificate_type === 'enrollment') {
    $pdf->Cell(0, 10, 'Enrollment Certificate', 0, 1, 'C');
} elseif ($certificate_type === 'grade_report') {
    $pdf->Cell(0, 10, 'Official Grade Report', 0, 1, 'C');
} else {
    $pdf->Cell(0, 10, 'Certificate of Completion', 0, 1, 'C');
}

$pdf->Ln(5);
$pdf->SetFont(PDF_FONT_NAME_MAIN, '', 12);

$pdf->MultiCell(0, 7, "This is to certify that:\n\n" . strtoupper($student['full_name']) . "\nStudent No: {$student['student_no']}\n\n", 0, 'L');

if ($certificate_type === 'enrollment') {
    $pdf->MultiCell(0, 7, "is officially enrolled in:\n" . ($student['course_code'] ?? 'N/A') . " - " . ($student['course_title'] ?? 'N/A') . "\nAcademic Year: {$academic_year}\nSemester: {$semester}\n\nPurpose: {$purpose}\n", 0, 'L');
} elseif ($certificate_type === 'grade_report') {
    $grades = $conn->query("
        SELECT cs.subject_code, cs.subject_title, cs.units, g.final_grade, g.remarks
        FROM grades g
        INNER JOIN classes cl ON g.class_id = cl.id
        LEFT JOIN curriculum_subjects cs ON cl.curriculum_subject_id = cs.id
        WHERE g.student_id = $student_id
    ");

    $pdf->MultiCell(0, 7, "Program: " . ($student['course_code'] ?? 'N/A') . " - " . ($student['course_title'] ?? 'N/A') . "\nAcademic Year: {$academic_year}\nSemester: {$semester}\n\n", 0, 'L');
    $pdf->SetFont(PDF_FONT_NAME_MAIN, 'B', 11);
    $pdf->Cell(35, 8, 'Subject', 1);
    $pdf->Cell(85, 8, 'Title', 1);
    $pdf->Cell(15, 8, 'Units', 1);
    $pdf->Cell(25, 8, 'Grade', 1);
    $pdf->Cell(25, 8, 'Remarks', 1);
    $pdf->Ln();

    $pdf->SetFont(PDF_FONT_NAME_MAIN, '', 10);
    $grades_data = [];
    while ($row = $grades->fetch_assoc()) {
        $grades_data[] = $row;
        $pdf->Cell(35, 8, $row['subject_code'], 1);
        $pdf->Cell(85, 8, $row['subject_title'], 1);
        $pdf->Cell(15, 8, $row['units'], 1);
        $pdf->Cell(25, 8, $row['final_grade'], 1);
        $pdf->Cell(25, 8, $row['remarks'], 1);
        $pdf->Ln();
    }

    $gpa = calculate_gpa($grades_data);
    $standing = get_academic_standing($gpa);
    $pdf->Ln(5);
    $pdf->MultiCell(0, 7, "GPA: {$gpa}\nAcademic Standing: {$standing}", 0, 'L');
} else {
    $grades = $conn->query("SELECT cs.units, g.final_grade FROM grades g INNER JOIN classes cl ON g.class_id = cl.id LEFT JOIN curriculum_subjects cs ON cl.curriculum_subject_id = cs.id WHERE g.student_id = $student_id");
    $grades_data = [];
    while ($row = $grades->fetch_assoc()) {
        $grades_data[] = $row;
    }
    $gpa = calculate_gpa($grades_data);
    $standing = get_academic_standing($gpa);
    $units = array_sum(array_column($grades_data, 'units'));

    $pdf->MultiCell(0, 7, "has successfully completed all academic requirements for:\n" . ($student['course_title'] ?? 'N/A') . "\n\nTotal Units Earned: {$units}\nGeneral Weighted Average: {$gpa}\nAcademic Standing: {$standing}\n", 0, 'L');
}

$pdf->Ln(10);
$pdf->MultiCell(0, 7, "Issued: " . date('F d, Y') . "\nReference No: {$reference_no}", 0, 'L');

$pdf->SetY(-40);
$pdf->Cell(0, 10, '_____________________', 0, 1, 'C');
$pdf->Cell(0, 5, 'Registrar Signature', 0, 1, 'C');

$log_action = "Generated {$certificate_type} certificate for {$student['full_name']} ({$student['student_no']})";
log_audit($conn, $_SESSION['user_id'], $log_action);

$stmt = $conn->prepare("INSERT INTO certificates_issued (student_id, certificate_type, reference_no, purpose, academic_year, semester, issued_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
$semester_val = $semester !== '' ? (int)$semester : null;
$stmt->bind_param("issssii", $student_id, $certificate_type, $reference_no, $purpose, $academic_year, $semester_val, $_SESSION['user_id']);
$stmt->execute();

$pdf->Output("certificate_{$reference_no}.pdf", 'I');
