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
?>

<style>
    .subject-card {
        background: white;
        border: 2px solid #e9ecef;
        border-radius: 12px;
        transition: all 0.3s ease;
        margin-bottom: 15px;
    }
    .subject-card:hover {
        border-color: #003366;
        box-shadow: 0 5px 15px rgba(0, 51, 102, 0.1);
    }
    .subject-card.assigned {
        border-color: #28a745;
        background: #f8fff9;
    }
    
    .teacher-badge {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        color: white;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.85rem;
    }
    
    .filter-card {
        background: linear-gradient(135deg, #003366 0%, #004080 100%);
        color: white;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 20px;
    }
    
    .filter-card .form-label {
        color: rgba(255,255,255,0.8);
        font-size: 0.85rem;
    }
    
    .filter-card .form-select, .filter-card .form-control {
        background: rgba(255,255,255,0.1);
        border: 1px solid rgba(255,255,255,0.2);
        color: white;
    }
    
    .filter-card .form-select option {
        color: #333;
    }
    
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    .spin { animation: spin 1s linear infinite; }
</style>

<?php include '../../includes/sidebar.php'; ?>

<div id="content">
    <div class="main-content-body">
        <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-0" style="color: #003366;">
                    <i class="bi bi-person-badge"></i> Teacher Subject Assignment
                </h4>
                <small class="text-muted">Academic Year: <?php echo htmlspecialchars($current_ay['year_name'] ?? 'Not Set'); ?></small>
            </div>
            <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Dashboard
            </a>
        </div>

        <div id="alertContainer"></div>

        <!-- Filters -->
        <div class="filter-card">
            <div class="row align-items-end">
                <div class="col-md-3 mb-3 mb-md-0">
                    <label class="form-label">Program Type</label>
                    <select class="form-select" id="programType" onchange="loadPrograms()">
                        <option value="college">College Programs</option>
                        <option value="shs">SHS Strands</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3 mb-md-0">
                    <label class="form-label">Program/Strand</label>
                    <select class="form-select" id="programSelect" onchange="loadYearLevels()">
                        <option value="">Select Program</option>
                    </select>
                </div>
                <div class="col-md-2 mb-3 mb-md-0">
                    <label class="form-label">Year Level</label>
                    <select class="form-select" id="yearLevelSelect" onchange="loadSubjects()">
                        <option value="">Select Year Level</option>
                    </select>
                </div>
                <div class="col-md-2 mb-3 mb-md-0">
                    <label class="form-label">Semester</label>
                    <select class="form-select" id="semesterSelect" onchange="loadSubjects()">
                        <option value="1st">1st Semester</option>
                        <option value="2nd">2nd Semester</option>
                        <option value="summer">Summer</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-light w-100" onclick="loadSubjects()">
                        <i class="bi bi-search"></i> Load Subjects
                    </button>
                </div>
            </div>
        </div>

        <!-- Subjects List -->
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-book"></i> Subjects</h6>
                <span class="badge bg-info" id="subjectCount">0 subjects</span>
            </div>
            <div class="card-body" id="subjectsList">
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-arrow-up-circle fs-1 d-block mb-3"></i>
                    Select a program, year level, and semester to view subjects
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Assign Teacher Modal -->
<div class="modal fade" id="assignTeacherModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white;">
                <h5 class="modal-title"><i class="bi bi-person-plus"></i> Assign Teacher</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="assignTeacherForm">
                <input type="hidden" name="subject_id" id="modal_subject_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Subject</label>
                        <input type="text" class="form-control" id="modal_subject_name" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Select Teacher</label>
                        <select class="form-select" name="teacher_id" id="teacherSelect" required>
                            <option value="">Choose a teacher...</option>
                            <?php 
                            $teachers->data_seek(0);
                            while ($teacher = $teachers->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $teacher['id']; ?>"><?php echo htmlspecialchars($teacher['name']); ?> (<?php echo $teacher['employee_id'] ?? 'N/A'; ?>)</option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-circle"></i> Assign Teacher
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

        </div> <!-- Close container-fluid -->

<script>
// Programs and strands data from PHP
const collegePrograms = <?php 
    $programs->data_seek(0);
    $progs = [];
    while ($p = $programs->fetch_assoc()) { $progs[] = $p; }
    echo json_encode($progs);
?>;

const shsStrands = <?php 
    $strands->data_seek(0);
    $strs = [];
    while ($s = $strands->fetch_assoc()) { $strs[] = $s; }
    echo json_encode($strs);
?>;

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    loadPrograms();
});

