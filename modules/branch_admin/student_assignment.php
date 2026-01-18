<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_BRANCH_ADMIN) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Student Section Assignment";
$branch_id = get_user_branch_id();
if ($branch_id === null) {
    echo "Error: Your account is not assigned to any branch. Please contact the School Administrator.";
    exit();
}
require_branch_assignment();

// Get current academic year
$current_ay = $conn->query("SELECT * FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$current_ay_id = $current_ay['id'] ?? 0;

// Fetch all students in this branch with program enrollment info
// Note: Students may not have branch_id assigned, so we fetch all active students
$students = $conn->query("
    SELECT 
        u.id,
        u.email,
        u.status,
        up.first_name,
        up.last_name,
        COALESCE(st.student_no, CONCAT('STU-', u.id)) as student_no,
        st.course_id,
        COALESCE(p.program_code, ss.strand_code) as program_code,
        COALESCE(p.program_name, ss.strand_name) as program_name,
        CASE 
            WHEN st.course_id IS NOT NULL AND EXISTS (SELECT 1 FROM programs WHERE id = st.course_id) THEN 'college'
            WHEN st.course_id IS NOT NULL AND EXISTS (SELECT 1 FROM shs_strands WHERE id = st.course_id) THEN 'shs'
            ELSE NULL 
        END as program_type,
        (SELECT COUNT(*) FROM section_students ss2 
         INNER JOIN sections s ON ss2.section_id = s.id 
         WHERE ss2.student_id = u.id AND s.branch_id = $branch_id AND s.academic_year_id = $current_ay_id AND ss2.status = 'active') as enrolled_sections
    FROM users u
    INNER JOIN user_profiles up ON u.id = up.user_id
    INNER JOIN user_roles ur ON u.id = ur.user_id
    LEFT JOIN students st ON u.id = st.user_id
    LEFT JOIN programs p ON st.course_id = p.id
    LEFT JOIN shs_strands ss ON st.course_id = ss.id
    WHERE ur.role_id = " . ROLE_STUDENT . " 
    AND u.status = 'active'
    ORDER BY up.last_name, up.first_name
");

// Fetch all programs with year levels for filter
$programs = $conn->query("
    SELECT p.id, p.program_code, p.program_name
    FROM programs p
    WHERE p.is_active = 1
    ORDER BY p.program_name
");

// Fetch program year levels
$year_levels_query = $conn->query("
    SELECT pyl.id, pyl.program_id, pyl.year_level, pyl.year_name
    FROM program_year_levels pyl
    WHERE pyl.is_active = 1
    ORDER BY pyl.program_id, pyl.year_level
");
$program_year_levels = [];
while ($row = $year_levels_query->fetch_assoc()) {
    $program_year_levels[$row['program_id']][] = $row;
}

// Fetch SHS strands with grade levels
$strands = $conn->query("
    SELECT s.id, s.strand_code, s.strand_name
    FROM shs_strands s
    WHERE s.is_active = 1
    ORDER BY s.strand_name
");

$grade_levels_query = $conn->query("
    SELECT sgl.id, sgl.strand_id, sgl.grade_level, sgl.grade_name
    FROM shs_grade_levels sgl
    WHERE sgl.is_active = 1
    ORDER BY sgl.strand_id, sgl.grade_level
");
$strand_grade_levels = [];
while ($row = $grade_levels_query->fetch_assoc()) {
    $strand_grade_levels[$row['strand_id']][] = $row;
}

include '../../includes/header.php';
?>

<style>
    .student-card {
        background: white;
        border-radius: 15px;
        border: none;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        transition: all 0.3s ease;
        overflow: hidden;
        border-left: 5px solid #17a2b8;
        cursor: pointer;
    }
    .student-card:hover {
        transform: translateX(5px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.1);
    }
    .student-card.selected {
        border-left-color: #800000;
        background: #fff5f5;
    }
    
    .student-avatar {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: linear-gradient(135deg, #17a2b8, #20c997);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.2rem;
        font-weight: bold;
    }
    
    .section-card {
        background: #f8f9fa;
        border-radius: 12px;
        padding: 15px;
        margin-bottom: 12px;
        border: 2px solid transparent;
        transition: all 0.2s ease;
        cursor: pointer;
    }
    .section-card:hover {
        background: #fff;
        border-color: #e9ecef;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    .section-card.enrolled {
        border-color: #28a745;
        background: #f0fff4;
    }
    .section-card.full {
        opacity: 0.6;
        cursor: not-allowed;
    }
    
    .filter-panel {
        background: linear-gradient(135deg, #f8f9fa, #ffffff);
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 20px;
        border: 1px solid #e9ecef;
    }
    
    .enrollment-badge {
        font-size: 0.75rem;
        padding: 4px 10px;
        border-radius: 20px;
    }
    
    .capacity-bar {
        height: 6px;
        border-radius: 3px;
        background: #e9ecef;
        overflow: hidden;
    }
    .capacity-bar .fill {
        height: 100%;
        transition: width 0.3s ease;
    }
    .capacity-bar .fill.low { background: #28a745; }
    .capacity-bar .fill.medium { background: #ffc107; }
    .capacity-bar .fill.high { background: #dc3545; }
    
    .quick-enroll-btn {
        width: 100%;
        border-radius: 8px;
        padding: 10px;
        font-weight: 600;
    }
</style>

<div class="wrapper">
    <?php include '../../includes/sidebar.php'; ?>

    <div id="content">
        <!-- Header -->
        <div class="navbar-custom d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-0" style="color: #003366;">
                    <i class="bi bi-person-plus"></i> Student Section Assignment
                </h4>
                <small class="text-muted">Assign students to sections by program and year level</small>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#bulkEnrollModal">
                    <i class="bi bi-people"></i> Bulk Enroll
                </button>
                <a href="students.php" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-people"></i> All Students
                </a>
                <a href="dashboard.php" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-arrow-left"></i> Dashboard
                </a>
            </div>
        </div>

        <div id="alertContainer"></div>

        <div class="row">
            <!-- Left: Students List -->
            <div class="col-lg-4 mb-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="bi bi-mortarboard-fill text-info"></i> Select Student</h6>
                    </div>
                    <div class="card-body">
                        <input type="text" class="form-control mb-3" id="searchStudent" placeholder="Search by name or student no...">
                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" id="showUnassignedOnly">
                            <label class="form-check-label" for="showUnassignedOnly">Show students with no enrollments only</label>
                        </div>
                        <div class="students-list" style="max-height: 550px; overflow-y: auto;">
                            <?php if ($students->num_rows > 0): ?>
                                <?php while ($student = $students->fetch_assoc()): ?>
                                    <div class="student-card p-3 mb-2" 
                                         data-student-id="<?php echo $student['id']; ?>"
                                         data-enrolled="<?php echo $student['enrolled_sections']; ?>"
                                         onclick="selectStudent(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars(addslashes($student['first_name'] . ' ' . $student['last_name'])); ?>')">
                                        <div class="d-flex align-items-center">
                                            <div class="student-avatar me-3">
                                                <?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-0"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h6>
                                                <small class="text-muted"><?php echo htmlspecialchars($student['student_no'] ?? 'No Student ID'); ?></small>
                                                <div class="mt-1">
                                                    <span class="badge <?php echo $student['enrolled_sections'] > 0 ? 'bg-success' : 'bg-warning text-dark'; ?> enrollment-badge">
                                                        <?php echo $student['enrolled_sections']; ?> Sections
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="text-center text-muted p-4">
                                    <i class="bi bi-people fs-1 opacity-25"></i>
                                    <p class="mt-2">No students found in this branch</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Section Assignment Panel -->
            <div class="col-lg-8">
                <div id="assignmentPanel" style="display: none;">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header" style="background: linear-gradient(135deg, #17a2b8, #20c997); color: white;">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="bi bi-person-check"></i> Enrolling: <span id="selectedStudentName"></span></h5>
                            </div>
                        </div>
                        <div class="card-body">
                            <!-- Filters -->
                            <div class="filter-panel">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold">Program Type</label>
                                        <select class="form-select" id="filterProgramType" onchange="updateProgramFilter()">
                                            <option value="">All Types</option>
                                            <option value="college">College</option>
                                            <option value="shs">Senior High School</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold">Program/Strand</label>
                                        <select class="form-select" id="filterProgram" onchange="updateYearLevelFilter()">
                                            <option value="">All Programs</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold">Year Level</label>
                                        <select class="form-select" id="filterYearLevel" onchange="loadAvailableSections()">
                                            <option value="">All Year Levels</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="row g-3 mt-2">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Subject Search</label>
                                        <input type="text" class="form-control" id="searchSubject" placeholder="Search by subject..." oninput="filterDisplayedSections()">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Show</label>
                                        <select class="form-select" id="filterAvailability" onchange="filterDisplayedSections()">
                                            <option value="">All Sections</option>
                                            <option value="available">Available Only</option>
                                            <option value="enrolled">Enrolled Only</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Available Sections -->
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0">Available Sections</h6>
                                <small class="text-muted">Click on a section to enroll/unenroll</small>
                            </div>
                            <div class="sections-list" id="sectionsList" style="max-height: 400px; overflow-y: auto;">
                                <div class="text-center text-muted p-5">
                                    <i class="bi bi-hand-index-thumb fs-1 opacity-25"></i>
                                    <p class="mt-2">Select a student to view available sections</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Current Enrollments -->
                    <div class="card shadow-sm">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><i class="bi bi-list-check text-success"></i> Current Enrollments</h6>
                            <button class="btn btn-sm btn-outline-danger" onclick="unenrollAll()">
                                <i class="bi bi-x-circle"></i> Unenroll All
                            </button>
                        </div>
                        <div class="card-body" id="currentEnrollments" style="max-height: 300px; overflow-y: auto;">
                            <div class="text-center text-muted p-4">
                                <p>Select a student to see their enrollments</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- No Student Selected -->
                <div id="noStudentSelected">
                    <div class="card shadow-sm">
                        <div class="card-body text-center p-5">
                            <i class="bi bi-arrow-left-circle fs-1 text-muted opacity-25"></i>
                            <h5 class="mt-3 text-muted">Select a Student</h5>
                            <p class="text-muted">Choose a student from the left panel to manage their section enrollments</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Enroll Modal -->
<div class="modal fade" id="bulkEnrollModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #28a745, #20c997); color: white;">
                <h5 class="modal-title"><i class="bi bi-people-fill"></i> Bulk Enroll Students</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Select Section</label>
                        <select class="form-select" id="bulkSectionSelect" onchange="loadUnenrolledStudents()">
                            <option value="">Choose a section...</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Section Info</label>
                        <div id="bulkSectionInfo" class="form-control bg-light">Select a section</div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Select Students to Enroll</label>
                    <div class="d-flex gap-2 mb-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAllBulkStudents()">Select All</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearBulkStudents()">Clear All</button>
                    </div>
                    <div id="bulkStudentsList" style="max-height: 300px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 8px; padding: 10px;">
                        <div class="text-center text-muted p-4">Select a section first</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" onclick="processBulkEnroll()">
                    <i class="bi bi-check-circle"></i> Enroll Selected Students
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Data from PHP
const programsData = <?php 
    $programs->data_seek(0);
    $progs = [];
    while ($p = $programs->fetch_assoc()) {
        $progs[] = $p;
    }
    echo json_encode($progs);
?>;

const strandsData = <?php 
    $strands->data_seek(0);
    $strs = [];
    while ($s = $strands->fetch_assoc()) {
        $strs[] = $s;
    }
    echo json_encode($strs);
?>;

const programYearLevels = <?php echo json_encode($program_year_levels); ?>;
const strandGradeLevels = <?php echo json_encode($strand_grade_levels); ?>;

let selectedStudentId = null;
let selectedStudentName = '';
let allSections = [];

// Search students
document.getElementById('searchStudent').addEventListener('input', filterStudentsList);
document.getElementById('showUnassignedOnly').addEventListener('change', filterStudentsList);

function filterStudentsList() {
    const search = document.getElementById('searchStudent').value.toLowerCase();
    const unassignedOnly = document.getElementById('showUnassignedOnly').checked;
    
    document.querySelectorAll('.student-card').forEach(card => {
        const name = card.querySelector('h6').textContent.toLowerCase();
        const studentNo = card.querySelector('small').textContent.toLowerCase();
        const enrolled = parseInt(card.dataset.enrolled);
        
        let show = (name.includes(search) || studentNo.includes(search));
        if (unassignedOnly && enrolled > 0) show = false;
        
        card.style.display = show ? 'block' : 'none';
    });
}

function selectStudent(studentId, studentName) {
    selectedStudentId = studentId;
    selectedStudentName = studentName;
    
    // Update UI
    document.querySelectorAll('.student-card').forEach(c => c.classList.remove('selected'));
    document.querySelector(`.student-card[data-student-id="${studentId}"]`).classList.add('selected');
    
    document.getElementById('selectedStudentName').textContent = studentName;
    document.getElementById('assignmentPanel').style.display = 'block';
    document.getElementById('noStudentSelected').style.display = 'none';
    
    loadAvailableSections();
    loadCurrentEnrollments();
}

function updateProgramFilter() {
    const type = document.getElementById('filterProgramType').value;
    const programSelect = document.getElementById('filterProgram');
    programSelect.innerHTML = '<option value="">All Programs</option>';
    
    if (type === 'college') {
        programsData.forEach(p => {
            programSelect.innerHTML += `<option value="college_${p.id}">${p.program_code} - ${p.program_name}</option>`;
        });
    } else if (type === 'shs') {
        strandsData.forEach(s => {
            programSelect.innerHTML += `<option value="shs_${s.id}">${s.strand_code} - ${s.strand_name}</option>`;
        });
    }
    
    document.getElementById('filterYearLevel').innerHTML = '<option value="">All Year Levels</option>';
    loadAvailableSections();
}

function updateYearLevelFilter() {
    const programValue = document.getElementById('filterProgram').value;
    const yearLevelSelect = document.getElementById('filterYearLevel');
    yearLevelSelect.innerHTML = '<option value="">All Year Levels</option>';
    
    if (programValue) {
        const [type, id] = programValue.split('_');
        const levels = type === 'college' ? (programYearLevels[id] || []) : (strandGradeLevels[id] || []);
        
        levels.forEach(l => {
            const name = type === 'college' ? l.year_name : l.grade_name;
            yearLevelSelect.innerHTML += `<option value="${l.id}">${name}</option>`;
        });
    }
    loadAvailableSections();
}

function loadAvailableSections() {
    if (!selectedStudentId) return;
    
    const container = document.getElementById('sectionsList');
    container.innerHTML = '<div class="text-center p-4"><i class="bi bi-arrow-repeat spin"></i> Loading sections...</div>';
    
    const params = new URLSearchParams({
        action: 'get_available_sections',
        student_id: selectedStudentId
    });
    
    fetch(`process/student_assignment_api.php?${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                allSections = data.sections;
                renderSections();
            } else {
                container.innerHTML = '<div class="alert alert-danger">Error loading sections</div>';
            }
        })
        .catch(error => {
            container.innerHTML = '<div class="alert alert-danger">Error loading sections</div>';
            console.error('Error:', error);
        });
}

function filterDisplayedSections() {
    renderSections();
}

function renderSections() {
    const container = document.getElementById('sectionsList');
    const programType = document.getElementById('filterProgramType').value;
    const programValue = document.getElementById('filterProgram').value;
    const yearLevel = document.getElementById('filterYearLevel').value;
    const availability = document.getElementById('filterAvailability').value;
    const search = document.getElementById('searchSubject').value.toLowerCase();
    
    let filteredSections = allSections.filter(section => {
        // Program type filter
        if (programType && section.subject_type !== programType) return false;
        
        // Program/Strand filter
        if (programValue) {
            const [type, id] = programValue.split('_');
            if (type === 'college' && section.program_id != id) return false;
            if (type === 'shs' && section.strand_id != id) return false;
        }
        
        // Year level filter
        if (yearLevel) {
            if (section.year_level_id != yearLevel && section.grade_level_id != yearLevel) return false;
        }
        
        // Availability filter
        if (availability === 'available' && (section.is_enrolled || section.is_full)) return false;
        if (availability === 'enrolled' && !section.is_enrolled) return false;
        
        // Search filter
        if (search) {
            const searchStr = `${section.subject_code} ${section.subject_title} ${section.section_name}`.toLowerCase();
            if (!searchStr.includes(search)) return false;
        }
        
        return true;
    });
    
    if (filteredSections.length === 0) {
        container.innerHTML = `
            <div class="text-center text-muted p-5">
                <i class="bi bi-search fs-1 opacity-25"></i>
                <p class="mt-2">No sections found matching your filters</p>
            </div>
        `;
        return;
    }
    
    let html = '';
    filteredSections.forEach(section => {
        const capacityPercent = Math.round((section.current_enrolled / section.max_capacity) * 100);
        const capacityClass = capacityPercent >= 90 ? 'high' : capacityPercent >= 70 ? 'medium' : 'low';
        const programLabel = section.program_name || section.strand_name || 'General';
        const yearLabel = section.year_name || section.grade_name || 'N/A';
        
        html += `
            <div class="section-card ${section.is_enrolled ? 'enrolled' : ''} ${section.is_full && !section.is_enrolled ? 'full' : ''}" 
                 onclick="${section.is_full && !section.is_enrolled ? '' : `toggleEnrollment(${section.id}, ${section.is_enrolled})`}">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between">
                            <div>
                                <strong>${section.subject_code}</strong> - ${section.section_name}
                                <br>
                                <small class="text-muted">${section.subject_title}</small>
                            </div>
                            <div>
                                ${section.is_enrolled 
                                    ? '<span class="badge bg-success"><i class="bi bi-check"></i> Enrolled</span>'
                                    : section.is_full 
                                        ? '<span class="badge bg-danger">Full</span>'
                                        : '<span class="badge bg-light text-dark border">Available</span>'
                                }
                            </div>
                        </div>
                        <div class="mt-2 d-flex justify-content-between align-items-center">
                            <div>
                                <span class="badge bg-light text-dark border me-1">${programLabel}</span>
                                <span class="badge bg-light text-dark border me-1">${yearLabel}</span>
                                <small class="text-muted">Teacher: ${section.teacher_name || 'TBA'}</small>
                            </div>
                            <div class="text-end" style="min-width: 100px;">
                                <small class="text-muted">${section.current_enrolled}/${section.max_capacity}</small>
                                <div class="capacity-bar mt-1">
                                    <div class="fill ${capacityClass}" style="width: ${capacityPercent}%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

function toggleEnrollment(sectionId, isEnrolled) {
    const action = isEnrolled ? 'unenroll' : 'enroll';
    
    const formData = new FormData();
    formData.append('action', action);
    formData.append('student_id', selectedStudentId);
    formData.append('section_id', sectionId);
    
    fetch('process/student_assignment_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('success', data.message);
            loadAvailableSections();
            loadCurrentEnrollments();
            updateStudentBadge();
        } else {
            showAlert('danger', data.message || 'Error processing enrollment');
        }
    })
    .catch(error => {
        showAlert('danger', 'Error processing enrollment');
        console.error('Error:', error);
    });
}

function loadCurrentEnrollments() {
    const container = document.getElementById('currentEnrollments');
    container.innerHTML = '<div class="text-center p-3"><i class="bi bi-arrow-repeat spin"></i> Loading...</div>';
    
    fetch(`process/student_assignment_api.php?action=get_student_enrollments&student_id=${selectedStudentId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.enrollments.length > 0) {
                let html = '<div class="table-responsive"><table class="table table-sm table-hover"><thead><tr><th>Subject</th><th>Section</th><th>Teacher</th><th>Schedule</th><th></th></tr></thead><tbody>';
                data.enrollments.forEach(e => {
                    html += `
                        <tr>
                            <td><strong>${e.subject_code}</strong><br><small class="text-muted">${e.subject_title}</small></td>
                            <td>${e.section_name}</td>
                            <td><small>${e.teacher_name || 'TBA'}</small></td>
                            <td><small>${e.schedule || '-'}</small></td>
                            <td>
                                <button class="btn btn-sm btn-outline-danger" onclick="toggleEnrollment(${e.class_id}, true)">
                                    <i class="bi bi-x"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                });
                html += '</tbody></table></div>';
                container.innerHTML = html;
            } else {
                container.innerHTML = '<div class="text-center text-muted p-4">No enrollments yet. Click on sections above to enroll.</div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
}

function unenrollAll() {
    if (!confirm('Are you sure you want to unenroll this student from ALL sections?')) return;
    
    const formData = new FormData();
    formData.append('action', 'unenroll_all');
    formData.append('student_id', selectedStudentId);
    
    fetch('process/student_assignment_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('success', 'All enrollments removed');
            loadAvailableSections();
            loadCurrentEnrollments();
            updateStudentBadge();
        } else {
            showAlert('danger', data.message || 'Error removing enrollments');
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

function updateStudentBadge() {
    // Update the student card badge
    fetch(`process/student_assignment_api.php?action=get_student_enrollments&student_id=${selectedStudentId}`)
        .then(response => response.json())
        .then(data => {
            const count = data.enrollments ? data.enrollments.length : 0;
            const card = document.querySelector(`.student-card[data-student-id="${selectedStudentId}"]`);
            if (card) {
                card.dataset.enrolled = count;
                const badge = card.querySelector('.enrollment-badge');
                badge.textContent = `${count} Sections`;
                badge.className = `badge ${count > 0 ? 'bg-success' : 'bg-warning text-dark'} enrollment-badge`;
            }
        });
}

// Bulk enroll functionality
document.getElementById('bulkEnrollModal').addEventListener('show.bs.modal', function() {
    loadBulkSections();
});

function loadBulkSections() {
    fetch('process/student_assignment_api.php?action=get_all_sections_for_bulk')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const select = document.getElementById('bulkSectionSelect');
                select.innerHTML = '<option value="">Choose a section...</option>';
                data.sections.forEach(s => {
                    select.innerHTML += `<option value="${s.id}" data-capacity="${s.max_capacity}" data-enrolled="${s.current_enrolled}">${s.subject_code} - ${s.section_name} (${s.current_enrolled}/${s.max_capacity})</option>`;
                });
            }
        });
}

function loadUnenrolledStudents() {
    const sectionId = document.getElementById('bulkSectionSelect').value;
    const container = document.getElementById('bulkStudentsList');
    const infoDiv = document.getElementById('bulkSectionInfo');
    
    if (!sectionId) {
        container.innerHTML = '<div class="text-center text-muted p-4">Select a section first</div>';
        infoDiv.textContent = 'Select a section';
        return;
    }
    
    const option = document.querySelector(`#bulkSectionSelect option[value="${sectionId}"]`);
    const capacity = option.dataset.capacity;
    const enrolled = option.dataset.enrolled;
    const available = capacity - enrolled;
    infoDiv.innerHTML = `<span class="badge bg-info me-2">${enrolled}/${capacity} enrolled</span><span class="badge bg-success">${available} slots available</span>`;
    
    container.innerHTML = '<div class="text-center p-3"><i class="bi bi-arrow-repeat spin"></i> Loading...</div>';
    
    fetch(`process/student_assignment_api.php?action=get_unenrolled_students&section_id=${sectionId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.students.length > 0) {
                let html = '';
                data.students.forEach(s => {
                    html += `
                        <div class="form-check mb-2">
                            <input type="checkbox" class="form-check-input bulk-student-cb" value="${s.id}" id="bulk_student_${s.id}">
                            <label class="form-check-label" for="bulk_student_${s.id}">
                                ${s.first_name} ${s.last_name} <small class="text-muted">(${s.student_no || 'No ID'})</small>
                            </label>
                        </div>
                    `;
                });
                container.innerHTML = html;
            } else {
                container.innerHTML = '<div class="text-center text-muted p-4">All students are already enrolled in this section</div>';
            }
        });
}

function selectAllBulkStudents() {
    document.querySelectorAll('.bulk-student-cb').forEach(cb => cb.checked = true);
}

function clearBulkStudents() {
    document.querySelectorAll('.bulk-student-cb').forEach(cb => cb.checked = false);
}

function processBulkEnroll() {
    const sectionId = document.getElementById('bulkSectionSelect').value;
    const studentIds = Array.from(document.querySelectorAll('.bulk-student-cb:checked')).map(cb => cb.value);
    
    if (!sectionId) {
        showAlert('warning', 'Please select a section');
        return;
    }
    if (studentIds.length === 0) {
        showAlert('warning', 'Please select at least one student');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'bulk_enroll');
    formData.append('section_id', sectionId);
    formData.append('student_ids', JSON.stringify(studentIds));
    
    fetch('process/student_assignment_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('success', data.message);
            bootstrap.Modal.getInstance(document.getElementById('bulkEnrollModal')).hide();
            if (selectedStudentId) {
                loadAvailableSections();
                loadCurrentEnrollments();
            }
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert('danger', data.message || 'Error enrolling students');
        }
    })
    .catch(error => {
        showAlert('danger', 'Error enrolling students');
        console.error('Error:', error);
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
    setTimeout(() => {
        const alert = container.querySelector('.alert');
        if (alert) alert.remove();
    }, 5000);
}
</script>

<?php include '../../includes/footer.php'; ?>
