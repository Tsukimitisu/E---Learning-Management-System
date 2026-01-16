<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SCHOOL_ADMIN) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Subject Catalog";

// Fetch all subjects with program info
$subjects_query = "
    SELECT 
        s.id,
        s.subject_code,
        s.subject_title,
        s.units,
        s.year_level,
        s.semester,
        s.is_active,
        p.program_code,
        p.program_name
    FROM subjects s
    INNER JOIN programs p ON s.program_id = p.id
    ORDER BY p.program_name, s.year_level, s.semester, s.subject_code
";
$subjects_result = $conn->query($subjects_query);

// Fetch programs for dropdown
$programs_result = $conn->query("SELECT id, program_code, program_name FROM programs WHERE is_active = 1 ORDER BY program_name");

include '../../includes/header.php';
?>

<div class="wrapper">
    <?php include '../../includes/sidebar.php'; ?>

    <div id="content">
        <div class="navbar-custom d-flex justify-content-between align-items-center">
            <h4 class="mb-0" style="color: #003366;">
                <i class="bi bi-book"></i> Subject Catalog / Curriculum
            </h4>
            <button class="btn btn-sm text-white" style="background-color: #800000;" data-bs-toggle="modal" data-bs-target="#addSubjectModal">
                <i class="bi bi-plus-circle"></i> Add New Subject
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
                                <th>Subject Code</th>
                                <th>Subject Title</th>
                                <th>Program</th>
                                <th>Year Level</th>
                                <th>Semester</th>
                                <th>Units</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($subject = $subjects_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $subject['id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($subject['subject_code']); ?></strong></td>
                                <td><?php echo htmlspecialchars($subject['subject_title']); ?></td>
                                <td>
                                    <span class="badge bg-primary"><?php echo htmlspecialchars($subject['program_code']); ?></span>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($subject['program_name']); ?></small>
                                </td>
                                <td><?php echo $subject['year_level']; ?></td>
                                <td><?php echo $subject['semester']; ?></td>
                                <td><?php echo $subject['units']; ?></td>
                                <td>
                                    <?php if ($subject['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-warning">
                                        <i class="bi bi-pencil"></i>
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

<!-- Add Subject Modal -->
<div class="modal fade" id="addSubjectModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #800000; color: white;">
                <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Add New Subject</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addSubjectForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Subject Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="subject_code" required placeholder="e.g. IT 101">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Units <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="units" required min="1" max="6" value="3">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Subject Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="subject_title" required placeholder="e.g. Cloud Computing">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Program <span class="text-danger">*</span></label>
                        <select class="form-select" name="program_id" required>
                            <option value="">-- Select Program --</option>
                            <?php 
                            $programs_result->data_seek(0);
                            while ($program = $programs_result->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $program['id']; ?>">
                                    <?php echo htmlspecialchars($program['program_code'] . ' - ' . $program['program_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Year Level <span class="text-danger">*</span></label>
                            <select class="form-select" name="year_level" required>
                                <option value="1">1st Year</option>
                                <option value="2">2nd Year</option>
                                <option value="3">3rd Year</option>
                                <option value="4">4th Year</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Semester <span class="text-danger">*</span></label>
                            <select class="form-select" name="semester" required>
                                <option value="1">1st Semester</option>
                                <option value="2">2nd Semester</option>
                                <option value="3">Summer</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn text-white" style="background-color: #800000;">
                        <i class="bi bi-save"></i> Add Subject
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('addSubjectForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    
    try {
        const response = await fetch('process/add_subject.php', {
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
    document.getElementById('alertContainer').innerHTML = alertHtml;}
</script>
</body>
</html>