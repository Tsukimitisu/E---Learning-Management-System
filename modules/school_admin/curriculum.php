<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SCHOOL_ADMIN) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Curriculum Management";

// Fetch data for stats
$track_count = $conn->query("SELECT COUNT(*) as count FROM shs_tracks WHERE is_active = 1")->fetch_assoc()['count'];
$program_count = $conn->query("SELECT COUNT(*) as count FROM programs WHERE is_active = 1")->fetch_assoc()['count'];
$shs_subject_count = $conn->query("SELECT COUNT(*) as count FROM curriculum_subjects WHERE subject_type IN ('shs_core', 'shs_applied', 'shs_specialized') AND is_active = 1")->fetch_assoc()['count'];
$college_subject_count = $conn->query("SELECT COUNT(*) as count FROM curriculum_subjects WHERE subject_type = 'college' AND is_active = 1")->fetch_assoc()['count'];

// Fetch actual data arrays for display and JavaScript
$tracks = [];
$tracks_result = $conn->query("SELECT id, track_name AS name, track_code, description, is_active FROM shs_tracks ORDER BY track_name");
if ($tracks_result) {
    while ($row = $tracks_result->fetch_assoc()) {
        $tracks[] = $row;
    }
}

$strands = [];
$strands_result = $conn->query("
    SELECT st.id, st.strand_name AS name, st.strand_code, st.description, st.is_active, st.track_id,
           t.track_name AS track_name
    FROM shs_strands st
    LEFT JOIN shs_tracks t ON st.track_id = t.id
    ORDER BY st.strand_name
");
if ($strands_result) {
    while ($row = $strands_result->fetch_assoc()) {
        $strands[] = $row;
    }
}

$grade_levels = [];
$grade_levels_result = $conn->query("SELECT id, grade_name AS name, semesters_count AS semesters, is_active FROM shs_grade_levels ORDER BY grade_name");
if ($grade_levels_result) {
    while ($row = $grade_levels_result->fetch_assoc()) {
        $grade_levels[] = $row;
    }
}

$college_programs = [];
$college_programs_result = $conn->query("
    SELECT id, program_code AS code, program_name AS name, degree_level, school_id, is_active
    FROM programs
    ORDER BY program_code
");
if ($college_programs_result) {
    while ($row = $college_programs_result->fetch_assoc()) {
        $college_programs[] = $row;
    }
}

$college_year_levels = [];
$college_year_levels_result = $conn->query("
    SELECT id, program_id, year_level as year_number, year_name as name, semesters_count as semesters, is_active
    FROM program_year_levels
    ORDER BY program_id, year_level
");
if ($college_year_levels_result) {
    while ($row = $college_year_levels_result->fetch_assoc()) {
        $college_year_levels[] = $row;
    }
}

include '../../includes/header.php';
?>

<div class="wrapper">
    <?php include '../../includes/sidebar.php'; ?>

    <div id="content">
        <div class="navbar-custom d-flex justify-content-between align-items-center">
            <div>
                <a href="javascript:void(0)" onclick="goBack()" class="btn btn-sm btn-outline-secondary me-3">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
                <span style="display: inline-block;">
                    <h4 class="mb-0 d-inline-block" style="color: #003366;">
                        <i class="bi bi-book"></i> Curriculum Management
                    </h4>
                    <br><small class="text-muted">Select SHS or College to manage subjects</small>
                </span>
            </div>
        </div>

        <div id="alertContainer" class="mt-3"></div>

        <div class="row mt-4">
            <!-- SHS Curriculum Card -->
            <div class="col-md-6 mb-4">
                <div class="card h-100 border-primary">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-mortarboard"></i> Senior High School (SHS) Curriculum
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="card-text">Manage SHS strands, grade levels, and subject assignments.</p>
                        <ul class="list-unstyled">
                            <li><i class="bi bi-check-circle text-success"></i> Specialized Strands</li>
                            <li><i class="bi bi-check-circle text-success"></i> Grade 11 & 12 Levels</li>
                            <li><i class="bi bi-check-circle text-success"></i> Subject Assignments</li>
                        </ul>
                    </div>
                    <div class="card-footer">
                        <a href="shs_curriculum.php" class="btn btn-primary btn-sm">
                            <i class="bi bi-arrow-right"></i> Manage SHS Curriculum
                        </a>
                    </div>
                </div>
            </div>

            <!-- College Curriculum Card -->
            <div class="col-md-6 mb-4">
                <div class="card h-100 border-info">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-building"></i> College Curriculum
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="card-text">Manage college programs, year levels, subjects, and course assignments.</p>
                        <ul class="list-unstyled">
                            <li><i class="bi bi-check-circle text-success"></i> Degree Programs (BSIT, BSCS, etc.)</li>
                            <li><i class="bi bi-check-circle text-success"></i> Year Level Structure</li>
                            <li><i class="bi bi-check-circle text-success"></i> Subject Prerequisites</li>
                            <li><i class="bi bi-check-circle text-success"></i> Course Assignments</li>
                        </ul>
                    </div>
                    <div class="card-footer">
                        <a href="college_curriculum.php" class="btn btn-info btn-sm">
                            <i class="bi bi-arrow-right"></i> Manage College Curriculum
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="row">
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-primary">
                            <?php
                            $track_count = $conn->query("SELECT COUNT(*) as count FROM shs_tracks WHERE is_active = 1")->fetch_assoc()['count'];
                            echo $track_count;
                            ?>
                        </h3>
                        <p class="text-muted mb-0">SHS Tracks</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-success">
                            <?php
                            $program_count = $conn->query("SELECT COUNT(*) as count FROM programs WHERE is_active = 1")->fetch_assoc()['count'];
                            echo $program_count;
                            ?>
                        </h3>
                        <p class="text-muted mb-0">College Programs</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-warning">
                            <?php
                            $shs_subject_count = $conn->query("SELECT COUNT(*) as count FROM curriculum_subjects WHERE subject_type IN ('shs_core', 'shs_applied', 'shs_specialized') AND is_active = 1")->fetch_assoc()['count'];
                            echo $shs_subject_count;
                            ?>
                        </h3>
                        <p class="text-muted mb-0">SHS Subjects</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-danger">
                            <?php
                            $college_subject_count = $conn->query("SELECT COUNT(*) as count FROM curriculum_subjects WHERE subject_type = 'college' AND is_active = 1")->fetch_assoc()['count'];
                            echo $college_subject_count;
                            ?>
                        </h3>
                        <p class="text-muted mb-0">College Subjects</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="card mt-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-activity"></i> Recent Curriculum Activity</h6>
            </div>
            <div class="card-body">
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
                    echo '<div class="list-group list-group-flush">';
                    while ($activity = $recent_activity->fetch_assoc()) {
                        echo '<div class="list-group-item px-0">';
                        echo '<small class="text-muted">' . date('M d, Y H:i', strtotime($activity['created_at'])) . '</small><br>';
                        echo '<span>' . htmlspecialchars($activity['action']) . '</span>';
                        echo '</div>';
                    }
                    echo '</div>';
                } else {
                    echo '<p class="text-muted mb-0">No recent curriculum activity</p>';
                }
                ?>
            </div>
        </div>
    </div>
</div>

    
<?php include 'curriculum_modals.php'; ?>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Track data for JavaScript
const tracksData = <?php echo json_encode($tracks); ?>;
const strandsData = <?php echo json_encode($strands); ?>;

// Form handlers
document.getElementById('addTrackForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);

    try {
        const response = await fetch('process/add_track.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        if (data.status === 'success') {
            showAlert(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert(data.message, 'danger');
        }
    } catch (error) {
        showAlert('An error occurred', 'danger');
    }
});

document.getElementById('addStrandForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);

    try {
        const response = await fetch('process/add_strand.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        if (data.status === 'success') {
            showAlert(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert(data.message, 'danger');
        }
    } catch (error) {
        showAlert('An error occurred', 'danger');
    }
});

document.getElementById('addGradeForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);

    try {
        const response = await fetch('process/add_grade_level.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        if (data.status === 'success') {
            showAlert(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert(data.message, 'danger');
        }
    } catch (error) {
        showAlert('An error occurred', 'danger');
    }
});

document.getElementById('addSubjectForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);

    try {
        const response = await fetch('process/add_shs_subject.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        if (data.status === 'success') {
            showAlert(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert(data.message, 'danger');
        }
    } catch (error) {
        showAlert('An error occurred', 'danger');
    }
});

document.getElementById('assignSubjectForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);

    try {
        const response = await fetch('/elms_system/modules/school_admin/process/assign_shs_subject.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        if (data.status === 'success') {
            showAlert(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert(data.message, 'danger');
        }
    } catch (error) {
        showAlert('An error occurred', 'danger');
    }
});

document.getElementById('addProgramForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);

    try {
        const response = await fetch('process/add_college_program.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        if (data.status === 'success') {
            showAlert(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert(data.message, 'danger');
        }
    } catch (error) {
        showAlert('An error occurred', 'danger');
    }
});

document.getElementById('addCollegeCourseForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);

    try {
        const response = await fetch('process/add_college_course.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        if (data.status === 'success') {
            showAlert(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert(data.message, 'danger');
        }
    } catch (error) {
        showAlert('An error occurred', 'danger');
    }
});

document.getElementById('editCollegeSubjectForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);

    try {
        const response = await fetch('/elms_system/modules/school_admin/process/update_subject.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        if (data.status === 'success') {
            showAlert('Subject updated successfully!', 'success');
            $('#editCollegeSubjectModal').modal('hide');
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert(data.message, 'danger');
        }
    } catch (error) {
        showAlert('An error occurred', 'danger');
    }
});

document.getElementById('addCollegeYearForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);

    try {
        const response = await fetch('process/add_college_year_level.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        if (data.status === 'success') {
            showAlert(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert(data.message, 'danger');
        }
    } catch (error) {
        showAlert('An error occurred', 'danger');
    }
});

document.getElementById('assignCollegeCourseForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);

    try {
        const response = await fetch('/elms_system/modules/school_admin/process/assign_college_course.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        if (data.status === 'success') {
            showAlert(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert(data.message, 'danger');
        }
    } catch (error) {
        showAlert('An error occurred', 'danger');
    }
});

document.getElementById('editTrackForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);

    try {
        const response = await fetch('process/update_track.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        if (data.status === 'success') {
            showAlert(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert(data.message, 'danger');
        }
    } catch (error) {
        showAlert('An error occurred', 'danger');
    }
});

document.getElementById('editStrandForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);

    try {
        const response = await fetch('process/update_strand.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        if (data.status === 'success') {
            showAlert(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert(data.message, 'danger');
        }
    } catch (error) {
        showAlert('An error occurred', 'danger');
    }
});

document.getElementById('editGradeForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);

    try {
        const response = await fetch('process/update_grade_level.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        if (data.status === 'success') {
            showAlert(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert(data.message, 'danger');
        }
    } catch (error) {
        showAlert('An error occurred', 'danger');
    }
});

document.getElementById('editSubjectForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);

    try {
        const response = await fetch('process/update_subject.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        if (data.status === 'success') {
            showAlert(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert(data.message, 'danger');
        }
    } catch (error) {
        showAlert('An error occurred', 'danger');
    }
});

document.getElementById('editProgramForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);

    try {
        const response = await fetch('process/update_college_program.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        if (data.status === 'success') {
            showAlert(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert(data.message, 'danger');
        }
    } catch (error) {
        showAlert('An error occurred', 'danger');
    }
});

document.getElementById('editCollegeCourseForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);

    try {
        const response = await fetch('process/update_college_course.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        if (data.status === 'success') {
            showAlert(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert(data.message, 'danger');
        }
    } catch (error) {
        showAlert('An error occurred', 'danger');
    }
});

document.getElementById('editCollegeYearForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);

    try {
        const response = await fetch('process/update_college_year_level.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        if (data.status === 'success') {
            showAlert(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert(data.message, 'danger');
        }
    } catch (error) {
        showAlert('An error occurred', 'danger');
    }
});

document.getElementById('gradingRulesForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);

    try {
        const response = await fetch('process/update_grading_rules.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        if (data.status === 'success') {
            showAlert(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert(data.message, 'danger');
        }
    } catch (error) {
        showAlert('An error occurred', 'danger');
    }
});

// Utility functions
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
    // Find track data and populate edit modal
    const tracksData = <?php echo json_encode($tracks); ?>;
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
            if (data.status === 'success') {
                showAlert(data.message, 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert(data.message, 'danger');
            }
        })
        .catch(error => showAlert('An error occurred', 'danger'));
    }
}

