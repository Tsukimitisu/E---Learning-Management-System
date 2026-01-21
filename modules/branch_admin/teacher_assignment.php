<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_BRANCH_ADMIN) {
    header('Location: ../../index.php');
    exit();
}

$branch_id = get_user_branch_id();
if ($branch_id === null) {
    die("No branch assigned to this admin.");
}

// Get current academic year
$current_ay = $conn->query("SELECT * FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$current_ay_id = $current_ay['id'] ?? 0;

// Fetch college programs
$programs = $conn->query("
    SELECT p.id, p.program_code, p.program_name
    FROM programs p 
    WHERE p.is_active = 1 
    ORDER BY p.program_code
");

// Fetch SHS strands
$strands = $conn->query("
    SELECT ss.id, ss.strand_code, ss.strand_name
    FROM shs_strands ss
    WHERE ss.is_active = 1
    ORDER BY ss.strand_code
");

// Fetch teachers
$teachers = $conn->query("
    SELECT u.id, CONCAT(up.first_name, ' ', up.last_name) as name
    FROM users u
    INNER JOIN user_profiles up ON u.id = up.user_id
    INNER JOIN user_roles ur ON u.id = ur.user_id
    WHERE ur.role_id = " . ROLE_TEACHER . " AND u.status = 'active'
    ORDER BY up.last_name, up.first_name
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

    /* Modern Subject Cards */
    .subject-card-modern {
        background: white; border-radius: 15px; border: 1px solid #f0f0f0;
        padding: 20px; transition: 0.3s; margin-bottom: 15px;
        border-left: 5px solid #dee2e6;
    }
    .subject-card-modern:hover { transform: translateX(5px); box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
    .subject-card-modern.assigned { border-left-color: #28a745; background: #f8fff9; }
    .subject-card-modern.unassigned { border-left-color: var(--maroon); }

    /* Control Panel / Filters */
    .control-panel {
        background: var(--blue); border-radius: 15px; padding: 25px;
        color: white; margin-bottom: 30px; box-shadow: 0 10px 25px rgba(0,51,102,0.1);
    }
    .control-panel .form-label-custom {
        font-size: 0.65rem; font-weight: 800; text-transform: uppercase;
        color: rgba(255,255,255,0.6); letter-spacing: 1px; margin-bottom: 8px; display: block;
    }
    .control-panel .form-select {
        background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2);
        color: white; font-weight: 600; font-size: 0.85rem; border-radius: 8px;
    }
    .control-panel .form-select option { color: #333; }

    .content-card { background: white; border-radius: 15px; border: none; box-shadow: 0 5px 20px rgba(0,0,0,0.05); overflow: hidden; }
    .card-header-modern {
        background: #fcfcfc; padding: 15px 20px; border-bottom: 1px solid #eee;
        font-weight: 700; color: var(--blue); text-transform: uppercase; font-size: 0.8rem; letter-spacing: 1px;
    }

    .stat-pill {
        display: inline-flex; align-items: center; padding: 5px 12px;
        border-radius: 20px; font-size: 0.7rem; font-weight: 700;
    }
    .pill-success { background: #e6f4ea; color: #1e7e34; }
    .pill-warning { background: #fff4e5; color: #664d03; }

    .btn-maroon { background-color: var(--maroon); color: white; font-weight: 700; border: none; }
    .btn-maroon:hover { background-color: #600000; color: white; }

    @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
    .spin { animation: spin 1s linear infinite; display: inline-block; }
</style>

<div class="main-content-body animate__animated animate__fadeIn">
    
    <!-- 1. PAGE HEADER -->
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center animate__animated animate__fadeInDown">
        <div class="mb-2 mb-md-0">
            <h4 class="fw-bold mb-0" style="color: var(--blue);">
                <i class="bi bi-person-badge me-2 text-maroon"></i>Teacher Subject Assignment
            </h4>
            <p class="text-muted small mb-0">Academic Year: <span class="fw-bold text-dark"><?php echo htmlspecialchars($current_ay['year_name'] ?? 'Not Set'); ?></span></p>
        </div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 bg-transparent p-0">
                <li class="breadcrumb-item"><a href="dashboard.php" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item active">Assignments</li>
            </ol>
        </nav>
    </div>

    <div id="alertContainer"></div>

    <!-- 2. CONTROL PANEL (FILTERS) -->
    <div class="control-panel animate__animated animate__fadeIn">
        <div class="row g-3 align-items-end">
            <div class="col-lg-3 col-md-6">
                <label class="form-label-custom">Program Type</label>
                <select class="form-select" id="programType" onchange="loadPrograms()">
                    <option value="college">College Programs</option>
                    <option value="shs">SHS Strands</option>
                </select>
            </div>
            <div class="col-lg-3 col-md-6">
                <label class="form-label-custom">Target Program/Strand</label>
                <select class="form-select" id="programSelect" onchange="loadYearLevels()">
                    <option value="">Select Option</option>
                </select>
            </div>
            <div class="col-lg-2 col-md-4">
                <label class="form-label-custom">Year Level</label>
                <select class="form-select" id="yearLevelSelect" onchange="loadSubjects()">
                    <option value="">Select Level</option>
                </select>
            </div>
            <div class="col-lg-2 col-md-4">
                <label class="form-label-custom">Semester</label>
                <select class="form-select" id="semesterSelect" onchange="loadSubjects()">
                    <option value="1st">1st Semester</option>
                    <option value="2nd">2nd Semester</option>
                    <option value="summer">Summer</option>
                </select>
            </div>
            <div class="col-lg-2 col-md-4">
                <button class="btn btn-light w-100 fw-bold py-2 shadow-sm" onclick="loadSubjects()">
                    <i class="bi bi-search me-1"></i> LOAD
                </button>
            </div>
        </div>
    </div>

    <!-- 3. SUBJECTS RESULTS -->
    <div class="content-card">
        <div class="card-header-modern bg-white d-flex justify-content-between align-items-center">
            <span><i class="bi bi-journal-text me-2 text-maroon"></i>Curriculum Subjects</span>
            <span class="badge bg-blue bg-opacity-10 text-blue fw-bold" id="subjectCount">0 subjects</span>
        </div>
        <div class="card-body p-4" id="subjectsList">
            <div class="text-center py-5 text-muted">
                <i class="bi bi-filter-circle fs-1 d-block mb-3 opacity-25"></i>
                <p class="fw-bold mb-0">No Selection Made</p>
                <small>Select filters above to load the branch curriculum subjects.</small>
            </div>
        </div>
    </div>
</div>

<!-- Assign Teacher Modal -->
<div class="modal fade" id="assignTeacherModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-success text-white py-3">
                <h5 class="modal-title fs-6 fw-bold"><i class="bi bi-person-plus me-2"></i>Assign Instructor</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="assignTeacherForm">
                <input type="hidden" name="subject_id" id="modal_subject_id">
                <div class="modal-body p-4">
                    <div class="mb-4">
                        <label class="form-label fw-bold small text-uppercase opacity-75">Target Subject</label>
                        <input type="text" class="form-control bg-light border-0 fw-bold" id="modal_subject_name" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase opacity-75">Assign Teacher *</label>
                        <select class="form-select" name="teacher_id" id="teacherSelect" required>
                            <option value="">Choose an instructor...</option>
                            <?php 
                            $teachers->data_seek(0);
                            while ($teacher = $teachers->fetch_assoc()): ?>
                                <option value="<?php echo $teacher['id']; ?>"><?php echo htmlspecialchars($teacher['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-light btn-sm px-4 fw-bold" data-bs-dismiss="modal">CANCEL</button>
                    <button type="submit" class="btn btn-success btn-sm px-4 fw-bold">UPDATE ASSIGNMENT</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Logic preserved as requested
const collegePrograms = <?php $programs->data_seek(0); $progs = []; while ($p = $programs->fetch_assoc()) { $progs[] = $p; } echo json_encode($progs); ?>;
const shsStrands = <?php $strands->data_seek(0); $strs = []; while ($s = $strands->fetch_assoc()) { $strs[] = $s; } echo json_encode($strs); ?>;

document.addEventListener('DOMContentLoaded', function() {
    loadPrograms();
});

function loadPrograms() {
    const type = document.getElementById('programType').value;
    const select = document.getElementById('programSelect');
    select.innerHTML = '<option value="">Select Option</option>';
    document.getElementById('yearLevelSelect').innerHTML = '<option value="">Select Level</option>';
    const data = type === 'college' ? collegePrograms : shsStrands;
    data.forEach(item => {
        const code = type === 'college' ? item.program_code : item.strand_code;
        const name = type === 'college' ? item.program_name : item.strand_name;
        select.innerHTML += `<option value="${item.id}">${code} - ${name}</option>`;
    });
}

function loadYearLevels() {
    const type = document.getElementById('programType').value;
    const programId = document.getElementById('programSelect').value;
    const select = document.getElementById('yearLevelSelect');
    if (!programId) { select.innerHTML = '<option value="">Select Level</option>'; return; }
    select.innerHTML = '<option value="">Loading...</option>';
    fetch(`process/teacher_assignment_api.php?action=get_year_levels&type=${type}&program_id=${programId}`)
        .then(response => response.json())
        .then(data => {
            select.innerHTML = '<option value="">Select Level</option>';
            if (data.success) {
                data.levels.forEach(level => { select.innerHTML += `<option value="${level.id}">${level.name}</option>`; });
            }
        });
}

function loadSubjects() {
    const type = document.getElementById('programType').value;
    const programId = document.getElementById('programSelect').value;
    const yearLevelId = document.getElementById('yearLevelSelect').value;
    const semester = document.getElementById('semesterSelect').value;
    
    if (!programId || !yearLevelId) return;
    
    const container = document.getElementById('subjectsList');
    container.innerHTML = `<div class="text-center py-5"><i class="bi bi-arrow-repeat spin fs-2 text-muted"></i><p class="mt-2 small">Fetching branch curriculum...</p></div>`;
    
    const params = new URLSearchParams({ action: 'get_subjects', type, program_id: programId, year_level_id: yearLevelId, semester });
    
    fetch('process/teacher_assignment_api.php?' + params)
        .then(response => response.json())
        .then(data => {
            document.getElementById('subjectCount').textContent = (data.subjects?.length || 0) + ' subjects';
            if (data.success && data.subjects.length > 0) {
                let html = '';
                data.subjects.forEach(subject => {
                    const isAssigned = subject.teacher_id != null;
                    html += `
                        <div class="subject-card-modern ${isAssigned ? 'assigned' : 'unassigned'}">
                            <div class="row align-items-center">
                                <div class="col-md-7">
                                    <div class="d-flex align-items-center mb-1">
                                        <span class="badge bg-blue text-white me-2" style="font-size: 0.65rem;">${subject.subject_code}</span>
                                        <h6 class="mb-0 fw-bold text-dark">${subject.subject_title}</h6>
                                    </div>
                                    <small class="text-muted fw-semibold"><i class="bi bi-hash"></i> ${subject.units} Units Curriculum</small>
                                </div>
                                <div class="col-md-5 text-md-end mt-3 mt-md-0">
                                    <div class="mb-2">
                                        ${isAssigned ? `
                                            <span class="stat-pill pill-success mb-2">
                                                <i class="bi bi-person-check-fill me-1"></i> ${subject.teacher_name}
                                            </span>
                                        ` : `
                                            <span class="stat-pill pill-warning mb-2">
                                                <i class="bi bi-person-x-fill me-1"></i> No Teacher Assigned
                                            </span>
                                        `}
                                    </div>
                                    <div class="btn-group shadow-sm">
                                        <button class="btn btn-sm btn-white border px-3" onclick="openAssignModal(${subject.id}, '${subject.subject_code} - ${subject.subject_title}', ${subject.teacher_id || 'null'})">
                                            <i class="bi bi-pencil-square me-1"></i> ${isAssigned ? 'Change' : 'Assign'}
                                        </button>
                                        ${isAssigned ? `
                                            <button class="btn btn-sm btn-white border text-danger px-3" onclick="unassignTeacher(${subject.id})">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        ` : ''}
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                });
                container.innerHTML = html;
            } else {
                container.innerHTML = `<div class="text-center py-5 text-muted"><i class="bi bi-book fs-1 d-block mb-3 opacity-25"></i>No subjects found for this selection.</div>`;
            }
        });
}

function openAssignModal(subjectId, subjectName, currentTeacherId) {
    document.getElementById('modal_subject_id').value = subjectId;
    document.getElementById('modal_subject_name').value = subjectName;
    document.getElementById('teacherSelect').value = currentTeacherId || '';
    new bootstrap.Modal(document.getElementById('assignTeacherModal')).show();
}

document.getElementById('assignTeacherForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('action', 'assign_teacher');
    fetch('process/teacher_assignment_api.php', { method: 'POST', body: formData }).then(response => response.json()).then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('assignTeacherModal')).hide();
            showAlert('success', data.message);
            loadSubjects();
        } else showAlert('danger', data.message);
    });
});

function unassignTeacher(subjectId) {
    if (!confirm('Unassign instructor from this subject?')) return;
    const formData = new FormData();
    formData.append('action', 'unassign_teacher');
    formData.append('subject_id', subjectId);
    fetch('process/teacher_assignment_api.php', { method: 'POST', body: formData }).then(response => response.json()).then(data => {
        if (data.success) { showAlert('success', data.message); loadSubjects(); }
        else showAlert('danger', data.message);
    });
}

function showAlert(type, message) {
    const container = document.getElementById('alertContainer');
    container.innerHTML = `<div class="alert alert-${type} alert-dismissible fade show border-0 shadow-sm" role="alert"><i class="bi bi-info-circle-fill me-2"></i>${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
    setTimeout(() => container.innerHTML = '', 5000);
}
</script>

<?php include '../../includes/footer.php'; ?>