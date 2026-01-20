<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_STUDENT) {
    header('Location: ../../index.php');
    exit();
}

$student_id = $_SESSION['user_id'];
$page_title = "Class Schedule";

/** 
 * BACKEND LOGIC - UNTOUCHED 
 */
$current_ay = $conn->query("SELECT id, year_name FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$current_ay_id = $current_ay['id'] ?? 0;

$section = $conn->query("
    SELECT sst.*, sec.*, 
           p.program_name, p.program_code,
           ss.strand_name, ss.strand_code
    FROM section_students sst
    INNER JOIN sections sec ON sst.section_id = sec.id
    LEFT JOIN programs p ON sec.program_id = p.id
    LEFT JOIN shs_strands ss ON sec.shs_strand_id = ss.id
    WHERE sst.student_id = $student_id AND sec.academic_year_id = $current_ay_id
    LIMIT 1
")->fetch_assoc();

$classes = [];
if ($section) {
    $section_name = $section['section_name'];
    $classes_query = $conn->query("
        SELECT cl.*, cs.subject_code, cs.subject_title, cs.units,
               CONCAT(up.first_name, ' ', up.last_name) as teacher_name
        FROM classes cl
        INNER JOIN curriculum_subjects cs ON cl.curriculum_subject_id = cs.id
        LEFT JOIN user_profiles up ON cl.teacher_id = up.user_id
        WHERE cl.section_name = '$section_name' AND cl.academic_year_id = $current_ay_id
        ORDER BY cl.schedule
    ");
    while ($row = $classes_query->fetch_assoc()) { $classes[] = $row; }
}

$subjects = [];
if ($section) {
    $branch_id = $section['branch_id'];
    $program_id = $section['program_id'] ?? 0;
    $strand_id = $section['shs_strand_id'] ?? 0;
    $where_conditions = [];
    if ($program_id > 0) { $where_conditions[] = "cs.program_id = $program_id"; }
    if ($strand_id > 0) { $where_conditions[] = "cs.shs_strand_id = $strand_id"; }
    
    if (!empty($where_conditions)) {
        $program_filter = "(" . implode(" OR ", $where_conditions) . ")";
        $subjects_query = $conn->query("
            SELECT tsa.*, cs.subject_code, cs.subject_title, cs.units,
                   cs.lecture_hours, cs.lab_hours,
                   CONCAT(up.first_name, ' ', up.last_name) as teacher_name
            FROM teacher_subject_assignments tsa
            INNER JOIN curriculum_subjects cs ON tsa.curriculum_subject_id = cs.id
            LEFT JOIN user_profiles up ON tsa.teacher_id = up.user_id
            WHERE tsa.branch_id = $branch_id 
              AND tsa.academic_year_id = $current_ay_id
              AND tsa.is_active = 1
              AND $program_filter
            ORDER BY cs.subject_code
        ");
        while ($row = $subjects_query->fetch_assoc()) { $subjects[] = $row; }
    }
}

include '../../includes/header.php';
include '../../includes/sidebar.php'; 
?>

<style>
    /* --- SCROLL & LAYOUT ENGINE --- */
    html, body { height: 100%; margin: 0; overflow: hidden; }
    #content { height: 100vh; display: flex; flex-direction: column; overflow: hidden; }
    .header-fixed-part { flex: 0 0 auto; background: white; padding: 15px 30px; border-bottom: 1px solid #eee; z-index: 10; }
    .body-scroll-part { flex: 1 1 auto; overflow-y: auto; padding: 25px 30px 100px 30px; background-color: #f8f9fa; }

    /* --- FANTASTIC SCHEDULE UI --- */
    .section-info-card {
        background: white; border-radius: 15px; border: none;
        box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        border-left: 6px solid var(--maroon);
        overflow: hidden; margin-bottom: 30px;
    }

    .table-modern { background: white; border-radius: 15px; overflow: hidden; box-shadow: 0 5px 20px rgba(0,0,0,0.05); }
    .table-modern thead th { 
        background: var(--blue); color: white; font-size: 0.75rem; 
        text-transform: uppercase; letter-spacing: 1px; padding: 15px 20px; border: none;
    }
    .table-modern tbody td { padding: 15px 20px; vertical-align: middle; border-bottom: 1px solid #f1f1f1; }

    .schedule-badge { background: #e7f5ff; color: var(--blue); font-weight: 700; padding: 5px 12px; border-radius: 6px; font-size: 0.8rem; }
    .unit-badge { background: #fff5f5; color: var(--maroon); font-weight: 800; padding: 5px 10px; border-radius: 6px; }

    /* Mobile handling */
    @media (max-width: 768px) {
        .header-fixed-part { flex-direction: column; gap: 10px; text-align: center; }
        .section-info-card .row > div { border: none !important; margin-bottom: 15px; text-align: center; }
    }
</style>

<!-- Part 1: Fixed Header -->
<div class="header-fixed-part d-flex justify-content-between align-items-center animate__animated animate__fadeInDown">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1" style="font-size: 0.7rem; font-weight: 700; text-transform: uppercase;">
                <li class="breadcrumb-item"><a href="dashboard.php" style="color: var(--maroon); text-decoration: none;">Student Portal</a></li>
                <li class="breadcrumb-item active">Schedule</li>
            </ol>
        </nav>
        <h4 class="fw-bold mb-0" style="color: var(--blue);"><i class="bi bi-calendar-week-fill me-2"></i>Registration & Schedule</h4>
    </div>
    <div class="text-end">
        <span class="badge bg-light text-dark border px-3 py-2 shadow-sm">
            AY: <?php echo htmlspecialchars($current_ay['year_name'] ?? 'N/A'); ?>
        </span>
    </div>
</div>

<!-- Part 2: Scrollable Body -->
<div class="body-scroll-part">
    
    <?php if (!$section): ?>
    <div class="alert bg-white border-start border-warning border-4 shadow-sm p-4 animate__animated animate__shakeX">
        <div class="d-flex align-items-center">
            <i class="bi bi-exclamation-triangle-fill text-warning fs-3 me-3"></i>
            <div>
                <h6 class="fw-bold mb-1">Section Not Assigned</h6>
                <p class="mb-0 small text-muted">You are not currently enrolled in any section. Please visit the Registrar's Office to finalize your enrollment.</p>
            </div>
        </div>
    </div>
    <?php else: ?>

    <!-- Section Identity Card -->
    <div class="section-info-card p-4 animate__animated animate__fadeInUp">
        <div class="row align-items-center text-center text-md-start">
            <div class="col-md-3 border-end">
                <small class="text-muted text-uppercase fw-bold opacity-50" style="font-size: 0.65rem;">Current Section</small>
                <div class="h2 fw-extrabold mb-0 text-maroon"><?php echo htmlspecialchars($section['section_name']); ?></div>
            </div>
            <div class="col-md-4 border-end px-md-4">
                <small class="text-muted text-uppercase fw-bold opacity-50" style="font-size: 0.65rem;">Academic Program</small>
                <div class="fw-bold text-dark"><?php echo htmlspecialchars($section['program_name'] ?? $section['strand_name'] ?? 'N/A'); ?></div>
                <small class="text-muted"><?php echo htmlspecialchars($section['program_code'] ?? $section['strand_code'] ?? ''); ?></small>
            </div>
            <div class="col-md-3 border-end px-md-4">
                <small class="text-muted text-uppercase fw-bold opacity-50" style="font-size: 0.65rem;">Year & Semester</small>
                <div class="fw-bold text-dark"><?php echo htmlspecialchars($section['year_level'] ?? 'N/A'); ?></div>
                <span class="badge bg-blue small"><?php echo ucfirst($section['semester'] ?? 'N/A'); ?> Semester</span>
            </div>
            <div class="col-md-2 text-center">
                <i class="bi bi-qr-code-scan display-6 text-muted opacity-25"></i>
            </div>
        </div>
    </div>

    <!-- Master Schedule Table -->
    <?php if (!empty($classes)): ?>
    <h6 class="fw-bold mb-3 text-uppercase small opacity-75" style="letter-spacing: 1px;"><i class="bi bi-clock-fill me-2 text-maroon"></i>Weekly Class Times</h6>
    <div class="table-modern mb-5 animate__animated animate__fadeInUp" style="animation-delay: 0.1s;">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Subject</th>
                        <th class="text-center">Units</th>
                        <th>Schedule</th>
                        <th>Room</th>
                        <th>Instructor</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($classes as $class): ?>
                    <tr>
                        <td>
                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($class['subject_code']); ?></div>
                            <small class="text-muted"><?php echo htmlspecialchars($class['subject_title']); ?></small>
                        </td>
                        <td class="text-center"><span class="unit-badge"><?php echo $class['units']; ?></span></td>
                        <td>
                            <span class="schedule-badge">
                                <i class="bi bi-clock me-1"></i><?php echo $class['schedule'] ?: 'TBA'; ?>
                            </span>
                        </td>
                        <td><span class="text-primary fw-semibold"><i class="bi bi-geo-alt me-1"></i><?php echo $class['room'] ?: 'TBA'; ?></span></td>
                        <td>
                            <small class="fw-bold text-muted">
                                <i class="bi bi-person-badge me-1"></i><?php echo htmlspecialchars($class['teacher_name'] ?: 'To be assigned'); ?>
                            </small>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Enrolled Curriculum List -->
    <h6 class="fw-bold mb-3 text-uppercase small opacity-75" style="letter-spacing: 1px;"><i class="bi bi-journal-check me-2 text-maroon"></i>Enrolled Curriculum Subjects</h6>
    <div class="table-modern animate__animated animate__fadeInUp" style="animation-delay: 0.2s;">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr style="background: #f8f9fa;">
                        <th class="ps-4">Code</th>
                        <th>Title</th>
                        <th class="text-center">Units</th>
                        <th class="text-center">Hours (Lec/Lab)</th>
                        <th>Assigned Teacher</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total_units = 0;
                    if (empty($subjects)): ?>
                        <tr><td colspan="5" class="text-center py-5 text-muted">No specific curriculum subjects listed for this section.</td></tr>
                    <?php else: 
                        foreach ($subjects as $subject): 
                        $total_units += $subject['units'];
                    ?>
                    <tr>
                        <td class="ps-4"><span class="badge bg-dark text-maroon border border-maroon"><?php echo htmlspecialchars($subject['subject_code']); ?></span></td>
                        <td class="fw-bold text-dark"><?php echo htmlspecialchars($subject['subject_title']); ?></td>
                        <td class="text-center fw-bold"><?php echo $subject['units']; ?></td>
                        <td class="text-center"><small class="text-muted"><?php echo $subject['lecture_hours']; ?> / <?php echo $subject['lab_hours']; ?></small></td>
                        <td>
                            <small class="text-muted italic">
                                <?php echo $subject['teacher_name'] ? '<i class="bi bi-person me-1"></i>'.htmlspecialchars($subject['teacher_name']) : 'TBA'; ?>
                            </small>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr class="fw-bold" style="background: #fcfcfc;">
                        <td colspan="2" class="text-end ps-4 text-uppercase small">Total Academic Units Enrolled:</td>
                        <td class="text-center"><span class="h5 mb-0 fw-bold text-maroon"><?php echo $total_units; ?></span></td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>