<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_STUDENT) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "My Classes";
$student_id = $_SESSION['user_id'];

// Get current academic year
$current_ay = $conn->query("SELECT id, year_name FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$current_ay_id = $current_ay['id'] ?? 0;

// Get enrolled section
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

// Get subjects for current section
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

// Get classmates
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
?>

<div class="wrapper">
    <?php include '../../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1"><i class="bi bi-book-fill text-primary me-2"></i>My Classes</h4>
                <small class="text-muted"><?php echo htmlspecialchars($current_ay['year_name'] ?? ''); ?></small>
            </div>
        </div>

        <?php if (!$section_info): ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-2"></i>
            You are not enrolled in any section for this academic year. Please contact the registrar.
        </div>
        <?php else: ?>

        <!-- Section Info Card -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h5 class="fw-bold text-primary mb-3">
                            <i class="bi bi-collection me-2"></i>Section Information
                        </h5>
                        <table class="table table-borderless mb-0">
                            <tr>
                                <td class="text-muted" width="150">Section:</td>
                                <td><strong><?php echo htmlspecialchars($section_info['section_name']); ?></strong></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Program:</td>
                                <td><?php echo htmlspecialchars($section_info['program_name']); ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Year Level:</td>
                                <td><?php echo htmlspecialchars($section_info['year_level']); ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Semester:</td>
                                <td><?php echo htmlspecialchars($section_info['semester']); ?> Semester</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h5 class="fw-bold text-primary mb-3">
                            <i class="bi bi-info-circle me-2"></i>Additional Info
                        </h5>
                        <table class="table table-borderless mb-0">
                            <tr>
                                <td class="text-muted" width="150">Branch:</td>
                                <td><?php echo htmlspecialchars($section_info['branch_name']); ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Room:</td>
                                <td><?php echo htmlspecialchars($section_info['room'] ?? 'TBA'); ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Adviser:</td>
                                <td><?php echo htmlspecialchars($section_info['adviser_name'] ?? 'TBA'); ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Classmates:</td>
                                <td><?php echo count($classmates); ?> students</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Subjects -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold"><i class="bi bi-journal-text text-success me-2"></i>Enrolled Subjects (<?php echo count($subjects); ?>)</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($subjects)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-inbox display-4"></i>
                    <p class="mt-2">No subjects assigned yet</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Subject Code</th>
                                <th>Subject Title</th>
                                <th>Units</th>
                                <th>Schedule</th>
                                <th>Teacher</th>
                                <th class="text-center">Materials</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($subjects as $subject): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-primary"><?php echo htmlspecialchars($subject['subject_code']); ?></span>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($subject['subject_title']); ?></strong>
                                    <br>
                                    <small class="text-muted">
                                        Lecture: <?php echo $subject['lecture_hours']; ?>hrs | 
                                        Lab: <?php echo $subject['lab_hours']; ?>hrs
                                    </small>
                                </td>
                                <td><?php echo $subject['units']; ?></td>
                                <td><small class="text-muted">See schedule</small></td>
                                <td>
                                    <?php if ($subject['teacher_name']): ?>
                                    <div>
                                        <i class="bi bi-person-badge me-1"></i>
                                        <?php echo htmlspecialchars($subject['teacher_name']); ?>
                                    </div>
                                    <small class="text-muted"><?php echo htmlspecialchars($subject['teacher_email']); ?></small>
                                    <?php else: ?>
                                    <span class="badge bg-secondary">TBA</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-info"><?php echo $subject['materials_count']; ?></span>
                                </td>
                                <td>
                                    <a href="subject_view.php?id=<?php echo $subject['id']; ?>&teacher=<?php echo $subject['teacher_id']; ?>" 
                                       class="btn btn-sm btn-primary">
                                        <i class="bi bi-arrow-right"></i> Enter
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Classmates -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold"><i class="bi bi-people text-info me-2"></i>Classmates (<?php echo count($classmates); ?>)</h5>
                <button class="btn btn-sm btn-outline-info" type="button" data-bs-toggle="collapse" data-bs-target="#classmatesList">
                    <i class="bi bi-chevron-down"></i>
                </button>
            </div>
            <div class="collapse" id="classmatesList">
                <div class="card-body">
                    <?php if (empty($classmates)): ?>
                    <p class="text-muted text-center mb-0">No classmates found</p>
                    <?php else: ?>
                    <div class="row">
                        <?php foreach ($classmates as $classmate): ?>
                        <div class="col-md-4 col-lg-3 mb-3">
                            <div class="d-flex align-items-center p-2 border rounded">
                                <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center me-2" 
                                     style="width: 40px; height: 40px;">
                                    <?php echo strtoupper(substr($classmate['name'], 0, 1)); ?>
                                </div>
                                <div>
                                    <small class="fw-bold d-block"><?php echo htmlspecialchars($classmate['name']); ?></small>
                                    <small class="text-muted"><?php echo htmlspecialchars($classmate['email']); ?></small>
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
</div>

<?php include '../../includes/footer.php'; ?>
