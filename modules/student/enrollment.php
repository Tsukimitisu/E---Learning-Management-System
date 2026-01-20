<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_STUDENT) {
    header('Location: ../../index.php');
    exit();
}

$student_id = $_SESSION['user_id'];
$page_title = "Enrollment Status";

/** 
 * BACKEND LOGIC - UNTOUCHED 
 */
$current_ay = $conn->query("SELECT id, year_name FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$current_ay_id = $current_ay['id'] ?? 0;

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
include '../../includes/sidebar.php'; 
?>

<style>
    /* --- SCROLL & LAYOUT ENGINE --- */
    html, body { height: 100%; margin: 0; overflow: hidden; }
    #content { height: 100vh; display: flex; flex-direction: column; overflow: hidden; }
    .header-fixed-part { flex: 0 0 auto; background: white; padding: 15px 30px; border-bottom: 1px solid #eee; z-index: 10; }
    .body-scroll-part { flex: 1 1 auto; overflow-y: auto; padding: 25px 30px 100px 30px; background-color: #f8f9fa; }

    /* --- FANTASTIC ENROLLMENT UI --- */
    .student-info-card {
        background: white; border-radius: 20px; border: none;
        box-shadow: 0 5px 20px rgba(0,0,0,0.05); overflow: hidden;
    }
    .profile-banner { background: var(--blue); height: 80px; }
    .profile-avatar-wrapper { margin-top: -40px; position: relative; z-index: 2; }

    .current-status-card {
        background: white; border-radius: 20px; border: none;
        box-shadow: 0 10px 30px rgba(0,0,0,0.08); position: relative; overflow: hidden;
    }
    .status-ribbon {
        position: absolute; top: 20px; right: -35px; transform: rotate(45deg);
        width: 150px; text-align: center; font-weight: 800; font-size: 0.7rem;
        padding: 5px; color: white; text-transform: uppercase; letter-spacing: 1px;
    }

    .info-label { font-size: 0.65rem; font-weight: 800; color: #888; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 2px; }
    .info-value { font-weight: 700; color: var(--blue); font-size: 1.1rem; }

    .history-card { background: white; border-radius: 20px; border: none; box-shadow: 0 5px 20px rgba(0,0,0,0.05); overflow: hidden; }
    .table-modern thead th { background: var(--blue); color: white; font-size: 0.7rem; text-transform: uppercase; padding: 15px 20px; }
    .table-modern tbody td { padding: 15px 20px; vertical-align: middle; border-bottom: 1px solid #f1f1f1; font-size: 0.9rem; }

    .btn-contact { background: var(--maroon); color: white; border: none; border-radius: 10px; font-weight: 700; padding: 12px; transition: 0.3s; }
    .btn-contact:hover { background: #600000; color: white; transform: translateY(-3px); }

    @media (max-width: 768px) {
        .header-fixed-part { flex-direction: column; gap: 10px; text-align: center; }
    }
</style>

<!-- Part 1: Fixed Header -->
<div class="header-fixed-part animate__animated animate__fadeInDown">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-0" style="color: var(--blue);"><i class="bi bi-clipboard-check-fill me-2 text-maroon"></i>Enrollment Records</h4>
            <p class="text-muted small mb-0">Current academic status and historical data</p>
        </div>
        <div class="text-end">
            <span class="badge bg-light text-dark border px-3 py-2 rounded-pill shadow-sm small">
                <i class="bi bi-calendar3 me-1"></i> AY: <?php echo htmlspecialchars($current_ay['year_name'] ?? 'N/A'); ?>
            </span>
        </div>
    </div>
</div>

<!-- Part 2: Scrollable Body -->
<div class="body-scroll-part">
    
    <div class="row g-4">
        <!-- Left: Student Info -->
        <div class="col-lg-4 animate__animated animate__fadeInLeft">
            <div class="student-info-card">
                <div class="profile-banner"></div>
                <div class="card-body text-center p-4">
                    <div class="profile-avatar-wrapper">
                        <div class="avatar-circle mx-auto border-4 border-white shadow" style="width: 90px; height: 90px; font-size: 2.5rem;">
                            <?php echo strtoupper(substr($student['first_name'] ?? 'S', 0, 1)); ?>
                        </div>
                    </div>
                    <h5 class="fw-bold mt-3 mb-1 text-dark">
                        <?php echo htmlspecialchars(($student['first_name'] ?? '') . ' ' . ((!empty($student['middle_name'])) ? $student['middle_name'][0] . '. ' : '') . ($student['last_name'] ?? '')); ?>
                    </h5>
                    <span class="badge bg-blue rounded-pill px-3 mb-4">SN: <?php echo htmlspecialchars($student['student_no'] ?? 'N/A'); ?></span>
                    
                    <div class="text-start space-y-3">
                        <div class="mb-3">
                            <label class="info-label">Official Email</label>
                            <div class="small fw-bold text-dark text-truncate"><?php echo htmlspecialchars($student['email'] ?? 'N/A'); ?></div>
                        </div>
                        <div class="mb-3">
                            <label class="info-label">Contact Number</label>
                            <div class="small fw-bold text-dark"><?php echo htmlspecialchars($student['contact_number'] ?? 'N/A'); ?></div>
                        </div>
                        <div class="mb-0">
                            <label class="info-label">Enrolled Program</label>
                            <div class="small fw-bold text-maroon">
                                <?php 
                                if ($student['program_name']) { echo htmlspecialchars($student['program_code'] . ' - ' . $student['program_name']); } 
                                elseif ($student['strand_name']) { echo htmlspecialchars($student['strand_code'] . ' - ' . $student['strand_name']); } 
                                else { echo 'No program data'; }
                                ?>
                            </div>
                        </div>
                    </div>
                    <hr class="my-4 opacity-50">
                    <button class="btn btn-contact w-100 shadow-sm" onclick="location.href='profile.php'">
                        <i class="bi bi-person-badge me-2"></i> View Full Profile
                    </button>
                </div>
            </div>
        </div>

        <!-- Right: Current Enrollment -->
        <div class="col-lg-8 animate__animated animate__fadeInRight">
            <div class="current-status-card p-4 p-md-5 h-100">
                <?php if ($enrollment): ?>
                    <div class="status-ribbon bg-success shadow-sm">ENROLLED</div>
                    
                    <div class="d-flex align-items-center mb-5">
                        <div class="rounded-circle bg-success bg-opacity-10 text-success d-flex align-items-center justify-content-center me-3" style="width: 60px; height: 60px;">
                            <i class="bi bi-check-all fs-1"></i>
                        </div>
                        <div>
                            <h4 class="fw-bold mb-0 text-success">Registration Validated</h4>
                            <p class="text-muted small mb-0">Active student for <?php echo htmlspecialchars($enrollment['year_name']); ?></p>
                        </div>
                    </div>

                    <div class="row g-4 mb-2">
                        <div class="col-md-6">
                            <label class="info-label">Assigned Section</label>
                            <div class="info-value"><?php echo htmlspecialchars($enrollment['section_name']); ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="info-label">Year Level / Grade</label>
                            <div class="info-value"><?php echo htmlspecialchars($enrollment['year_level']); ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="info-label">Current Semester</label>
                            <div class="info-value"><?php echo ucfirst($enrollment['semester'] ?? 'N/A'); ?> Term</div>
                        </div>
                        <div class="col-md-6">
                            <label class="info-label">School Branch</label>
                            <div class="info-value"><?php echo htmlspecialchars($enrollment['branch_name'] ?? 'Main Campus'); ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="info-label">Registration Status</label>
                            <div><span class="badge bg-success px-4 py-2 rounded-pill"><?php echo strtoupper($enrollment['status']); ?></span></div>
                        </div>
                        <div class="col-md-6">
                            <label class="info-label">Validation Date</label>
                            <div class="info-value text-muted small"><i class="bi bi-calendar-check me-2"></i><?php echo date('F d, Y', strtotime($enrollment['enrolled_at'])); ?></div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-exclamation-octagon text-warning display-1 opacity-25"></i>
                        <h4 class="fw-bold mt-4">Enrollment Data Missing</h4>
                        <p class="text-muted">You are not recorded as an active student for the current academic year: <strong><?php echo htmlspecialchars($current_ay['year_name'] ?? 'N/A'); ?></strong></p>
                        <div class="alert bg-light border-0 mt-4 small">
                            <i class="bi bi-info-circle me-2 text-blue"></i>
                            If you have recently paid your tuition, please allow 24-48 hours for the Registrar to validate your section assignment.
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Bottom: History -->
        <div class="col-12 animate__animated animate__fadeInUp" style="animation-delay: 0.2s;">
            <div class="history-card">
                <div class="card-header-modern bg-white p-4">
                    <h6 class="fw-bold mb-0 text-blue"><i class="bi bi-clock-history me-2 text-maroon"></i>Enrollment History</h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-modern align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">Academic Period</th>
                                <th>Section & Level</th>
                                <th>Term</th>
                                <th>Program/Strand</th>
                                <th class="text-center">Status</th>
                                <th class="pe-4">Validation Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($history->num_rows > 0): ?>
                                <?php while ($row = $history->fetch_assoc()): ?>
                                <tr>
                                    <td class="ps-4 fw-bold text-dark"><?php echo htmlspecialchars($row['year_name']); ?></td>
                                    <td>
                                        <div class="fw-bold text-blue"><?php echo htmlspecialchars($row['section_name']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($row['year_level']); ?></small>
                                    </td>
                                    <td><span class="badge bg-light text-dark border small"><?php echo ucfirst($row['semester'] ?? 'N/A'); ?></span></td>
                                    <td><small class="text-muted fw-bold"><?php echo htmlspecialchars($row['program_name'] ?? $row['strand_name'] ?? 'N/A'); ?></small></td>
                                    <td class="text-center">
                                        <span class="badge rounded-pill bg-<?php echo $row['status'] == 'enrolled' ? 'success' : 'warning'; ?> px-3">
                                            <?php echo strtoupper($row['status']); ?>
                                        </span>
                                    </td>
                                    <td class="pe-4 small text-muted"><?php echo date('M d, Y', strtotime($row['enrolled_at'])); ?></td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="text-center py-5 text-muted">No historical records available.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>

<?php include '../../includes/footer.php'; ?>