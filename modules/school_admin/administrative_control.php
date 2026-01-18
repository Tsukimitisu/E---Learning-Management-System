<?php
require_once '../../config/init.php';

$user_role = $_SESSION['role_id'] ?? $_SESSION['role'] ?? null;
if (!isset($_SESSION['user_id']) || $user_role != ROLE_SCHOOL_ADMIN) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Administrative Control";

// Fetch Branch Administrators
$branch_admins_query = "
    SELECT 
        u.id,
        u.email,
        u.status,
        CONCAT(up.first_name, ' ', up.last_name) as full_name,
        b.name as branch_name,
        u.created_at
    FROM users u
    LEFT JOIN user_profiles up ON u.id = up.user_id
    LEFT JOIN branches b ON up.branch_id = b.id
    INNER JOIN user_roles ur ON u.id = ur.user_id
    WHERE ur.role_id = " . ROLE_BRANCH_ADMIN . "
    ORDER BY u.created_at DESC
";
$branch_admins_result = $conn->query($branch_admins_query);
$branch_admins = [];
while ($admin = $branch_admins_result->fetch_assoc()) {
    $branch_admins[] = $admin;
}

// Fetch Announcements (System-wide)
$announcements_query = "
    SELECT 
        a.id,
        a.title,
        a.content,
        a.priority,
        a.is_active,
        a.created_at,
        a.expires_at,
        CONCAT(up.first_name, ' ', up.last_name) as created_by_name
    FROM announcements a
    LEFT JOIN user_profiles up ON a.created_by = up.user_id
    WHERE a.school_id IS NULL AND a.branch_id IS NULL
    ORDER BY a.created_at DESC
    LIMIT 10
";
$announcements_result = $conn->query($announcements_query);

// Fetch Branch Performance Data
$branch_performance_query = "
    SELECT 
        b.id,
        b.name,
        COUNT(DISTINCT e.id) as student_count,
        COUNT(DISTINCT up.user_id) as teacher_count,
        COUNT(DISTINCT c.id) as class_count
    FROM branches b
    LEFT JOIN classes c ON b.id = c.branch_id
    LEFT JOIN enrollments e ON c.id = e.class_id
    LEFT JOIN user_profiles up ON b.id = up.branch_id
    WHERE up.user_id IN (
        SELECT ur.user_id FROM user_roles ur WHERE ur.role_id = " . ROLE_TEACHER . "
    ) OR up.user_id IS NULL
    GROUP BY b.id
    ORDER BY b.name ASC
";
$branch_performance_result = $conn->query($branch_performance_query);

// Fetch Branches for dropdown
$branches_result = $conn->query("SELECT id, name FROM branches ORDER BY name");
$branches = [];
while ($branch = $branches_result->fetch_assoc()) {
    $branches[] = $branch;
}

// Get audit logs for monitoring
$audit_logs_query = "
    SELECT 
        al.action,
        al.timestamp,
        CONCAT(up.first_name, ' ', up.last_name) as user_name,
        u.email
    FROM audit_logs al
    LEFT JOIN user_profiles up ON al.user_id = up.user_id
    LEFT JOIN users u ON al.user_id = u.id
    ORDER BY al.timestamp DESC
    LIMIT 20
";
$audit_logs_result = $conn->query($audit_logs_query);

include '../../includes/header.php';
?>

