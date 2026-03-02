<?php
require_once '../../config/init.php';

$user_role = $_SESSION['role_id'] ?? $_SESSION['role'] ?? null;
if (!isset($_SESSION['user_id']) || $user_role != ROLE_TEACHER) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Assessment Submissions";
$teacher_id = $_SESSION['user_id'];
$assessment_id = (int)($_GET['id'] ?? 0);

if ($assessment_id <= 0) {
    header('Location: assessments.php');
    exit();
}

// Get assessment details - support both old (class_id) and new (section_id + curriculum_subject_id) structure
$assessment = $conn->query("
    SELECT a.*,
           COALESCE(cs.subject_code, s_old.subject_code) as subject_code,
           COALESCE(cs.subject_title, s_old.subject_title) as subject_title,
           COALESCE(sec.section_name, cl.section_name) as section_name,
           COALESCE(a.section_id, cl.id) as resolved_section_id
    FROM assessments a
    LEFT JOIN curriculum_subjects cs ON a.curriculum_subject_id = cs.id
    LEFT JOIN sections sec ON a.section_id = sec.id
    LEFT JOIN classes cl ON a.class_id = cl.id
    LEFT JOIN subjects s_old ON cl.subject_id = s_old.id
    WHERE a.id = $assessment_id AND a.created_by = $teacher_id
")->fetch_assoc();

if (!$assessment) {
    header('Location: assessments.php');
    exit();
}

$section_id = $assessment['section_id'] ?? 0;

// Get all students in the section
$students_query = "
    SELECT 
        u.id as student_id,
        CONCAT(up.first_name, ' ', up.last_name) as student_name,
        COALESCE(st.student_no, CONCAT('STU-', u.id)) as student_no,
        ascr.id as score_id,
        ascr.score,
        ascr.status as score_status,
        ascr.feedback,
        ascr.submitted_at,
        ascr.graded_at
    FROM section_students ss
    INNER JOIN users u ON ss.student_id = u.id
    INNER JOIN user_profiles up ON u.id = up.user_id
    LEFT JOIN students st ON u.id = st.user_id
    LEFT JOIN assessment_scores ascr ON ascr.assessment_id = $assessment_id AND ascr.student_id = u.id
    WHERE ss.section_id = $section_id AND ss.status = 'active'
    ORDER BY up.last_name, up.first_name
";
$students = $conn->query($students_query);

include '../../includes/header.php';
?>

<link rel="stylesheet" href="css/assessments.css">

<style>
    .grade-input { width: 80px; text-align: center; border-radius: 8px; border: 1px solid #ddd; padding: 5px 8px; font-weight: 600; }
    .grade-input:focus { border-color: var(--blue); box-shadow: 0 0 0 2px rgba(0,51,102,0.15); outline: none; }
    .feedback-input { border-radius: 8px; border: 1px solid #eee; padding: 5px 10px; font-size: 0.85rem; width: 100%; }
    .status-pending { color: #f39c12; }
    .status-submitted { color: #3498db; }
    .status-graded { color: #27ae60; }
    .assessment-info-card { background: white; border-radius: 15px; padding: 20px 25px; box-shadow: 0 3px 15px rgba(0,0,0,0.04); margin-bottom: 20px; }
    .info-label { font-size: 0.75rem; text-transform: uppercase; color: #999; font-weight: 600; letter-spacing: 0.5px; }
    .info-value { font-size: 1rem; font-weight: 600; color: #333; }
    .btn-grade-all { background: linear-gradient(135deg, var(--maroon), var(--maroon-light)); color: white; border: none; border-radius: 12px; padding: 10px 20px; font-weight: 600; }
    .btn-grade-all:hover { opacity: 0.9; color: white; }
</style>

<!-- Fixed Header -->
<div class="header-fixed-part d-flex justify-content-between align-items-center animate__animated animate__fadeInDown">
    <div>
        <h4 class="fw-bold mb-0" style="color: var(--blue);">
            <i class="bi bi-clipboard-data me-2"></i><?php echo htmlspecialchars($assessment['title']); ?>
        </h4>
        <p class="text-muted small mb-0">
            <?php echo htmlspecialchars($assessment['subject_code'] ?? 'N/A'); ?> &bull; 
            <?php echo htmlspecialchars($assessment['section_name'] ?? 'N/A'); ?> &bull;
            <?php echo ucfirst($assessment['assessment_type']); ?> &bull;
            Max: <?php echo $assessment['max_score']; ?> pts
        </p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-grade-all shadow-sm" onclick="saveAllGrades()">
            <i class="bi bi-check-all me-1"></i> Save All Grades
        </button>
        <a href="assessments.php" class="btn btn-outline-secondary px-3 shadow-sm">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
    </div>
</div>

<!-- Scrollable Content -->
<div class="body-scroll-part animate__animated animate__fadeInUp">
    
    <div id="alertContainer"></div>

    <!-- Assessment Info -->
    <div class="assessment-info-card">
        <div class="row g-3">
            <div class="col-md-2">
                <div class="info-label">Type</div>
                <div class="info-value"><?php echo ucfirst($assessment['assessment_type']); ?></div>
            </div>
            <div class="col-md-2">
                <div class="info-label">Max Score</div>
                <div class="info-value"><?php echo $assessment['max_score']; ?> pts</div>
            </div>
            <div class="col-md-2">
                <div class="info-label">Date</div>
                <div class="info-value"><?php echo $assessment['scheduled_date'] ? date('M d, Y', strtotime($assessment['scheduled_date'])) : 'Not set'; ?></div>
            </div>
            <div class="col-md-2">
                <div class="info-label">Duration</div>
                <div class="info-value"><?php echo $assessment['duration_minutes'] ? $assessment['duration_minutes'] . ' mins' : 'N/A'; ?></div>
            </div>
            <div class="col-md-4">
                <div class="info-label">Instructions</div>
                <div class="info-value small fw-normal"><?php echo htmlspecialchars($assessment['instructions'] ?: 'None'); ?></div>
            </div>
        </div>
    </div>

    <!-- Students Grade Table -->
    <div class="assessment-table-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>Student No.</th>
                        <th>Student Name</th>
                        <th class="text-center" style="width:120px">Score</th>
                        <th class="text-center" style="width:100px">Status</th>
                        <th style="width:250px">Feedback</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $counter = 1;
                    $total_students = 0;
                    if ($students && $students->num_rows > 0):
                        while ($stu = $students->fetch_assoc()):
                            $total_students++;
                            $score_status = $stu['score_status'] ?? 'pending';
                            $status_class = "status-$score_status";
                    ?>
                    <tr data-student-id="<?php echo $stu['student_id']; ?>" data-score-id="<?php echo $stu['score_id'] ?? ''; ?>">
                        <td class="text-center text-muted"><?php echo $counter++; ?></td>
                        <td><span class="small fw-bold"><?php echo htmlspecialchars($stu['student_no']); ?></span></td>
                        <td><?php echo htmlspecialchars($stu['student_name']); ?></td>
                        <td class="text-center">
                            <input type="number" class="grade-input score-input" 
                                   value="<?php echo $stu['score'] ?? ''; ?>" 
                                   min="0" max="<?php echo $assessment['max_score']; ?>" 
                                   step="0.01" placeholder="0">
                        </td>
                        <td class="text-center">
                            <span class="badge rounded-pill <?php 
                                echo match($score_status) {
                                    'graded' => 'bg-success',
                                    'submitted' => 'bg-info',
                                    default => 'bg-warning text-dark'
                                };
                            ?>"><?php echo ucfirst($score_status); ?></span>
                        </td>
                        <td>
                            <input type="text" class="feedback-input" 
                                   value="<?php echo htmlspecialchars($stu['feedback'] ?? ''); ?>" 
                                   placeholder="Optional feedback...">
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center py-5 text-muted">No students found in this section.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="p-3 bg-light border-top">
            <small class="text-muted fw-bold">Total Students: <?php echo $total_students; ?></small>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
const ASSESSMENT_ID = <?php echo $assessment_id; ?>;
const MAX_SCORE = <?php echo $assessment['max_score']; ?>;

async function saveAllGrades() {
    const rows = document.querySelectorAll('tbody tr[data-student-id]');
    if (rows.length === 0) {
        showAlert('No students to grade.', 'warning');
        return;
    }
    
    const grades = [];
    let hasErrors = false;
    
    rows.forEach(row => {
        const studentId = row.dataset.studentId;
        const scoreInput = row.querySelector('.score-input');
        const feedbackInput = row.querySelector('.feedback-input');
        const score = scoreInput.value.trim();
        
        if (score !== '') {
            const scoreVal = parseFloat(score);
            if (isNaN(scoreVal) || scoreVal < 0 || scoreVal > MAX_SCORE) {
                scoreInput.classList.add('is-invalid');
                hasErrors = true;
            } else {
                scoreInput.classList.remove('is-invalid');
                grades.push({
                    student_id: studentId,
                    score: scoreVal,
                    feedback: feedbackInput.value.trim()
                });
            }
        }
    });
    
    if (hasErrors) {
        showAlert('Some scores are invalid. Please check highlighted fields.', 'danger');
        return;
    }
    
    if (grades.length === 0) {
        showAlert('No scores to save. Enter at least one score.', 'warning');
        return;
    }
    
    try {
        const response = await fetch('process/grade_assessment.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                assessment_id: ASSESSMENT_ID,
                grades: grades
            })
        });
        const data = await response.json();
        if (data.status === 'success') {
            showAlert(`<strong>Saved!</strong> ${data.graded_count} student grades updated successfully.`, 'success');
            // Update status badges
            rows.forEach(row => {
                const scoreInput = row.querySelector('.score-input');
                if (scoreInput.value.trim() !== '') {
                    const badge = row.querySelector('.badge');
                    if (badge) {
                        badge.className = 'badge rounded-pill bg-success';
                        badge.textContent = 'Graded';
                    }
                }
            });
        } else {
            showAlert(data.message || 'Failed to save grades.', 'danger');
        }
    } catch (err) {
        console.error('Grade save error:', err);
        showAlert('Failed to save grades. Please try again.', 'danger');
    }
}

function showAlert(message, type) {
    const alertHtml = `<div class="alert alert-${type} alert-dismissible fade show border-0 shadow-sm animate__animated animate__shakeX" role="alert"><i class="bi ${type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'} me-2"></i>${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
    document.getElementById('alertContainer').innerHTML = alertHtml;
    document.querySelector('.body-scroll-part').scrollTo({ top: 0, behavior: 'smooth' });
}
</script>
</body>
</html>
