<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_BRANCH_ADMIN) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Student Enrollment Management";
$branch_id = get_user_branch_id();

/** 
 * BACKEND LOGIC - UNTOUCHED 
 */
if ($branch_id === null) {
    echo "Error: Your account is not assigned to any branch. Please contact the School Administrator.";
    exit();
}
require_branch_assignment();

$branch = $conn->query("SELECT * FROM branches WHERE id = $branch_id")->fetch_assoc();
$current_ay = $conn->query("SELECT id, year_name FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$current_ay_id = $current_ay['id'] ?? 0;

$students_query = "
    SELECT s.user_id, up.first_name, up.last_name, CONCAT(up.first_name, ' ', up.last_name) as full_name,
        COALESCE(p.program_code, ss.strand_code) as program_code,
        COALESCE(p.program_name, ss.strand_name) as program_name,
        COALESCE((SELECT SUM(amount) FROM student_fees WHERE student_id = s.user_id), 0) as total_fees,
        COALESCE((SELECT SUM(amount) FROM payments WHERE student_id = s.user_id AND status = 'verified'), 0) as total_paid
    FROM students s
    INNER JOIN user_profiles up ON s.user_id = up.user_id
    LEFT JOIN programs p ON s.course_id = p.id
    LEFT JOIN shs_strands ss ON s.course_id = ss.id
    WHERE up.branch_id = $branch_id
    ORDER BY up.last_name, up.first_name
";
$students_result = $conn->query($students_query);

$sections_query = "
    SELECT cl.id, cl.section_name, cs.subject_code, cs.subject_title, cs.units, cl.max_capacity,
        cl.current_enrolled, cl.schedule, cl.room, CONCAT(up.first_name, ' ', up.last_name) as teacher_name,
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
    WHERE cl.branch_id = $branch_id AND cl.academic_year_id = $current_ay_id
    ORDER BY COALESCE(p.program_name, ss.strand_name), cs.subject_code, cl.section_name
";
$sections_result = $conn->query($sections_query);

include '../../includes/header.php';
include '../../includes/sidebar.php'; 
?>

<style>
    /* --- SHARED UI DESIGN SYSTEM --- */
    .page-header {
        background: white; padding: 20px; border-radius: 15px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.03); margin-bottom: 25px;
    }

    .content-card { background: white; border-radius: 15px; border: none; box-shadow: 0 5px 20px rgba(0,0,0,0.05); overflow: hidden; height: 100%; }
    
    .card-header-modern {
        background: #fcfcfc; padding: 15px 20px; border-bottom: 1px solid #eee;
        font-weight: 700; color: var(--blue); text-transform: uppercase; font-size: 0.8rem; letter-spacing: 1px;
    }

    /* Student Selector */
    .student-item-modern {
        padding: 15px; border-bottom: 1px solid #f5f5f5; cursor: pointer; transition: 0.2s;
    }
    .student-item-modern:hover { background: #f8faff; }
    .student-item-modern.active { background: #fff5f5; border-left: 5px solid var(--maroon); }

    .balance-text { font-size: 0.7rem; font-weight: 800; text-transform: uppercase; }

    /* Table Styling */
    .table-modern thead th { 
        background: #f8f9fa; font-size: 0.7rem; text-transform: uppercase; 
        color: #888; padding: 12px 15px; border-bottom: 1px solid #eee;
    }
    .table-modern tbody td { padding: 12px 15px; vertical-align: middle; font-size: 0.85rem; }

    .capacity-bar { height: 6px; border-radius: 10px; background: #eee; overflow: hidden; width: 60px; margin-top: 4px; }
    .capacity-fill { height: 100%; transition: 0.3s; }
    
    .btn-maroon { background-color: var(--maroon); color: white; font-weight: 700; border: none; }
    .btn-maroon:hover { background-color: #600000; color: white; }

    .enroll-btn:disabled { opacity: 0.3; cursor: not-allowed; }
</style>

<div class="main-content-body animate__animated animate__fadeIn">
    
    <!-- 1. PAGE HEADER -->
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center animate__animated animate__fadeInDown">
        <div class="mb-2 mb-md-0">
            <h4 class="fw-bold mb-0" style="color: var(--blue);">
                <i class="bi bi-pencil-square me-2 text-maroon"></i>Student Enrollment
            </h4>
            <p class="text-muted small mb-0">Managing Branch: <span class="fw-bold text-dark"><?php echo htmlspecialchars($branch['name'] ?? 'Main'); ?></span> | Academic Year: <?php echo htmlspecialchars($current_ay['year_name'] ?? 'N/A'); ?></p>
        </div>
        <div class="d-flex gap-2">
            <span id="selectedStudentBadge" class="badge bg-blue text-white rounded-pill px-3 py-2 shadow-sm animate__animated animate__pulse animate__infinite" style="display:none; font-size: 0.75rem;"></span>
        </div>
    </div>

    <div id="alertContainer"></div>

    <div class="row g-4">
        <!-- LEFT: STUDENT DIRECTORY -->
        <div class="col-lg-4">
            <div class="content-card">
                <div class="card-header-modern bg-white">
                    <i class="bi bi-person-check me-2"></i>Select Student
                </div>
                <div class="p-3 bg-light border-bottom">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control border-start-0 shadow-none" id="studentSearch" placeholder="Find student name...">
                    </div>
                </div>
                <div style="max-height: 600px; overflow-y: auto;" id="studentList">
                    <?php while ($student = $students_result->fetch_assoc()): 
                        $balance = $student['total_fees'] - $student['total_paid'];
                        $has_payment = $student['total_paid'] > 0;
                    ?>
                    <div class="student-item-modern student-item" 
                       data-student-id="<?php echo $student['user_id']; ?>"
                       data-student-name="<?php echo htmlspecialchars($student['full_name']); ?>"
                       data-has-payment="<?php echo $has_payment ? '1' : '0'; ?>">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="mb-0 fw-bold text-dark" style="font-size: 0.85rem;"><?php echo htmlspecialchars($student['full_name']); ?></h6>
                                <small class="text-muted" style="font-size: 0.7rem;"><?php echo htmlspecialchars($student['program_code'] ?? 'No Program'); ?></small>
                            </div>
                            <?php if ($has_payment): ?>
                                <span class="badge bg-success bg-opacity-10 text-success rounded-pill" style="font-size: 0.6rem;">PAID</span>
                            <?php else: ?>
                                <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill" style="font-size: 0.6rem;">NO PMT</span>
                            <?php endif; ?>
                        </div>
                        <div class="mt-2 d-flex justify-content-between">
                            <span class="balance-text <?php echo ($balance > 0) ? 'text-danger' : 'text-success'; ?>">
                                <?php echo ($balance > 0) ? "Bal: ₱" . number_format($balance, 2) : 'Cleared'; ?>
                            </span>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>

            <!-- CURRENT REGISTERED SUBJECTS (Sticky-like) -->
            <div class="content-card mt-4" id="currentEnrollmentsCard" style="display:none;">
                <div class="card-header-modern bg-blue text-white" style="background: var(--blue) !important;">
                    <i class="bi bi-journal-check me-2"></i>Registered Subjects
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush" id="currentEnrollmentsList"></ul>
                </div>
            </div>
        </div>

        <!-- RIGHT: ENROLLMENT ACTION PANEL -->
        <div class="col-lg-8">
            <div class="content-card">
                <div class="card-header-modern bg-white d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-grid-3x3-gap me-2"></i>Available Class Catalog</span>
                    <select class="form-select form-select-sm w-auto" id="programFilter">
                        <option value="">All Curriculums</option>
                        <?php 
                        $programs_list = [];
                        $sections_result->data_seek(0);
                        while ($sec = $sections_result->fetch_assoc()) {
                            $key = $sec['program_code'] ?? 'Other';
                            if (!isset($programs_list[$key])) { $programs_list[$key] = $sec['program_name'] ?? 'Other'; }
                        }
                        foreach ($programs_list as $code => $name): ?>
                            <option value="<?php echo htmlspecialchars($code); ?>"><?php echo htmlspecialchars($code); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="card-body p-0">
                    <!-- Warning Banner -->
                    <div id="paymentWarning" class="alert alert-warning border-0 rounded-0 mb-0 d-flex align-items-center" style="display:none !important;">
                        <i class="bi bi-exclamation-triangle-fill fs-4 me-3"></i>
                        <div>
                            <p class="mb-0 fw-bold small">Enrollment Restricted</p>
                            <small>This student has no verified payments. Please settle accounts before proceeding.</small>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover table-modern mb-0" id="sectionsTable">
                            <thead>
                                <tr>
                                    <th>Subject & Section</th>
                                    <th>Schedule / Room</th>
                                    <th>Instructor</th>
                                    <th class="text-center">Capacity</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $sections_result->data_seek(0);
                                while ($section = $sections_result->fetch_assoc()): 
                                    $pct = $section['max_capacity'] > 0 ? ($section['current_enrolled'] / $section['max_capacity']) * 100 : 0;
                                    $bar_color = ($pct >= 90) ? 'bg-high' : (($pct >= 75) ? 'bg-medium' : 'bg-low');
                                ?>
                                <tr data-program="<?php echo htmlspecialchars($section['program_code'] ?? 'Other'); ?>">
                                    <td>
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($section['subject_code']); ?></div>
                                        <div class="small text-muted fw-semibold"><?php echo htmlspecialchars($section['section_name']); ?></div>
                                    </td>
                                    <td>
                                        <div class="small fw-bold text-blue"><i class="bi bi-clock me-1"></i><?php echo htmlspecialchars($section['schedule'] ?? 'TBA'); ?></div>
                                        <div class="small text-muted"><i class="bi bi-geo-alt me-1"></i><?php echo htmlspecialchars($section['room'] ?? 'TBA'); ?></div>
                                    </td>
                                    <td><small class="fw-bold"><?php echo htmlspecialchars($section['teacher_name'] ?? 'TBA'); ?></small></td>
                                    <td class="text-center">
                                        <small class="fw-bold"><?php echo $section['current_enrolled']; ?>/<?php echo $section['max_capacity']; ?></small>
                                        <div class="capacity-bar mx-auto"><div class="capacity-fill <?php echo $bar_color; ?>" style="width: <?php echo $pct; ?>%"></div></div>
                                    </td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-white border enroll-btn rounded-pill px-3" 
                                                data-class-id="<?php echo $section['id']; ?>"
                                                data-class-name="<?php echo htmlspecialchars($section['subject_code']); ?>"
                                                disabled>
                                            <i class="bi bi-plus-circle-fill text-success"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($sections_result->num_rows == 0): ?>
                        <div class="text-center py-5 text-muted"><i class="bi bi-grid-3x3-gap fs-1 d-block mb-2 opacity-25"></i>No sections found.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let selectedStudentId = null;
let selectedStudentName = '';
let selectedHasPayment = false;

// Search Logic
document.getElementById('studentSearch').addEventListener('input', function(e) {
    const searchTerm = e.target.value.toLowerCase();
    document.querySelectorAll('.student-item').forEach(item => {
        const name = item.getAttribute('data-student-name').toLowerCase();
        item.style.display = name.includes(searchTerm) ? '' : 'none';
    });
});

// Filter Logic
document.getElementById('programFilter').addEventListener('change', function(e) {
    const selectedProgram = e.target.value;
    document.querySelectorAll('#sectionsTable tbody tr').forEach(row => {
        row.style.display = (!selectedProgram || row.getAttribute('data-program') === selectedProgram) ? '' : 'none';
    });
});

// Selection Logic
document.querySelectorAll('.student-item').forEach(item => {
    item.addEventListener('click', function(e) {
        e.preventDefault();
        document.querySelectorAll('.student-item').forEach(i => i.classList.remove('active'));
        this.classList.add('active');
        
        selectedStudentId = this.getAttribute('data-student-id');
        selectedStudentName = this.getAttribute('data-student-name');
        selectedHasPayment = this.getAttribute('data-has-payment') === '1';
        
        const badge = document.getElementById('selectedStudentBadge');
        badge.innerHTML = `<i class="bi bi-person-fill me-2"></i>${selectedStudentName}`;
        badge.style.display = 'inline-block';

        const warning = document.getElementById('paymentWarning');
        warning.style.display = !selectedHasPayment ? 'flex' : 'none';
        
        document.querySelectorAll('.enroll-btn').forEach(btn => btn.disabled = !selectedHasPayment);
        loadCurrentEnrollments(selectedStudentId);
    });
});

async function loadCurrentEnrollments(studentId) {
    const card = document.getElementById('currentEnrollmentsCard');
    const list = document.getElementById('currentEnrollmentsList');
    try {
        const response = await fetch(`process/get_student_enrollments.php?student_id=${studentId}`);
        const data = await response.json();
        if (data.status === 'success' && data.enrollments.length > 0) {
            list.innerHTML = data.enrollments.map(e => `
                <li class="list-group-item d-flex justify-content-between align-items-center py-3 bg-white">
                    <div>
                        <div class="fw-bold text-dark small">${e.subject_code}</div>
                        <small class="text-muted" style="font-size:0.65rem;">${e.section_name}</small>
                    </div>
                    <button class="btn btn-sm btn-outline-danger border-0 unenroll-btn" data-enrollment-id="${e.enrollment_id}" data-class-name="${e.subject_code}"><i class="bi bi-trash"></i></button>
                </li>
            `).join('');
            card.style.display = 'block';
            document.querySelectorAll('.unenroll-btn').forEach(btn => btn.addEventListener('click', handleUnenroll));
        } else {
            list.innerHTML = '<li class="list-group-item text-muted small text-center py-3">No active enrollments</li>';
            card.style.display = 'block';
        }
    } catch (error) { console.error('Error:', error); }
}

async function handleUnenroll() {
    const enrollmentId = this.getAttribute('data-enrollment-id');
    const className = this.getAttribute('data-class-name');
    if (!confirm(`Unenroll student from ${className}?`)) return;
    try {
        const formData = new FormData();
        formData.append('enrollment_id', enrollmentId);
        const response = await fetch('process/unenroll_student.php', { method: 'POST', body: formData });
        const data = await response.json();
        if (data.status === 'success') { showAlert(data.message, 'success'); setTimeout(() => location.reload(), 1200); }
        else { showAlert(data.message, 'danger'); }
    } catch (error) { showAlert('Error during unenrollment', 'danger'); }
}

document.querySelectorAll('.enroll-btn').forEach(btn => {
    btn.addEventListener('click', async function() {
        if (!selectedStudentId || !selectedHasPayment) return;
        const classId = this.getAttribute('data-class-id');
        const className = this.getAttribute('data-class-name');
        if (!confirm(`Process enrollment for ${className}?`)) return;
        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        try {
            const formData = new FormData();
            formData.append('student_id', selectedStudentId);
            formData.append('class_id', classId);
            const response = await fetch('process/process_enroll.php', { method: 'POST', body: formData });
            const data = await response.json();
            if (data.status === 'success') { showAlert(data.message, 'success'); setTimeout(() => location.reload(), 1200); }
            else { showAlert(data.message, 'danger'); this.disabled = false; this.innerHTML = '<i class="bi bi-plus-circle-fill text-success"></i>'; }
        } catch (error) { showAlert('Enrollment failed', 'danger'); this.disabled = false; }
    });
});

function showAlert(message, type) {
    const container = document.getElementById('alertContainer');
    container.innerHTML = `<div class="alert alert-${type} alert-dismissible fade show border-0 shadow-sm" role="alert">${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
</script>

<?php include '../../includes/footer.php'; ?>