function editStrand(id) {
    const strandsData = <?php echo json_encode($strands); ?>;
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
            if (data.status === 'success') {
                showAlert(data.message, 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert(data.message, 'danger');
            }
        })
        .catch(error => showAlert('An error occurred', 'danger'));
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
            if (data.status === 'success') {
                showAlert(data.message, 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert(data.message, 'danger');
            }
        })
        .catch(error => showAlert('An error occurred', 'danger'));
    }
}

function editSubject(id) {
    // Fetch subject data and populate edit modal
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
            } else {
                showAlert('Failed to load subject data', 'danger');
            }
        })
        .catch(error => showAlert('An error occurred', 'danger'));
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
    // Redirect to program-specific curriculum view
    window.location.href = `program_curriculum.php?program_id=${id}`;
}

function editCollegeCourse(code) {
    // Fetch course data and populate edit modal
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
            } else {
                showAlert('Failed to load course data', 'danger');
            }
        })
        .catch(error => showAlert('An error occurred', 'danger'));
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

function goBack() {
    if (document.referrer && document.referrer.includes('/elms_system/')) {
        window.history.back();
    } else {
        window.location.href = 'index.php';
    }
}

function showAlert(message, type) {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    document.getElementById('alertContainer').innerHTML = alertHtml;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
</script>

</body>
</html>