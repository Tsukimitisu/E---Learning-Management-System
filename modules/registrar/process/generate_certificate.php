<?php
ob_start();
require_once '../../../config/init.php';
require_once '../../../vendor/autoload.php';
require_once '../../../vendor/tcpdf/tcpdf.php';
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
$include_grades = (int)($_GET['include_grades'] ?? 1);

if ($student_id <= 0 || !in_array($certificate_type, ['enrollment', 'grade_report', 'completion'], true)) {
    echo '<h3 style="color:red;text-align:center;margin-top:40px;">Invalid request. Please select a student and certificate type.</h3>';
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

// Determine display name for the program/strand
$program_code = $student['program_code'] ?? $student['strand_code'] ?? 'N/A';
$program_name = $student['program_name'] ?? $student['strand_name'] ?? 'N/A';
$program_display = $program_code . ' - ' . $program_name;
$is_shs = ($student['program_type'] === 'shs');

// Get enrollment info for the student
$enrollment = null;
$enroll_result = $conn->query("
    SELECT ste.*, ay.year_name, pyl.year_name as year_level_name
    FROM student_term_enrollments ste
    LEFT JOIN academic_years ay ON ste.academic_year_id = ay.id
    LEFT JOIN program_year_levels pyl ON ste.year_level_id = pyl.id
    WHERE ste.student_id = $student_id AND ste.status = 'enrolled'
    ORDER BY ste.created_at DESC LIMIT 1
");
if ($enroll_result && $enroll_result->num_rows > 0) {
    $enrollment = $enroll_result->fetch_assoc();
}

// If no academic year was provided, use the enrollment's academic year
if (empty($academic_year) && $enrollment) {
    $academic_year = $enrollment['year_name'] ?? '';
}
// If no semester was provided, use the enrollment's semester  
$semester_display = '';
if (!empty($semester)) {
    $semester_display = ($semester == '1') ? '1st Semester' : (($semester == '2') ? '2nd Semester' : $semester . ' Semester');
} elseif ($enrollment) {
    $semester_display = $enrollment['semester'] ? ucfirst($enrollment['semester']) . ' Semester' : '';
}

$reference_no = generate_certificate_reference($certificate_type, $student_id);

// ===================================================================
// Build the PDF
// ===================================================================
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8');
$pdf->SetCreator('ELMS - Datamex');
$pdf->SetAuthor('Registrar Office');
$pdf->SetTitle('Certificate - ' . $student['full_name']);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(20, 15, 20);
$pdf->SetAutoPageBreak(true, 25);
$pdf->AddPage();

// --- HEADER: Logo + Institution ---
$logo_path = PDF_HEADER_LOGO;
if (file_exists($logo_path)) {
    $pdf->Image($logo_path, 85, 12, 40);
    $pdf->SetY(52);
} else {
    $pdf->SetY(20);
}

$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 7, PDF_HEADER_TITLE, 0, 1, 'C');
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(0, 5, 'Office of the Registrar', 0, 1, 'C');
$pdf->Ln(3);

// --- Decorative Line ---
$pdf->SetDrawColor(128, 0, 0);
$pdf->SetLineWidth(0.8);
$pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
$pdf->Ln(8);

// ===================================================================
// ENROLLMENT CERTIFICATE
// ===================================================================
if ($certificate_type === 'enrollment') {
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'CERTIFICATE OF ENROLLMENT', 0, 1, 'C');
    $pdf->Ln(10);

    $pdf->SetFont('helvetica', '', 12);
    $pdf->MultiCell(0, 8, "To Whom It May Concern:\n", 0, 'L');
    $pdf->Ln(2);

    $body = "This is to certify that ";
    $body .= strtoupper($student['full_name']);
    $body .= " with Student No. " . $student['student_no'];
    if ($student['lrn']) {
        $body .= " (LRN: " . $student['lrn'] . ")";
    }
    $body .= " is officially enrolled";
    if ($enrollment) {
        $year_level = $enrollment['year_level_name'] ?? '';
        if ($year_level) $body .= " in $year_level";
    }
    $body .= " under the program:";
    
    $pdf->SetFont('helvetica', '', 12);
    $pdf->MultiCell(0, 7, $body, 0, 'J');
    $pdf->Ln(3);
    
    $pdf->SetFont('helvetica', 'B', 13);
    $pdf->Cell(0, 8, $program_display, 0, 1, 'C');
    $pdf->Ln(3);
    
    $pdf->SetFont('helvetica', '', 12);
    $details = '';
    if ($academic_year) $details .= "Academic Year: $academic_year\n";
    if ($semester_display) $details .= "Semester: $semester_display\n";
    $details .= "\nThis certification is issued upon request for the purpose of: $purpose.";
    $pdf->MultiCell(0, 7, $details, 0, 'L');

// ===================================================================
// GRADE REPORT
// ===================================================================
} elseif ($certificate_type === 'grade_report') {
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'OFFICIAL GRADE REPORT', 0, 1, 'C');
    $pdf->Ln(5);

    $pdf->SetFont('helvetica', '', 11);
    $pdf->Cell(30, 7, 'Student:', 0, 0, 'L');
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 7, strtoupper($student['full_name']) . '  (' . $student['student_no'] . ')', 0, 1, 'L');
    
    $pdf->SetFont('helvetica', '', 11);
    $pdf->Cell(30, 7, 'Program:', 0, 0, 'L');
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 7, $program_display, 0, 1, 'L');

    if ($academic_year) {
        $pdf->SetFont('helvetica', '', 11);
        $pdf->Cell(30, 7, 'A.Y.:', 0, 0, 'L');
        $pdf->Cell(0, 7, $academic_year . ($semester_display ? '  |  ' . $semester_display : ''), 0, 1, 'L');
    }
    $pdf->Ln(5);

    // Fetch grades — link through classes and curriculum_subjects
    $grades_result = $conn->query("
        SELECT cs.subject_code, cs.subject_title, cs.units,
               g.prelim, g.midterm, g.prefinal, g.final, g.final_grade, g.remarks,
               g.semester as grade_semester,
               ay.year_name as grade_ay
        FROM grades g
        LEFT JOIN classes cl ON g.class_id = cl.id
        LEFT JOIN curriculum_subjects cs ON COALESCE(cl.curriculum_subject_id, cl.subject_id, g.subject_id) = cs.id
        LEFT JOIN academic_years ay ON COALESCE(cl.academic_year_id, g.academic_year_id) = ay.id
        WHERE g.student_id = $student_id
        ORDER BY ay.year_name DESC, g.semester, cs.subject_code
    ");
    
    $grades_data = [];
    if ($grades_result) {
        while ($row = $grades_result->fetch_assoc()) {
            $grades_data[] = $row;
        }
    }

    if (count($grades_data) > 0) {
        // Table header
        $pdf->SetFillColor(0, 51, 102);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 9);
        
        $col_widths = [25, 65, 12, 17, 17, 17, 17, 17];
        $headers = ['Code', 'Subject Title', 'Units', 'Prelim', 'Mid', 'PreFin', 'Final', 'Remarks'];
        
        for ($i = 0; $i < count($headers); $i++) {
            $align = ($i <= 1) ? 'L' : 'C';
            $pdf->Cell($col_widths[$i], 7, $headers[$i], 1, 0, $align, true);
        }
        $pdf->Ln();

        // Table body
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', '', 9);
        $fill = false;
        
        foreach ($grades_data as $row) {
            if ($fill) $pdf->SetFillColor(245, 245, 245);
            
            $pdf->Cell($col_widths[0], 6, $row['subject_code'] ?? '-', 1, 0, 'L', $fill);
            $pdf->Cell($col_widths[1], 6, $row['subject_title'] ?? '-', 1, 0, 'L', $fill);
            $pdf->Cell($col_widths[2], 6, $row['units'] ?? '-', 1, 0, 'C', $fill);
            $pdf->Cell($col_widths[3], 6, $row['prelim'] ?? '-', 1, 0, 'C', $fill);
            $pdf->Cell($col_widths[4], 6, $row['midterm'] ?? '-', 1, 0, 'C', $fill);
            $pdf->Cell($col_widths[5], 6, $row['prefinal'] ?? '-', 1, 0, 'C', $fill);
            $pdf->Cell($col_widths[6], 6, $row['final_grade'] ?? '-', 1, 0, 'C', $fill);
            $pdf->Cell($col_widths[7], 6, $row['remarks'] ?? '-', 1, 0, 'C', $fill);
            $pdf->Ln();
            $fill = !$fill;
        }

        // GPA Summary
        $gpa = calculate_gpa($grades_data);
        $standing = get_academic_standing($gpa);
        $pdf->Ln(5);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(60, 7, 'General Weighted Average:', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 11);
        $pdf->Cell(0, 7, number_format($gpa, 2), 0, 1, 'L');
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(60, 7, 'Academic Standing:', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 11);
        $pdf->Cell(0, 7, $standing, 0, 1, 'L');
    } else {
        $pdf->SetFont('helvetica', 'I', 11);
        $pdf->SetTextColor(150, 150, 150);
        $pdf->Cell(0, 10, 'No grade records found for this student.', 0, 1, 'C');
        $pdf->SetTextColor(0, 0, 0);
    }

