<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SUPER_ADMIN) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "School & Branch Management";

$schools_query = "
    SELECT s.id, s.name, s.logo,
           COUNT(b.id) as branch_count
    FROM schools s
    LEFT JOIN branches b ON s.id = b.school_id
    GROUP BY s.id
    ORDER BY s.name
";
$schools_result = $conn->query($schools_query);

$branches_query = "
    SELECT b.id, b.name, b.address, s.name as school_name
    FROM branches b
    INNER JOIN schools s ON b.school_id = s.id
    ORDER BY s.name, b.name
";
$branches_result = $conn->query($branches_query);

include '../../includes/header.php';
include '../../includes/sidebar.php'; 
?>

<style>
    /* --- SCROLL & LAYOUT ENGINE --- */
    html, body { height: 100%; margin: 0; overflow: hidden; }

    #content {
        height: 100vh;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .header-fixed-part {
        flex: 0 0 auto;
        background: white;
        padding: 20px 30px;
        border-bottom: 1px solid #eee;
    }

    .body-scroll-part {
        flex: 1 1 auto;
        overflow-y: auto;
        padding: 25px 30px 100px 30px; 
        background-color: #f8f9fa;
    }

    /* --- FANTASTIC CORPORATE COMPONENTS --- */
    .mgmt-card {
        background: white;
        border-radius: 15px;
        border: none;
        box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        margin-bottom: 30px;
        overflow: hidden;
    }

    .card-header-blue { background: var(--blue); color: white; padding: 15px 25px; font-weight: 700; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 1px; }
    .card-header-maroon { background: var(--maroon); color: white; padding: 15px 25px; font-weight: 700; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 1px; }

    .table thead th {
        background: #fcfcfc;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #888;
        padding: 15px;
        border-bottom: 2px solid #f1f1f1;
    }

    .table tbody td { padding: 15px; vertical-align: middle; }

    .btn-blue-action { background: var(--blue); color: white; border: none; border-radius: 8px; font-weight: 600; padding: 10px 20px; transition: 0.3s; }
    .btn-blue-action:hover { background: #002244; color: white; transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0,51,102,0.2); }

    .btn-maroon-action { background: var(--maroon); color: white; border: none; border-radius: 8px; font-weight: 600; padding: 10px 20px; transition: 0.3s; }
    .btn-maroon-action:hover { background: #600000; color: white; transform: translateY(-2px); box-shadow: 0 4px 10px rgba(128,0,0,0.2); }

    .count-badge { background: #e7f5ff; color: #1971c2; font-weight: 800; padding: 5px 12px; border-radius: 6px; }

    /* Mobile Logic */
    @media (max-width: 576px) {
        .header-fixed-part { flex-direction: column; gap: 15px; text-align: center; }
        .header-fixed-part .d-flex { width: 100%; flex-direction: column; gap: 10px; }
        .body-scroll-part { padding: 15px 15px 100px 15px; }
    }
</style>

<!-- Part 1: Fixed Header -->
<div class="header-fixed-part d-flex justify-content-between align-items-center animate__animated animate__fadeInDown">
    <div>
        <h4 class="fw-bold mb-0" style="color: #003366;"><i class="bi bi-building-fill me-2"></i>Institutional Management</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="dashboard.php" class="text-maroon text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item active">Schools & Branches</li>
            </ol>
        </nav>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-blue-action shadow-sm" data-bs-toggle="modal" data-bs-target="#addSchoolModal">
            <i class="bi bi-plus-circle me-1"></i> Add School
        </button>
        <button class="btn btn-maroon-action shadow-sm" data-bs-toggle="modal" data-bs-target="#addBranchModal">
            <i class="bi bi-building-add me-1"></i> Add Branch
        </button>
    </div>
</div>

<!-- Part 2: Scrollable Content Area -->
<div class="body-scroll-part animate__animated animate__fadeInUp">
    
    <div id="alertContainer"></div>

    <!-- Schools Section -->
    <div class="mgmt-card">
        <div class="card-header-blue">
            <i class="bi bi-bank me-2"></i> Registered Institutions
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>School Name</th>
                        <th class="text-center">Branch Count</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($school = $schools_result->fetch_assoc()): ?>
                    <tr>
                        <td class="text-muted fw-bold">#<?php echo $school['id']; ?></td>
                        <td class="fw-bold text-dark"><?php echo htmlspecialchars($school['name']); ?></td>
                        <td class="text-center"><span class="count-badge"><?php echo $school['branch_count']; ?></span></td>
                        <td class="text-end">
                            <button class="btn btn-light btn-sm border" title="Edit School"><i class="bi bi-pencil text-warning"></i></button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Branches Section -->
    <div class="mgmt-card">
        <div class="card-header-maroon">
            <i class="bi bi-geo-alt-fill me-2"></i> Active Branches
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Branch Name</th>
                        <th>Parent Institution</th>
                        <th>Address</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($branch = $branches_result->fetch_assoc()): ?>
                    <tr>
                        <td class="fw-bold text-dark"><?php echo htmlspecialchars($branch['name']); ?></td>
                        <td><span class="badge bg-light text-primary border"><?php echo htmlspecialchars($branch['school_name']); ?></span></td>
                        <td class="text-muted small"><?php echo htmlspecialchars($branch['address'] ?? 'No Address Provided'); ?></td>
                        <td class="text-end">
                            <div class="btn-group shadow-sm">
                                <button class="btn btn-white btn-sm border" title="Edit"><i class="bi bi-pencil-square text-warning"></i></button>
                                <button class="btn btn-white btn-sm border" title="Delete"><i class="bi bi-trash text-danger"></i></button>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Add School Modal -->
<div class="modal fade" id="addSchoolModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header p-4 text-white" style="background-color: var(--blue); border: none;">
                <h5 class="modal-title fw-bold"><i class="bi bi-bank me-2"></i> Register Institution</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addSchoolForm">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">SCHOOL NAME *</label>
                        <input type="text" class="form-control shadow-sm border-light" name="school_name" placeholder="e.g. Datamex College" required>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4">
                    <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-blue-action px-4">Save Institution</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Branch Modal -->
<div class="modal fade" id="addBranchModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header p-4 text-white" style="background-color: var(--maroon); border: none;">
                <h5 class="modal-title fw-bold"><i class="bi bi-building me-2"></i> Create New Branch</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addBranchForm">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">SELECT INSTITUTION *</label>
                        <select class="form-select shadow-sm border-light" name="school_id" required>
                            <option value="">-- Choose School --</option>
                            <?php 
                            $schools_result->data_seek(0);
                            while ($school = $schools_result->fetch_assoc()): ?>
                                <option value="<?php echo $school['id']; ?>"><?php echo htmlspecialchars($school['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">BRANCH NAME *</label>
                        <input type="text" class="form-control shadow-sm border-light" name="branch_name" placeholder="e.g. Manila Campus" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">FULL ADDRESS</label>
                        <textarea class="form-control shadow-sm border-light" name="address" rows="3" placeholder="Enter branch location..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4">
                    <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-maroon-action px-4">Register Branch</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<!-- --- JAVASCRIPT LOGIC - UNTOUCHED --- -->
<script>
document.getElementById('addSchoolForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    try {
        const response = await fetch('process/add_school.php', { method: 'POST', body: formData });
        const data = await response.json();
        if (data.status === 'success') {
            showAlert(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else { showAlert(data.message, 'danger'); }
    } catch (error) { showAlert('An error occurred', 'danger'); }
});

document.getElementById('addBranchForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    try {
        const response = await fetch('process/add_branch.php', { method: 'POST', body: formData });
        const data = await response.json();
        if (data.status === 'success') {
            showAlert(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else { showAlert(data.message, 'danger'); }
    } catch (error) { showAlert('An error occurred', 'danger'); }
});

function showAlert(message, type) {
    const alertHtml = `<div class="alert alert-${type} alert-dismissible fade show border-0 shadow-sm animate__animated animate__shakeX" role="alert"><i class="bi ${type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'} me-2"></i>${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
    document.getElementById('alertContainer').innerHTML = alertHtml;
    document.querySelector('.body-scroll-part').scrollTo({ top: 0, behavior: 'smooth' });
}
</script>
</body>
</html>