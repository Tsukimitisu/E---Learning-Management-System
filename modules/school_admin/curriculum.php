<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SCHOOL_ADMIN) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Curriculum Management";

// ==========================================
// BACKEND LOGIC - UNTOUCHED
// ==========================================

// Fetch data for stats
$track_count = $conn->query("SELECT COUNT(*) as count FROM shs_tracks WHERE is_active = 1")->fetch_assoc()['count'];
$program_count = $conn->query("SELECT COUNT(*) as count FROM programs WHERE is_active = 1")->fetch_assoc()['count'];
$shs_subject_count = $conn->query("SELECT COUNT(*) as count FROM curriculum_subjects WHERE subject_type IN ('shs_core', 'shs_applied', 'shs_specialized') AND is_active = 1")->fetch_assoc()['count'];
$college_subject_count = $conn->query("SELECT COUNT(*) as count FROM curriculum_subjects WHERE subject_type = 'college' AND is_active = 1")->fetch_assoc()['count'];

// Fetch actual data arrays for display and JavaScript
$tracks = [];
$tracks_result = $conn->query("SELECT id, track_name AS name, track_code, description, is_active FROM shs_tracks ORDER BY track_name");
if ($tracks_result) { while ($row = $tracks_result->fetch_assoc()) { $tracks[] = $row; } }

$strands = [];
$strands_result = $conn->query("SELECT st.id, st.strand_name AS name, st.strand_code, st.description, st.is_active, st.track_id, t.track_name AS track_name FROM shs_strands st LEFT JOIN shs_tracks t ON st.track_id = t.id ORDER BY st.strand_name");
if ($strands_result) { while ($row = $strands_result->fetch_assoc()) { $strands[] = $row; } }

$grade_levels = [];
$grade_levels_result = $conn->query("SELECT id, grade_name AS name, semesters_count AS semesters, is_active FROM shs_grade_levels ORDER BY grade_name");
if ($grade_levels_result) { while ($row = $grade_levels_result->fetch_assoc()) { $grade_levels[] = $row; } }

$college_programs = [];
$college_programs_result = $conn->query("SELECT id, program_code AS code, program_name AS name, degree_level, school_id, is_active FROM programs ORDER BY program_code");
if ($college_programs_result) { while ($row = $college_programs_result->fetch_assoc()) { $college_programs[] = $row; } }

$college_year_levels = [];
$college_year_levels_result = $conn->query("SELECT id, program_id, year_level as year_number, year_name as name, semesters_count as semesters, is_active FROM program_year_levels ORDER BY program_id, year_level");
if ($college_year_levels_result) { while ($row = $college_year_levels_result->fetch_assoc()) { $college_year_levels[] = $row; } }

include '../../includes/header.php';
?>

<link rel=stylesheet href="css/curriculum.css">

