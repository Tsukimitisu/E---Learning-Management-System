<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_STUDENT) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Assessments";
$student_id = $_SESSION['user_id'];

// Get filter
$filter_status = $_GET['status'] ?? 'all';
$filter_type = $_GET['type'] ?? 'all';

// Build query conditions
$conditions = ["e.student_id = $student_id"];
if ($filter_status == 'pending') {
    $conditions[] = "(ascore.status IS NULL OR ascore.status = 'pending')";
} elseif ($filter_status == 'submitted') {
    $conditions[] = "ascore.status = 'submitted'";
} elseif ($filter_status == 'graded') {
    $conditions[] = "ascore.status = 'graded'";
}

if ($filter_type != 'all') {
    $conditions[] = "a.assessment_type = '" . $conn->real_escape_string($filter_type) . "'";
}

$where_clause = implode(' AND ', $conditions);

// Get assessments
$assessments = $conn->query("
    SELECT a.*, c.course_code as subject_code, c.title as subject_title,
           CONCAT(up.first_name, ' ', up.last_name) as teacher_name,
           ascore.score, ascore.status as submission_status, ascore.feedback, ascore.graded_at
    FROM assessments a
    INNER JOIN classes cl ON a.class_id = cl.id
    INNER JOIN courses c ON cl.course_id = c.id
    INNER JOIN enrollments e ON e.class_id = a.class_id
    LEFT JOIN assessment_scores ascore ON ascore.assessment_id = a.id AND ascore.student_id = $student_id
    LEFT JOIN user_profiles up ON a.created_by = up.user_id
    WHERE $where_clause
    ORDER BY a.scheduled_date DESC, a.created_at DESC
");

// Count by status
$counts = [
    'all' => 0,
    'pending' => 0,
    'submitted' => 0,
    'graded' => 0
];

$all_assessments = [];
while ($row = $assessments->fetch_assoc()) {
    $all_assessments[] = $row;
    $counts['all']++;
    
    $status = $row['submission_status'] ?? 'pending';
    if (isset($counts[$status])) {
        $counts[$status]++;
    }
}

include '../../includes/header.php';
?>

<div class="wrapper">
    <?php include '../../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1"><i class="bi bi-clipboard-check text-warning me-2"></i>Assessments</h4>
                <small class="text-muted">View and manage your assessments</small>
            </div>
        </div>

        <!-- Filter Tabs -->
        <ul class="nav nav-pills mb-4">
            <li class="nav-item">
                <a class="nav-link <?php echo $filter_status == 'all' ? 'active' : ''; ?>" href="?status=all">
                    All <span class="badge bg-secondary"><?php echo $counts['all']; ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $filter_status == 'pending' ? 'active' : ''; ?>" href="?status=pending">
                    Pending <span class="badge bg-warning"><?php echo $counts['pending']; ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $filter_status == 'submitted' ? 'active' : ''; ?>" href="?status=submitted">
                    Submitted <span class="badge bg-info"><?php echo $counts['submitted']; ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $filter_status == 'graded' ? 'active' : ''; ?>" href="?status=graded">
                    Graded <span class="badge bg-success"><?php echo $counts['graded']; ?></span>
                </a>
            </li>
        </ul>

        <!-- Type Filter -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body py-2">
                <div class="d-flex align-items-center gap-2">
                    <span class="text-muted me-2">Type:</span>
                    <a href="?status=<?php echo $filter_status; ?>&type=all" 
                       class="btn btn-sm <?php echo $filter_type == 'all' ? 'btn-primary' : 'btn-outline-primary'; ?>">All</a>
                    <a href="?status=<?php echo $filter_status; ?>&type=quiz" 
                       class="btn btn-sm <?php echo $filter_type == 'quiz' ? 'btn-primary' : 'btn-outline-primary'; ?>">Quiz</a>
                    <a href="?status=<?php echo $filter_status; ?>&type=exam" 
                       class="btn btn-sm <?php echo $filter_type == 'exam' ? 'btn-primary' : 'btn-outline-primary'; ?>">Exam</a>
                    <a href="?status=<?php echo $filter_status; ?>&type=activity" 
                       class="btn btn-sm <?php echo $filter_type == 'activity' ? 'btn-primary' : 'btn-outline-primary'; ?>">Activity</a>
                    <a href="?status=<?php echo $filter_status; ?>&type=project" 
                       class="btn btn-sm <?php echo $filter_type == 'project' ? 'btn-primary' : 'btn-outline-primary'; ?>">Project</a>
                </div>
            </div>
        </div>

        <!-- Assessments List -->
        <?php if (empty($all_assessments)): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="bi bi-clipboard-x display-3 text-muted"></i>
                <p class="mt-3 text-muted">No assessments found</p>
            </div>
        </div>
        <?php else: ?>
        <div class="row">
            <?php foreach ($all_assessments as $assessment): 
                $status = $assessment['submission_status'] ?? 'pending';
                $status_colors = [
                    'pending' => 'warning',
                    'submitted' => 'info',
                    'graded' => 'success'
                ];
                $type_icons = [
                    'quiz' => 'bi-question-circle',
                    'exam' => 'bi-file-earmark-text',
                    'activity' => 'bi-lightning',
                    'project' => 'bi-kanban'
                ];
                $is_overdue = $assessment['scheduled_date'] && strtotime($assessment['scheduled_date']) < time() && $status == 'pending';
            ?>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card border-0 shadow-sm h-100 <?php echo $is_overdue ? 'border-danger' : ''; ?>" 
                     style="<?php echo $is_overdue ? 'border-left: 4px solid #dc3545 !important;' : ''; ?>">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center py-2">
                        <span class="badge bg-primary"><?php echo htmlspecialchars($assessment['subject_code']); ?></span>
                        <span class="badge bg-<?php echo $status_colors[$status] ?? 'secondary'; ?>">
                            <?php echo ucfirst($status); ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-start mb-2">
                            <i class="<?php echo $type_icons[$assessment['assessment_type']] ?? 'bi-file-earmark'; ?> fs-4 text-muted me-2"></i>
                            <div>
                                <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($assessment['title']); ?></h6>
                                <small class="badge bg-light text-dark"><?php echo ucfirst($assessment['assessment_type']); ?></small>
                            </div>
                        </div>
                        
                        <p class="text-muted small mb-2">
                            <?php echo htmlspecialchars($assessment['subject_title']); ?>
                        </p>
                        
                        <?php if ($assessment['scheduled_date']): ?>
                        <p class="small mb-2 <?php echo $is_overdue ? 'text-danger' : 'text-muted'; ?>">
                            <i class="bi bi-calendar me-1"></i>
                            <?php echo date('M d, Y', strtotime($assessment['scheduled_date'])); ?>
                            <?php if ($is_overdue): ?>
                            <span class="badge bg-danger ms-1">Overdue</span>
                            <?php endif; ?>
                        </p>
                        <?php endif; ?>
                        
                        <?php if ($assessment['duration_minutes']): ?>
                        <p class="small text-muted mb-2">
                            <i class="bi bi-clock me-1"></i>
                            <?php echo $assessment['duration_minutes']; ?> minutes
                        </p>
                        <?php endif; ?>
                        
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted small">Max: <?php echo $assessment['max_score']; ?> pts</span>
                            <?php if ($status == 'graded'): ?>
                            <span class="fw-bold text-<?php echo ($assessment['score'] / $assessment['max_score'] * 100) >= 75 ? 'success' : 'danger'; ?>">
                                Score: <?php echo $assessment['score']; ?>/<?php echo $assessment['max_score']; ?>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-footer bg-white border-top-0">
                        <button class="btn btn-sm btn-outline-primary w-100" 
                                onclick="viewAssessment(<?php echo $assessment['id']; ?>)">
                            <i class="bi bi-eye me-1"></i> View Details
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Assessment Details Modal -->
<div class="modal fade" id="assessmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-clipboard-check me-2"></i>Assessment Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="assessmentDetails">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function viewAssessment(id) {
    const modal = new bootstrap.Modal(document.getElementById('assessmentModal'));
    modal.show();
    
    // In a real implementation, fetch assessment details via AJAX
    document.getElementById('assessmentDetails').innerHTML = `
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>
            Assessment details will be loaded here. Contact your teacher for more information.
        </div>
    `;
}
</script>

<?php include '../../includes/footer.php'; ?>
