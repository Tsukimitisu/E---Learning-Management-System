<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_STUDENT) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Assessments";
$student_id = $_SESSION['user_id'];

// --- AJAX HANDLER ---
if (isset($_GET['ajax'])) {
    $filter_status = $_GET['status'] ?? 'all';
    $filter_type = $_GET['type'] ?? 'all';

    // Build conditions (Logic untouched)
    $conditions = ["e.student_id = $student_id"];
    if ($filter_status == 'pending') { $conditions[] = "(ascore.status IS NULL OR ascore.status = 'pending')"; } 
    elseif ($filter_status == 'submitted') { $conditions[] = "ascore.status = 'submitted'"; } 
    elseif ($filter_status == 'graded') { $conditions[] = "ascore.status = 'graded'"; }

    if ($filter_type != 'all') { $conditions[] = "a.assessment_type = '" . $conn->real_escape_string($filter_type) . "'"; }
    $where_clause = implode(' AND ', $conditions);

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
                    <button class="btn btn-maroon-outline w-100 mt-4" onclick="viewAssessment('.$row['id'].')">View Details</button>
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
$counts_query = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN ascore.status IS NULL OR ascore.status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN ascore.status = 'submitted' THEN 1 ELSE 0 END) as submitted,
        SUM(CASE WHEN ascore.status = 'graded' THEN 1 ELSE 0 END) as graded
    FROM assessments a
    INNER JOIN enrollments e ON e.class_id = a.class_id
    LEFT JOIN assessment_scores ascore ON ascore.assessment_id = a.id AND ascore.student_id = $student_id
    WHERE e.student_id = $student_id
")->fetch_assoc();

include '../../includes/header.php';
include '../../includes/sidebar.php'; 
?>

<style>
    /* --- SCROLL & LAYOUT ENGINE --- */
    html, body { height: 100%; margin: 0; overflow: hidden; }
    #content { height: 100vh; display: flex; flex-direction: column; overflow: hidden; }
    .header-fixed-part { flex: 0 0 auto; background: white; padding: 15px 30px; border-bottom: 1px solid #eee; z-index: 10; }
    .body-scroll-part { flex: 1 1 auto; overflow-y: auto; padding: 25px 30px 100px 30px; background-color: #f8f9fa; }

    /* --- ASSESSMENT UI --- */
    .filter-pills .nav-link {
        color: #666; font-weight: 700; font-size: 0.75rem; text-transform: uppercase;
        padding: 10px 20px; border-radius: 50px; transition: 0.3s; margin-right: 10px;
        background: white; border: 1px solid #eee;
    }
    .filter-pills .nav-link.active { background-color: var(--maroon); color: white; border-color: var(--maroon); box-shadow: 0 4px 10px rgba(128,0,0,0.2); }
    
    .type-filter-btn { border-radius: 8px; font-weight: 600; font-size: 0.8rem; padding: 6px 15px; transition: 0.2s; }
    
    .assessment-card {
        background: white; border-radius: 20px; border: none;
        box-shadow: 0 5px 15px rgba(0,0,0,0.05); transition: 0.3s;
        height: 100%; position: relative; overflow: hidden;
    }
    .assessment-card:hover { transform: translateY(-8px); box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
    .assessment-card.overdue { border-left: 5px solid #dc3545 !important; }

    .type-icon-box {
        width: 45px; height: 45px; background: rgba(0, 51, 102, 0.05);
        color: var(--blue); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;
    }

    .task-info-row { display: flex; gap: 15px; font-size: 0.75rem; color: #888; }
    .task-info-row i { color: var(--maroon); }

    .grade-pill { background: #e6fcf5; color: #0ca678; padding: 5px 15px; border-radius: 50px; font-weight: 800; font-size: 0.8rem; display: inline-block; }

    .btn-maroon-outline { border: 2px solid var(--maroon); color: var(--maroon); font-weight: 700; border-radius: 10px; transition: 0.3s; background: transparent; }
    .btn-maroon-outline:hover { background: var(--maroon); color: white; }

    .ajax-loading { opacity: 0.4; pointer-events: none; transition: 0.3s; }
</style>

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
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header p-4 text-white" style="background-color: var(--blue); border: none;">
                <h5 class="modal-title fw-bold"><i class="bi bi-info-circle me-2"></i>Assessment Brief</h5>
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

function viewAssessment(id) {
    const modal = new bootstrap.Modal(document.getElementById('assessmentModal'));
    modal.show();
    document.getElementById('assessmentDetails').innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-maroon" role="status"></div>
            <p class="mt-2 text-muted small">Retrieving instructions...</p>
        </div>
    `;
    
    // Simulate/Implement Details fetch
    setTimeout(() => {
        document.getElementById('assessmentDetails').innerHTML = `
            <div class="alert bg-light border-0 shadow-sm small">
                <i class="bi bi-info-circle-fill text-blue me-2"></i>
                Detailed instructions and submission portal will be handled by the Subject View.
            </div>
            <p class="text-muted small">ID REF: TASK-${id}</p>
        `;
    }, 600);
}
</script>
</body>
</html>