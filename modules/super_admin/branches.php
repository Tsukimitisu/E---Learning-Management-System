<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SUPER_ADMIN) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "School & Branch Management";

// Fetch all schools with branch count
$schools_query = "
    SELECT s.id, s.name, s.logo,
           COUNT(b.id) as branch_count
    FROM schools s
    LEFT JOIN branches b ON s.id = b.school_id
    GROUP BY s.id
    ORDER BY s.name
";
$schools_result = $conn->query($schools_query);

// Fetch all branches
$branches_query = "
    SELECT b.id, b.name, b.address, s.name as school_name
    FROM branches b
    INNER JOIN schools s ON b.school_id = s.id
    ORDER BY s.name, b.name
";
$branches_result = $conn->query($branches_query);

include '../../includes/header.php';
?>

<div class="wrapper">
    <?php include '../../includes/sidebar.php'; ?>

    <div id="content">
        <div class="navbar-custom d-flex justify-content-between align-items-center">
            <h4 class="mb-0" style="color: #003366;">
                <i class="bi bi-building"></i> School & Branch Management
            </h4>
            <div>
                <button class="btn btn-sm btn-success me-2" data-bs-toggle="modal" data-bs-target="#addSchoolModal">
                    <i class="bi bi-plus-circle"></i> Add School
                </button>
                <button class="btn btn-sm text-white" style="background-color: #800000;" data-bs-toggle="modal" data-bs-target="#addBranchModal">
                    <i class="bi bi-building"></i> Add Branch
                </button>
            </div>
        </div>

        <div id="alertContainer"></div>

        <!-- Schools Section -->
        <div class="card shadow-sm mb-4">
            <div class="card-header" style="background-color: #003366; color: white;">
                <h5 class="mb-0"><i class="bi bi-bank"></i> Schools</h5>
            </div>
            <div class="card-body">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>School Name</th>
                            <th>Total Branches</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($school = $schools_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $school['id']; ?></td>
                            <td><?php echo htmlspecialchars($school['name']); ?></td>
                            <td><span class="badge bg-info"><?php echo $school['branch_count']; ?></span></td>
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

        <!-- Branches Section -->
        <div class="card shadow-sm">
            <div class="card-header" style="background-color: #800000; color: white;">
                <h5 class="mb-0"><i class="bi bi-building"></i> Branches</h5>
            </div>
            <div class="card-body">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Branch Name</th>
                            <th>School</th>
                            <th>Address</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($branch = $branches_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $branch['id']; ?></td>
                            <td><?php echo htmlspecialchars($branch['name']); ?></td>
                            <td><?php echo htmlspecialchars($branch['school_name']); ?></td>
                            <td><?php echo htmlspecialchars($branch['address'] ?? 'N/A'); ?></td>
                            <td>
                                <button class="btn btn-sm btn-warning">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-sm btn-danger">
                                    <i class="bi bi-trash"></i>
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

<!-- Add School Modal -->
<div class="modal fade" id="addSchoolModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #003366; color: white;">
                <h5 class="modal-title"><i class="bi bi-bank"></i> Add New School</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addSchoolForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">School Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="school_name" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-save"></i> Create School
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Branch Modal -->
<div class="modal fade" id="addBranchModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #800000; color: white;">
                <h5 class="modal-title"><i class="bi bi-building"></i> Add New Branch</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addBranchForm">
                <div class="modal-body">
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
                    <div class="mb-3">
                        <label class="form-label">Branch Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="branch_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea class="form-control" name="address" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn text-white" style="background-color: #800000;">
                        <i class="bi bi-save"></i> Create Branch
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('addSchoolForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    
    try {
        const response = await fetch('process/add_school.php', {
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

document.getElementById('addBranchForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    
    try {
        const response = await fetch('process/add_branch.php', {
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
</script>
</body>
</html>