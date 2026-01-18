<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_STUDENT) {
    header('Location: ../../index.php');
    exit();
}

$student_id = $_SESSION['user_id'];
$subject_id = (int)($_GET['id'] ?? 0);
$teacher_id = (int)($_GET['teacher'] ?? 0);

if ($subject_id == 0) {
    header('Location: my_classes.php');
    exit();
}

// Get subject info
$subject = $conn->query("
    SELECT cs.*, 
           CONCAT(up.first_name, ' ', up.last_name) as teacher_name,
           u.email as teacher_email
    FROM curriculum_subjects cs
    LEFT JOIN teacher_subject_assignments tsa ON tsa.curriculum_subject_id = cs.id AND tsa.teacher_id = $teacher_id
    LEFT JOIN user_profiles up ON tsa.teacher_id = up.user_id
    LEFT JOIN users u ON tsa.teacher_id = u.id
    WHERE cs.id = $subject_id
")->fetch_assoc();

if (!$subject) {
    header('Location: my_classes.php');
    exit();
}

$page_title = $subject['subject_code'] . ' - ' . $subject['subject_title'];

// Get current academic year
$current_ay = $conn->query("SELECT id, year_name FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$current_ay_id = $current_ay['id'] ?? 0;

// Get materials for this subject (via class)
$materials = $conn->query("
    SELECT lm.*, 
           CONCAT(up.first_name, ' ', up.last_name) as uploaded_by
    FROM learning_materials lm
    INNER JOIN classes cl ON lm.class_id = cl.id
    LEFT JOIN user_profiles up ON cl.teacher_id = up.user_id
    WHERE cl.curriculum_subject_id = $subject_id
    ORDER BY lm.uploaded_at DESC
    LIMIT 10
");

// Get assessments for this subject
$assessments = $conn->query("
    SELECT a.*, 
           ascore.score, ascore.status as submission_status
    FROM assessments a
    INNER JOIN classes cl ON a.class_id = cl.id
    LEFT JOIN assessment_scores ascore ON ascore.assessment_id = a.id AND ascore.student_id = $student_id
    WHERE cl.curriculum_subject_id = $subject_id
    ORDER BY a.scheduled_date DESC
    LIMIT 10
");

// Get attendance for this subject
$attendance = $conn->query("
    SELECT att.*
    FROM attendance att
    INNER JOIN classes cl ON att.class_id = cl.id
    WHERE cl.curriculum_subject_id = $subject_id AND att.student_id = $student_id
    ORDER BY att.attendance_date DESC
    LIMIT 10
");

// Get grade components for this subject
$grade_components = $conn->query("
    SELECT gc.*, sgd.score
    FROM grade_components gc
    INNER JOIN classes cl ON gc.class_id = cl.id
    LEFT JOIN student_grade_details sgd ON sgd.component_id = gc.id AND sgd.student_id = $student_id
    WHERE cl.curriculum_subject_id = $subject_id AND gc.is_active = 1
    ORDER BY gc.component_type, gc.component_name
");

include '../../includes/header.php';
?>

<div class="wrapper">
    <?php include '../../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <!-- Back Button and Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <a href="my_classes.php" class="btn btn-sm btn-outline-secondary mb-2">
                    <i class="bi bi-arrow-left me-1"></i> Back to My Classes
                </a>
                <h4 class="fw-bold mb-1">
                    <span class="badge bg-primary me-2"><?php echo htmlspecialchars($subject['subject_code']); ?></span>
                    <?php echo htmlspecialchars($subject['subject_title']); ?>
                </h4>
            </div>
        </div>

        <!-- Subject Info Card -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-muted mb-3">Subject Details</h6>
                        <table class="table table-borderless table-sm mb-0">
                            <tr>
                                <td class="text-muted" width="120">Code:</td>
                                <td><strong><?php echo htmlspecialchars($subject['subject_code']); ?></strong></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Title:</td>
                                <td><?php echo htmlspecialchars($subject['subject_title']); ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Units:</td>
                                <td><?php echo $subject['units']; ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Hours:</td>
                                <td>Lecture: <?php echo $subject['lecture_hours']; ?> | Lab: <?php echo $subject['lab_hours']; ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted mb-3">Instructor</h6>
                        <?php if ($subject['teacher_name']): ?>
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center me-3" 
                                 style="width: 50px; height: 50px;">
                                <?php echo strtoupper(substr($subject['teacher_name'], 0, 1)); ?>
                            </div>
                            <div>
                                <strong><?php echo htmlspecialchars($subject['teacher_name']); ?></strong>
                                <br>
                                <small class="text-muted"><?php echo htmlspecialchars($subject['teacher_email']); ?></small>
                            </div>
                        </div>
                        <?php else: ?>
                        <p class="text-muted">To be announced</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Materials -->
            <div class="col-lg-6 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold"><i class="bi bi-file-earmark-pdf text-danger me-2"></i>Learning Materials</h5>
                        <a href="materials.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <?php if ($materials->num_rows == 0): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-folder2-open"></i>
                            <p class="small mb-0">No materials yet</p>
                        </div>
                        <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php while ($mat = $materials->fetch_assoc()): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="bi bi-file-earmark me-2"></i>
                                    <small><?php echo basename($mat['file_path']); ?></small>
                                </div>
                                <a href="../../uploads/<?php echo $mat['file_path']; ?>" class="btn btn-sm btn-outline-primary" target="_blank" download>
                                    <i class="bi bi-download"></i>
                                </a>
                            </li>
                            <?php endwhile; ?>
                        </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Assessments -->
            <div class="col-lg-6 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold"><i class="bi bi-clipboard-check text-warning me-2"></i>Assessments</h5>
                        <a href="assessments.php" class="btn btn-sm btn-outline-warning">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <?php if ($assessments->num_rows == 0): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-clipboard-x"></i>
                            <p class="small mb-0">No assessments yet</p>
                        </div>
                        <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php while ($assess = $assessments->fetch_assoc()): 
                                $status = $assess['submission_status'] ?? 'pending';
                                $status_colors = ['pending' => 'warning', 'submitted' => 'info', 'graded' => 'success'];
                            ?>
                            <li class="list-group-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong class="small"><?php echo htmlspecialchars($assess['title']); ?></strong>
                                        <br>
                                        <small class="text-muted">
                                            <span class="badge bg-light text-dark"><?php echo ucfirst($assess['assessment_type']); ?></span>
                                            <?php if ($assess['scheduled_date']): ?>
                                            | <?php echo date('M d', strtotime($assess['scheduled_date'])); ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-<?php echo $status_colors[$status] ?? 'secondary'; ?>">
                                            <?php echo ucfirst($status); ?>
                                        </span>
                                        <?php if ($status == 'graded'): ?>
                                        <br>
                                        <small class="fw-bold"><?php echo $assess['score']; ?>/<?php echo $assess['max_score']; ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </li>
                            <?php endwhile; ?>
                        </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Attendance -->
            <div class="col-lg-6 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold"><i class="bi bi-calendar-check text-success me-2"></i>Recent Attendance</h5>
                        <a href="attendance.php" class="btn btn-sm btn-outline-success">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <?php if ($attendance->num_rows == 0): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-calendar-x"></i>
                            <p class="small mb-0">No attendance records</p>
                        </div>
                        <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php while ($att = $attendance->fetch_assoc()): 
                                $att_colors = ['present' => 'success', 'absent' => 'danger', 'late' => 'warning', 'excused' => 'info'];
                            ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>
                                    <?php echo date('M d, Y', strtotime($att['attendance_date'])); ?>
                                    <small class="text-muted">(<?php echo date('l', strtotime($att['attendance_date'])); ?>)</small>
                                </span>
                                <span class="badge bg-<?php echo $att_colors[$att['status']] ?? 'secondary'; ?>">
                                    <?php echo ucfirst($att['status']); ?>
                                </span>
                            </li>
                            <?php endwhile; ?>
                        </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Grade Components -->
            <div class="col-lg-6 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold"><i class="bi bi-bar-chart text-info me-2"></i>Grade Components</h5>
                        <a href="grades.php" class="btn btn-sm btn-outline-info">View All Grades</a>
                    </div>
                    <div class="card-body p-0">
                        <?php if ($grade_components->num_rows == 0): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-graph-down"></i>
                            <p class="small mb-0">No grade components yet</p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Component</th>
                                        <th>Type</th>
                                        <th class="text-center">Score</th>
                                        <th class="text-center">Max</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($gc = $grade_components->fetch_assoc()): ?>
                                    <tr>
                                        <td><small><?php echo htmlspecialchars($gc['component_name']); ?></small></td>
                                        <td><span class="badge bg-secondary"><?php echo ucfirst($gc['component_type']); ?></span></td>
                                        <td class="text-center">
                                            <?php echo $gc['score'] !== null ? number_format($gc['score'], 2) : '-'; ?>
                                        </td>
                                        <td class="text-center"><?php echo number_format($gc['max_score'], 2); ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
