<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SCHOOL_ADMIN) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Program Management";

// Fetch all programs
$programs_query = "
    SELECT
        p.id,
        p.program_code,
        p.program_name,
        p.degree_level,
        p.is_active,
        s.name as school_name,
        COUNT(DISTINCT cs.id) as subject_count,
        COUNT(DISTINCT yl.id) as year_levels_count,
        p.created_at
    FROM programs p
    INNER JOIN schools s ON p.school_id = s.id
    LEFT JOIN curriculum_subjects cs ON p.id = cs.program_id
    LEFT JOIN program_year_levels yl ON p.id = yl.program_id AND yl.is_active = 1
    GROUP BY p.id
    ORDER BY p.program_name
";
$programs_result = $conn->query($programs_query);

// Fetch schools for dropdown
$schools_result = $conn->query("SELECT id, name FROM schools ORDER BY name");

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
                        <i class="bi bi-mortarboard"></i> Program Management
                    </h4>
                    <br><small class="text-muted">Manage academic programs and degree offerings</small>
                </span>
            </div>
            <button class="btn btn-sm text-white" style="background-color: #800000;" data-bs-toggle="modal" data-bs-target="#addProgramModal">
                <i class="bi bi-plus-circle"></i> Add New Program
            </button>
        </div>

        <div id="alertContainer" class="mt-3"></div>

        <div class="card shadow-sm mt-4">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead style="background-color: #f8f9fa;">
                            <tr>
                                <th>ID</th>
                                <th>Program Code</th>
                                <th>Program Name</th>
                                <th>Degree Level</th>
                                <th>School</th>
                                <th>Subjects</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($program = $programs_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $program['id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($program['program_code']); ?></strong></td>
                                <td><?php echo htmlspecialchars($program['program_name']); ?></td>
                                <td><?php echo htmlspecialchars($program['degree_level']); ?></td>
                                <td><?php echo htmlspecialchars($program['school_name']); ?></td>
                                <td><span class="badge bg-info"><?php echo $program['subject_count']; ?></span></td>
                                <td>
                                    <?php if ($program['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-warning" onclick="editProgram(<?php echo $program['id']; ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-sm btn-<?php echo $program['is_active'] ? 'secondary' : 'success'; ?>" 
                                            onclick="toggleStatus(<?php echo $program['id']; ?>, <?php echo $program['is_active']; ?>)">
                                        <i class="bi bi-<?php echo $program['is_active'] ? 'x-circle' : 'check-circle'; ?>"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Include curriculum modals -->
<?php include 'curriculum_modals.php'; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../assets/js/curriculum.js"></script>

<script>
// Prepare programs data for editing
const collegePrograms = <?php 
$programs_result->data_seek(0);
$programs_data = [];
while ($prog = $programs_result->fetch_assoc()) {
    $programs_data[] = $prog;
}
echo json_encode($programs_data); 
?>;

function goBack() {
    if (document.referrer && document.referrer.includes('/elms_system/')) {
        window.history.back();
    } else {
        window.location.href = 'index.php';
    }
}

document.getElementById('addProgramForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    
    try {
        const response = await fetch('process/add_program.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        
        if (data.status === 'success') {
            showAlert(data.message, 'success');
            $('#addProgramModal').modal('hide');
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert(data.message, 'danger');
        }
    } catch (error) {
        showAlert('An error occurred', 'danger');
    }
});

function showAlert(message, type) {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    document.getElementById('alertContainer').innerHTML = alertHtml;
}

function toggleStatus(id, currentStatus) {
    const action = currentStatus ? 'deactivate' : 'activate';
    if (confirm(`Are you sure you want to ${action} this program?`)) {
        fetch('process/toggle_program_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ program_id: id, is_active: currentStatus ? 0 : 1 })
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

// Edit Program function
function editProgram(id) {
    const program = collegePrograms.find(p => p.id == id);
    if (program) {
        document.getElementById('editProgramId').value = program.id;
        document.getElementById('editProgramCode').value = program.program_code;
        document.getElementById('editProgramName').value = program.program_name;
        document.getElementById('editProgramDegree').value = program.degree_level;
        document.getElementById('editProgramStatus').value = program.is_active;
        
        // Get school ID (need to fetch it from server)
        fetch('process/get_program.php?id=' + id)
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    document.getElementById('editProgramSchool').value = data.program.school_id;
                }
            });
        
        const modal = new bootstrap.Modal(document.getElementById('editProgramModal'));
        modal.show();
    }
}

// Edit Program form submit
document.getElementById('editProgramForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    
    try {
        const response = await fetch('process/update_program.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        
        if (data.status === 'success') {
            showAlert(data.message, 'success');
            $('#editProgramModal').modal('hide');
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert(data.message, 'danger');
        }
    } catch (error) {
        showAlert('An error occurred', 'danger');
    }
});
</script>

<?php include '../../includes/footer.php'; ?>