// ===================================================================
// COMPLETION CERTIFICATE
// ===================================================================
} else {
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'CERTIFICATE OF COMPLETION', 0, 1, 'C');
    $pdf->Ln(10);

    $pdf->SetFont('helvetica', '', 12);
    $pdf->MultiCell(0, 8, "To Whom It May Concern:\n", 0, 'L');
    $pdf->Ln(2);

    // Gather grade data for summary
    $grades_result = $conn->query("
        SELECT cs.units, g.final_grade 
        FROM grades g 
        LEFT JOIN classes cl ON g.class_id = cl.id 
        LEFT JOIN curriculum_subjects cs ON COALESCE(cl.curriculum_subject_id, cl.subject_id, g.subject_id) = cs.id 
        WHERE g.student_id = $student_id
    ");
    $grades_data = [];
    if ($grades_result) {
        while ($row = $grades_result->fetch_assoc()) {
            $grades_data[] = $row;
        }
    }
    
    $gpa = calculate_gpa($grades_data);
    $standing = get_academic_standing($gpa);
    $total_units = array_sum(array_column($grades_data, 'units'));

    $body = "This is to certify that ";
    $body .= strtoupper($student['full_name']);
    $body .= " with Student No. " . $student['student_no'];
    $body .= " has successfully completed all academic requirements for:";
    
    $pdf->SetFont('helvetica', '', 12);
    $pdf->MultiCell(0, 7, $body, 0, 'J');
    $pdf->Ln(3);
    
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 8, $program_name, 0, 1, 'C');
    if ($program_code !== 'N/A') {
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 6, '(' . $program_code . ')', 0, 1, 'C');
    }
    $pdf->Ln(5);
    
    $pdf->SetFont('helvetica', '', 12);
    $summary = '';
    if ($total_units > 0) $summary .= "Total Units Earned: $total_units\n";
    if ($gpa > 0) $summary .= "General Weighted Average: " . number_format($gpa, 2) . "\n";
    if ($gpa > 0) $summary .= "Academic Standing: $standing\n";
    $summary .= "\nThis certification is issued upon request for the purpose of: $purpose.";
    $pdf->MultiCell(0, 7, $summary, 0, 'L');
}

