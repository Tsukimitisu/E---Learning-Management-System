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
    AND up.branch_id = $branch_id
    ORDER BY up.last_name, up.first_name
");

// Fetch all programs with year levels for filter
$programs = $conn->query("SELECT p.id, p.program_code, p.program_name FROM programs p WHERE p.is_active = 1 ORDER BY p.program_name");

// Fetch program year levels
$year_levels_query = $conn->query("SELECT pyl.id, pyl.program_id, pyl.year_level, pyl.year_name FROM program_year_levels pyl WHERE pyl.is_active = 1 ORDER BY pyl.program_id, pyl.year_level");
$program_year_levels = [];
while ($row = $year_levels_query->fetch_assoc()) { $program_year_levels[$row['program_id']][] = $row; }

// Fetch SHS strands with grade levels
$strands = $conn->query("SELECT s.id, s.strand_code, s.strand_name FROM shs_strands s WHERE s.is_active = 1 ORDER BY s.strand_name");
$grade_levels_query = $conn->query("SELECT sgl.id, sgl.strand_id, sgl.grade_level, sgl.grade_name FROM shs_grade_levels sgl WHERE sgl.is_active = 1 ORDER BY sgl.strand_id, sgl.grade_level");
$strand_grade_levels = [];
while ($row = $grade_levels_query->fetch_assoc()) { $strand_grade_levels[$row['strand_id']][] = $row; }

include '../../includes/header.php';
?>

<link rel="stylesheet" href="css/student_assignment.css">