<div class="wrapper">
    <?php include '../../includes/sidebar.php'; ?>

    <div id="content">
        <div class="navbar-custom d-flex justify-content-between align-items-center">
            <div>
                <a href="index.php" class="btn btn-sm btn-outline-secondary me-3">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
                <span style="display: inline-block;">
                    <h4 class="mb-0 d-inline-block" style="color: #003366;">
                        <i class="bi bi-shield-check"></i> Administrative Control
                    </h4>
                    <br><small class="text-muted">Institutional-wide management and oversight</small>
                </span>
            </div>
        </div>

        <div id="alertContainer" class="mt-3"></div>

        <!-- Control Tabs -->
        <div class="card shadow-sm mt-4">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" id="branch-admins-tab" data-bs-toggle="tab" data-bs-target="#branch-admins" type="button">
                            <i class="bi bi-people-fill"></i> Branch Administrators
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="announcements-tab" data-bs-toggle="tab" data-bs-target="#announcements" type="button">
                            <i class="bi bi-megaphone"></i> Institution Announcements
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="performance-tab" data-bs-toggle="tab" data-bs-target="#performance" type="button">
                            <i class="bi bi-bar-chart"></i> Branch Performance
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="policies-tab" data-bs-toggle="tab" data-bs-target="#policies" type="button">
                            <i class="bi bi-file-earmark-text"></i> Academic Policies
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="monitoring-tab" data-bs-toggle="tab" data-bs-target="#monitoring" type="button">
                            <i class="bi bi-eye"></i> Activity Monitoring
                        </button>
                    </li>
                </ul>
            </div>

            <div class="card-body">
                <div class="tab-content" id="controlTabContent">

                    <!-- Branch Administrators Tab -->
                    <div class="tab-pane fade show active" id="branch-admins" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0"><i class="bi bi-people-fill"></i> Manage Branch Administrators</h5>
                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addAdminModal">
                                <i class="bi bi-plus-circle"></i> Add Branch Admin
                            </button>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead style="background-color: #f8f9fa;">
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Branch</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($branch_admins)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">
                                            No branch administrators found
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($branch_admins as $admin): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($admin['full_name']); ?></strong>
                                            </td>
                                            <td><?php echo htmlspecialchars($admin['email']); ?></td>
                                            <td>
                                                <span class="badge bg-info">
                                                    <?php echo htmlspecialchars($admin['branch_name'] ?? 'Unassigned'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $admin['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                    <?php echo ucfirst($admin['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo date('M d, Y', strtotime($admin['created_at'])); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-info" onclick="editBranchAdmin(<?php echo $admin['id']; ?>)" title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" onclick="deactivateAdmin(<?php echo $admin['id']; ?>)" title="Deactivate">
                                                    <i class="bi bi-lock"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Institution Announcements Tab -->
                    <div class="tab-pane fade" id="announcements" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0"><i class="bi bi-megaphone"></i> Institution-Wide Announcements</h5>
                            <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#addAnnouncementModal">
                                <i class="bi bi-plus-circle"></i> Publish Announcement
                            </button>
                        </div>

                        <div class="row">
                            <?php while ($announcement = $announcements_result->fetch_assoc()): 
                                $priority_colors = [
                                    'low' => 'secondary',
                                    'normal' => 'info',
                                    'high' => 'warning',
                                    'urgent' => 'danger'
                                ];
                                $priority_color = $priority_colors[$announcement['priority']] ?? 'info';
                            ?>
                            <div class="col-md-12 mb-3">
                                <div class="card border-<?php echo $priority_color; ?> shadow-sm">
                                    <div class="card-header bg-<?php echo $priority_color; ?> text-white">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h6 class="mb-0"><?php echo htmlspecialchars($announcement['title']); ?></h6>
                                            <div>
                                                <span class="badge bg-light text-dark"><?php echo strtoupper($announcement['priority']); ?></span>
                                                <?php if (!$announcement['is_active']): ?>
                                                <span class="badge bg-dark ms-1">Inactive</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <p><?php echo nl2br(htmlspecialchars(substr($announcement['content'], 0, 200))); ?>...</p>
                                        <small class="text-muted">
                                            By <?php echo htmlspecialchars($announcement['created_by_name']); ?> | 
                                            <?php echo date('M d, Y H:i', strtotime($announcement['created_at'])); ?>
                                            <?php if ($announcement['expires_at']): ?>
                                            | Expires: <?php echo date('M d, Y', strtotime($announcement['expires_at'])); ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                    <div class="card-footer bg-light">
                                        <button class="btn btn-sm btn-outline-warning me-1" onclick="editAnnouncement(<?php echo $announcement['id']; ?>)">
                                            <i class="bi bi-pencil"></i> Edit
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteAnnouncement(<?php echo $announcement['id']; ?>)">
                                            <i class="bi bi-trash"></i> Delete
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    </div>

                    <!-- Branch Performance Tab -->
                    <div class="tab-pane fade" id="performance" role="tabpanel">
                        <h5 class="mb-3"><i class="bi bi-bar-chart"></i> Branch Performance Overview</h5>

                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead style="background-color: #f8f9fa;">
                                    <tr>
                                        <th>Branch Name</th>
                                        <th>Students</th>
                                        <th>Teachers</th>
                                        <th>Classes</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($branch = $branch_performance_result->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($branch['name']); ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary"><?php echo $branch['student_count']; ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo $branch['teacher_count']; ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-success"><?php echo $branch['class_count']; ?></span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-info" onclick="viewBranchDetails(<?php echo $branch['id']; ?>)">
                                                <i class="bi bi-eye"></i> Details
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Academic Policies Tab -->
                    <div class="tab-pane fade" id="policies" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0"><i class="bi bi-file-earmark-text"></i> Academic Policies</h5>
                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addPolicyModal">
                                <i class="bi bi-plus-circle"></i> Add Policy
                            </button>
                        </div>

                        <div class="row">
                            <!-- SHS Policies -->
                            <div class="col-md-6 mb-3">
                                <div class="card border-primary">
                                    <div class="card-header bg-primary text-white">
                                        <h6 class="mb-0"><i class="bi bi-mortarboard"></i> SHS Academic Policies</h6>
                                    </div>
                                    <div class="card-body">
                                        <ul class="list-unstyled">
                                            <li class="mb-2 pb-2 border-bottom">
                                                <strong>Minimum GPA:</strong> 1.50 for good standing
                                            </li>
                                            <li class="mb-2 pb-2 border-bottom">
                                                <strong>Course Load:</strong> 12-18 units per semester
                                            </li>
                                            <li class="mb-2 pb-2 border-bottom">
                                                <strong>Attendance:</strong> 85% minimum per subject
                                            </li>
                                            <li class="mb-2 pb-2 border-bottom">
                                                <strong>Grading Scale:</strong> 1.0 (Excellent) to 5.0 (Failed)
                                            </li>
                                            <li>
                                                <strong>Probation:</strong> GPA below 1.50 for two consecutive terms
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <!-- College Policies -->
                            <div class="col-md-6 mb-3">
                                <div class="card border-info">
                                    <div class="card-header bg-info text-white">
                                        <h6 class="mb-0"><i class="bi bi-building"></i> College Academic Policies</h6>
                                    </div>
                                    <div class="card-body">
                                        <ul class="list-unstyled">
                                            <li class="mb-2 pb-2 border-bottom">
                                                <strong>Minimum GPA:</strong> 2.00 for graduation
                                            </li>
                                            <li class="mb-2 pb-2 border-bottom">
                                                <strong>Course Load:</strong> 9-18 units per semester
                                            </li>
                                            <li class="mb-2 pb-2 border-bottom">
                                                <strong>Attendance:</strong> 80% minimum per subject
                                            </li>
                                            <li class="mb-2 pb-2 border-bottom">
                                                <strong>Prerequisites:</strong> Must be completed before enrollment
                                            </li>
                                            <li>
                                                <strong>Dean's List:</strong> GPA 3.5 and above
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Policy Enforcement -->
                        <div class="card mt-3">
                            <div class="card-header bg-secondary text-white">
                                <h6 class="mb-0"><i class="bi bi-shield-lock"></i> Policy Enforcement Settings</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-check">
                                            <input class="form-check-input" type="checkbox" checked>
                                            <span class="form-check-label">Enforce minimum GPA requirements</span>
                                        </label>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-check">
                                            <input class="form-check-input" type="checkbox" checked>
                                            <span class="form-check-label">Require attendance threshold</span>
                                        </label>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-check">
                                            <input class="form-check-input" type="checkbox" checked>
                                            <span class="form-check-label">Validate course prerequisites</span>
                                        </label>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-check">
                                            <input class="form-check-input" type="checkbox" checked>
                                            <span class="form-check-label">Monitor course load limits</span>
                                        </label>
                                    </div>
                                </div>
                                <button class="btn btn-primary">
                                    <i class="bi bi-save"></i> Save Policy Settings
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Activity Monitoring Tab -->
                    <div class="tab-pane fade" id="monitoring" role="tabpanel">
                        <h5 class="mb-3"><i class="bi bi-eye"></i> System Activity Monitoring</h5>

                        <div class="card">
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover">
                                        <thead style="background-color: #f8f9fa;">
                                            <tr>
                                                <th>User</th>
                                                <th>Action</th>
                                                <th>Timestamp</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($log = $audit_logs_result->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($log['user_name'] ?? 'System'); ?></strong>
                                                    <br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($log['email'] ?? '-'); ?></small>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars(substr($log['action'], 0, 100)); ?>
                                                    <?php if (strlen($log['action']) > 100): ?>...<?php endif; ?>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php echo date('M d, Y H:i:s', strtotime($log['timestamp'])); ?>
                                                    </small>
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
            </div>
        </div>
    </div>
</div>

<!-- Add Admin Modal -->
<div class="modal fade" id="addAdminModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Add Branch Administrator</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addAdminForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="full_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Branch <span class="text-danger">*</span></label>
                        <select class="form-select" name="branch_id" required>
                            <option value="">-- Select Branch --</option>
                            <?php foreach ($branches as $branch): ?>
                            <option value="<?php echo $branch['id']; ?>">
                                <?php echo htmlspecialchars($branch['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Create Admin Account
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Announcement Modal -->
<div class="modal fade" id="addAnnouncementModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="bi bi-megaphone"></i> Publish Institution-Wide Announcement</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addAnnouncementForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="title" required placeholder="Announcement title">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Content <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="content" rows="5" required placeholder="Announcement content"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Priority <span class="text-danger">*</span></label>
                            <select class="form-select" name="priority" required>
                                <option value="low">Low</option>
                                <option value="normal" selected>Normal</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Expires (Optional)</label>
                            <input type="date" class="form-control" name="expires_at">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-send"></i> Publish
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
const BASE_URL = '/elms_system/';

function showAlert(message, type = 'info') {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    document.getElementById('alertContainer').innerHTML = alertHtml;
}

$('#addAdminForm').on('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    fetch(BASE_URL + 'modules/school_admin/process/add_branch_admin.php', {
        method: 'POST', body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            showAlert('Branch administrator created successfully!', 'success');
            setTimeout(() => location.reload(), 1500);
        } else showAlert(data.message, 'danger');
    })
    .catch(() => showAlert('Error creating administrator', 'danger'));
});

$('#addAnnouncementForm').on('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    fetch(BASE_URL + 'modules/school_admin/process/add_system_announcement.php', {
        method: 'POST', body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            showAlert('Announcement published successfully!', 'success');
            setTimeout(() => location.reload(), 1500);
        } else showAlert(data.message, 'danger');
    })
    .catch(() => showAlert('Error publishing announcement', 'danger'));
});

