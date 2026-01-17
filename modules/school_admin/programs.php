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
            <h4 class="mb-0" style="color: #003366;">
                <i class="bi bi-mortarboard"></i> Program Management
            </h4>
            <button class="btn btn-sm text-white" style="background-color: #800000;" data-bs-toggle="modal" data-bs-target="#addProgramModal">
                <i class="bi bi-plus-circle"></i> Add New Program
            </button>
        </div>

        <div id="alertContainer"></div>

        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
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

<!-- Add Program Modal -->
<div class="modal fade" id="addProgramModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #800000; color: white;">
                <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Add New Program</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addProgramForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Program Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="program_code" required placeholder="e.g. BSIT">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Program Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="program_name" required placeholder="e.g. Bachelor of Science in Information Technology">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Degree Level <span class="text-danger">*</span></label>
                        <select class="form-select" name="degree_level" required>
                            <option value="">-- Select Level --</option>
                            <option value="Certificate">Certificate</option>
                            <option value="Associate">Associate</option>
                            <option value="Bachelor" selected>Bachelor</option>
                            <option value="Master">Master</option>
                            <option value="Doctorate">Doctorate</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">School <span class="text-danger">*</span></label>
                        <select class="form-select" name="school_id" required>
                            <option value="">-- Select School --</option>
                            <?php 
                            $schools_result->data_seek(0);
                            while ($school = $schools_result->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $school['id']; ?>"><?php echo htmlspecialchars($school['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn text-white" style="background-color: #800000;">
                        <i class="bi bi-save"></i> Create Program
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
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

function editProgram(id) {
    alert('Edit functionality will be implemented. Program ID: ' + id);
}

function toggleStatus(id, currentStatus) {
    const action = currentStatus ? 'deactivate' : 'activate';
    if (confirm(`Are you sure you want to ${action} this program?`)) {
        // Implement toggle status
        alert('Toggle status functionality will be implemented');
    }
}
</script>
</body>
</html>