<div class="main-content-body animate__animated animate__fadeIn">
    
    <!-- 1. PAGE HEADER -->
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center animate__animated animate__fadeInDown">
        <div class="mb-2 mb-md-0">
            <h4 class="fw-bold mb-0" style="color: var(--blue);">
                <i class="bi bi-person-plus-fill me-2 text-maroon"></i>Student Section Assignment
            </h4>
            <p class="text-muted small mb-0">Assign individual students to specific subject sections & classes.</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-success btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#bulkEnrollModal">
                <i class="bi bi-people me-1"></i> Bulk Enroll
            </button>
            <a href="dashboard.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                <i class="bi bi-arrow-left"></i> Dashboard
            </a>
        </div>
    </div>

    <div id="alertContainer"></div>

    <div class="row g-4">
        <!-- LEFT: STUDENT SELECTOR -->
        <div class="col-lg-4">
            <div class="content-card h-100">
                <div class="card-header-modern bg-white">
                    <i class="bi bi-search me-2"></i>Find Student
                </div>
                <div class="p-3">
                    <div class="input-group input-group-sm mb-3">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control border-start-0" id="searchStudent" placeholder="Search name or ID...">
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input type="checkbox" class="form-check-input" id="showUnassignedOnly">
                        <label class="form-check-label small fw-bold text-muted" for="showUnassignedOnly">Show unassigned only</label>
                    </div>

                    <div class="students-list" style="max-height: 600px; overflow-y: auto; padding-right: 5px;">
                        <?php if ($students->num_rows > 0): ?>
                            <?php while ($student = $students->fetch_assoc()): ?>
                                <div class="student-card-modern" 
                                     data-student-id="<?php echo $student['id']; ?>"
                                     data-enrolled="<?php echo $student['enrolled_sections']; ?>"
                                     onclick="selectStudent(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars(addslashes($student['first_name'] . ' ' . $student['last_name'])); ?>')">
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-circle-sm me-3">
                                            <?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-0 fw-bold text-dark" style="font-size: 0.85rem;"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h6>
                                            <small class="text-muted d-block" style="font-size: 0.7rem;"><?php echo htmlspecialchars($student['student_no'] ?? 'NO-ID'); ?></small>
                                            <span class="badge <?php echo $student['enrolled_sections'] > 0 ? 'bg-success' : 'bg-warning text-dark'; ?> mt-1" style="font-size: 0.6rem;">
                                                <?php echo $student['enrolled_sections']; ?> Enrollments
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center py-5 text-muted opacity-50">
                                <i class="bi bi-people fs-1"></i>
                                <p class="small fw-bold">No students found.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT: ASSIGNMENT PANEL -->
        <div class="col-lg-8">
            <div id="assignmentPanel" style="display: none;">
                <div class="content-card mb-4">
                    <div class="card-header-modern bg-blue text-white" style="background: var(--blue) !important;">
                        <i class="bi bi-person-check me-2"></i>Enrollment Session: <span id="selectedStudentName" class="text-warning"></span>
                    </div>
                    <div class="p-4">
                        <!-- Filters -->
                        <div class="control-panel-light">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-muted">Program Type</label>
                                    <select class="form-select form-select-sm" id="filterProgramType" onchange="updateProgramFilter()">
                                        <option value="">All Types</option>
                                        <option value="college">College</option>
                                        <option value="shs">Senior High School</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-muted">Program/Strand</label>
                                    <select class="form-select form-select-sm" id="filterProgram" onchange="updateYearLevelFilter()">
                                        <option value="">All Programs</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-muted">Year Level</label>
                                    <select class="form-select form-select-sm" id="filterYearLevel" onchange="loadAvailableSections()">
                                        <option value="">All Year Levels</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row g-3 mt-1">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-muted">Quick Search Subject</label>
                                    <input type="text" class="form-control form-control-sm" id="searchSubject" placeholder="Enter subject code..." oninput="filterDisplayedSections()">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-muted">Status Filter</label>
                                    <select class="form-select form-select-sm" id="filterAvailability" onchange="filterDisplayedSections()">
                                        <option value="">Show All Sections</option>
                                        <option value="available">Available Only</option>
                                        <option value="enrolled">Already Enrolled</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Sections Display -->
                        <h6 class="fw-bold mb-3" style="font-size: 0.8rem; color: var(--blue);"><i class="bi bi-collection me-2"></i>Available Class Sections</h6>
                        <div class="sections-list" id="sectionsList" style="max-height: 450px; overflow-y: auto; padding-right: 5px;">
                            <!-- Dynamic Content -->
                        </div>
                    </div>
                </div>

                <!-- Current Table View -->
                <div class="content-card">
                    <div class="card-header-modern bg-white d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-list-check me-2 text-success"></i>Current Registered Enrollments</span>
                        <button class="btn btn-sm btn-outline-danger border-0 fw-bold" style="font-size: 0.65rem;" onclick="unenrollAll()">
                            <i class="bi bi-trash me-1"></i> UNENROLL ALL
                        </button>
                    </div>
                    <div class="p-0">
                        <div id="currentEnrollments">
                            <!-- Dynamic Table -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Placeholder -->
            <div id="noStudentSelected">
                <div class="content-card h-100 d-flex align-items-center justify-content-center py-5">
                    <div class="text-center py-5">
                        <i class="bi bi-person-bounding-box display-4 text-muted opacity-25"></i>
                        <h5 class="mt-3 fw-bold text-muted">No Student Selected</h5>
                        <p class="text-muted small">Select a student from the left directory to manage their class assignments.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Enroll Modal -->
