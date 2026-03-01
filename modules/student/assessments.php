<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_STUDENT) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Assessments";
$student_id = $_SESSION['user_id'];
$current_ay = $conn->query("SELECT id, year_name FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$current_ay_id = $current_ay['id'] ?? 0;

$section_info = $conn->query("
    SELECT s.id, s.section_name, s.branch_id
    FROM section_students ss
    INNER JOIN sections s ON ss.section_id = s.id
    WHERE ss.student_id = $student_id
      AND ss.status = 'active'
      AND s.academic_year_id = $current_ay_id
    LIMIT 1
")->fetch_assoc();

$has_subject_enrollment_table = false;
$check_sse = $conn->query("SHOW TABLES LIKE 'student_subject_enrollments'");
if ($check_sse && $check_sse->num_rows > 0) {
    $has_subject_enrollment_table = true;
}

$use_subject_enrollment = false;
$enrolled_subject_ids = [];
if ($has_subject_enrollment_table) {
    $sse_count = $conn->query("
        SELECT COUNT(*) as cnt
        FROM student_subject_enrollments
        WHERE student_id = $student_id AND academic_year_id = $current_ay_id AND status = 'enrolled'
    ")->fetch_assoc();
    $use_subject_enrollment = (($sse_count['cnt'] ?? 0) > 0);

    if ($use_subject_enrollment) {
        $sse_subjects = $conn->query("
            SELECT DISTINCT subject_id
            FROM student_subject_enrollments
            WHERE student_id = $student_id
              AND academic_year_id = $current_ay_id
              AND status = 'enrolled'
              AND subject_id IS NOT NULL
        ");
        while ($row = $sse_subjects->fetch_assoc()) {
            $enrolled_subject_ids[] = (int)$row['subject_id'];
        }
        if (empty($enrolled_subject_ids)) {
            $use_subject_enrollment = false;
        }
    }
}

// --- AJAX HANDLER ---
if (isset($_GET['ajax'])) {

    // Single assessment detail fetch
    if (isset($_GET['detail_id'])) {
        $detail_id = (int)$_GET['detail_id'];
        $section_name = $conn->real_escape_string($section_info['section_name'] ?? '');
        $branch_id = (int)($section_info['branch_id'] ?? 0);

        $detail = $conn->query("
            SELECT a.*,
                   COALESCE(csub.subject_code, c.course_code) as subject_code,
                   COALESCE(csub.subject_title, c.title) as subject_title,
                   CONCAT(up.first_name, ' ', up.last_name) as teacher_name,
                   ascore.score, ascore.status as submission_status, ascore.feedback, ascore.graded_at,
                   ascore.submitted_file, ascore.submitted_at, ascore.student_notes
            FROM assessments a
            INNER JOIN classes cl ON a.class_id = cl.id
            LEFT JOIN curriculum_subjects csub ON cl.curriculum_subject_id = csub.id
            LEFT JOIN courses c ON cl.course_id = c.id
            LEFT JOIN assessment_scores ascore ON ascore.assessment_id = a.id AND ascore.student_id = $student_id
            LEFT JOIN user_profiles up ON a.created_by = up.user_id
            WHERE a.id = $detail_id
              AND cl.section_name = '$section_name'
              AND cl.branch_id = $branch_id
              AND cl.academic_year_id = $current_ay_id
            LIMIT 1
        ")->fetch_assoc();

        header('Content-Type: application/json');
        if ($detail) {
            echo json_encode(['success' => true, 'data' => $detail]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Assessment not found']);
        }
        exit();
    }

    // List assessments (existing handler)
    $filter_status = $_GET['status'] ?? 'all';
    $filter_type = $_GET['type'] ?? 'all';
    $section_name = $conn->real_escape_string($section_info['section_name'] ?? '');
    $branch_id = (int)($section_info['branch_id'] ?? 0);

    if (empty($section_name) || $branch_id <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['html' => '<div class="col-12 text-center py-5 opacity-50"><i class="bi bi-clipboard-x display-1"></i><p class="mt-3">No assessments found. You are not assigned to an active section.</p></div>']);
        exit();
    }

    // Build conditions (Logic untouched)
    $conditions = [
        "cl.section_name = '$section_name'",
        "cl.branch_id = $branch_id",
        "cl.academic_year_id = $current_ay_id"
    ];
    if ($use_subject_enrollment) {
        $conditions[] = "cl.curriculum_subject_id IN (" . implode(',', $enrolled_subject_ids) . ")";
    }
    if ($filter_status == 'pending') { $conditions[] = "(ascore.status IS NULL OR ascore.status = 'pending')"; } 
    elseif ($filter_status == 'submitted') { $conditions[] = "ascore.status = 'submitted'"; } 
    elseif ($filter_status == 'graded') { $conditions[] = "ascore.status = 'graded'"; }

    if ($filter_type != 'all') { $conditions[] = "a.assessment_type = '" . $conn->real_escape_string($filter_type) . "'"; }
    $where_clause = implode(' AND ', $conditions);

    $assessments = $conn->query("
        SELECT a.*,
               COALESCE(csub.subject_code, c.course_code) as subject_code,
               COALESCE(csub.subject_title, c.title) as subject_title,
               CONCAT(up.first_name, ' ', up.last_name) as teacher_name,
               ascore.score, ascore.status as submission_status, ascore.feedback, ascore.graded_at,
               ascore.submitted_file, ascore.submitted_at, ascore.student_notes
        FROM assessments a
        INNER JOIN classes cl ON a.class_id = cl.id
        LEFT JOIN curriculum_subjects csub ON cl.curriculum_subject_id = csub.id
        LEFT JOIN courses c ON cl.course_id = c.id
        LEFT JOIN assessment_scores ascore ON ascore.assessment_id = a.id AND ascore.student_id = $student_id
        LEFT JOIN user_profiles up ON a.created_by = up.user_id
        WHERE $where_clause
        ORDER BY a.scheduled_date DESC, a.created_at DESC
    ");
    if (!$assessments) {
        header('Content-Type: application/json');
        echo json_encode(['html' => '<div class="col-12 text-center py-5 opacity-50"><i class="bi bi-exclamation-triangle display-1"></i><p class="mt-3">Unable to load assessments right now.</p></div>']);
        exit();
    }

    $html = '';
    $count = 0;
    while ($row = $assessments->fetch_assoc()) {
        $count++;
        $status = $row['submission_status'] ?? 'pending';
        $is_overdue = $row['scheduled_date'] && strtotime($row['scheduled_date']) < time() && $status == 'pending';
        
        $type_icons = ['quiz' => 'bi-patch-question', 'exam' => 'bi-file-earmark-medical', 'activity' => 'bi-lightning-charge', 'project' => 'bi-kanban'];
        $icon = $type_icons[$row['assessment_type']] ?? 'bi-file-earmark';
        
        $status_color = ['pending' => 'warning', 'submitted' => 'info', 'graded' => 'success'][$status] ?? 'secondary';
        
        $html .= '
        <div class="col-md-6 col-lg-4 animate__animated animate__zoomIn">
            <div class="assessment-card '.($is_overdue ? 'overdue' : '').'">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between mb-3">
                        <span class="badge bg-light text-primary border border-primary px-3">'.$row['subject_code'].'</span>
                        <span class="badge bg-'.$status_color.'">'.strtoupper($status).'</span>
                    </div>
                    <div class="d-flex align-items-start gap-3 mb-3">
                        <div class="type-icon-box"><i class="bi '.$icon.'"></i></div>
                        <div>
                            <h6 class="fw-bold mb-0 text-dark">'.htmlspecialchars($row['title']).'</h6>
                            <small class="text-muted text-uppercase fw-bold" style="font-size:0.6rem">'.$row['assessment_type'].'</small>
                        </div>
                    </div>
                    <p class="text-muted small mb-3">'.htmlspecialchars($row['subject_title']).'</p>
                    <div class="task-info-row">
                        <span><i class="bi bi-calendar3"></i> '.($row['scheduled_date'] ? date('M d, Y', strtotime($row['scheduled_date'])) : 'TBA').'</span>
                        '.($row['duration_minutes'] ? '<span><i class="bi bi-clock"></i> '.$row['duration_minutes'].'m</span>' : '').'
                    </div>
                    '.($status == 'graded' ? '<div class="grade-pill mt-3">Score: '.$row['score'].' / '.$row['max_score'].'</div>' : '').'
                    '.($status == 'submitted' && !empty($row['submitted_at']) ? '<div class="mt-2"><small class="text-muted"><i class="bi bi-check2-circle text-info"></i> Submitted '.date('M d, g:i A', strtotime($row['submitted_at'])).'</small></div>' : '').'
                    <div class="d-flex gap-2 mt-4">
                        <button class="btn btn-maroon-outline flex-fill" onclick="viewAssessment('.$row['id'].')"><i class="bi bi-eye me-1"></i>View</button>
                        '.($status == 'pending' ? '<button class="btn btn-success btn-sm flex-fill" onclick="markAsDone('.$row['id'].')"><i class="bi bi-check-lg me-1"></i>Mark Done</button>' : '').'
                    </div>
                </div>
            </div>
        </div>';
    }

    if($count == 0) {
        $html = '<div class="col-12 text-center py-5 opacity-50"><i class="bi bi-clipboard-x display-1"></i><p class="mt-3">No assessments found matching your filter.</p></div>';
    }

    header('Content-Type: application/json');
    echo json_encode(['html' => $html]);
    exit();
}

/** 
 * INITIAL LOAD LOGIC 
 */
$filter_status = 'all';
$filter_type = 'all';
// Fetch initial counts for labels (Logic untouched)
$counts_query = ['total' => 0, 'pending' => 0, 'submitted' => 0, 'graded' => 0];
if (!empty($section_info)) {
    $section_name = $conn->real_escape_string($section_info['section_name'] ?? '');
    $branch_id = (int)($section_info['branch_id'] ?? 0);
    $subject_filter_sql = "";
    if ($use_subject_enrollment) {
        $subject_filter_sql = " AND cl.curriculum_subject_id IN (" . implode(',', $enrolled_subject_ids) . ") ";
    }
    $counts_result = $conn->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN ascore.status IS NULL OR ascore.status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN ascore.status = 'submitted' THEN 1 ELSE 0 END) as submitted,
            SUM(CASE WHEN ascore.status = 'graded' THEN 1 ELSE 0 END) as graded
        FROM assessments a
        INNER JOIN classes cl ON a.class_id = cl.id
        LEFT JOIN assessment_scores ascore ON ascore.assessment_id = a.id AND ascore.student_id = $student_id
        WHERE cl.section_name = '$section_name'
          AND cl.branch_id = $branch_id
          AND cl.academic_year_id = $current_ay_id
          $subject_filter_sql
    ");
    if ($counts_result) {
        $counts_query = $counts_result->fetch_assoc();
    }
}

include '../../includes/header.php';
?>

<link rel="stylesheet" href="css/assessments.css">

<!-- Part 1: Fixed Header -->
<div class="header-fixed-part animate__animated animate__fadeInDown">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="fw-bold mb-0" style="color: var(--blue);"><i class="bi bi-clipboard-check-fill me-2 text-maroon"></i>Assessments</h4>
            <p class="text-muted small mb-0">Quizzes, Exams, and Projects Overview</p>
        </div>
        <div class="badge bg-light text-dark border px-3 py-2 rounded-pill shadow-sm">
            <i class="bi bi-funnel me-1 text-primary"></i> Filter Active
        </div>
    </div>
</div>

<!-- Part 2: Scrollable Content -->
<div class="body-scroll-part">
    
    <!-- Status Tabs (AJAX) -->
    <ul class="nav filter-pills mb-4" id="statusFilters">
        <li class="nav-item"><a class="nav-link active" data-status="all" href="#">All Tasks <span class="ms-1 opacity-50"><?php echo $counts_query['total']; ?></span></a></li>
        <li class="nav-item"><a class="nav-link" data-status="pending" href="#">Pending <span class="ms-1 opacity-50"><?php echo $counts_query['pending']; ?></span></a></li>
        <li class="nav-item"><a class="nav-link" data-status="submitted" href="#">Submitted <span class="ms-1 opacity-50"><?php echo $counts_query['submitted']; ?></span></a></li>
        <li class="nav-item"><a class="nav-link" data-status="graded" href="#">Graded <span class="ms-1 opacity-50"><?php echo $counts_query['graded']; ?></span></a></li>
    </ul>

    <!-- Type Selection (AJAX) -->
    <div class="card border-0 shadow-sm rounded-4 mb-5">
        <div class="card-body py-3">
            <div class="d-flex align-items-center flex-wrap gap-2" id="typeFilters">
                <span class="text-muted small fw-bold text-uppercase me-2">Categories:</span>
                <button class="btn btn-sm btn-primary type-filter-btn" data-type="all">All</button>
                <button class="btn btn-sm btn-outline-primary type-filter-btn" data-type="quiz">Quiz</button>
                <button class="btn btn-sm btn-outline-primary type-filter-btn" data-type="exam">Exam</button>
                <button class="btn btn-sm btn-outline-primary type-filter-btn" data-type="activity">Activity</button>
                <button class="btn btn-sm btn-outline-primary type-filter-btn" data-type="project">Project</button>
            </div>
        </div>
    </div>

    <!-- Results Container -->
    <div class="row g-4" id="assessmentsGrid">
        <!-- Content loaded via AJAX -->
    </div>

</div>

<!-- Assessment Details Modal -->
<div class="modal fade" id="assessmentModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header p-4 text-white" style="background-color: var(--blue); border: none;">
                <h5 class="modal-title fw-bold"><i class="bi bi-info-circle me-2"></i>Assessment Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="assessmentDetails">
                <!-- Data fetched via viewAssessment function -->
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<!-- --- AJAX LOGIC --- -->
<script>
let currentStatus = 'all';
let currentType = 'all';

$(document).ready(function() {
    // Initial Load
    loadAssessments();

    // Status Filter Click
    $('#statusFilters .nav-link').on('click', function(e) {
        e.preventDefault();
        $('#statusFilters .nav-link').removeClass('active');
        $(this).addClass('active');
        currentStatus = $(this).data('status');
        loadAssessments();
    });

    // Type Filter Click
    $('#typeFilters .type-filter-btn').on('click', function() {
        $('#typeFilters .type-filter-btn').removeClass('btn-primary').addClass('btn-outline-primary');
        $(this).removeClass('btn-outline-primary').addClass('btn-primary');
        currentType = $(this).data('type');
        loadAssessments();
    });
});

async function loadAssessments() {
    const grid = $('#assessmentsGrid');
    grid.addClass('ajax-loading');

    try {
        const response = await fetch(`?ajax=1&status=${currentStatus}&type=${currentType}`);
        const data = await response.json();
        
        grid.html(data.html);
        
        setTimeout(() => grid.removeClass('ajax-loading'), 200);
    } catch (e) {
        console.error("Failed to load assessments:", e);
        grid.removeClass('ajax-loading');
    }
}

async function viewAssessment(id) {
    const modal = new bootstrap.Modal(document.getElementById('assessmentModal'));
    modal.show();
    const container = document.getElementById('assessmentDetails');
    container.innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-maroon" role="status"></div>
            <p class="mt-2 text-muted small">Loading details...</p>
        </div>
    `;

    try {
        const response = await fetch(`?ajax=1&detail_id=${id}`);
        const result = await response.json();

        if (!result.success) {
            container.innerHTML = `<div class="alert alert-danger">${result.message}</div>`;
            return;
        }

        const d = result.data;
        const status = d.submission_status || 'pending';
        const statusColors = {pending: 'warning', submitted: 'info', graded: 'success'};
        const statusColor = statusColors[status] || 'secondary';
        const isGraded = status === 'graded';
        const isSubmitted = status === 'submitted';
        const isPending = status === 'pending';

        let html = `
            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h5 class="fw-bold mb-1">${escapeHtml(d.title)}</h5>
                        <span class="badge bg-light text-primary border border-primary px-3 me-2">${d.subject_code}</span>
                        <span class="badge bg-${statusColor}">${status.toUpperCase()}</span>
                    </div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-sm-6">
                        <small class="text-muted d-block">Subject</small>
                        <strong>${escapeHtml(d.subject_title)}</strong>
                    </div>
                    <div class="col-sm-6">
                        <small class="text-muted d-block">Teacher</small>
                        <strong>${escapeHtml(d.teacher_name || 'N/A')}</strong>
                    </div>
                    <div class="col-sm-4">
                        <small class="text-muted d-block">Type</small>
                        <strong class="text-capitalize">${d.assessment_type}</strong>
                    </div>
                    <div class="col-sm-4">
                        <small class="text-muted d-block">Due Date</small>
                        <strong>${d.scheduled_date && d.scheduled_date !== '0000-00-00' ? new Date(d.scheduled_date).toLocaleDateString('en-US', {month: 'short', day: 'numeric', year: 'numeric'}) : 'TBA'}</strong>
                    </div>
                    <div class="col-sm-4">
                        <small class="text-muted d-block">Max Score</small>
                        <strong>${d.max_score}</strong>
                    </div>
                </div>
        `;

        // Instructions
        if (d.instructions) {
            html += `
                <div class="alert bg-light border-0 shadow-sm mb-3">
                    <h6 class="fw-bold mb-2"><i class="bi bi-journal-text me-1"></i> Instructions</h6>
                    <p class="mb-0 small">${escapeHtml(d.instructions)}</p>
                </div>
            `;
        }

        // Graded result
        if (isGraded) {
            html += `
                <div class="alert alert-success border-0 shadow-sm">
                    <h6 class="fw-bold mb-2"><i class="bi bi-award me-1"></i> Grading Result</h6>
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="fs-4 fw-bold">${d.score}</span> <span class="text-muted">/ ${d.max_score}</span>
                        </div>
                        <small class="text-muted">Graded: ${d.graded_at ? new Date(d.graded_at).toLocaleDateString() : '-'}</small>
                    </div>
                    ${d.feedback ? `<hr class="my-2"><small class="text-muted"><strong>Feedback:</strong> ${escapeHtml(d.feedback)}</small>` : ''}
                </div>
            `;
        }

        // Show existing submission
        if ((isSubmitted || isGraded) && d.submitted_at) {
            html += `
                <div class="alert alert-info border-0 shadow-sm mb-3">
                    <h6 class="fw-bold mb-2"><i class="bi bi-check2-circle me-1"></i> Your Submission</h6>
                    <small class="text-muted d-block mb-1">Submitted: ${new Date(d.submitted_at).toLocaleString()}</small>
                    ${d.student_notes ? `<p class="small mb-1"><strong>Notes:</strong> ${escapeHtml(d.student_notes)}</p>` : ''}
                    ${d.submitted_file ? `<a href="../../${d.submitted_file}" target="_blank" class="btn btn-sm btn-outline-primary mt-1"><i class="bi bi-download me-1"></i>Download Submitted File</a>` : '<small class="text-muted">No file attached</small>'}
                </div>
            `;
        }

        // Submission form (for pending or submitted—allow resubmission)
        if (!isGraded) {
            html += `
                <hr>
                <h6 class="fw-bold mb-3"><i class="bi bi-upload me-1"></i> ${isSubmitted ? 'Resubmit' : 'Submit'} Your Work</h6>
                <form id="submissionForm" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="submit">
                    <input type="hidden" name="assessment_id" value="${d.id}">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Upload File <small class="text-muted">(Max 10MB - PDF, DOC, DOCX, XLS, PPT, Images, ZIP)</small></label>
                        <input type="file" class="form-control" name="submission_file" id="submissionFile" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.webp,.txt,.zip,.rar">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Notes <small class="text-muted">(optional)</small></label>
                        <textarea class="form-control" name="student_notes" rows="3" placeholder="Add any notes for your teacher...">${d.student_notes ? escapeHtml(d.student_notes) : ''}</textarea>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-fill" id="submitBtn">
                            <i class="bi bi-send me-1"></i> ${isSubmitted ? 'Resubmit' : 'Submit Assessment'}
                        </button>
                        ${isPending ? `<button type="button" class="btn btn-success flex-fill" onclick="markAsDone(${d.id})">
                            <i class="bi bi-check-lg me-1"></i> Mark as Done
                        </button>` : ''}
                    </div>
                </form>
            `;
        }

        html += `</div>`;
        container.innerHTML = html;

        // Bind form submit
        const form = document.getElementById('submissionForm');
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                submitAssessment(this);
            });
        }

    } catch (e) {
        console.error("Failed to load details:", e);
        container.innerHTML = `<div class="alert alert-danger">Failed to load assessment details.</div>`;
    }
}

async function submitAssessment(form) {
    const btn = document.getElementById('submitBtn');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Submitting...';

    try {
        const formData = new FormData(form);
        
        // Add CSRF token
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        if (csrfMeta) {
            formData.append('csrf_token', csrfMeta.content);
        }

        const response = await fetch('process/submit_assessment.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if (result.success) {
            Swal.fire({icon: 'success', title: 'Success!', text: result.message, timer: 2000, showConfirmButton: false});
            bootstrap.Modal.getInstance(document.getElementById('assessmentModal')).hide();
            loadAssessments();
        } else {
            Swal.fire({icon: 'error', title: 'Error', text: result.message});
        }
    } catch (e) {
        console.error("Submit failed:", e);
        Swal.fire({icon: 'error', title: 'Error', text: 'Failed to submit. Please try again.'});
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}

async function markAsDone(id) {
    const confirm = await Swal.fire({
        title: 'Mark as Done?',
        text: 'This will mark the assessment as completed without uploading a file.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#198754',
        confirmButtonText: 'Yes, Mark Done'
    });

    if (!confirm.isConfirmed) return;

    try {
        const formData = new FormData();
        formData.append('action', 'mark_done');
        formData.append('assessment_id', id);
        
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        if (csrfMeta) {
            formData.append('csrf_token', csrfMeta.content);
        }

        const response = await fetch('process/submit_assessment.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if (result.success) {
            Swal.fire({icon: 'success', title: 'Done!', text: result.message, timer: 2000, showConfirmButton: false});
            // Close modal if open
            const modalEl = document.getElementById('assessmentModal');
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
            loadAssessments();
        } else {
            Swal.fire({icon: 'error', title: 'Error', text: result.message});
        }
    } catch (e) {
        console.error("Mark done failed:", e);
        Swal.fire({icon: 'error', title: 'Error', text: 'Failed to mark as done.'});
    }
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>
</body>
</html>
