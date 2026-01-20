<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_REGISTRAR) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Student Enrollment";
$registrar_id = $_SESSION['user_id'];

/** 
 * BACKEND LOGIC - UNTOUCHED 
 */
$registrar_profile = $conn->query("SELECT branch_id FROM user_profiles WHERE user_id = $registrar_id")->fetch_assoc();
$branch_id = $registrar_profile['branch_id'] ?? 0;
$branch = $conn->query("SELECT * FROM branches WHERE id = $branch_id")->fetch_assoc();
$current_ay = $conn->query("SELECT id, year_name FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$current_ay_id = $current_ay['id'] ?? 0;

$branch_condition = $branch_id > 0 ? "up.branch_id = $branch_id" : "(up.branch_id IS NULL OR up.branch_id = 0 OR up.branch_id > 0)";
$students_query = "
    SELECT 
        s.user_id, s.student_no, CONCAT(up.first_name, ' ', up.last_name) as full_name,
        COALESCE(p.program_code, ss.strand_code) as program_code,
        COALESCE(p.program_name, ss.strand_name) as program_name,
        COALESCE((SELECT SUM(amount) FROM student_fees WHERE student_id = s.user_id), 0) as total_fees,
        COALESCE((SELECT SUM(amount) FROM payments WHERE student_id = s.user_id AND status = 'verified'), 0) as total_paid
    FROM students s
    INNER JOIN user_profiles up ON s.user_id = up.user_id
    LEFT JOIN programs p ON s.course_id = p.id
    LEFT JOIN shs_strands ss ON s.course_id = ss.id
    WHERE $branch_condition
    ORDER BY up.last_name, up.first_name
";
$students_result = $conn->query($students_query);

$class_branch_condition = $branch_id > 0 ? "cl.branch_id = $branch_id" : "(cl.branch_id IS NULL OR cl.branch_id >= 0)";
$sections_query = "
    SELECT 
        cl.id, cl.section_name, cs.subject_code, cs.subject_title, cs.units,
        cl.max_capacity, cl.current_enrolled, cl.schedule, cl.room,
        CONCAT(up.first_name, ' ', up.last_name) as teacher_name,
        COALESCE(p.program_code, ss.strand_code) as program_code,
        COALESCE(p.program_name, ss.strand_name) as program_name,
        COALESCE(pyl.year_name, sgl.grade_name) as year_level,
        (cl.max_capacity - cl.current_enrolled) as available_slots
    FROM classes cl
    LEFT JOIN curriculum_subjects cs ON cl.curriculum_subject_id = cs.id
    LEFT JOIN programs p ON cs.program_id = p.id
    LEFT JOIN shs_strands ss ON cs.shs_strand_id = ss.id
    LEFT JOIN program_year_levels pyl ON cs.year_level_id = pyl.id
    LEFT JOIN shs_grade_levels sgl ON cs.shs_grade_level_id = sgl.id
    LEFT JOIN users u ON cl.teacher_id = u.id
    LEFT JOIN user_profiles up ON u.id = up.user_id
    WHERE $class_branch_condition
    AND (cl.academic_year_id = $current_ay_id OR cl.academic_year_id IS NULL)
    AND cl.current_enrolled < cl.max_capacity
    ORDER BY COALESCE(p.program_name, ss.strand_name), cs.subject_code, cl.section_name
";
$sections_result = $conn->query($sections_query);

include '../../includes/header.php';
include '../../includes/sidebar.php'; 
?>

<style>
    /* --- SCROLL & LAYOUT ENGINE --- */
    html, body { height: 100%; margin: 0; overflow: hidden; }
    #content { height: 100vh; display: flex; flex-direction: column; overflow: hidden; }
    .header-fixed-part { flex: 0 0 auto; background: white; padding: 15px 30px; border-bottom: 1px solid #eee; z-index: 10; }
    .body-scroll-part { flex: 1 1 auto; overflow-y: auto; padding: 25px 30px 100px 30px; background-color: #f8f9fa; }

    /* --- FANTASTIC ENROLLMENT UI --- */
    .student-list-container {
        height: 450px;
        overflow-y: auto;
        border-radius: 12px;
        border: 1px solid #eee;
    }

    .student-card-item {
        border-left: 4px solid transparent;
        transition: 0.2s;
        cursor: pointer;
    }
    .student-card-item:hover { background-color: #fcfcfc; border-left-color: var(--blue); }
    .student-card-item.active { background-color: #e7f5ff; border-left-color: var(--blue); border-right: none; }

    .main-card-modern {
        background: white; border-radius: 20px; border: none;
        box-shadow: 0 5px 20px rgba(0,0,0,0.05); overflow: hidden;
    }
    .card-header-blue { background: var(--blue); color: white; padding: 15px 25px; font-weight: 700; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 1px; }
    .card-header-maroon { background: var(--maroon); color: white; padding: 15px 25px; font-weight: 700; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 1px; }

    .table-modern thead th { 
        background: #fcfcfc; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 1px; color: #888; 
        padding: 12px 15px; position: sticky; top: -1px; z-index: 5;
    }
    .table-modern tbody td { padding: 12px 15px; vertical-align: middle; font-size: 0.85rem; border-bottom: 1px solid #f1f1f1; }

    .btn-enroll-action {
        background: var(--blue); color: white; border: none; border-radius: 8px; 
        padding: 6px 12px; transition: 0.3s;
    }
    .btn-enroll-action:hover:not(:disabled) { background: #002244; transform: scale(1.1); }
    .btn-enroll-action:disabled { opacity: 0.3; cursor: not-allowed; }

    /* Search & Filter inputs */
    .modern-input { border-radius: 50px; border: 1px solid #ddd; padding-left: 20px; font-size: 0.9rem; }
    .modern-input:focus { border-color: var(--maroon); box-shadow: 0 0 0 3px rgba(128,0,0,0.1); }

    @media (max-width: 768px) {
        .header-fixed-part { flex-direction: column; gap: 10px; text-align: center; }
    }
</style>

<!-- Part 1: Fixed Header -->
<div class="header-fixed-part animate__animated animate__fadeInDown">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-0" style="color: var(--blue);"><i class="bi bi-pencil-square me-2 text-maroon"></i>Admissions & Enrollment</h4>
            <p class="text-muted small mb-0"><?php echo htmlspecialchars($branch['name'] ?? 'Registrar Panel'); ?> • AY <?php echo htmlspecialchars($current_ay['year_name'] ?? 'N/A'); ?></p>
        </div>
        <div id="selectedStudentBadge" class="badge bg-light text-maroon border border-maroon p-2 px-3 rounded-pill animate__animated animate__bounceIn" style="display:none;"></div>
    </div>
</div>

<!-- Part 2: Scrollable Body -->
<div class="body-scroll-part">
    
    <div id="alertContainer"></div>

    <div class="row g-4">
        <!-- Left Column: Student Search -->
        <div class="col-lg-4 animate__animated animate__fadeInLeft">
            <div class="main-card-modern mb-4">
                <div class="card-header-maroon">
                    <i class="bi bi-person-search me-2"></i> Find Student
                </div>
                <div class="p-4">
                    <div class="input-group mb-3">
                        <span class="input-group-text bg-white border-end-0 rounded-start-pill ps-3"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" class="form-control border-start-0 rounded-end-pill modern-input" id="studentSearch" placeholder="Search name or ID...">
                    </div>

                    <div class="student-list-container shadow-sm bg-white" id="studentList">
                        <?php while ($student = $students_result->fetch_assoc()): 
                            $balance = $student['total_fees'] - $student['total_paid'];
                            $has_payment = $student['total_paid'] > 0;
                        ?>
                        <div class="list-group-item student-item p-3 border-bottom" 
                           data-student-id="<?php echo $student['user_id']; ?>"
                           data-student-name="<?php echo htmlspecialchars($student['full_name']); ?>"
                           data-student-no="<?php echo htmlspecialchars($student['student_no']); ?>"
                           data-total-fees="<?php echo $student['total_fees']; ?>"
                           data-total-paid="<?php echo $student['total_paid']; ?>"
                           data-has-payment="<?php echo $has_payment ? '1' : '0'; ?>">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="fw-bold text-dark small mb-0"><?php echo htmlspecialchars($student['full_name']); ?></div>
                                    <small class="text-muted" style="font-size: 0.7rem;"><?php echo htmlspecialchars($student['student_no']); ?></small>
                                </div>
                                <span class="badge <?php echo $has_payment ? 'bg-success' : 'bg-danger'; ?> rounded-pill" style="font-size: 0.6rem;">
                                    <?php echo $has_payment ? 'PAID' : 'UNPAID'; ?>
                                </span>
                            </div>
                            <div class="d-flex justify-content-between mt-2 align-items-center">
                                <small class="text-blue fw-bold" style="font-size: 0.7rem;"><?php echo htmlspecialchars($student['program_code'] ?? 'GENERAL'); ?></small>
                                <?php if ($balance > 0): ?>
                                    <small class="text-danger fw-bold" style="font-size: 0.7rem;">Bal: ₱<?php echo number_format($balance, 2); ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>

            <!-- Active Enrollments Widget -->
            <div class="main-card-modern animate__animated animate__fadeInUp" id="currentEnrollmentsCard" style="display:none;">
                <div class="card-header-modern bg-white"><i class="bi bi-list-check me-2"></i>Active Enrolled Loads</div>
                <div class="p-0">
                    <ul class="list-group list-group-flush" id="currentEnrollmentsList"></ul>
                </div>
            </div>
        </div>

        <!-- Right Column: Sections Picker -->
        <div class="col-lg-8 animate__animated animate__fadeInRight">
            <div class="main-card-modern">
                <div class="card-header-blue d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-grid-3x3-gap me-2"></i>Available Class Sections</span>
                    <!-- Program Filter -->
                    <select class="form-select form-select-sm border-0 rounded-pill shadow-sm" id="programFilter" style="width: 220px; font-size: 0.75rem;">
                        <option value="">All Programs/Strands</option>
                        <?php 
                        $programs_list = []; $sections_result->data_seek(0);
                        while ($sec = $sections_result->fetch_assoc()) {
                            $key = $sec['program_code'] ?? 'Other';
                            if (!isset($programs_list[$key])) { $programs_list[$key] = $sec['program_name'] ?? 'Other'; }
                        }
                        foreach ($programs_list as $code => $name): ?>
                        <option value="<?php echo htmlspecialchars($code); ?>"><?php echo htmlspecialchars($code . ' - ' . $name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="p-4">
                    <div id="paymentWarning" class="alert bg-danger bg-opacity-10 border border-danger border-opacity-25 text-danger mb-4 small" style="display:none;">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i> Financial restriction: Student has no verified payments. Record a payment before proceeding.
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover table-modern align-middle mb-0" id="sectionsTable">
                            <thead>
                                <tr>
                                    <th>Section & Room</th>
                                    <th>Subject Details</th>
                                    <th>Schedule</th>
                                    <th class="text-center">Slots</th>
                                    <th class="text-end">Enroll</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $sections_result->data_seek(0);
                                if ($sections_result->num_rows == 0): ?>
                                    <tr><td colspan="5" class="text-center py-5 text-muted">No sections available for enrollment.</td></tr>
                                <?php else: while ($section = $sections_result->fetch_assoc()): 
                                    $pct = $section['max_capacity'] > 0 ? ($section['current_enrolled'] / $section['max_capacity']) * 100 : 0;
                                    $b_clr = $pct >= 90 ? 'bg-danger' : ($pct >= 70 ? 'bg-warning' : 'bg-success');
                                ?>
                                <tr data-program="<?php echo htmlspecialchars($section['program_code'] ?? 'Other'); ?>">
                                    <td>
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($section['section_name']); ?></div>
                                        <small class="text-muted"><i class="bi bi-geo-alt me-1"></i><?php echo htmlspecialchars($section['room'] ?: 'TBA'); ?></small>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-blue small"><?php echo htmlspecialchars($section['subject_code']); ?></div>
                                        <small class="text-muted text-truncate d-block" style="max-width: 180px;"><?php echo htmlspecialchars($section['subject_title']); ?></small>
                                        <span class="badge bg-light text-dark border small mt-1"><?php echo $section['units']; ?> Units</span>
                                    </td>
                                    <td>
                                        <small class="text-muted d-block" style="font-size:0.75rem;"><i class="bi bi-clock me-1 text-maroon"></i><?php echo htmlspecialchars($section['schedule'] ?: 'TBA'); ?></small>
                                        <small class="text-muted italic"><i class="bi bi-person me-1"></i><?php echo htmlspecialchars($section['teacher_name'] ?: 'No Teacher'); ?></small>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge <?php echo $b_clr; ?> px-2 py-2 w-100"><?php echo $section['current_enrolled']; ?> / <?php echo $section['max_capacity']; ?></span>
                                    </td>
                                    <td class="text-end">
                                        <button class="btn-enroll-action enroll-btn" 
                                                data-class-id="<?php echo $section['id']; ?>"
                                                data-class-name="<?php echo htmlspecialchars($section['section_name'] . ' - ' . $section['subject_code']); ?>"
                                                disabled>
                                            <i class="bi bi-plus-lg"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endwhile; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<!-- --- JAVASCRIPT LOGIC - UNTOUCHED & RE-WIRED --- -->
<script>
let selectedStudentId = null;
let selectedStudentName = '';
let selectedHasPayment = false;

// Student search functionality
document.getElementById('studentSearch').addEventListener('input', function(e) {
    const searchTerm = e.target.value.toLowerCase();
    const studentItems = document.querySelectorAll('.student-item');
    studentItems.forEach(item => {
        const name = item.getAttribute('data-student-name').toLowerCase();
        const studentNo = item.getAttribute('data-student-no').toLowerCase();
        item.style.display = (name.includes(searchTerm) || studentNo.includes(searchTerm)) ? '' : 'none';
    });
});

// Program filter functionality
document.getElementById('programFilter').addEventListener('change', function(e) {
    const selectedProgram = e.target.value;
    const rows = document.querySelectorAll('#sectionsTable tbody tr');
    rows.forEach(row => {
        if (!selectedProgram || row.getAttribute('data-program') === selectedProgram) { row.style.display = ''; } 
        else { row.style.display = 'none'; }
    });
});

// Student selection (RESTORED logic)
document.querySelectorAll('.student-item').forEach(item => {
    item.addEventListener('click', function(e) {
        e.preventDefault();
        document.querySelectorAll('.student-item').forEach(i => i.classList.remove('active'));
        this.classList.add('active');
        
        selectedStudentId = this.getAttribute('data-student-id');
        selectedStudentName = this.getAttribute('data-student-name');
        selectedHasPayment = this.getAttribute('data-has-payment') === '1';
        
        const badge = document.getElementById('selectedStudentBadge');
        badge.innerHTML = `<i class="bi bi-person-fill me-2"></i>Selecting: ${selectedStudentName}`;
        badge.style.display = 'inline-block';

        const warning = document.getElementById('paymentWarning');
        warning.style.display = (!selectedHasPayment) ? 'block' : 'none';
        
        document.querySelectorAll('.enroll-btn').forEach(btn => btn.disabled = !selectedHasPayment);
        loadCurrentEnrollments(selectedStudentId);
    });
});

// AJAX: Load current enrollments (RESTORED endpoint)
async function loadCurrentEnrollments(studentId) {
    const card = document.getElementById('currentEnrollmentsCard');
    const list = document.getElementById('currentEnrollmentsList');
    try {
        const response = await fetch(`process/get_student_enrollments.php?student_id=${studentId}`);
        const data = await response.json();
        if (data.status === 'success' && data.enrollments.length > 0) {
            list.innerHTML = data.enrollments.map(e => `
                <li class="list-group-item d-flex justify-content-between align-items-center p-3">
                    <div>
                        <div class="fw-bold text-dark small">${e.subject_code || 'N/A'}</div>
                        <small class="text-muted">${e.section_name || 'TBA'}</small>
                    </div>
                    <button class="btn btn-sm btn-outline-danger unenroll-btn border-0" data-enrollment-id="${e.enrollment_id}" data-class-name="${e.subject_code}">
                        <i class="bi bi-trash"></i>
                    </button>
                </li>
            `).join('');
            card.style.display = 'block';
            document.querySelectorAll('.unenroll-btn').forEach(btn => btn.addEventListener('click', handleUnenroll));
        } else {
            list.innerHTML = '<li class="list-group-item text-muted small p-4 text-center">No active enrollments for this student</li>';
            card.style.display = 'block';
        }
    } catch (error) { console.error('Error loading enrollments:', error); }
}

// AJAX: Handle Unenroll (RESTORED endpoint)
async function handleUnenroll() {
    const enrollmentId = this.getAttribute('data-enrollment-id');
    const className = this.getAttribute('data-class-name');
    if (!confirm(`Remove load ${className} from ${selectedStudentName}?`)) return;
    try {
        const formData = new FormData();
        formData.append('enrollment_id', enrollmentId);
        const response = await fetch('process/unenroll_student.php', { method: 'POST', body: formData });
        const data = await response.json();
        if (data.status === 'success') { showAlert(data.message, 'success'); setTimeout(() => location.reload(), 1000); } 
        else { showAlert(data.message, 'danger'); }
    } catch (error) { showAlert('Error during unenrollment', 'danger'); }
}

// AJAX: Enrollment Process (RESTORED endpoint)
document.querySelectorAll('.enroll-btn').forEach(btn => {
    btn.addEventListener('click', async function() {
        if (!selectedStudentId) return showAlert('Please select a student', 'warning');
        if (!selectedHasPayment) return showAlert('Financial clearance required', 'danger');
        
        const classId = this.getAttribute('data-class-id');
        const className = this.getAttribute('data-class-name');
        if (!confirm(`Enroll ${selectedStudentName} in ${className}?`)) return;
        
        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        
        try {
            const formData = new FormData();
            formData.append('student_id', selectedStudentId);
            formData.append('class_id', classId);
            const response = await fetch('process/process_enroll.php', { method: 'POST', body: formData });
            const data = await response.json();
            if (data.status === 'success') { showAlert(data.message, 'success'); setTimeout(() => location.reload(), 1000); } 
            else { showAlert(data.message, 'danger'); this.disabled = false; this.innerHTML = '<i class="bi bi-plus-lg"></i>'; }
        } catch (error) { showAlert('Enrollment failed', 'danger'); this.disabled = false; }
    });
});

function showAlert(message, type) {
    const alertHtml = `<div class="alert alert-${type} alert-dismissible fade show border-0 shadow-sm animate__animated animate__shakeX" role="alert"><i class="bi ${type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'} me-2"></i>${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
    document.getElementById('alertContainer').innerHTML = alertHtml;
}
</script>
</body>
</html>