<div class="modal fade" id="bulkEnrollModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-success text-white py-3">
                <h5 class="modal-title fs-6 fw-bold"><i class="bi bi-people-fill me-2"></i>Bulk Student Enrollment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Step 1: Filter Students -->
                <div class="mb-4">
                    <h6 class="fw-bold text-primary mb-3"><i class="bi bi-funnel me-2"></i>Step 1: Filter Students</h6>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Program Type *</label>
                            <select class="form-select form-select-sm" id="bulkProgramType" onchange="updateBulkProgramFilter()">
                                <option value="">-- Select Type --</option>
                                <option value="college">College</option>
                                <option value="shs">Senior High School</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Program/Strand *</label>
                            <select class="form-select form-select-sm" id="bulkProgram" onchange="updateBulkYearLevelFilter()" disabled>
                                <option value="">-- Select Program --</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Year/Grade Level *</label>
                            <select class="form-select form-select-sm" id="bulkYearLevel" onchange="loadFilteredStudentsAndSections()" disabled>
                                <option value="">-- Select Level --</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Select Section -->
                <div class="mb-4">
                    <h6 class="fw-bold text-primary mb-3"><i class="bi bi-collection me-2"></i>Step 2: Select Section</h6>
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label small fw-bold">Target Class Section</label>
                            <select class="form-select" id="bulkSectionSelect" onchange="updateBulkSectionInfo()" disabled>
                                <option value="">-- Complete Step 1 first --</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Capacity Status</label>
                            <div id="bulkSectionInfo" class="form-control bg-light border-0 small py-2">Select filters first</div>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Select Students -->
                <div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="fw-bold text-primary mb-0"><i class="bi bi-person-check me-2"></i>Step 3: Select Students</h6>
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-primary px-3" onclick="selectAllBulkStudents()">Select All</button>
                            <button type="button" class="btn btn-outline-secondary px-3" onclick="clearBulkStudents()">Clear</button>
                        </div>
                    </div>
                    <div class="mb-2">
                        <input type="text" class="form-control form-control-sm" id="bulkStudentSearch" placeholder="Search student name..." oninput="filterBulkStudentsList()">
                    </div>
                    <div id="bulkStudentsList" class="bg-light p-3 rounded-3" style="max-height: 250px; overflow-y: auto;">
                        <div class="text-center py-4 text-muted small">Complete filters above to see eligible students.</div>
                    </div>
                    <div id="bulkStudentCount" class="text-end small text-muted mt-2"></div>
                </div>
            </div>
            <div class="modal-footer bg-light border-0">
                <button type="button" class="btn btn-light btn-sm px-4 fw-bold" data-bs-dismiss="modal">CANCEL</button>
                <button type="button" class="btn btn-success btn-sm px-4 fw-bold" onclick="processBulkEnroll()">ENROLL SELECTED</button>
            </div>
        </div>
    </div>
</div>

<script>
// Keep original logic untouched
const programsData = <?php echo json_encode($programs->fetch_all(MYSQLI_ASSOC)); ?>;
const strandsData = <?php echo json_encode($strands->fetch_all(MYSQLI_ASSOC)); ?>;
const programYearLevels = <?php echo json_encode($program_year_levels); ?>;
const strandGradeLevels = <?php echo json_encode($strand_grade_levels); ?>;

let selectedStudentId = null;
let selectedStudentName = '';
let allSections = [];

document.getElementById('searchStudent').addEventListener('input', filterStudentsList);
document.getElementById('showUnassignedOnly').addEventListener('change', filterStudentsList);