<div class="animate__animated animate__fadeIn">

    <!-- Header Summary -->
    <div class="welcome-card p-4 animate__animated animate__fadeInDown">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div class="d-flex align-items-center">
                <a href="javascript:void(0)" onclick="goBack()" class="btn btn-light rounded-circle shadow-sm me-3 border" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                    <i class="bi bi-arrow-left text-maroon"></i>
                </a>
                <div>
                    <h4 class="fw-bold mb-0 text-blue">Curriculum Management</h4>
                    <p class="text-muted small mb-0">Manage SHS Tracks, College Programs, and Subject Catalogs</p>
                </div>
            </div>
            <div id="alertContainer"></div>
        </div>
    </div>

    <!-- Main Navigation Cards (SHS vs College) -->
    <div class="row g-4 mb-4">
        <div class="col-md-6 animate__animated animate__fadeInLeft delay-1">
            <a href="shs_curriculum.php" class="nav-action-card">
                <div class="icon-wrapper"><i class="bi bi-mortarboard"></i></div>
                <h5>Senior High School (SHS)</h5>
                <p class="text-muted small mb-0">Manage Tracks, Strands, Grade Levels (11-12), and Subject assignments.</p>
                <div class="btn-indicator">Manage SHS <i class="bi bi-arrow-right ms-2"></i></div>
            </a>
        </div>
        <div class="col-md-6 animate__animated animate__fadeInRight delay-1">
            <a href="college_curriculum.php" class="nav-action-card">
                <div class="icon-wrapper text-primary"><i class="bi bi-building"></i></div>
                <h5 class="text-primary">College Department</h5>
                <p class="text-muted small mb-0">Manage Degree Programs, Prospectus, Year Levels, and Course assignments.</p>
                <div class="btn-indicator text-primary">Manage College <i class="bi bi-arrow-right ms-2"></i></div>
            </a>
        </div>
    </div>

    <!-- Stats Row (Gradient Cards) -->
    <div class="row g-4 mb-4">
        <div class="col-md-3 animate__animated animate__zoomIn delay-1">
            <div class="admin-stat-card shadow-sm" style="background: linear-gradient(135deg, var(--blue) 0%, #001a33 100%);">
                <div class="stat-icon-bg"><i class="bi bi-diagram-3"></i></div>
                <div>
                    <h3 class="fw-bold mb-0"><?php echo number_format($track_count); ?></h3>
                    <small class="text-uppercase fw-bold opacity-75" style="font-size:0.6rem;">SHS Tracks</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 animate__animated animate__zoomIn delay-2">
            <div class="admin-stat-card shadow-sm" style="background: linear-gradient(135deg, var(--maroon) 0%, #4a0000 100%);">
                <div class="stat-icon-bg"><i class="bi bi-bank"></i></div>
                <div>
                    <h3 class="fw-bold mb-0"><?php echo number_format($program_count); ?></h3>
                    <small class="text-uppercase fw-bold opacity-75" style="font-size:0.6rem;">College Programs</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 animate__animated animate__zoomIn delay-3">
            <div class="admin-stat-card shadow-sm" style="background: linear-gradient(135deg, #17a2b8 0%, #0b5e6b 100%);">
                <div class="stat-icon-bg"><i class="bi bi-journal-bookmark"></i></div>
                <div>
                    <h3 class="fw-bold mb-0"><?php echo number_format($shs_subject_count); ?></h3>
                    <small class="text-uppercase fw-bold opacity-75" style="font-size:0.6rem;">SHS Subjects</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 animate__animated animate__zoomIn delay-4">
            <div class="admin-stat-card shadow-sm" style="background: linear-gradient(135deg, #ffc107 0%, #d39e00 100%); color: #333;">
                <div class="stat-icon-bg" style="background: rgba(0,0,0,0.1);"><i class="bi bi-journal-text"></i></div>
                <div>
                    <h3 class="fw-bold mb-0"><?php echo number_format($college_subject_count); ?></h3>
                    <small class="text-uppercase fw-bold opacity-75" style="font-size:0.6rem;">College Courses</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="row animate__animated animate__fadeInUp delay-3">
        <div class="col-12">
            <div class="main-card-modern">
                <div class="card-header-modern"><i class="bi bi-activity me-2"></i>Recent Curriculum Changes</div>
                <div class="table-responsive">
                    <table class="table table-hover table-modern mb-0">
                        <tbody>
                            <?php
                            $recent_activity = $conn->query("
                                SELECT al.action,
                                       al.timestamp AS created_at,
                                       COALESCE(up.first_name, '') AS first_name,
                                       COALESCE(up.last_name, '') AS last_name,
                                       u.email
                                FROM audit_logs al
                                LEFT JOIN users u ON al.user_id = u.id
                                LEFT JOIN user_profiles up ON up.user_id = u.id
                                WHERE al.action LIKE '%curriculum%' OR al.action LIKE '%subject%' OR al.action LIKE '%program%' OR al.action LIKE '%track%'
                                ORDER BY al.timestamp DESC
                                LIMIT 5
                            ");

                            if ($recent_activity->num_rows > 0) {
                                while ($activity = $recent_activity->fetch_assoc()) {
                                    ?>
                                    <tr>
                                        <td width="5%">
                                            <div class="rounded-circle bg-light text-primary d-flex align-items-center justify-content-center fw-bold border" style="width:35px; height:35px;">
                                                <i class="bi bi-pencil-square"></i>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-dark small"><?php echo htmlspecialchars($activity['action']); ?></div>
                                            <div class="text-muted small" style="font-size: 0.75rem;">
                                                by <?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?>
                                            </div>
                                        </td>
                                        <td class="text-end text-muted small">
                                            <i class="bi bi-clock me-1"></i><?php echo date('M d, h:i A', strtotime($activity['created_at'])); ?>
                                        </td>
                                    </tr>
                                    <?php
                                }
                            } else {
                                echo '<tr><td colspan="3" class="text-center text-muted py-4">No recent curriculum activity found.</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Include Modals & Scripts -->
<?php include 'curriculum_modals.php'; ?>
<?php include '../../includes/footer.php'; ?>

<!-- Custom Scripts -->
<script>
// Track data for JavaScript
const tracksData = <?php echo json_encode($tracks); ?>;
const strandsData = <?php echo json_encode($strands); ?>;

// --- UTILITY FUNCTIONS ---
function goBack() {
    if (document.referrer && document.referrer.includes('/elms_system/')) {
        window.history.back();
    } else {
        window.location.href = 'index.php';
    }
}

function showAlert(message, type) {
    if (type === 'success') {
        if (typeof window.elmsEmitRealtime === 'function') {
            window.elmsEmitRealtime('school_admin', {
                type: 'curriculum_updated',
                message: message
            });
        } else if (window.elmsSocket && window.elmsSocket.connected) {
            window.elmsSocket.emit('update_role', {
                role: 'school_admin',
                data: {
                    type: 'curriculum_updated',
                    message: message
                }
            });
        }
    }

    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show border-0 shadow-sm" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    document.getElementById('alertContainer').innerHTML = alertHtml;
    // Auto hide after 3 seconds
    setTimeout(() => {
        const alertNode = document.querySelector('.alert');
        if(alertNode) {
            const alert = bootstrap.Alert.getInstance(alertNode) || new bootstrap.Alert(alertNode);
            alert.close();
        }
    }, 3000);
}

// --- FORM SUBMISSION HANDLERS (Untouched Logic) ---

document.getElementById('addTrackForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    try {
        const response = await fetch('process/add_track.php', { method: 'POST', body: formData });
        const data = await response.json();
        if (data.status === 'success') { showAlert(data.message, 'success'); setTimeout(() => location.reload(), 1500); } 
        else { showAlert(data.message, 'danger'); }
    } catch (error) { showAlert('An error occurred', 'danger'); }
});

