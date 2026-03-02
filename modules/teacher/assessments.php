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
 * BACKEND LOGIC - Uses teacher_subject_assignments + sections (new structure)
 */

// Get current academic year
$active_ay = $conn->query("SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$active_ay_id = $active_ay['id'] ?? 0;

$assessments = $conn->query("
    SELECT 
        a.*,
        cs.subject_code,
        sec.section_name,
        COUNT(ascore.id) as total_submissions,
        SUM(CASE WHEN ascore.status = 'graded' THEN 1 ELSE 0 END) as graded_count
    FROM assessments a
    LEFT JOIN curriculum_subjects cs ON a.curriculum_subject_id = cs.id
    LEFT JOIN sections sec ON a.section_id = sec.id
    LEFT JOIN classes cl ON a.class_id = cl.id
    LEFT JOIN subjects s ON cl.subject_id = s.id
    LEFT JOIN assessment_scores ascore ON a.id = ascore.assessment_id
    WHERE a.created_by = $teacher_id
    GROUP BY a.id
    ORDER BY a.created_at DESC
");

// Get teacher's current classes from teacher_subject_assignments + sections
$classes_raw = $conn->query("
    SELECT 
        tsa.id as tsa_id,
        cs.id as curriculum_subject_id,
        cs.subject_code,
        cs.subject_title,
        cs.program_id,
        cs.year_level_id,
        cs.shs_strand_id,
        cs.shs_grade_level_id,
        cs.semester,
        tsa.branch_id,
        tsa.academic_year_id
    FROM teacher_subject_assignments tsa
    INNER JOIN curriculum_subjects cs ON tsa.curriculum_subject_id = cs.id
    WHERE tsa.teacher_id = $teacher_id
    AND tsa.academic_year_id = $active_ay_id
    AND tsa.is_active = 1
    ORDER BY cs.subject_code
");

// For each assignment, get matching sections
$classes = [];
while ($row = $classes_raw->fetch_assoc()) {
    $semester_map = [1 => '1st', 2 => '2nd', 3 => 'summer'];
    $sem_str = $semester_map[$row['semester']] ?? '1st';
    
    if (!empty($row['program_id'])) {
        $sec_q = $conn->prepare("SELECT id, section_name FROM sections WHERE program_id = ? AND year_level_id = ? AND semester = ? AND branch_id = ? AND academic_year_id = ? AND is_active = 1");
        $sec_q->bind_param("iisii", $row['program_id'], $row['year_level_id'], $sem_str, $row['branch_id'], $row['academic_year_id']);
    } else {
        // SHS: match by strand + grade_level NUMBER
        $shs_gl = $conn->query("SELECT grade_level FROM shs_grade_levels WHERE id = " . (int)$row['shs_grade_level_id']);
        $gl_num = $shs_gl ? ($shs_gl->fetch_assoc()['grade_level'] ?? 11) : 11;
        $sec_q = $conn->prepare("SELECT s.id, s.section_name FROM sections s INNER JOIN shs_grade_levels sgl ON s.shs_grade_level_id = sgl.id WHERE s.shs_strand_id = ? AND sgl.grade_level = ? AND s.semester = ? AND s.branch_id = ? AND s.academic_year_id = ? AND s.is_active = 1");
        $sec_q->bind_param("iisii", $row['shs_strand_id'], $gl_num, $sem_str, $row['branch_id'], $row['academic_year_id']);
    }
    $sec_q->execute();
    $sections = $sec_q->get_result()->fetch_all(MYSQLI_ASSOC);
    
    foreach ($sections as $sec) {
        $classes[] = [
            'section_id' => $sec['id'],
            'curriculum_subject_id' => $row['curriculum_subject_id'],
            'subject_code' => $row['subject_code'],
            'section_name' => $sec['section_name'],
            'display' => $row['subject_code'] . ' - ' . $sec['section_name']
        ];
    }
}

include '../../includes/header.php';
?>

<link rel=stylesheet href="css/assessments.css">

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
                            <div class="small fw-bold text-maroon"><?php echo htmlspecialchars($assessment['subject_code'] ?? 'N/A'); ?></div>
                            <div class="small text-muted"><?php echo htmlspecialchars($assessment['section_name'] ?? 'N/A'); ?></div>
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
                                <?php foreach ($classes as $cls): ?>
                                    <option value="<?php echo $cls['section_id'] . '_' . $cls['curriculum_subject_id']; ?>">
                                        <?php echo htmlspecialchars($cls['display']); ?>
                                    </option>
                                <?php endforeach; ?>
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