<?php
require_once '../../config/init.php';

$user_role = $_SESSION['role_id'] ?? $_SESSION['role'] ?? null;
if (!isset($_SESSION['user_id']) || $user_role != ROLE_TEACHER) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Assessments Management";
$teacher_id = $_SESSION['user_id'];

/** 
 * BACKEND LOGIC - UNTOUCHED 
 */
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

$classes = $conn->query("
    SELECT cl.id, cl.section_name, s.subject_code
    FROM classes cl
    LEFT JOIN subjects s ON cl.subject_id = s.id
    WHERE cl.teacher_id = $teacher_id
    ORDER BY s.subject_code
");

include '../../includes/header.php';
include '../../includes/sidebar.php'; // This opens the .wrapper and starts #content
?>

<style>
    /* --- SCROLL & LAYOUT ENGINE --- */
    html, body { height: 100%; margin: 0; overflow: hidden; }
    #content { height: 100vh; display: flex; flex-direction: column; overflow: hidden; }

    .header-fixed-part {
        flex: 0 0 auto;
        background: white;
        padding: 20px 30px;
        border-bottom: 1px solid #eee;
        z-index: 10;
    }

    .body-scroll-part {
        flex: 1 1 auto;
        overflow-y: auto;
        padding: 25px 30px 100px 30px; 
        background-color: #f8f9fa;
    }

    /* --- FANTASTIC UI COMPONENTS --- */
    .assessment-table-card {
        background: white;
        border-radius: 15px;
        border: none;
        box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        overflow: hidden;
    }

    .table thead th {
        background: #fcfcfc;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #888;
        padding: 15px 20px;
        border-bottom: 2px solid #eee;
        position: sticky;
        top: 0;
        z-index: 5;
    }

    .table tbody td { padding: 15px 20px; vertical-align: middle; }

    .type-badge {
        padding: 5px 12px;
        border-radius: 6px;
        font-weight: 700;
        font-size: 0.7rem;
        text-transform: uppercase;
    }

    .btn-create-assessment {
        background-color: var(--maroon);
        color: white;
        border: none;
        border-radius: 10px;
        font-weight: 700;
        padding: 10px 20px;
        transition: 0.3s;
    }
    .btn-create-assessment:hover { background-color: #600000; color: white; transform: translateY(-2px); shadow: 0 5px 15px rgba(128,0,0,0.2); }

    .action-btn-view {
        background: var(--blue);
        color: white;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        padding: 6px 15px;
        font-size: 0.85rem;
        transition: 0.2s;
    }
    .action-btn-view:hover { background: #002244; color: white; }

    /* Mobile Handling */
    @media (max-width: 576px) {
        .header-fixed-part { flex-direction: column; gap: 15px; text-align: center; }
        .body-scroll-part { padding: 15px 15px 100px 15px; }
    }
</style>

<!-- Part 1: Fixed Top Header -->
<div class="header-fixed-part d-flex justify-content-between align-items-center animate__animated animate__fadeInDown">
    <div>
        <h4 class="fw-bold mb-0" style="color: var(--blue);"><i class="bi bi-clipboard-check-fill me-2"></i>Assessments</h4>
        <p class="text-muted small mb-0">Evaluate student performance through activities and exams</p>
    </div>
    <button class="btn btn-create-assessment shadow-sm" data-bs-toggle="modal" data-bs-target="#createAssessmentModal">
        <i class="bi bi-plus-circle me-1"></i> Create New
    </button>
</div>

<!-- Part 2: Scrollable Content -->
<div class="body-scroll-part animate__animated animate__fadeInUp">
    
    <div id="alertContainer"></div>

    <div class="assessment-table-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Title / Type</th>
                        <th>Class & Section</th>
                        <th class="text-center">Max Score</th>
                        <th>Scheduled Date</th>
                        <th class="text-center">Submissions</th>
                        <th class="text-center">Graded</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($assessment = $assessments->fetch_assoc()): 
                        $type = ucfirst($assessment['assessment_type']);
                        $type_class = match($assessment['assessment_type']) {
                            'exam' => 'bg-danger text-white',
                            'quiz' => 'bg-warning text-dark',
                            'activity' => 'bg-info text-white',
                            default => 'bg-secondary text-white'
                        };
                    ?>
                    <tr>
                        <td>
                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($assessment['title']); ?></div>
                            <span class="type-badge <?php echo $type_class; ?> mt-1 d-inline-block"><?php echo $type; ?></span>
                        </td>
                        <td>
                            <div class="small fw-bold text-maroon"><?php echo htmlspecialchars($assessment['subject_code']); ?></div>
                            <div class="small text-muted"><?php echo htmlspecialchars($assessment['section_name']); ?></div>
                        </td>
                        <td class="text-center fw-bold text-blue"><?php echo $assessment['max_score']; ?></td>
                        <td>
                            <small class="text-muted">
                                <i class="bi bi-calendar3 me-1"></i>
                                <?php echo $assessment['scheduled_date'] ? date('M d, Y', strtotime($assessment['scheduled_date'])) : '-'; ?>
                            </small>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-light text-primary border rounded-pill px-3"><?php echo $assessment['total_submissions']; ?></span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-light text-success border rounded-pill px-3"><?php echo $assessment['graded_count']; ?></span>
                        </td>
                        <td class="text-end">
                            <button class="action-btn-view shadow-sm" onclick="viewSubmissions(<?php echo $assessment['id']; ?>)">
                                <i class="bi bi-eye me-1"></i> Submissions
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php if ($assessments->num_rows == 0): ?>
                        <tr><td colspan="7" class="text-center py-5 text-muted">No assessments found. Start by creating one.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Part 3: Create Assessment Modal  -->
<div class="modal fade" id="createAssessmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header p-4 text-white" style="background-color: var(--maroon); border: none;">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i>Create New Assessment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="createAssessmentForm">
                <div class="modal-body p-4 bg-light">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted text-uppercase">Target Class *</label>
                            <select class="form-select border-light shadow-sm" name="class_id" required>
                                <option value="">-- Select Class --</option>
                                <?php 
                                $classes->data_seek(0);
                                while ($class = $classes->fetch_assoc()): ?>
                                    <option value="<?php echo $class['id']; ?>">
                                        <?php echo htmlspecialchars($class['subject_code'] . ' - ' . $class['section_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted text-uppercase">Assessment Type *</label>
                            <select class="form-select border-light shadow-sm" name="assessment_type" required>
                                <option value="quiz">Quiz</option>
                                <option value="exam">Exam</option>
                                <option value="activity">Activity</option>
                                <option value="project">Project</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold text-muted text-uppercase">Assessment Title *</label>
                            <input type="text" class="form-control border-light shadow-sm" name="title" placeholder="e.g. Midterm Quiz on Algebra" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-muted text-uppercase">Max Points *</label>
                            <input type="number" class="form-control border-light shadow-sm" name="max_score" value="100" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-muted text-uppercase">Scheduled Date</label>
                            <input type="date" class="form-control border-light shadow-sm" name="scheduled_date">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-muted text-uppercase">Duration (Mins)</label>
                            <input type="number" class="form-control border-light shadow-sm" name="duration_minutes" placeholder="Optional">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold text-muted text-uppercase">Instructions</label>
                            <textarea class="form-control border-light shadow-sm" name="instructions" rows="3" placeholder="Provide details or links for the students..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4">
                    <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal">Discard</button>
                    <button type="submit" class="btn btn-create-assessment px-4">Create Assessment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<!-- --- JAVASCRIPT LOGIC --- -->
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
    const alertHtml = `<div class="alert alert-${type} alert-dismissible fade show border-0 shadow-sm animate__animated animate__shakeX" role="alert"><i class="bi ${type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'} me-2"></i>${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
    document.getElementById('alertContainer').innerHTML = alertHtml;
    document.querySelector('.body-scroll-part').scrollTo({ top: 0, behavior: 'smooth' });
}
</script>
</body>
</html>