document.getElementById('addStrandForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    try {
        const response = await fetch('process/add_strand.php', { method: 'POST', body: formData });
        const data = await response.json();
        if (data.status === 'success') { showAlert(data.message, 'success'); setTimeout(() => location.reload(), 1500); }
        else { showAlert(data.message, 'danger'); }
    } catch (error) { showAlert('An error occurred', 'danger'); }
});

document.getElementById('addGradeForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    try {
        const response = await fetch('process/add_grade_level.php', { method: 'POST', body: formData });
        const data = await response.json();
        if (data.status === 'success') { showAlert(data.message, 'success'); setTimeout(() => location.reload(), 1500); }
        else { showAlert(data.message, 'danger'); }
    } catch (error) { showAlert('An error occurred', 'danger'); }
});

document.getElementById('addSubjectForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    try {
        const response = await fetch('process/add_shs_subject.php', { method: 'POST', body: formData });
        const data = await response.json();
        if (data.status === 'success') { showAlert(data.message, 'success'); setTimeout(() => location.reload(), 1500); }
        else { showAlert(data.message, 'danger'); }
    } catch (error) { showAlert('An error occurred', 'danger'); }
});

document.getElementById('assignSubjectForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    try {
        const response = await fetch('/elms_system/modules/school_admin/process/assign_shs_subject.php', { method: 'POST', body: formData });
        const data = await response.json();
        if (data.status === 'success') { showAlert(data.message, 'success'); setTimeout(() => location.reload(), 1500); }
        else { showAlert(data.message, 'danger'); }
    } catch (error) { showAlert('An error occurred', 'danger'); }
});

