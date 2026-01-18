<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_BRANCH_ADMIN) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Bulk Assign Students to Sections";
$branch_id = get_user_branch_id();
if ($branch_id === null) {
    echo "Error: Your account is not assigned to any branch. Please contact the School Administrator.";
    exit();
}
require_branch_assignment();

// Get current academic year
$current_ay = $conn->query("SELECT * FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$current_ay_id = $current_ay['id'] ?? 0;

include '../../includes/header.php';
?>

<style>
    .section-card {
        background: white;
        border-radius: 12px;
        border: 2px solid #e9ecef;
        transition: all 0.3s ease;
        cursor: pointer;
        margin-bottom: 10px;
        padding: 15px;
    }
    .section-card:hover {
        border-color: #003366;
        box-shadow: 0 4px 12px rgba(0, 51, 102, 0.1);
    }
    .section-card.selected {
        border-color: #003366;
        background: linear-gradient(135deg, #f0f4f8, #ffffff);
    }
    
    .program-badge {
        background: linear-gradient(135deg, #003366, #004080);
        color: white;
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
    }
    
    .capacity-info {
        display: flex;
        gap: 10px;
        font-size: 0.85rem;
        margin-top: 10px;
    }
    
    .capacity-bar {
        height: 8px;
        background: #e9ecef;
        border-radius: 4px;
        overflow: hidden;
        margin-top: 10px;
    }
    .capacity-bar .fill {
        height: 100%;
        transition: width 0.3s ease;
    }
    .capacity-bar .fill.low { background: #28a745; }
    .capacity-bar .fill.medium { background: #ffc107; }
    .capacity-bar .fill.high { background: #dc3545; }
</style>

<div class="wrapper">
    <?php include '../../includes/sidebar.php'; ?>

    <div id="content">
        <div class="navbar-custom d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-0" style="color: #003366;">
                    <i class="bi bi-people-check"></i> Bulk Assign Students to Sections
                </h4>
                <small class="text-muted">A.Y. <?php echo htmlspecialchars($current_ay['year_name'] ?? 'N/A'); ?></small>
            </div>
            <a href="student_assignment.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Back to Individual Assignment
            </a>
        </div>

        <div id="alertContainer"></div>

        <div class="row">
            <!-- Left: Section Selection -->
            <div class="col-lg-5 mb-4">
                <div class="card shadow-sm">
                    <div class="card-header" style="background-color: #003366; color: white;">
                        <h6 class="mb-0"><i class="bi bi-grid-3x3-gap"></i> Select Section</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <input type="text" class="form-control" id="searchSection" placeholder="Search sections...">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Filter by Program</label>
                            <select class="form-select" id="filterProgram" onchange="filterSections()">
                                <option value="">All Programs</option>
                                <?php 
                                $programs = $conn->query("
                                    SELECT DISTINCT COALESCE(p.program_code, ss.strand_code) as code,
                                                    COALESCE(p.program_name, ss.strand_name) as name,
                                                    COALESCE(p.id, ss.id) as id,
                                                    COALESCE(p.program_code, ss.strand_code) as program_type
                                    FROM sections s
                                    LEFT JOIN programs p ON s.program_id = p.id
                                    LEFT JOIN shs_strands ss ON s.shs_strand_id = ss.id
                                    WHERE s.branch_id = $branch_id AND s.academic_year_id = $current_ay_id AND s.is_active = 1
                                    ORDER BY code
                                ");
                                while ($prog = $programs->fetch_assoc()): 
                                ?>
                                <option value="<?php echo htmlspecialchars($prog['code']); ?>">
                                    <?php echo htmlspecialchars($prog['code'] . ' - ' . $prog['name']); ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="sections-list" style="max-height: 600px; overflow-y: auto;">
                            <?php 
                            $sections = $conn->query("
                                SELECT 
                                    s.id,
                                    s.section_name,
                                    s.max_capacity,
                                    s.room,
                                    s.semester,
                                    COALESCE(p.program_code, ss.strand_code) as program_code,
                                    COALESCE(p.program_name, ss.strand_name) as program_name,
                                    COALESCE(pyl.year_name, sgl.grade_name) as year_level,
                                    COALESCE(cs.subject_code, '') as subject_code,
                                    COALESCE(cs.subject_title, '') as subject_title,
                                    (SELECT COUNT(*) FROM section_students WHERE section_id = s.id AND status = 'active') as current_enrolled
                                FROM sections s
                                LEFT JOIN programs p ON s.program_id = p.id
                                LEFT JOIN shs_strands ss ON s.shs_strand_id = ss.id
                                LEFT JOIN program_year_levels pyl ON s.year_level_id = pyl.id
                                LEFT JOIN shs_grade_levels sgl ON s.shs_grade_level_id = sgl.id
                                LEFT JOIN classes cl ON s.id = cl.section_id
                                LEFT JOIN curriculum_subjects cs ON cl.curriculum_subject_id = cs.id
                                WHERE s.branch_id = $branch_id AND s.academic_year_id = $current_ay_id AND s.is_active = 1
                                ORDER BY COALESCE(p.program_code, ss.strand_code), s.section_name
                            ");
                            
                            while ($section = $sections->fetch_assoc()): 
                                $current = $section['current_enrolled'];
                                $max = $section['max_capacity'];
                                $available = $max - $current;
                                $percent = $max > 0 ? ($current / $max) * 100 : 0;
                                $capacity_class = $percent >= 90 ? 'high' : ($percent >= 70 ? 'medium' : 'low');
                            ?>
                            <div class="section-card" 
                                 data-section-id="<?php echo $section['id']; ?>"
                                 data-program="<?php echo htmlspecialchars($section['program_code']); ?>"
                                 data-section-name="<?php echo htmlspecialchars($section['section_name'] . ' - ' . ($section['subject_code'] ?? 'N/A')); ?>"
                                 onclick="selectSection(<?php echo $section['id']; ?>, '<?php echo htmlspecialchars($section['section_name']); ?>', <?php echo $section['max_capacity']; ?>, <?php echo $current; ?>)">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <strong><?php echo htmlspecialchars($section['section_name']); ?></strong>
                                        <br>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($section['subject_code'] . ' - ' . $section['subject_title']); ?>
                                        </small>
                                        <div class="mt-2">
                                            <span class="program-badge"><?php echo htmlspecialchars($section['program_code']); ?></span>
                                            <span class="badge bg-info ms-1"><?php echo htmlspecialchars($section['year_level']); ?></span>
                                        </div>
                                        <div class="capacity-info">
                                            <span><i class="bi bi-people"></i> <?php echo $current; ?>/<?php echo $max; ?></span>
                                            <span class="badge bg-<?php echo $available > 0 ? 'success' : 'danger'; ?>">
                                                <?php echo $available > 0 ? $available . ' slots' : 'Full'; ?>
                                            </span>
                                        </div>
                                        <div class="capacity-bar">
                                            <div class="fill <?php echo $capacity_class; ?>" style="width: <?php echo $percent; ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Student Assignment -->
            <div class="col-lg-7">
                <div id="assignmentPanel" style="display: none;">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">
                                <i class="bi bi-grid-1x2"></i> Assign Students to: <strong id="selectedSectionText"></strong>
                            </h6>
                            <button class="btn btn-sm btn-outline-secondary" onclick="clearSelection()">Change Section</button>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Filter Students</label>
                                    <select class="form-select" id="filterStudents" onchange="loadUnenrolledStudents()">
                                        <option value="">All Students Without Section</option>
                                        <option value="no_program">No Program Yet</option>
                                        <option value="with_program">With Program Enrollment</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Capacity Info</label>
                                    <div class="form-control bg-light" id="sectionCapacityInfo">
                                        Select a section
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex gap-2 mb-3">
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAllStudents()">
                                    <i class="bi bi-check-all"></i> Select All
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearAllStudents()">
                                    <i class="bi bi-dash-circle"></i> Clear All
                                </button>
                                <span class="ms-auto badge bg-info" id="selectedCountBadge">0 selected</span>
                            </div>

                            <div id="studentsList" style="max-height: 400px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 8px; padding: 10px;">
                                <div class="text-center text-muted p-4">Select a section first</div>
                            </div>
                        </div>
                    </div>

                    <div class="card shadow-sm mt-4">
                        <div class="card-footer">
                            <button type="button" class="btn btn-success btn-lg w-100" onclick="processBulkAssign()">
                                <i class="bi bi-check-circle"></i> Assign Selected Students to Section
                            </button>
                        </div>
                    </div>
                </div>

                <!-- No Section Selected -->
                <div id="noSectionSelected">
                    <div class="card shadow-sm">
                        <div class="card-body text-center p-5">
                            <i class="bi bi-arrow-left-circle display-1 text-muted opacity-25"></i>
                            <h4 class="mt-4 text-muted">Select a Section</h4>
                            <p class="text-muted">Choose a section from the left panel to assign students to it.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let selectedSectionId = null;
let selectedSectionName = '';
let selectedSectionCapacity = 0;
let selectedSectionEnrolled = 0;

function selectSection(sectionId, sectionName, maxCapacity, currentEnrolled) {
    selectedSectionId = sectionId;
    selectedSectionName = sectionName;
    selectedSectionCapacity = maxCapacity;
    selectedSectionEnrolled = currentEnrolled;
    
    // Highlight selected section
    document.querySelectorAll('.section-card').forEach(c => c.classList.remove('selected'));
    event.currentTarget.classList.add('selected');
    
    // Show assignment panel
    document.getElementById('assignmentPanel').style.display = 'block';
    document.getElementById('noSectionSelected').style.display = 'none';
    
    document.getElementById('selectedSectionText').textContent = sectionName;
    const available = maxCapacity - currentEnrolled;
    document.getElementById('sectionCapacityInfo').innerHTML = `
        <div>
            <span class="badge bg-info me-2">${currentEnrolled}/${maxCapacity} enrolled</span>
            <span class="badge bg-success">${available} slots available</span>
        </div>
    `;
    
    loadUnenrolledStudents();
}

function clearSelection() {
    selectedSectionId = null;
    document.querySelectorAll('.section-card').forEach(c => c.classList.remove('selected'));
    document.getElementById('assignmentPanel').style.display = 'none';
    document.getElementById('noSectionSelected').style.display = 'block';
}

function filterSections() {
    const filter = document.getElementById('filterProgram').value;
    document.querySelectorAll('.section-card').forEach(card => {
        if (!filter || card.dataset.program === filter) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}

document.getElementById('searchSection').addEventListener('input', function(e) {
    const search = e.target.value.toLowerCase();
    document.querySelectorAll('.section-card').forEach(card => {
        const text = (card.dataset.sectionName || '').toLowerCase();
        card.style.display = text.includes(search) ? 'block' : 'none';
    });
});

function loadUnenrolledStudents() {
    if (!selectedSectionId) return;
    
    const filter = document.getElementById('filterStudents').value;
    const container = document.getElementById('studentsList');
    const available = selectedSectionCapacity - selectedSectionEnrolled;
    
    container.innerHTML = '<div class="text-center p-4"><i class="bi bi-arrow-repeat spin"></i> Loading...</div>';
    
    const params = new URLSearchParams({
        action: 'get_bulk_unenrolled_students',
        section_id: selectedSectionId,
        filter: filter
    });
    
    fetch('process/student_assignment_api.php?' + params)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.students.length > 0) {
                if (data.students.length > available) {
                    container.innerHTML = `<div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> Only ${available} slots available, but ${data.students.length} students found</div>`;
                }
                
                let html = '';
                data.students.forEach(s => {
                    html += `
                        <div class="form-check mb-2">
                            <input type="checkbox" class="form-check-input student-bulk-cb" value="${s.id}" id="bulk_${s.id}">
                            <label class="form-check-label" for="bulk_${s.id}">
                                ${s.first_name} ${s.last_name}
                                <small class="text-muted">(${s.student_no || 'No ID'})</small>
                                ${s.program_code ? `<span class="badge bg-success ms-1">${s.program_code}</span>` : '<span class="badge bg-warning text-dark ms-1">No Program</span>'}
                            </label>
                        </div>
                    `;
                });
                container.innerHTML = html;
                
                // Update count on checkbox change
                document.querySelectorAll('.student-bulk-cb').forEach(cb => {
                    cb.addEventListener('change', updateSelectedCount);
                });
                
                updateSelectedCount();
            } else {
                container.innerHTML = '<div class="text-center text-muted p-4">No unassigned students found</div>';
            }
        });
}

function updateSelectedCount() {
    const count = document.querySelectorAll('.student-bulk-cb:checked').length;
    document.getElementById('selectedCountBadge').textContent = count + ' selected';
}

function selectAllStudents() {
    const available = selectedSectionCapacity - selectedSectionEnrolled;
    const checkboxes = Array.from(document.querySelectorAll('.student-bulk-cb'));
    
    checkboxes.forEach((cb, index) => {
        cb.checked = index < available;
    });
    
    updateSelectedCount();
}

function clearAllStudents() {
    document.querySelectorAll('.student-bulk-cb').forEach(cb => cb.checked = false);
    updateSelectedCount();
}

function processBulkAssign() {
    const studentIds = Array.from(document.querySelectorAll('.student-bulk-cb:checked')).map(cb => cb.value);
    
    if (!selectedSectionId) {
        showAlert('warning', 'Please select a section');
        return;
    }
    
    if (studentIds.length === 0) {
        showAlert('warning', 'Please select at least one student');
        return;
    }
    
    const available = selectedSectionCapacity - selectedSectionEnrolled;
    if (studentIds.length > available) {
        showAlert('danger', `Only ${available} slots available but ${studentIds.length} selected`);
        return;
    }
    
    if (!confirm(`Assign ${studentIds.length} student(s) to ${selectedSectionName}?`)) return;
    
    const formData = new FormData();
    formData.append('action', 'bulk_assign_to_section');
    formData.append('section_id', selectedSectionId);
    formData.append('student_ids', JSON.stringify(studentIds));
    
    fetch('process/student_assignment_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('success', data.message);
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert('danger', data.message || 'Error assigning students');
        }
    })
    .catch(error => {
        showAlert('danger', 'Error assigning students');
    });
}

function showAlert(type, message) {
    const container = document.getElementById('alertContainer');
    container.innerHTML = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            <strong>${type === 'success' ? '<i class="bi bi-check-circle"></i>' : '<i class="bi bi-exclamation-circle"></i>'}</strong> ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
</script>

<?php include '../../includes/footer.php'; ?>