// ===================================================================
// FOOTER: Date, Reference, Signature
// ===================================================================
$pdf->Ln(15);
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 6, 'Issued: ' . date('F d, Y'), 0, 1, 'L');
$pdf->Cell(0, 6, 'Reference No: ' . $reference_no, 0, 1, 'L');

$pdf->Ln(20);
$pdf->SetFont('helvetica', '', 11);
$pdf->Cell(0, 5, '________________________________________', 0, 1, 'C');
$pdf->Cell(0, 6, 'Registrar', 0, 1, 'C');
$pdf->SetFont('helvetica', 'I', 8);
$pdf->Cell(0, 5, 'This is a system-generated document.', 0, 1, 'C');

// ===================================================================
// LOG & SAVE
// ===================================================================
$log_action = "Generated {$certificate_type} certificate for {$student['full_name']} ({$student['student_no']})";
log_audit($conn, $_SESSION['user_id'], $log_action);

if ($semester !== '') {
    $stmt = $conn->prepare("INSERT INTO certificates_issued (student_id, certificate_type, reference_no, purpose, academic_year, semester, issued_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $semester_int = (int)$semester;
    $stmt->bind_param("issssii", $student_id, $certificate_type, $reference_no, $purpose, $academic_year, $semester_int, $_SESSION['user_id']);
} else {
    $stmt = $conn->prepare("INSERT INTO certificates_issued (student_id, certificate_type, reference_no, purpose, academic_year, semester, issued_by) VALUES (?, ?, ?, ?, ?, NULL, ?)");
    $stmt->bind_param("issssi", $student_id, $certificate_type, $reference_no, $purpose, $academic_year, $_SESSION['user_id']);
}
$stmt->execute();

if (ob_get_length()) ob_end_clean();
$pdf->Output("certificate_{$reference_no}.pdf", 'I');