document.getElementById('addProgramForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    try {
        const response = await fetch('process/add_college_program.php', { method: 'POST', body: formData });
        const data = await response.json();
        if (data.status === 'success') { showAlert(data.message, 'success'); setTimeout(() => location.reload(), 1500); }
        else { showAlert(data.message, 'danger'); }
    } catch (error) { showAlert('An error occurred', 'danger'); }
});

document.getElementById('addCollegeCourseForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    try {
        const response = await fetch('process/add_college_course.php', { method: 'POST', body: formData });
        const data = await response.json();
        if (data.status === 'success') { showAlert(data.message, 'success'); setTimeout(() => location.reload(), 1500); }
        else { showAlert(data.message, 'danger'); }
    } catch (error) { showAlert('An error occurred', 'danger'); }
});

document.getElementById('editCollegeSubjectForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    try {
        const response = await fetch('/elms_system/modules/school_admin/process/update_subject.php', { method: 'POST', body: formData });
        const data = await response.json();
        if (data.status === 'success') { showAlert('Subject updated successfully!', 'success'); $('#editCollegeSubjectModal').modal('hide'); setTimeout(() => location.reload(), 1500); }
        else { showAlert(data.message, 'danger'); }
    } catch (error) { showAlert('An error occurred', 'danger'); }
});

document.getElementById('addCollegeYearForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    try {
        const response = await fetch('process/add_college_year_level.php', { method: 'POST', body: formData });
        const data = await response.json();
        if (data.status === 'success') { showAlert(data.message, 'success'); setTimeout(() => location.reload(), 1500); }
        else { showAlert(data.message, 'danger'); }
    } catch (error) { showAlert('An error occurred', 'danger'); }
});

document.getElementById('assignCollegeCourseForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    try {
        const response = await fetch('/elms_system/modules/school_admin/process/assign_college_course.php', { method: 'POST', body: formData });
        const data = await response.json();
        if (data.status === 'success') { showAlert(data.message, 'success'); setTimeout(() => location.reload(), 1500); }
        else { showAlert(data.message, 'danger'); }
    } catch (error) { showAlert('An error occurred', 'danger'); }
});

document.getElementById('editTrackForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    try {
        const response = await fetch('process/update_track.php', { method: 'POST', body: formData });
        const data = await response.json();
        if (data.status === 'success') { showAlert(data.message, 'success'); setTimeout(() => location.reload(), 1500); }
        else { showAlert(data.message, 'danger'); }
    } catch (error) { showAlert('An error occurred', 'danger'); }
});

document.getElementById('editStrandForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    try {
        const response = await fetch('process/update_strand.php', { method: 'POST', body: formData });
        const data = await response.json();
        if (data.status === 'success') { showAlert(data.message, 'success'); setTimeout(() => location.reload(), 1500); }
        else { showAlert(data.message, 'danger'); }
    } catch (error) { showAlert('An error occurred', 'danger'); }
});

document.getElementById('editGradeForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    try {
        const response = await fetch('process/update_grade_level.php', { method: 'POST', body: formData });
        const data = await response.json();
        if (data.status === 'success') { showAlert(data.message, 'success'); setTimeout(() => location.reload(), 1500); }
        else { showAlert(data.message, 'danger'); }
    } catch (error) { showAlert('An error occurred', 'danger'); }
});

document.getElementById('editSubjectForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    try {
        const response = await fetch('process/update_subject.php', { method: 'POST', body: formData });
        const data = await response.json();
        if (data.status === 'success') { showAlert(data.message, 'success'); setTimeout(() => location.reload(), 1500); }
        else { showAlert(data.message, 'danger'); }
    } catch (error) { showAlert('An error occurred', 'danger'); }
});

document.getElementById('editProgramForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    try {
        const response = await fetch('process/update_college_program.php', { method: 'POST', body: formData });
        const data = await response.json();
        if (data.status === 'success') { showAlert(data.message, 'success'); setTimeout(() => location.reload(), 1500); }
        else { showAlert(data.message, 'danger'); }
    } catch (error) { showAlert('An error occurred', 'danger'); }
});

