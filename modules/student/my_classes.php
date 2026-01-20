<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_STUDENT) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "My Classes";
$student_id = $_SESSION['user_id'];

/** 
 * BACKEND LOGIC - UNTOUCHED 
 */
$current_ay = $conn->query("SELECT id, year_name FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$current_ay_id = $current_ay['id'] ?? 0;

$section_info = $conn->query("
    SELECT s.*, 
           COALESCE(p.program_name, ss.strand_name) as program_name,
           COALESCE(p.program_code, ss.strand_code) as program_code,
           COALESCE(pyl.year_name, sgl.grade_name) as year_level,
           b.name as branch_name,
           CONCAT(advup.first_name, ' ', advup.last_name) as adviser_name
    FROM section_students stu
    INNER JOIN sections s ON stu.section_id = s.id
    LEFT JOIN programs p ON s.program_id = p.id
    LEFT JOIN shs_strands ss ON s.shs_strand_id = ss.id
    LEFT JOIN program_year_levels pyl ON s.year_level_id = pyl.id
    LEFT JOIN shs_grade_levels sgl ON s.shs_grade_level_id = sgl.id
    LEFT JOIN branches b ON s.branch_id = b.id
    LEFT JOIN user_profiles advup ON s.adviser_id = advup.user_id
    WHERE stu.student_id = $student_id AND stu.status = 'active' AND s.academic_year_id = $current_ay_id
    LIMIT 1
")->fetch_assoc();

$section_id = $section_info['id'] ?? 0;

$subjects = [];
if ($section_id > 0) {
    $subjects_query = $conn->query("
        SELECT cs.*, 
               tsa.teacher_id,
               CONCAT(up.first_name, ' ', up.last_name) as teacher_name,
               u.email as teacher_email,
               (SELECT COUNT(*) FROM learning_materials lm WHERE lm.class_id = cs.id) as materials_count
        FROM teacher_subject_assignments tsa
        INNER JOIN curriculum_subjects cs ON tsa.curriculum_subject_id = cs.id
        LEFT JOIN user_profiles up ON tsa.teacher_id = up.user_id
        LEFT JOIN users u ON tsa.teacher_id = u.id
        WHERE tsa.branch_id = " . ($section_info['branch_id'] ?? 0) . "
        AND tsa.academic_year_id = $current_ay_id
        AND tsa.is_active = 1
        AND cs.is_active = 1
        AND (
            (cs.program_id = " . ($section_info['program_id'] ?? 0) . " AND cs.year_level_id = " . ($section_info['year_level_id'] ?? 0) . ")
            OR (cs.shs_strand_id = " . ($section_info['shs_strand_id'] ?? 0) . " AND cs.shs_grade_level_id = " . ($section_info['shs_grade_level_id'] ?? 0) . ")
        )
        ORDER BY cs.subject_code
    ");
    while ($row = $subjects_query->fetch_assoc()) {
        $subjects[] = $row;
    }
}

$classmates = [];
if ($section_id > 0) {
    $classmates_query = $conn->query("
        SELECT u.id, CONCAT(up.first_name, ' ', up.last_name) as name, u.email
        FROM section_students ss
        INNER JOIN users u ON ss.student_id = u.id
        INNER JOIN user_profiles up ON u.id = up.user_id
        WHERE ss.section_id = $section_id AND ss.status = 'active' AND ss.student_id != $student_id
        ORDER BY up.last_name, up.first_name
    ");
    while ($row = $classmates_query->fetch_assoc()) {
        $classmates[] = $row;
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

    /* --- FANTASTIC CLASS UI --- */
    .section-banner-card {
        background: white; border-radius: 20px; border: none;
        box-shadow: 0 5px 20px rgba(0,0,0,0.05); border-left: 6px solid var(--maroon);
        margin-bottom: 30px; overflow: hidden;
    }
    
    .info-label { font-size: 0.65rem; font-weight: 800; color: #888; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 2px; }
    .info-value { font-weight: 700; color: var(--blue); font-size: 1rem; }

    .main-card-modern {
        background: white; border-radius: 20px; border: none;
        box-shadow: 0 5px 20px rgba(0,0,0,0.05); overflow: hidden;
    }
    .card-header-modern { background: #fcfcfc; padding: 15px 25px; border-bottom: 1px solid #eee; font-weight: 700; color: var(--blue); text-transform: uppercase; font-size: 0.8rem; letter-spacing: 1px; }

    .table-modern thead th { background: var(--blue); color: white; font-size: 0.7rem; text-transform: uppercase; padding: 15px 20px; position: sticky; top: -1px; z-index: 5; }
    .table-modern tbody td { padding: 15px 20px; vertical-align: middle; border-bottom: 1px solid #f1f1f1; font-size: 0.9rem; }

    .classmate-circle {
        width: 40px; height: 40px; border-radius: 50%; background: #eee;
        display: flex; align-items: center; justify-content: center;
        font-weight: 700; color: var(--maroon); border: 2px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    .classmate-item {
        padding: 10px; border-radius: 12px; transition: 0.3s;
    }
    .classmate-item:hover { background: #f8f9fa; transform: scale(1.02); }

    .btn-enter-class {
        background-color: var(--maroon); color: white; border: none;
        border-radius: 8px; font-weight: 700; padding: 6px 15px; transition: 0.3s;
    }
    .btn-enter-class:hover { background-color: #600000; color: white; }

    @media (max-width: 768px) {
        .header-fixed-part { flex-direction: column; gap: 10px; text-align: center; }
    }
</style>

<!-- Part 1: Fixed Header -->
<div class="header-fixed-part animate__animated animate__fadeInDown">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-0" style="color: var(--blue);"><i class="bi bi-book-half me-2 text-maroon"></i>My Current Classes</h4>
            <p class="text-muted small mb-0">Academic Year: <?php echo htmlspecialchars($current_ay['year_name'] ?? 'N/A'); ?></p>
        </div>
        <div class="text-end">
            <span class="badge bg-dark text-maroon border px-3 py-2 rounded-pill shadow-sm">
                <i class="bi bi-people-fill me-1"></i> Peers: <?php echo count($classmates); ?>
            </span>
        </div>
    </div>
</div>

<!-- Part 2: Scrollable Body -->
<div class="body-scroll-part">
    
    <?php if (!$section_info): ?>
    <div class="alert bg-white border-start border-warning border-4 shadow-sm p-4 animate__animated animate__shakeX">
        <i class="bi bi-exclamation-triangle-fill text-warning fs-3 me-3"></i>
        <div>
            <h6 class="fw-bold mb-1">No Section Assignment</h6>
            <p class="mb-0 small text-muted">You are not currently enrolled in any section for this academic year. Please contact the registrar.</p>
        </div>
    </div>
    <?php else: ?>

    <!-- Section Information Banner -->
    <div class="section-banner-card p-4 animate__animated animate__fadeIn">
        <div class="row g-4 align-items-center">
            <div class="col-md-3 border-end">
                <label class="info-label">Section Name</label>
                <div class="h4 fw-bold text-maroon mb-0"><?php echo htmlspecialchars($section_info['section_name']); ?></div>
            </div>
            <div class="col-md-4 border-end px-md-4">
                <label class="info-label">Academic Program</label>
                <div class="info-value"><?php echo htmlspecialchars($section_info['program_name']); ?></div>
                <small class="text-muted"><?php echo htmlspecialchars($section_info['program_code']); ?> | <?php echo htmlspecialchars($section_info['year_level']); ?></small>
            </div>
            <div class="col-md-3 border-end px-md-4">
                <label class="info-label">Class Adviser</label>
                <div class="info-value" style="font-size: 0.9rem;"><i class="bi bi-person-badge me-2 text-muted"></i><?php echo htmlspecialchars($section_info['adviser_name'] ?? 'TBA'); ?></div>
                <small class="text-muted">Room: <?php echo htmlspecialchars($section_info['room'] ?? 'TBA'); ?></small>
            </div>
            <div class="col-md-2 text-center">
                <span class="badge bg-blue px-3 py-2"><?php echo htmlspecialchars($section_info['semester']); ?> Semester</span>
            </div>
        </div>
    </div>

    <!-- Subjects List -->
    <div class="main-card-modern mb-5 animate__animated animate__fadeInUp">
        <div class="card-header-modern d-flex justify-content-between align-items-center">
            <span><i class="bi bi-journal-check me-2"></i>Enrolled Subjects (<?php echo count($subjects); ?>)</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover table-modern align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Subject & Code</th>
                        <th class="text-center">Units</th>
                        <th>Instructor</th>
                        <th class="text-center">Resources</th>
                        <th class="text-end pe-4">Enter</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($subjects)): ?>
                        <tr><td colspan="5" class="text-center py-5 text-muted">No subjects have been assigned to this section yet.</td></tr>
                    <?php else: foreach ($subjects as $subject): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($subject['subject_title']); ?></div>
                                <span class="badge bg-light text-blue border border-blue small" style="font-size: 0.65rem;"><?php echo htmlspecialchars($subject['subject_code']); ?></span>
                            </td>
                            <td class="text-center fw-bold text-maroon"><?php echo $subject['units']; ?></td>
                            <td>
                                <?php if ($subject['teacher_name']): ?>
                                    <div class="fw-bold text-dark small">Prof. <?php echo htmlspecialchars($subject['teacher_name']); ?></div>
                                    <div class="small text-muted" style="font-size: 0.75rem;"><?php echo htmlspecialchars($subject['teacher_email']); ?></div>
                                <?php else: ?>
                                    <span class="text-muted italic small">To be assigned</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <span class="badge rounded-pill bg-light text-info border border-info px-3">
                                    <i class="bi bi-file-earmark-pdf me-1"></i><?php echo $subject['materials_count']; ?>
                                </span>
                            </td>
                            <td class="text-end pe-4">
                                <a href="subject_view.php?id=<?php echo $subject['id']; ?>&teacher=<?php echo $subject['teacher_id']; ?>" class="btn btn-enter-class shadow-sm">
                                    <i class="bi bi-arrow-right"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Classmates Accordion -->
    <div class="main-card-modern animate__animated animate__fadeInUp" style="animation-delay: 0.2s;">
        <div class="card-header-modern bg-white d-flex justify-content-between align-items-center">
            <span><i class="bi bi-people-fill me-2 text-info"></i>Class Peers (<?php echo count($classmates); ?>)</span>
            <button class="btn btn-sm btn-light border" type="button" data-bs-toggle="collapse" data-bs-target="#peerGrid">
                <i class="bi bi-chevron-expand"></i>
            </button>
        </div>
        <div class="collapse show" id="peerGrid">
            <div class="card-body p-4">
                <?php if (empty($classmates)): ?>
                    <div class="text-center py-3 text-muted small">No other students enrolled in this section.</div>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($classmates as $mate): ?>
                        <div class="col-md-4 col-lg-3">
                            <div class="classmate-item d-flex align-items-center border shadow-xs">
                                <div class="classmate-circle me-3">
                                    <?php echo strtoupper(substr($mate['name'], 0, 1)); ?>
                                </div>
                                <div class="overflow-hidden">
                                    <div class="fw-bold text-dark text-truncate small"><?php echo htmlspecialchars($mate['name']); ?></div>
                                    <div class="text-muted text-truncate" style="font-size: 0.7rem;"><?php echo htmlspecialchars($mate['email']); ?></div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>