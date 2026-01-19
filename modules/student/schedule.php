<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_STUDENT) {
    header('Location: ../../index.php');
    exit();
}

$student_id = $_SESSION['user_id'];
$page_title = "Class Schedule";

// Get current academic year
$current_ay = $conn->query("SELECT id, year_name FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$current_ay_id = $current_ay['id'] ?? 0;

// Get student's section
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

// Get schedule from classes table if available
$classes = [];
if ($section) {
    $section_name = $section['section_name'];
    $program_id = $section['program_id'];
    $strand_id = $section['shs_strand_id'];
    
    // Try to get schedule from classes
    $classes_query = $conn->query("
        SELECT cl.*, cs.subject_code, cs.subject_title, cs.units,
               CONCAT(up.first_name, ' ', up.last_name) as teacher_name
        FROM classes cl
        INNER JOIN curriculum_subjects cs ON cl.curriculum_subject_id = cs.id
        LEFT JOIN user_profiles up ON cl.teacher_id = up.user_id
        WHERE cl.section_name = '$section_name' AND cl.academic_year_id = $current_ay_id
        ORDER BY cl.schedule
    ");
    while ($row = $classes_query->fetch_assoc()) {
        $classes[] = $row;
    }
}

// Get subjects from teacher assignments for schedule display
$subjects = [];
if ($section) {
    $branch_id = $section['branch_id'];
    $program_id = $section['program_id'] ?? 0;
    $strand_id = $section['shs_strand_id'] ?? 0;
    
    // Build WHERE conditions based on available data
    $where_conditions = [];
    if ($program_id > 0) {
        $where_conditions[] = "cs.program_id = $program_id";
    }
    if ($strand_id > 0) {
        $where_conditions[] = "cs.shs_strand_id = $strand_id";
    }
    
    // Only query if we have valid conditions
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
        while ($row = $subjects_query->fetch_assoc()) {
            $subjects[] = $row;
        }
    }
}

include '../../includes/header.php';
?>

<div class="wrapper">
    <?php include '../../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1"><i class="bi bi-calendar-week me-2"></i>Class Schedule</h4>
                <small class="text-muted">Academic Year: <?php echo htmlspecialchars($current_ay['year_name'] ?? 'N/A'); ?></small>
            </div>
            <?php if ($section): ?>
            <span class="badge bg-primary fs-6"><?php echo htmlspecialchars($section['section_name']); ?></span>
            <?php endif; ?>
        </div>

        <?php if (!$section): ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-2"></i>
            You are not currently enrolled in any section. Please contact the registrar's office.
        </div>
        <?php else: ?>

        <!-- Section Info Card -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-3 text-center border-end">
                        <div class="display-6 text-primary fw-bold"><?php echo htmlspecialchars($section['section_name']); ?></div>
                        <small class="text-muted">Section</small>
                    </div>
                    <div class="col-md-3">
                        <span class="text-muted small">Program/Strand</span>
                        <div class="fw-bold"><?php echo htmlspecialchars($section['program_name'] ?? $section['strand_name'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="col-md-3">
                        <span class="text-muted small">Year Level</span>
                        <div class="fw-bold"><?php echo htmlspecialchars($section['year_level'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="col-md-3">
                        <span class="text-muted small">Semester</span>
                        <div class="fw-bold"><?php echo ucfirst($section['semester'] ?? 'N/A'); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Schedule from Classes Table -->
        <?php if (!empty($classes)): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold"><i class="bi bi-clock text-primary me-2"></i>Class Schedule</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Subject Code</th>
                                <th>Subject Title</th>
                                <th>Units</th>
                                <th>Schedule</th>
                                <th>Room</th>
                                <th>Instructor</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($classes as $class): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-primary"><?php echo htmlspecialchars($class['subject_code']); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($class['subject_title']); ?></td>
                                <td class="text-center"><?php echo $class['units']; ?></td>
                                <td>
                                    <?php if ($class['schedule']): ?>
                                    <i class="bi bi-clock me-1"></i><?php echo htmlspecialchars($class['schedule']); ?>
                                    <?php else: ?>
                                    <span class="text-muted">TBA</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($class['room']): ?>
                                    <i class="bi bi-geo-alt me-1"></i><?php echo htmlspecialchars($class['room']); ?>
                                    <?php else: ?>
                                    <span class="text-muted">TBA</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($class['teacher_name']): ?>
                                    <i class="bi bi-person me-1"></i><?php echo htmlspecialchars($class['teacher_name']); ?>
                                    <?php else: ?>
                                    <span class="text-muted">TBA</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Subjects List -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold"><i class="bi bi-book text-success me-2"></i>Enrolled Subjects</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($subjects)): ?>
                <div class="text-center py-4 text-muted">
                    <i class="bi bi-calendar-x display-4"></i>
                    <p class="mb-0 mt-2">No subjects assigned yet</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Subject Code</th>
                                <th>Subject Title</th>
                                <th class="text-center">Units</th>
                                <th class="text-center">Lec Hours</th>
                                <th class="text-center">Lab Hours</th>
                                <th>Instructor</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total_units = 0;
                            foreach ($subjects as $subject): 
                                $total_units += $subject['units'];
                            ?>
                            <tr>
                                <td>
                                    <a href="subject_view.php?id=<?php echo $subject['curriculum_subject_id']; ?>&teacher=<?php echo $subject['teacher_id']; ?>" 
                                       class="text-decoration-none">
                                        <span class="badge bg-primary"><?php echo htmlspecialchars($subject['subject_code']); ?></span>
                                    </a>
                                </td>
                                <td>
                                    <a href="subject_view.php?id=<?php echo $subject['curriculum_subject_id']; ?>&teacher=<?php echo $subject['teacher_id']; ?>" 
                                       class="text-decoration-none text-dark">
                                        <?php echo htmlspecialchars($subject['subject_title']); ?>
                                    </a>
                                </td>
                                <td class="text-center"><?php echo $subject['units']; ?></td>
                                <td class="text-center"><?php echo $subject['lecture_hours']; ?></td>
                                <td class="text-center"><?php echo $subject['lab_hours']; ?></td>
                                <td>
                                    <?php if ($subject['teacher_name']): ?>
                                    <i class="bi bi-person me-1"></i><?php echo htmlspecialchars($subject['teacher_name']); ?>
                                    <?php else: ?>
                                    <span class="text-muted">TBA</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th colspan="2" class="text-end">Total Units:</th>
                                <th class="text-center"><?php echo $total_units; ?></th>
                                <th colspan="3"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