document.getElementById('editCollegeCourseForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    try {
        const response = await fetch('process/update_college_course.php', { method: 'POST', body: formData });
        const data = await response.json();
        if (data.status === 'success') { showAlert(data.message, 'success'); setTimeout(() => location.reload(), 1500); }
        else { showAlert(data.message, 'danger'); }
    } catch (error) { showAlert('An error occurred', 'danger'); }
});

document.getElementById('editCollegeYearForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    try {
        const response = await fetch('process/update_college_year_level.php', { method: 'POST', body: formData });
        const data = await response.json();
        if (data.status === 'success') { showAlert(data.message, 'success'); setTimeout(() => location.reload(), 1500); }
        else { showAlert(data.message, 'danger'); }
    } catch (error) { showAlert('An error occurred', 'danger'); }
});

document.getElementById('gradingRulesForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    try {
        const response = await fetch('process/update_grading_rules.php', { method: 'POST', body: formData });
        const data = await response.json();
        if (data.status === 'success') { showAlert(data.message, 'success'); setTimeout(() => location.reload(), 1500); }
        else { showAlert(data.message, 'danger'); }
    } catch (error) { showAlert('An error occurred', 'danger'); }
});

// --- EDIT/DELETE MODAL POPULATION FUNCTIONS ---

function loadStrands(trackId) {
    const strandSelect = document.getElementById('strandSelect');
    strandSelect.innerHTML = '<option value="">-- Select Strand --</option>';
    if (trackId) {
        const trackStrands = strandsData.filter(strand => strand.track_id == trackId);
        trackStrands.forEach(strand => {
            const option = document.createElement('option');
            option.value = strand.id;
            option.textContent = strand.name;
            strandSelect.appendChild(option);
        });
    }
}

function editTrack(id) {
    const track = tracksData.find(t => t.id == id);
    if (track) {
        document.getElementById('editTrackId').value = track.id;
        document.getElementById('editTrackName').value = track.name;
        document.getElementById('editTrackDescription').value = track.description;
        document.getElementById('editTrackStatus').value = track.is_active ? '1' : '0';
        new bootstrap.Modal(document.getElementById('editTrackModal')).show();
    }
}

function deleteTrack(id) {
    if (confirm('Are you sure you want to delete this track? This will also delete all associated strands and may affect subject assignments.')) {
        fetch('process/delete_track.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ track_id: id })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') { showAlert(data.message, 'success'); setTimeout(() => location.reload(), 1500); }
            else { showAlert(data.message, 'danger'); }
        }).catch(error => showAlert('An error occurred', 'danger'));
    }
}

function editStrand(id) {
    const strand = strandsData.find(s => s.id == id);
    if (strand) {
        document.getElementById('editStrandId').value = strand.id;
        document.getElementById('editStrandName').value = strand.name;
        document.getElementById('editStrandTrack').value = strand.track_id;
        document.getElementById('editStrandDescription').value = strand.description;
        document.getElementById('editStrandStatus').value = strand.is_active ? '1' : '0';
        new bootstrap.Modal(document.getElementById('editStrandModal')).show();
    }
}

function deleteStrand(id) {
    if (confirm('Are you sure you want to delete this strand? This may affect subject assignments.')) {
        fetch('process/delete_strand.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ strand_id: id })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') { showAlert(data.message, 'success'); setTimeout(() => location.reload(), 1500); }
            else { showAlert(data.message, 'danger'); }
        }).catch(error => showAlert('An error occurred', 'danger'));
    }
}

function editGrade(id) {
    const gradeLevels = <?php echo json_encode($grade_levels); ?>;
    const grade = gradeLevels.find(g => g.id == id);
    if (grade) {
        document.getElementById('editGradeId').value = grade.id;
        document.getElementById('editGradeName').value = grade.name;
        document.getElementById('editGradeSemesters').value = grade.semesters;
        document.getElementById('editGradeStatus').value = grade.is_active ? '1' : '0';
        new bootstrap.Modal(document.getElementById('editGradeModal')).show();
    }
}

