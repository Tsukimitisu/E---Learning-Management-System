<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_STUDENT) {
    header('Location: ../../index.php');
    exit();
}

$student_id = $_SESSION['user_id'];
$page_title = "Enrollment Status";

// Get current academic year
$current_ay = $conn->query("SELECT id, year_name FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$current_ay_id = $current_ay['id'] ?? 0;

// Get student info
$student = $conn->query("
    SELECT s.student_no, up.*, u.email,
           p.program_name, p.program_code,
           ss.strand_name, ss.strand_code
    FROM students s
    INNER JOIN user_profiles up ON s.user_id = up.user_id
    INNER JOIN users u ON s.user_id = u.id
    LEFT JOIN programs p ON s.course_id = p.id
    LEFT JOIN shs_strands ss ON s.course_id = ss.id
    WHERE s.user_id = $student_id
")->fetch_assoc();

// Get current enrollment status
$enrollment = $conn->query("
    SELECT sst.*, sec.section_name, sec.semester,
           COALESCE(pyl.year_name, sgl.grade_name) as year_level,
           p.program_name, p.program_code,
           ss.strand_name, ss.strand_code,
           ay.year_name
    FROM section_students sst
    INNER JOIN sections sec ON sst.section_id = sec.id
    INNER JOIN academic_years ay ON sec.academic_year_id = ay.id
    LEFT JOIN programs p ON sec.program_id = p.id
    LEFT JOIN shs_strands ss ON sec.shs_strand_id = ss.id
    LEFT JOIN program_year_levels pyl ON sec.year_level_id = pyl.id
    LEFT JOIN shs_grade_levels sgl ON sec.shs_grade_level_id = sgl.id
    WHERE sst.student_id = $student_id AND sec.academic_year_id = $current_ay_id
    ORDER BY sst.enrolled_at DESC
    LIMIT 1
")->fetch_assoc();

// Get enrollment history
$history = $conn->query("
    SELECT sst.*, sec.section_name, sec.semester,
           COALESCE(pyl.year_name, sgl.grade_name) as year_level,
           p.program_name, ss.strand_name, ay.year_name
    FROM section_students sst
    INNER JOIN sections sec ON sst.section_id = sec.id
    INNER JOIN academic_years ay ON sec.academic_year_id = ay.id
    LEFT JOIN programs p ON sec.program_id = p.id
    LEFT JOIN shs_strands ss ON sec.shs_strand_id = ss.id
    LEFT JOIN program_year_levels pyl ON sec.year_level_id = pyl.id
    LEFT JOIN shs_grade_levels sgl ON sec.shs_grade_level_id = sgl.id
    WHERE sst.student_id = $student_id
    ORDER BY ay.year_name DESC, sec.semester DESC
");

include '../../includes/header.php';
?>

<div class="wrapper">
    <?php include '../../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1"><i class="bi bi-clipboard-check me-2"></i>Enrollment Status</h4>
                <small class="text-muted">Academic Year: <?php echo htmlspecialchars($current_ay['year_name'] ?? 'N/A'); ?></small>
            </div>
        </div>

        <div class="row">
            <!-- Student Information -->
            <div class="col-lg-4 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-primary text-white py-3">
                        <h5 class="mb-0"><i class="bi bi-person-circle me-2"></i>Student Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-3">
                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 80px; height: 80px; font-size: 2rem;">
                                <?php echo strtoupper(substr($student['first_name'] ?? 'S', 0, 1)); ?>
                            </div>
                            <h5 class="mb-1"><?php echo htmlspecialchars(($student['first_name'] ?? '') . ' ' . ((!empty($student['middle_name'])) ? $student['middle_name'][0] . '. ' : '') . ($student['last_name'] ?? '')); ?></h5>
                            <span class="badge bg-primary"><?php echo htmlspecialchars($student['student_no'] ?? 'N/A'); ?></span>
                        </div>
                        <hr>
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td class="text-muted">Email:</td>
                                <td><?php echo htmlspecialchars($student['email'] ?? 'N/A'); ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Contact:</td>
                                <td><?php echo htmlspecialchars($student['contact_number'] ?? 'N/A'); ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Program:</td>
                                <td>
                                    <?php 
                                    if ($student['program_name']) {
                                        echo htmlspecialchars($student['program_code'] . ' - ' . $student['program_name']);
                                    } elseif ($student['strand_name']) {
                                        echo htmlspecialchars($student['strand_code'] . ' - ' . $student['strand_name']);
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Current Enrollment -->
            <div class="col-lg-8 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-success text-white py-3">
                        <h5 class="mb-0"><i class="bi bi-check-circle me-2"></i>Current Enrollment</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($enrollment): ?>
                        <div class="alert alert-success border-0 mb-4">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            <strong>Enrolled</strong> - You are currently enrolled for <?php echo htmlspecialchars($enrollment['year_name']); ?>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="text-muted small">Section</label>
                                <div class="fw-bold fs-5"><?php echo htmlspecialchars($enrollment['section_name']); ?></div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="text-muted small">Year Level</label>
                                <div class="fw-bold fs-5"><?php echo htmlspecialchars($enrollment['year_level']); ?></div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="text-muted small">Semester</label>
                                <div class="fw-bold"><?php echo ucfirst($enrollment['semester'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="text-muted small">Program/Strand</label>
                                <div class="fw-bold">
                                    <?php 
                                    echo htmlspecialchars($enrollment['program_name'] ?? $enrollment['strand_name'] ?? 'N/A');
                                    ?>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="text-muted small">Status</label>
                                <div>
                                    <span class="badge bg-<?php echo $enrollment['status'] == 'enrolled' ? 'success' : 'warning'; ?> fs-6">
                                        <?php echo ucfirst($enrollment['status']); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="text-muted small">Enrolled Date</label>
                                <div class="fw-bold"><?php echo date('M d, Y', strtotime($enrollment['enrolled_at'])); ?></div>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-5">
                            <i class="bi bi-exclamation-circle text-warning display-4"></i>
                            <h5 class="mt-3">Not Enrolled</h5>
                            <p class="text-muted">You are not currently enrolled for <?php echo htmlspecialchars($current_ay['year_name'] ?? 'this academic year'); ?>.</p>
                            <p class="text-muted small">Please contact the registrar's office for enrollment assistance.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Enrollment History -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold"><i class="bi bi-clock-history me-2"></i>Enrollment History</h5>
            </div>
            <div class="card-body p-0">
                <?php if ($history->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Academic Year</th>
                                <th>Section</th>
                                <th>Year Level</th>
                                <th>Semester</th>
                                <th>Program/Strand</th>
                                <th>Status</th>
                                <th>Enrolled Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $history->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['year_name']); ?></td>
                                <td><strong><?php echo htmlspecialchars($row['section_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['year_level']); ?></td>
                                <td><?php echo ucfirst($row['semester'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($row['program_name'] ?? $row['strand_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $row['status'] == 'enrolled' ? 'success' : 'warning'; ?>">
                                        <?php echo ucfirst($row['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($row['enrolled_at'])); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-4 text-muted">
                    <i class="bi bi-inbox"></i>
                    <p class="mb-0">No enrollment history found</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