function filterStudentsList() {
    const search = document.getElementById('searchStudent').value.toLowerCase();
    const unassignedOnly = document.getElementById('showUnassignedOnly').checked;
    document.querySelectorAll('.student-card-modern').forEach(card => {
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
    document.querySelectorAll('.student-card-modern').forEach(c => c.classList.remove('selected'));
    const selectedCard = document.querySelector(`.student-card-modern[data-student-id="${studentId}"]`);
    if(selectedCard) selectedCard.classList.add('selected');
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
        programsData.forEach(p => { programSelect.innerHTML += `<option value="college_${p.id}">${p.program_code} - ${p.program_name}</option>`; });
    } else if (type === 'shs') {
        strandsData.forEach(s => { programSelect.innerHTML += `<option value="shs_${s.id}">${s.strand_code} - ${s.strand_name}</option>`; });
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
    container.innerHTML = '<div class="text-center p-4"><i class="bi bi-arrow-repeat spin"></i> Loading class catalog...</div>';
    const params = new URLSearchParams({ action: 'get_available_sections', student_id: selectedStudentId });
    fetch(`process/student_assignment_api.php?${params}`).then(response => response.json()).then(data => {
        if (data.success) { allSections = data.sections; renderSections(); }
        else { container.innerHTML = '<div class="alert alert-danger p-2 small">Error loading sections</div>'; }
    });
}

function filterDisplayedSections() { renderSections(); }

function renderSections() {
    const container = document.getElementById('sectionsList');
    const programType = document.getElementById('filterProgramType').value;
    const programValue = document.getElementById('filterProgram').value;
    const yearLevel = document.getElementById('filterYearLevel').value;
    const availability = document.getElementById('filterAvailability').value;
    const search = document.getElementById('searchSubject').value.toLowerCase();
    
    let filteredSections = allSections.filter(section => {
        if (programType && section.subject_type !== programType) return false;
        if (programValue) {
            const [type, id] = programValue.split('_');
            if (type === 'college' && section.program_id != id) return false;
            if (type === 'shs' && section.strand_id != id) return false;
        }
        if (yearLevel && (section.year_level_id != yearLevel && section.grade_level_id != yearLevel)) return false;
        if (availability === 'available' && (section.is_enrolled || section.is_full)) return false;
        if (availability === 'enrolled' && !section.is_enrolled) return false;
        if (search) {
            const searchStr = `${section.program_code || ''} ${section.program_name || ''} ${section.strand_code || ''} ${section.strand_name || ''} ${section.section_name || ''}`.toLowerCase();
            if (!searchStr.includes(search)) return false;
        }
        return true;
    });
    
    if (filteredSections.length === 0) {
        container.innerHTML = '<div class="text-center p-5 text-muted small"><i class="bi bi-search fs-2 d-block opacity-25"></i>No matching class sections found.</div>';
        return;
    }
    
    let html = '';
    filteredSections.forEach(section => {
        const displayProgramCode = section.program_code || section.strand_code || 'N/A';
        const displayProgramName = section.program_name || section.strand_name || 'Program not set';
        const displayYearLevel = section.year_name || section.grade_name || 'N/A';
        const displaySemester = section.semester ? `${section.semester} Semester` : 'N/A';
        const displayAdviser = section.adviser_name || 'TBA';
        const capacityPercent = Math.round((section.current_enrolled / section.max_capacity) * 100);
        const capColor = capacityPercent >= 90 ? 'bg-high' : capacityPercent >= 70 ? 'bg-medium' : 'bg-low';
        const isFull = section.is_full && !section.is_enrolled;
        
        html += `
            <div class="section-card-modern ${section.is_enrolled ? 'enrolled' : ''} ${isFull ? 'full' : ''}" 
                 onclick="${isFull ? '' : `toggleEnrollment(${section.id}, ${section.is_enrolled})`}">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <span class="badge bg-blue text-white mb-1" style="font-size:0.6rem;">${displayProgramCode}</span>
                                <div class="fw-bold text-dark" style="font-size:0.85rem;">${section.section_name}</div>
                                <small class="text-muted">${displayProgramName} • ${displayYearLevel} • ${displaySemester}</small>
                            </div>
                            <div>
                                ${section.is_enrolled 
                                    ? '<span class="badge bg-success shadow-sm"><i class="bi bi-check-circle me-1"></i> Registered</span>'
                                    : isFull ? '<span class="badge bg-danger">Full</span>' : '<span class="badge bg-light text-muted border">Available</span>'
                                }
                            </div>
                        </div>
                        <div class="mt-2 d-flex justify-content-between align-items-center">
                            <div class="small text-muted" style="font-size: 0.7rem;">
                                <i class="bi bi-person-workspace me-1"></i>${displayAdviser}
                            </div>
                            <div class="capacity-wrapper">
                                <div class="d-flex justify-content-between small text-muted mb-1" style="font-size:0.6rem;">
                                    <span>Occupancy</span><span>${section.current_enrolled}/${section.max_capacity}</span>
                                </div>
                                <div class="capacity-bar"><div class="capacity-fill ${capColor}" style="width:${capacityPercent}%"></div></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>`;
    });
    container.innerHTML = html;
}

function toggleEnrollment(sectionId, isEnrolled) {
    const action = isEnrolled ? 'unenroll' : 'enroll';
    const formData = new FormData();
    formData.append('action', action);
    formData.append('student_id', selectedStudentId);
    formData.append('section_id', sectionId);
    
    fetch('process/student_assignment_api.php', { method: 'POST', body: formData }).then(response => response.json()).then(data => {
        if (data.success) {
            showAlert('success', data.message);
            loadAvailableSections();
            loadCurrentEnrollments();
            updateStudentBadge();
        } else { showAlert('danger', data.message || 'Error processing enrollment'); }
    });
}

function loadCurrentEnrollments() {
    const container = document.getElementById('currentEnrollments');
    container.innerHTML = '<div class="text-center p-3 small text-muted"><i class="bi bi-arrow-repeat spin"></i> Refreshing list...</div>';
    fetch(`process/student_assignment_api.php?action=get_student_enrollments&student_id=${selectedStudentId}`).then(response => response.json()).then(data => {
        if (data.success && data.enrollments.length > 0) {
            let html = '<div class="table-responsive"><table class="table table-sm table-hover mb-0 align-middle"><thead class="bg-light"><tr style="font-size:0.65rem; text-transform:uppercase;"><th>Program</th><th>Section</th><th>Adviser</th><th>Action</th></tr></thead><tbody style="font-size:0.8rem;">';
            data.enrollments.forEach(e => {
                html += `<tr>
                    <td><div class="fw-bold">${e.program_code || 'N/A'}</div><small class="text-muted line-clamp-1">${e.program_name || ''}</small></td>
                    <td><span class="badge bg-light text-dark border">${e.section_name}</span><div><small class="text-muted">${e.year_level || ''}</small></div></td>
                    <td><small>${e.adviser_name || 'TBA'}</small></td>
                    <td><button class="btn btn-sm btn-outline-danger border-0" onclick="toggleEnrollment(${e.section_id}, true)"><i class="bi bi-trash"></i></button></td>
                </tr>`;
            });
            html += '</tbody></table></div>';
            container.innerHTML = html;
        } else { container.innerHTML = '<div class="text-center text-muted p-4 small">No current enrollments for this student.</div>'; }
    });
}

function unenrollAll() {
    if (!confirm('Unregister this student from all class sections?')) return;
    const formData = new FormData();
    formData.append('action', 'unenroll_all');
    formData.append('student_id', selectedStudentId);
    fetch('process/student_assignment_api.php', { method: 'POST', body: formData }).then(response => response.json()).then(data => {
        if (data.success) {
            showAlert('success', 'Student unenrolled from all sections');
            loadAvailableSections();
            loadCurrentEnrollments();
            updateStudentBadge();
        }
    });
}

function updateStudentBadge() {
    fetch(`process/student_assignment_api.php?action=get_student_enrollments&student_id=${selectedStudentId}`).then(response => response.json()).then(data => {
        const count = data.enrollments ? data.enrollments.length : 0;
        const card = document.querySelector(`.student-card-modern[data-student-id="${selectedStudentId}"]`);
        if (card) {
            card.dataset.enrolled = count;
            const badge = card.querySelector('.badge');
            badge.textContent = `${count} Enrollments`;
            badge.className = `badge ${count > 0 ? 'bg-success' : 'bg-warning text-dark'} mt-1`;
        }
    });
}

// Bulk Logic
let bulkStudentsData = [];

document.getElementById('bulkEnrollModal').addEventListener('show.bs.modal', function() { 
    resetBulkModal();
});

function resetBulkModal() {
    document.getElementById('bulkProgramType').value = '';
    document.getElementById('bulkProgram').innerHTML = '<option value="">-- Select Program --</option>';
    document.getElementById('bulkProgram').disabled = true;
    document.getElementById('bulkYearLevel').innerHTML = '<option value="">-- Select Level --</option>';
    document.getElementById('bulkYearLevel').disabled = true;
    document.getElementById('bulkSectionSelect').innerHTML = '<option value="">-- Complete Step 1 first --</option>';
    document.getElementById('bulkSectionSelect').disabled = true;
    document.getElementById('bulkSectionInfo').textContent = 'Select filters first';
    document.getElementById('bulkStudentsList').innerHTML = '<div class="text-center py-4 text-muted small">Complete filters above to see eligible students.</div>';
    document.getElementById('bulkStudentCount').textContent = '';
    document.getElementById('bulkStudentSearch').value = '';
    bulkStudentsData = [];
}

function updateBulkProgramFilter() {
    const type = document.getElementById('bulkProgramType').value;
    const programSelect = document.getElementById('bulkProgram');
    programSelect.innerHTML = '<option value="">-- Select Program --</option>';
    
    if (type === 'college') {
        programsData.forEach(p => { 
            programSelect.innerHTML += `<option value="${p.id}">${p.program_code} - ${p.program_name}</option>`; 
        });
        programSelect.disabled = false;
    } else if (type === 'shs') {
        strandsData.forEach(s => { 
            programSelect.innerHTML += `<option value="${s.id}">${s.strand_code} - ${s.strand_name}</option>`; 
        });
        programSelect.disabled = false;
    } else {
        programSelect.disabled = true;
    }
    
    document.getElementById('bulkYearLevel').innerHTML = '<option value="">-- Select Level --</option>';
    document.getElementById('bulkYearLevel').disabled = true;
    document.getElementById('bulkSectionSelect').innerHTML = '<option value="">-- Complete Step 1 first --</option>';
    document.getElementById('bulkSectionSelect').disabled = true;
    document.getElementById('bulkStudentsList').innerHTML = '<div class="text-center py-4 text-muted small">Complete filters above to see eligible students.</div>';
    document.getElementById('bulkStudentCount').textContent = '';
}

function updateBulkYearLevelFilter() {
    const type = document.getElementById('bulkProgramType').value;
    const programId = document.getElementById('bulkProgram').value;
    const yearLevelSelect = document.getElementById('bulkYearLevel');
    yearLevelSelect.innerHTML = '<option value="">-- Select Level --</option>';
    
    if (programId) {
        const levels = type === 'college' ? (programYearLevels[programId] || []) : (strandGradeLevels[programId] || []);
        levels.forEach(l => {
            const name = type === 'college' ? l.year_name : l.grade_name;
            yearLevelSelect.innerHTML += `<option value="${l.id}">${name}</option>`;
        });
        yearLevelSelect.disabled = false;
    } else {
        yearLevelSelect.disabled = true;
    }
    
    document.getElementById('bulkSectionSelect').innerHTML = '<option value="">-- Complete Step 1 first --</option>';
    document.getElementById('bulkSectionSelect').disabled = true;
    document.getElementById('bulkStudentsList').innerHTML = '<div class="text-center py-4 text-muted small">Complete filters above to see eligible students.</div>';
    document.getElementById('bulkStudentCount').textContent = '';
}

function loadFilteredStudentsAndSections() {
    const type = document.getElementById('bulkProgramType').value;
    const programId = document.getElementById('bulkProgram').value;
    const yearLevelId = document.getElementById('bulkYearLevel').value;
    
    if (!type || !programId || !yearLevelId) {
        document.getElementById('bulkSectionSelect').innerHTML = '<option value="">-- Complete Step 1 first --</option>';
        document.getElementById('bulkSectionSelect').disabled = true;
        return;
    }
    
    // Load sections for the selected program/year level
    const sectionSelect = document.getElementById('bulkSectionSelect');
    sectionSelect.innerHTML = '<option value="">Loading sections...</option>';
    
    fetch(`process/student_assignment_api.php?action=get_sections_by_program&program_type=${type}&program_id=${programId}&year_level_id=${yearLevelId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.sections.length > 0) {
                sectionSelect.innerHTML = '<option value="">-- Select Section --</option>';
                data.sections.forEach(s => {
                    sectionSelect.innerHTML += `<option value="${s.id}" data-capacity="${s.max_capacity}" data-enrolled="${s.current_enrolled}">${s.subject_code} - ${s.section_name} (${s.current_enrolled}/${s.max_capacity})</option>`;
                });
                sectionSelect.disabled = false;
            } else {
                sectionSelect.innerHTML = '<option value="">No sections available</option>';
                sectionSelect.disabled = true;
            }
        });
    
    // Load students for the selected program/year level
    loadFilteredStudents(type, programId, yearLevelId);
}

function loadFilteredStudents(type, programId, yearLevelId) {
    const container = document.getElementById('bulkStudentsList');
    container.innerHTML = '<div class="text-center py-4 small text-muted"><i class="bi bi-arrow-repeat spin"></i> Loading students...</div>';
    
    fetch(`process/student_assignment_api.php?action=get_students_by_program&program_type=${type}&program_id=${programId}&year_level_id=${yearLevelId}`)
        .then(response => response.json())
        .then(data => {
            bulkStudentsData = data.students || [];
            renderBulkStudentsList();
        });
}

function renderBulkStudentsList() {
    const container = document.getElementById('bulkStudentsList');
    const search = document.getElementById('bulkStudentSearch').value.toLowerCase();
    
    let filteredStudents = bulkStudentsData.filter(s => {
        if (search) {
            const searchStr = `${s.first_name} ${s.last_name} ${s.student_no}`.toLowerCase();
            return searchStr.includes(search);
        }
        return true;
    });
    
    if (filteredStudents.length === 0) {
        container.innerHTML = '<div class="text-center py-4 text-muted small">No students found matching the criteria.</div>';
        document.getElementById('bulkStudentCount').textContent = '';
        return;
    }
    
    let html = '';
    filteredStudents.forEach(s => {
        html += `<div class="form-check mb-2 bg-white p-2 px-3 rounded-2 shadow-sm border-start border-3 border-info bulk-student-item" data-name="${s.first_name} ${s.last_name}" data-no="${s.student_no}">
            <input type="checkbox" class="form-check-input bulk-student-cb" value="${s.id}" id="bulk_s_${s.id}">
            <label class="form-check-label small fw-bold" for="bulk_s_${s.id}">
                ${s.first_name} ${s.last_name} 
                <small class="text-muted ms-2">${s.student_no || 'NO-ID'}</small>
                <span class="badge bg-${s.program_code ? 'primary' : 'secondary'} bg-opacity-10 text-${s.program_code ? 'primary' : 'secondary'} ms-2" style="font-size: 0.6rem;">${s.program_code || 'N/A'}</span>
            </label>
        </div>`;
    });
    container.innerHTML = html;
    document.getElementById('bulkStudentCount').textContent = `${filteredStudents.length} student(s) found`;
}

function filterBulkStudentsList() {
    renderBulkStudentsList();
}

function updateBulkSectionInfo() {
    const sectionId = document.getElementById('bulkSectionSelect').value;
    const infoDiv = document.getElementById('bulkSectionInfo');
    
    if (!sectionId) {
        infoDiv.textContent = 'Select a section';
        return;
    }
    
    const option = document.querySelector(`#bulkSectionSelect option[value="${sectionId}"]`);
    const capacity = option.dataset.capacity;
    const enrolled = option.dataset.enrolled;
    const available = capacity - enrolled;
    infoDiv.innerHTML = `<span class="badge bg-primary me-2">${enrolled}/${capacity} Filled</span><span class="badge bg-success">${available} Open Slots</span>`;
}

function loadBulkSections() {
    // This is now handled by loadFilteredStudentsAndSections
}

function loadUnenrolledStudents() {
    // This is now handled by loadFilteredStudents
}

function selectAllBulkStudents() { document.querySelectorAll('.bulk-student-cb').forEach(cb => cb.checked = true); }
function clearBulkStudents() { document.querySelectorAll('.bulk-student-cb').forEach(cb => cb.checked = false); }

function processBulkEnroll() {
    const sectionId = document.getElementById('bulkSectionSelect').value;
    const studentIds = Array.from(document.querySelectorAll('.bulk-student-cb:checked')).map(cb => cb.value);
    if (!sectionId || studentIds.length === 0) { showAlert('warning', 'Selection incomplete.'); return; }
    const formData = new FormData();
    formData.append('action', 'bulk_enroll');
    formData.append('section_id', sectionId);
    formData.append('student_ids', JSON.stringify(studentIds));
    fetch('process/student_assignment_api.php', { method: 'POST', body: formData }).then(response => response.json()).then(data => {
        if (data.success) {
            showAlert('success', data.message);
            bootstrap.Modal.getInstance(document.getElementById('bulkEnrollModal')).hide();
            setTimeout(() => location.reload(), 1200);
        } else showAlert('danger', data.message);
    });
}

function showAlert(type, message) {
    const container = document.getElementById('alertContainer');
    container.innerHTML = `<div class="alert alert-${type} alert-dismissible fade show border-0 shadow-sm" role="alert"><i class="bi bi-info-circle me-2"></i>${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
    setTimeout(() => { if(container.querySelector('.alert')) container.querySelector('.alert').remove(); }, 5000);
}
</script>

<?php include '../../includes/footer.php'; ?>