function deleteGrade(id) {
    if (confirm('Are you sure you want to delete this grade level? This may affect subject assignments.')) {
        fetch('process/delete_grade_level.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ grade_id: id })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') { showAlert(data.message, 'success'); setTimeout(() => location.reload(), 1500); }
            else { showAlert(data.message, 'danger'); }
        }).catch(error => showAlert('An error occurred', 'danger'));
    }
}

function editSubject(id) {
    fetch(`process/get_subject.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                const subject = data.subject;
                document.getElementById('editSubjectId').value = subject.id;
                document.getElementById('editSubjectCode').value = subject.subject_code;
                document.getElementById('editSubjectTitle').value = subject.subject_title;
                document.getElementById('editSubjectCategory').value = subject.category || 'core';
                document.getElementById('editSubjectUnits').value = subject.units;
                document.getElementById('editSubjectHours').value = subject.hours || 3;
                document.getElementById('editSubjectPrerequisites').value = subject.prerequisites || '';
                document.getElementById('editSubjectStatus').value = subject.is_active ? '1' : '0';
                new bootstrap.Modal(document.getElementById('editSubjectModal')).show();
            } else { showAlert('Failed to load subject data', 'danger'); }
        }).catch(error => showAlert('An error occurred', 'danger'));
}

function editProgram(id) {
    const programs = <?php echo json_encode($college_programs); ?>;
    const program = programs.find(p => p.id == id);
    if (program) {
        document.getElementById('editProgramId').value = program.id;
        document.getElementById('editProgramCode').value = program.code;
        document.getElementById('editProgramName').value = program.name;
        document.getElementById('editProgramLevel').value = program.degree_level;
        document.getElementById('editProgramDuration').value = program.duration_years;
        document.getElementById('editProgramUnits').value = program.total_units || '';
        document.getElementById('editProgramDescription').value = program.description || '';
        document.getElementById('editProgramStatus').value = program.is_active ? '1' : '0';
        new bootstrap.Modal(document.getElementById('editProgramModal')).show();
    }
}

function viewProgramCurriculum(id) {
    window.location.href = `program_curriculum.php?program_id=${id}`;
}

function editCollegeCourse(code) {
    fetch(`process/get_college_course.php?code=${code}`)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                const course = data.course;
                document.getElementById('editCourseCode').value = course.code;
                document.getElementById('editCourseTitle').value = course.title;
                document.getElementById('editCourseCategory').value = course.category;
                document.getElementById('editCourseUnits').value = course.units;
                document.getElementById('editCourseHours').value = course.hours;
                document.getElementById('editCourseLectureHours').value = course.lecture_hours || '';
                document.getElementById('editCourseLabHours').value = course.lab_hours || '';
                document.getElementById('editCoursePrerequisites').value = course.prerequisites || '';
                document.getElementById('editCourseDescription').value = course.description || '';
                document.getElementById('editCourseStatus').value = course.active ? '1' : '0';
                new bootstrap.Modal(document.getElementById('editCollegeCourseModal')).show();
            } else { showAlert('Failed to load course data', 'danger'); }
        }).catch(error => showAlert('An error occurred', 'danger'));
}

function assignCollegeCourse(code) {
    document.querySelector('select[name="course_id"]').value = code;
    new bootstrap.Modal(document.getElementById('assignCollegeCourseModal')).show();
}

function editCollegeYear(id) {
    const yearLevels = <?php echo json_encode($college_year_levels); ?>;
    const year = yearLevels.find(y => y.id == id);
    if (year) {
        document.getElementById('editYearId').value = year.id;
        document.getElementById('editYearName').value = year.name;
        document.getElementById('editYearNumber').value = year.year_number;
        document.getElementById('editYearSemesters').value = year.semesters;
        document.getElementById('editYearStatus').value = year.is_active ? '1' : '0';
        new bootstrap.Modal(document.getElementById('editCollegeYearModal')).show();
    }
}

function assignSubject(id) {
    const target = document.querySelector('select[name="subject_id"]') || document.querySelector('input[name="subject_id"]');
    if (target) target.value = id;
    new bootstrap.Modal(document.getElementById('assignSubjectModal')).show();
}
</script>
</body>
</html>