function editBranchAdmin(adminId) {
    alert('Edit admin functionality coming soon');
}

function deactivateAdmin(adminId) {
    if (confirm('Are you sure you want to deactivate this administrator?')) {
        fetch(BASE_URL + 'modules/school_admin/process/deactivate_admin.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ admin_id: adminId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                showAlert('Administrator deactivated', 'success');
                setTimeout(() => location.reload(), 1500);
            } else showAlert(data.message, 'danger');
        })
        .catch(() => showAlert('Error deactivating administrator', 'danger'));
    }
}

function editAnnouncement(announcementId) {
    alert('Edit announcement functionality coming soon');
}

function deleteAnnouncement(announcementId) {
    if (confirm('Are you sure you want to delete this announcement?')) {
        fetch(BASE_URL + 'modules/school_admin/process/delete_announcement.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ announcement_id: announcementId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                showAlert('Announcement deleted', 'success');
                setTimeout(() => location.reload(), 1500);
            } else showAlert(data.message, 'danger');
        })
        .catch(() => showAlert('Error deleting announcement', 'danger'));
    }
}

function viewBranchDetails(branchId) {
    // Redirect to branch details page
    window.location.href = BASE_URL + `modules/super_admin/branches.php?id=${branchId}`;
}
</script>

<?php include '../../includes/footer.php'; ?>
