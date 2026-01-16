<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_TEACHER) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Assessments Management";
$teacher_id = $_SESSION['user_id'];

// Fetch all assessments
$assessments = $conn->query("
    SELECT 
        a.*,
        s.subject_code,
        cl.section_name,
        COUNT(ascore.id) as total_submissions,
        SUM(CASE WHEN ascore.status = 'graded' THEN 1 ELSE 0 END) as graded_count
    FROM assessments a
    INNER JOIN classes cl ON a.class_id = cl.id
    LEFT JOIN subjects s ON cl.subject_id = s.id
    LEFT JOIN assessment_scores ascore ON a.id = ascore.assessment_id
    WHERE a.created_by = $teacher_id
    GROUP BY a.id
    ORDER BY a.created_at DESC
");

// Fetch teacher's classes for dropdown
$classes = $conn->query("
    SELECT cl.id, cl.section_name, s.subject_code
    FROM classes cl
    LEFT JOIN subjects s ON cl.subject_id = s.id
    WHERE cl.teacher_id = $teacher_id
    ORDER BY s.subject_code
");

include '../../includes/header.php';
?>

<link rel="stylesheet" href="../../assets/css/minimal.css">

<div class="wrapper">
    <?php include '../../includes/sidebar.php'; ?>

    <div id="content">
        <div class="minimal-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1" style="color: var(--navy); font-weight: 600;">Assessments Management</h4>
                    <small class="text-muted">Create and manage quizzes, exams, and activities</small>
                </div>
                <button class="btn btn-primary-minimal" data-bs-toggle="modal" data-bs-target="#createAssessmentModal">
                    <i class="bi bi-plus-circle"></i> Create Assessment
                </button>
            </div>
        </div>

        <div id="alertContainer"></div>

        <!-- Assessments List -->
        <div class="minimal-card">
            <h5 class="section-title">All Assessments</h5>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead style="background-color: var(--light-gray);">
                        <tr>
                            <th>Title</th>
                            <th>Class</th>
                            <th>Type</th>
                            <th>Max Score</th>
                            <th>Date</th>
                            <th>Submissions</th>
                            <th>Graded</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($assessment = $assessments->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($assessment['title']); ?></strong></td>
                            <td><?php echo htmlspecialchars($assessment['subject_code'] . ' - ' . $assessment['section_name']); ?></td>
                            <td><span class="badge bg-info"><?php echo ucfirst($assessment['assessment_type']); ?></span></td>
                            <td><?php echo $assessment['max_score']; ?></td>
                            <td><small><?php echo $assessment['scheduled_date'] ? date('M d, Y', strtotime($assessment['scheduled_date'])) : '-'; ?></small></td>
                            <td><span class="badge bg-primary"><?php echo $assessment['total_submissions']; ?></span></td>
                            <td><span class="badge bg-success"><?php echo $assessment['graded_count']; ?></span></td>
                            <td>
                                <button class="btn btn-sm btn-minimal" onclick="viewSubmissions(<?php echo $assessment['id']; ?>)">
                                    <i class="bi bi-eye"></i> View
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Create Assessment Modal -->
<div class="modal fade" id="createAssessmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background-color: var(--maroon); color: white;">
                <h5 class="modal-title">Create New Assessment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="createAssessmentForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Class <span class="text-danger">*</span></label>
                            <select class="form-select" name="class_id" required>
                                <option value="">-- Select Class --</option>
                                <?php 
                                $classes->data_seek(0);
                                while ($class = $classes->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $class['id']; ?>">
                                        <?php echo htmlspecialchars($class['subject_code'] . ' - ' . $class['section_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Type <span class="text-danger">*</span></label>
                            <select class="form-select" name="assessment_type" required>
                                <option value="quiz">Quiz</option>
                                <option value="exam">Exam</option>
                                <option value="activity">Activity</option>
                                <option value="project">Project</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="title" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Max Score <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="max_score" value="100" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Scheduled Date</label>
                            <input type="date" class="form-control" name="scheduled_date">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Duration (minutes)</label>
                            <input type="number" class="form-control" name="duration_minutes" placeholder="Optional">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Instructions</label>
                        <textarea class="form-control" name="instructions" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-minimal" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-minimal">
                        <i class="bi bi-plus-circle"></i> Create
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('createAssessmentForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    
    try {
        const response = await fetch('process/create_assessment.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.status === 'success') {
            showAlert(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert(data.message, 'danger');
        }
    } catch (error) {
        showAlert('Failed to create assessment', 'danger');
    }
});

function viewSubmissions(id) {
    window.location.href = 'assessment_submissions.php?id=' + id;
}

function showAlert(message, type) {
    const alertHtml = `
        <div class="alert alert-${type} alert-minimal alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    document.getElementById('alertContainer').innerHTML = alertHtml;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
</script>
</body>
</html>