function loadPrograms() {
    const type = document.getElementById('programType').value;
    const select = document.getElementById('programSelect');
    
    select.innerHTML = '<option value="">Select ' + (type === 'college' ? 'Program' : 'Strand') + '</option>';
    document.getElementById('yearLevelSelect').innerHTML = '<option value="">Select Year Level</option>';
    
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
    
    if (!programId) {
        select.innerHTML = '<option value="">Select Year Level</option>';
        return;
    }
    
    select.innerHTML = '<option value="">Loading...</option>';
    
    fetch(`process/teacher_assignment_api.php?action=get_year_levels&type=${type}&program_id=${programId}`)
        .then(response => response.json())
        .then(data => {
            select.innerHTML = '<option value="">Select Year Level</option>';
            if (data.success) {
                data.levels.forEach(level => {
                    select.innerHTML += `<option value="${level.id}">${level.name}</option>`;
                });
            }
        });
}

function loadSubjects() {
    const type = document.getElementById('programType').value;
    const programId = document.getElementById('programSelect').value;
    const yearLevelId = document.getElementById('yearLevelSelect').value;
    const semester = document.getElementById('semesterSelect').value;
    
    if (!programId || !yearLevelId) {
        document.getElementById('subjectsList').innerHTML = `
            <div class="text-center py-5 text-muted">
                <i class="bi bi-arrow-up-circle fs-1 d-block mb-3"></i>
                Select a program, year level, and semester to view subjects
            </div>
        `;
        return;
    }
    
    document.getElementById('subjectsList').innerHTML = `
        <div class="text-center py-4">
            <i class="bi bi-arrow-repeat spin fs-3"></i>
            <p class="mt-2">Loading subjects...</p>
        </div>
    `;
    
    const params = new URLSearchParams({
        action: 'get_subjects',
        type: type,
        program_id: programId,
        year_level_id: yearLevelId,
        semester: semester
    });
    
    fetch('process/teacher_assignment_api.php?' + params)
        .then(response => response.json())
        .then(data => {
            document.getElementById('subjectCount').textContent = (data.subjects?.length || 0) + ' subjects';
            
            if (data.success && data.subjects.length > 0) {
                let html = '';
                data.subjects.forEach(subject => {
                    const isAssigned = subject.teacher_id != null;
                    html += `
                        <div class="subject-card ${isAssigned ? 'assigned' : ''} p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1 fw-bold">${subject.subject_code}</h6>
                                    <p class="mb-1 text-muted">${subject.subject_title}</p>
                                    <small class="text-muted">${subject.units} units</small>
                                </div>
                                <div class="text-end">
                                    ${isAssigned ? `
                                        <span class="teacher-badge mb-2 d-inline-block">
                                            <i class="bi bi-person-check"></i> ${subject.teacher_name}
                                        </span>
                                        <br>
                                        <button class="btn btn-sm btn-outline-primary me-1" onclick="openAssignModal(${subject.id}, '${subject.subject_code} - ${subject.subject_title}', ${subject.teacher_id || 'null'})">
                                            <i class="bi bi-arrow-repeat"></i> Change
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="unassignTeacher(${subject.id})">
                                            <i class="bi bi-x"></i> Remove
                                        </button>
                                    ` : `
                                        <span class="badge bg-secondary mb-2 d-inline-block">
                                            <i class="bi bi-person-x"></i> Not Assigned
                                        </span>
                                        <br>
                                        <button class="btn btn-sm btn-success" onclick="openAssignModal(${subject.id}, '${subject.subject_code} - ${subject.subject_title}', null)">
                                            <i class="bi bi-person-plus"></i> Assign Teacher
                                        </button>
                                    `}
                                </div>
                            </div>
                        </div>
                    `;
                });
                document.getElementById('subjectsList').innerHTML = html;
            } else {
                document.getElementById('subjectsList').innerHTML = `
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-book fs-1 d-block mb-3"></i>
                        No subjects found for this selection
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('subjectsList').innerHTML = '<div class="alert alert-danger">Error loading subjects</div>';
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
    
    fetch('process/teacher_assignment_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('assignTeacherModal')).hide();
            showAlert('success', data.message);
            loadSubjects();
        } else {
            showAlert('danger', data.message);
        }
    });
});

function unassignTeacher(subjectId) {
    if (!confirm('Are you sure you want to remove the teacher assignment?')) return;
    
    const formData = new FormData();
    formData.append('action', 'unassign_teacher');
    formData.append('subject_id', subjectId);
    
    fetch('process/teacher_assignment_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('success', data.message);
            loadSubjects();
        } else {
            showAlert('danger', data.message);
        }
    });
}

function showAlert(type, message) {
    const container = document.getElementById('alertContainer');
    container.innerHTML = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    setTimeout(() => container.innerHTML = '', 5000);
}
</script>

<?php include '../../includes/footer.php'; ?>
