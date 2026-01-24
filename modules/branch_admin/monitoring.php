<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_BRANCH_ADMIN) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Monitoring & Compliance";
$branch_id = get_user_branch_id();
if ($branch_id === null) {
    echo "Error: Your account is not assigned to any branch. Please contact the School Administrator.";
    exit();
}
require_branch_assignment();

/** 
 * BACKEND LOGIC - UNTOUCHED 
 */
$end_date = date('Y-m-d');
$start_date = date('Y-m-d', strtotime('-30 days'));

// Low Attendance Classes (Logic preserved)
$low_attendance_classes = $conn->query("
    SELECT cl.id, cl.section_name, s.subject_code, s.subject_title, COUNT(DISTINCT e.student_id) as total_enrolled, COUNT(DISTINCT CASE WHEN a.status = 'present' THEN a.student_id END) as total_present, ROUND((COUNT(DISTINCT CASE WHEN a.status = 'present' THEN a.student_id END) * 100.0) / NULLIF(COUNT(DISTINCT e.student_id), 0), 2) as attendance_rate, CONCAT(up.first_name, ' ', up.last_name) as teacher_name
    FROM classes cl
    LEFT JOIN subjects s ON cl.subject_id = s.id
    LEFT JOIN enrollments e ON cl.id = e.class_id AND e.status = 'approved'
    LEFT JOIN attendance a ON cl.id = a.class_id AND a.attendance_date BETWEEN '$start_date' AND '$end_date'
    LEFT JOIN users u ON cl.teacher_id = u.id
    LEFT JOIN user_profiles up ON u.id = up.user_id
    WHERE cl.branch_id = $branch_id
    GROUP BY cl.id, cl.section_name, s.subject_code, s.subject_title, up.first_name, up.last_name
    HAVING attendance_rate < 70 OR attendance_rate IS NULL
    ORDER BY attendance_rate ASC
");

// Students with Poor Attendance (Logic preserved)
$poor_attendance_students = $conn->query("
    SELECT u.id as student_id, CONCAT(up.first_name, ' ', up.last_name) as student_name, cl.section_name, s.subject_code, s.subject_title, COUNT(a.attendance_date) as total_sessions, COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_count, ROUND((COUNT(CASE WHEN a.status = 'present' THEN 1 END) * 100.0) / NULLIF(COUNT(a.attendance_date), 0), 2) as attendance_rate
    FROM users u
    INNER JOIN user_profiles up ON u.id = up.user_id
    INNER JOIN enrollments e ON u.id = e.student_id AND e.status = 'approved'
    INNER JOIN classes cl ON e.class_id = cl.id AND cl.branch_id = $branch_id
    LEFT JOIN subjects s ON cl.subject_id = s.id
    LEFT JOIN attendance a ON cl.id = a.class_id AND a.attendance_date BETWEEN '$start_date' AND '$end_date'
    GROUP BY u.id, up.first_name, up.last_name, cl.id, cl.section_name, s.subject_code, s.subject_title
    HAVING attendance_rate < 70
    ORDER BY attendance_rate ASC
");

// Failing Students (Logic preserved)
$failing_students = $conn->query("
    SELECT u.id as student_id, CONCAT(up.first_name, ' ', up.last_name) as student_name, cl.section_name, s.subject_code, s.subject_title, g.final_grade, g.remarks, CONCAT(tup.first_name, ' ', tup.last_name) as teacher_name
    FROM users u
    INNER JOIN user_profiles up ON u.id = up.user_id
    INNER JOIN enrollments e ON u.id = e.student_id AND e.status = 'approved'
    INNER JOIN classes cl ON e.class_id = cl.id AND cl.branch_id = $branch_id
    LEFT JOIN subjects s ON cl.subject_id = s.id
    LEFT JOIN grades g ON u.id = g.student_id AND cl.id = g.class_id
    LEFT JOIN users tu ON cl.teacher_id = tu.id
    LEFT JOIN user_profiles tup ON tu.id = tup.user_id
    WHERE (g.final_grade < 75 AND g.final_grade IS NOT NULL) OR g.final_grade IS NULL
    ORDER BY g.final_grade ASC, up.last_name, up.first_name
");

// Classes without grades (Logic preserved)
$classes_without_grades = $conn->query("
    SELECT cl.id, cl.section_name, s.subject_code, s.subject_title, COUNT(DISTINCT e.student_id) as enrolled_count, COUNT(g.student_id) as graded_count, CONCAT(up.first_name, ' ', up.last_name) as teacher_name
    FROM classes cl
    LEFT JOIN subjects s ON cl.subject_id = s.id
    LEFT JOIN enrollments e ON cl.id = e.class_id AND e.status = 'approved'
    LEFT JOIN grades g ON cl.id = g.class_id
    LEFT JOIN users u ON cl.teacher_id = u.id
    LEFT JOIN user_profiles up ON u.id = up.user_id
    WHERE cl.branch_id = $branch_id
    GROUP BY cl.id, cl.section_name, s.subject_code, s.subject_title, up.first_name, up.last_name
    HAVING graded_count = 0 AND enrolled_count > 0
    ORDER BY enrolled_count DESC
");

// Grade Lock Status (Logic preserved)
$grade_lock_classes = $conn->query("
    SELECT cl.id, cl.section_name, s.subject_code, s.subject_title, CONCAT(up.first_name, ' ', up.last_name) as teacher_name, MAX(CASE WHEN gl.grading_period = 'prelim' THEN gl.is_locked END) as prelim_locked, MAX(CASE WHEN gl.grading_period = 'midterm' THEN gl.is_locked END) as midterm_locked, MAX(CASE WHEN gl.grading_period = 'final' THEN gl.is_locked END) as final_locked, MAX(CASE WHEN gl.grading_period = 'quarterly' THEN gl.is_locked END) as quarterly_locked
    FROM classes cl
    LEFT JOIN subjects s ON cl.subject_id = s.id
    LEFT JOIN users u ON cl.teacher_id = u.id
    LEFT JOIN user_profiles up ON u.id = up.user_id
    LEFT JOIN grade_locks gl ON cl.id = gl.class_id
    WHERE cl.branch_id = $branch_id
    GROUP BY cl.id, cl.section_name, s.subject_code, s.subject_title, up.first_name, up.last_name
    ORDER BY s.subject_code, cl.section_name
");

include '../../includes/header.php';
include '../../includes/sidebar.php'; 
?>

<style>
    /* --- SHARED UI DESIGN SYSTEM --- */
    .page-header {
        background: white; padding: 20px; border-radius: 15px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.03); margin-bottom: 25px;
    }

    .stat-card-modern {
        background: white; border-radius: 15px; padding: 20px; border: none;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05); display: flex; align-items: center;
        gap: 15px; transition: 0.3s; height: 100%; border-left: 5px solid transparent;
    }
    
    .content-card { background: white; border-radius: 15px; border: none; box-shadow: 0 5px 20px rgba(0,0,0,0.05); overflow: hidden; margin-bottom: 30px; }
    
    .card-header-modern {
        padding: 15px 20px; border-bottom: 1px solid #eee;
        font-weight: 700; color: var(--blue); text-transform: uppercase; font-size: 0.8rem; letter-spacing: 1px;
    }

    /* Table Styling */
    .table-modern thead th { 
        background: #f8f9fa; font-size: 0.7rem; text-transform: uppercase; 
        color: #888; padding: 15px 20px; border-bottom: 1px solid #eee;
    }
    .table-modern tbody td { padding: 15px 20px; vertical-align: middle; font-size: 0.85rem; }

    .grade-btn { font-size: 0.65rem; font-weight: 800; border-radius: 6px; padding: 4px 10px; text-transform: uppercase; }

    .btn-maroon { background-color: var(--maroon); color: white; font-weight: 700; border: none; }
    .btn-maroon:hover { background-color: #600000; color: white; transform: translateY(-1px); }

    .date-filter-group {
        background: #f1f3f5; border-radius: 10px; padding: 5px 15px; display: flex; align-items: center; gap: 10px;
    }
    .date-filter-group input { background: transparent; border: none; outline: none; font-size: 0.85rem; font-weight: 600; color: var(--blue); }
</style>

<div class="main-content-body animate__animated animate__fadeIn">
    
    <!-- 1. PAGE HEADER -->
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center animate__animated animate__fadeInDown">
        <div class="mb-2 mb-md-0">
            <h4 class="fw-bold mb-0" style="color: var(--blue);">
                <i class="bi bi-eye-fill me-2 text-maroon"></i>Monitoring & Compliance
            </h4>
            <p class="text-muted small mb-0">Track branch attendance, grading status, and academic risk metrics.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <div class="date-filter-group">
                <i class="bi bi-calendar-range text-muted"></i>
                <input type="date" id="start_date" value="<?php echo $start_date; ?>">
                <span class="text-muted small fw-bold">TO</span>
                <input type="date" id="end_date" value="<?php echo $end_date; ?>">
            </div>
            <button class="btn btn-maroon btn-sm px-4 rounded-pill fw-bold shadow-sm" onclick="updateMonitoring()">
                <i class="bi bi-arrow-clockwise me-1"></i> UPDATE DATA
            </button>
        </div>
    </div>

    <div id="alertContainer"></div>

    <!-- 2. SUMMARY ALERT STATS -->
    <div class="row g-4 mb-5">
        <div class="col-md-3">
            <div class="stat-card-modern" style="border-left-color: #dc3545;">
                <div class="bg-danger bg-opacity-10 p-3 rounded-3 text-danger"><i class="bi bi-calendar-x fs-4"></i></div>
                <div><h4 class="mb-0 fw-bold"><?php echo $low_attendance_classes->num_rows; ?></h4><small class="text-muted fw-bold text-uppercase" style="font-size:0.6rem;">Low Attendance</small></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card-modern" style="border-left-color: #fd7e14;">
                <div class="bg-warning bg-opacity-10 p-3 rounded-3 text-warning"><i class="bi bi-exclamation-octagon fs-4"></i></div>
                <div><h4 class="mb-0 fw-bold"><?php echo $failing_students->num_rows; ?></h4><small class="text-muted fw-bold text-uppercase" style="font-size:0.6rem;">At-Risk Students</small></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card-modern" style="border-left-color: #6f42c1;">
                <div class="bg-purple bg-opacity-10 p-3 rounded-3 text-primary"><i class="bi bi-file-earmark-x fs-4"></i></div>
                <div><h4 class="mb-0 fw-bold"><?php echo $classes_without_grades->num_rows; ?></h4><small class="text-muted fw-bold text-uppercase" style="font-size:0.6rem;">Ungraded Classes</small></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card-modern" style="border-left-color: var(--blue);">
                <div class="bg-primary bg-opacity-10 p-3 rounded-3 text-blue"><i class="bi bi-person-x fs-4"></i></div>
                <div><h4 class="mb-0 fw-bold"><?php echo $poor_attendance_students->num_rows; ?></h4><small class="text-muted fw-bold text-uppercase" style="font-size:0.6rem;">Absentee Students</small></div>
            </div>
        </div>
    </div>

    <!-- 3. LOW ATTENDANCE CLASSES -->
    <div class="content-card animate__animated animate__fadeInUp">
        <div class="card-header-modern bg-danger text-white" style="background: #dc3545 !important;">
            <i class="bi bi-calendar-x me-2"></i> Critical Class Attendance (Below 70%)
        </div>
        <div class="table-responsive">
            <table class="table table-hover table-modern mb-0">
                <thead>
                    <tr>
                        <th>Class Section</th>
                        <th>Subject Title</th>
                        <th>Teacher</th>
                        <th class="text-center">Enrolled</th>
                        <th class="text-center">Rate</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($class = $low_attendance_classes->fetch_assoc()): ?>
                    <tr>
                        <td class="fw-bold text-dark"><?php echo htmlspecialchars($class['section_name'] ?? 'N/A'); ?></td>
                        <td>
                            <div class="fw-bold"><?php echo htmlspecialchars($class['subject_code']); ?></div>
                            <small class="text-muted"><?php echo htmlspecialchars($class['subject_title']); ?></small>
                        </td>
                        <td><small class="fw-bold text-muted"><?php echo htmlspecialchars($class['teacher_name'] ?? 'TBA'); ?></small></td>
                        <td class="text-center"><?php echo $class['total_enrolled']; ?> Students</td>
                        <td class="text-center">
                            <span class="badge bg-danger bg-opacity-10 text-danger px-3 py-2 fw-bold">
                                <?php echo number_format($class['attendance_rate'] ?? 0, 1); ?>%
                            </span>
                        </td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-white border px-3" onclick="viewAttendanceDetails(<?php echo $class['id']; ?>)">Details</button>
                            <button class="btn btn-sm btn-white border text-danger px-3" onclick="sendReminder(<?php echo $class['id']; ?>, 'attendance')"><i class="bi bi-envelope"></i></button>
                        </td>
                    </tr>
                    <?php endwhile; if ($low_attendance_classes->num_rows == 0): ?>
                    <tr><td colspan="6" class="text-center text-success py-5"><i class="bi bi-check-circle me-2"></i> All classes maintaining healthy attendance.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 4. ACADEMIC ATTENTION (FAILING) -->
    <div class="content-card animate__animated animate__fadeInUp">
        <div class="card-header-modern bg-warning text-dark" style="background: #ffc107 !important;">
            <i class="bi bi-mortarboard me-2"></i> Students Requiring Academic Attention
        </div>
        <div class="table-responsive">
            <table class="table table-hover table-modern mb-0">
                <thead>
                    <tr>
                        <th>Student Name</th>
                        <th>Subject & Section</th>
                        <th>Instructor</th>
                        <th class="text-center">Current Grade</th>
                        <th>Remarks</th>
                        <th class="text-end">Notify</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($student = $failing_students->fetch_assoc()): ?>
                    <tr>
                        <td class="fw-bold text-dark"><?php echo htmlspecialchars($student['student_name']); ?></td>
                        <td>
                            <div class="fw-bold small"><?php echo htmlspecialchars($student['subject_code']); ?></div>
                            <small class="text-muted"><?php echo htmlspecialchars($student['section_name']); ?></small>
                        </td>
                        <td><small><?php echo htmlspecialchars($student['teacher_name']); ?></small></td>
                        <td class="text-center">
                            <span class="badge <?php echo ($student['final_grade'] < 75) ? 'bg-danger' : 'bg-secondary'; ?>">
                                <?php echo $student['final_grade'] ? number_format($student['final_grade'], 2) : 'N/A'; ?>
                            </span>
                        </td>
                        <td><small class="text-muted italic"><?php echo htmlspecialchars($student['remarks'] ?? 'No comments'); ?></small></td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-white border text-warning px-3" onclick="sendReminder(<?php echo $student['student_id']; ?>, 'academic')">
                                <i class="bi bi-bell-fill"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; if ($failing_students->num_rows == 0): ?>
                    <tr><td colspan="6" class="text-center text-success py-5"><i class="bi bi-shield-check me-2"></i> No failing students reported.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 5. MISSING GRADES -->
    <div class="content-card animate__animated animate__fadeInUp">
        <div class="card-header-modern bg-purple text-white" style="background: #6f42c1 !important;">
            <i class="bi bi-file-earmark-x me-2"></i> Class Records: Missing Final Grades
        </div>
        <div class="table-responsive">
            <table class="table table-hover table-modern mb-0">
                <thead>
                    <tr>
                        <th>Section</th>
                        <th>Subject</th>
                        <th>Teacher</th>
                        <th class="text-center">Enrolled</th>
                        <th class="text-end">Compliance Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($class = $classes_without_grades->fetch_assoc()): ?>
                    <tr>
                        <td class="fw-bold"><?php echo htmlspecialchars($class['section_name']); ?></td>
                        <td><?php echo htmlspecialchars($class['subject_title']); ?></td>
                        <td><small class="fw-bold"><?php echo htmlspecialchars($class['teacher_name']); ?></small></td>
                        <td class="text-center"><span class="badge bg-light text-dark border"><?php echo $class['enrolled_count']; ?> Students</span></td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-white border text-primary" onclick="sendReminder(<?php echo $class['id']; ?>, 'grades')">REMIND</button>
                            <button class="btn btn-sm btn-maroon px-3" onclick="escalateIssue(<?php echo $class['id']; ?>, 'missing_grades')">ESCALATE</button>
                        </td>
                    </tr>
                    <?php endwhile; if ($classes_without_grades->num_rows == 0): ?>
                    <tr><td colspan="5" class="text-center text-success py-5"><i class="bi bi-check-all me-2"></i> All active classes have submitted grades.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 6. GRADE LOCKS -->
    <div class="content-card animate__animated animate__fadeInUp">
        <div class="card-header-modern bg-blue text-white" style="background: var(--blue) !important;">
            <i class="bi bi-lock me-2"></i> Class Record Lock Management
        </div>
        <div class="table-responsive">
            <table class="table table-hover table-modern mb-0">
                <thead>
                    <tr>
                        <th>Class Section</th>
                        <th>Subject Title</th>
                        <th class="text-center">Prelim</th>
                        <th class="text-center">Midterm</th>
                        <th class="text-center">Final</th>
                        <th class="text-center">SHS Quarterly</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($class = $grade_lock_classes->fetch_assoc()): ?>
                    <tr>
                        <td class="fw-bold"><?php echo htmlspecialchars($class['section_name']); ?></td>
                        <td><small class="text-muted"><?php echo htmlspecialchars($class['subject_title']); ?></small></td>
                        <?php 
                        $periods = ['prelim' => 'prelim_locked', 'midterm' => 'midterm_locked', 'final' => 'final_locked', 'quarterly' => 'quarterly_locked'];
                        foreach($periods as $key => $col): 
                            $is_locked = (int)($class[$col] ?? 0) === 1;
                        ?>
                        <td class="text-center">
                            <button class="grade-btn border-0 w-100 <?php echo $is_locked ? 'bg-danger text-white' : 'bg-success text-white'; ?>" 
                                    onclick="toggleGradeLock(<?php echo $class['id']; ?>, '<?php echo $key; ?>', '<?php echo $is_locked ? 'unlock' : 'lock'; ?>')">
                                <i class="bi <?php echo $is_locked ? 'bi-lock-fill' : 'bi-unlock-fill'; ?> me-1"></i>
                                <?php echo $is_locked ? 'LOCKED' : 'OPEN'; ?>
                            </button>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// All JS logic preserved from original
function updateMonitoring() {
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    if (startDate && endDate) {
        window.location.href = `monitoring.php?start_date=${startDate}&end_date=${endDate}`;
    }
}

function toggleGradeLock(classId, period, action) {
    if (!confirm(`Are you sure you want to ${action} ${period} records for this class?`)) return;
    fetch('process/toggle_grade_lock.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ class_id: classId, grading_period: period, action: action })
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            showAlert(data.message, 'success');
            setTimeout(() => location.reload(), 800);
        } else { showAlert(data.message, 'danger'); }
    });
}

function viewAttendanceDetails(classId) { window.location.href = `attendance_details.php?class_id=${classId}`; }
function sendReminder(id, type) {
    if (!confirm('Send automated reminder notification?')) return;
    fetch('process/send_reminder.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id, type: type })
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') showAlert(data.message, 'success');
        else showAlert(data.message, 'danger');
    });
}

function escalateIssue(id, issueType) {
    if (!confirm('Escalate this issue to School Administration?')) return;
    fetch('process/escalate_issue.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id, issue_type: issueType })
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') showAlert(data.message, 'success');
        else showAlert(data.message, 'danger');
    });
}

function showAlert(message, type) {
    const container = document.getElementById('alertContainer');
    container.innerHTML = `<div class="alert alert-${type} alert-dismissible fade show border-0 shadow-sm" role="alert">${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
</script>

<?php include '../../includes/footer.php'; ?>