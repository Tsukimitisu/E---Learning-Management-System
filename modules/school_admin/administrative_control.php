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
        u.created_at,
        u.last_login
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

// Fetch Branch Performance Data - Updated for new structure
$current_ay = $conn->query("SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$current_ay_id = $current_ay['id'] ?? 0;

$branch_performance_query = "
    SELECT 
        b.id,
        b.name,
        (SELECT COUNT(DISTINCT ss.student_id) 
         FROM section_students ss 
         INNER JOIN sections s ON ss.section_id = s.id 
         WHERE s.branch_id = b.id AND s.academic_year_id = $current_ay_id AND ss.status = 'active') as student_count,
        (SELECT COUNT(DISTINCT tsa.teacher_id) 
         FROM teacher_subject_assignments tsa 
         WHERE tsa.branch_id = b.id AND tsa.academic_year_id = $current_ay_id AND tsa.is_active = 1) as teacher_count,
        (SELECT COUNT(*) 
         FROM sections s 
         WHERE s.branch_id = b.id AND s.academic_year_id = $current_ay_id AND s.is_active = 1) as section_count
    FROM branches b
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
                <a href="javascript:void(0)" onclick="goBack()" class="btn btn-sm btn-outline-secondary me-3">
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
                                        <th>Last Login</th>
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
                                                <?php if (empty($admin['branch_name'])): ?>
                                                    <span class="badge bg-warning" data-bs-toggle="tooltip" title="Unassigned admins cannot access the system">⚠ Unassigned</span>
                                                <?php else: ?>
                                                    <span class="badge bg-info">
                                                        <?php echo htmlspecialchars($admin['branch_name']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $admin['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                    <?php echo ucfirst($admin['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if (!empty($admin['last_login'])): ?>
                                                    <small class="text-muted"><?php echo date('M d, Y H:i', strtotime($admin['last_login'])); ?></small>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Never logged in</span>
                                                <?php endif; ?>
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
                                        <th>Sections</th>
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
                                            <span class="badge bg-success"><?php echo $branch['section_count']; ?></span>
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
                        <label class="form-label">Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="password" required minlength="6">
                        <small class="text-muted">Minimum 6 characters</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Branch <span class="text-danger">*</span></label>
                        <select class="form-select" name="branch_id" required>
                            <option value="">-- Select Branch --</option>
                            <?php foreach ($branches as $branch): ?>
                            <option value="<?php echo $branch['id']; ?>">
                                <?php echo htmlspecialchars($branch['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Branch assignment is required. The admin will only be able to manage this branch.</small>
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
<div class="modal fade" id="editAdminModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="bi bi-pencil"></i> Edit Branch Administrator</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="editAdminForm">
                <div class="modal-body">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" class="form-control" id="edit_full_name" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" id="edit_email" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Branch <span class="text-danger">*</span></label>
                        <select class="form-select" name="branch_id" id="edit_branch_id" required>
                            <option value="">-- Select Branch --</option>
                            <?php foreach ($branches as $branch): ?>
                            <option value="<?php echo $branch['id']; ?>">
                                <?php echo htmlspecialchars($branch['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Changing branch assignment will affect the admin's access immediately.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info text-white">
                        <i class="bi bi-save"></i> Update Branch Assignment
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

<!-- Branch Details Modal -->
<div class="modal fade" id="branchDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="branchDetailsTitle"><i class="bi bi-building"></i> Branch Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="branchDetailsContent">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Add Policy Modal -->
<div class="modal fade" id="addPolicyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-file-earmark-plus"></i> Add Academic Policy</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addPolicyForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Policy Category <span class="text-danger">*</span></label>
                        <select class="form-select" name="category" required>
                            <option value="">-- Select Category --</option>
                            <option value="shs">SHS Academic Policy</option>
                            <option value="college">College Academic Policy</option>
                            <option value="general">General Policy</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Policy Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="title" required placeholder="e.g., Minimum GPA Requirement">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Policy Description <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="description" rows="4" required placeholder="Describe the policy details..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Enforcement Level</label>
                        <select class="form-select" name="enforcement">
                            <option value="mandatory">Mandatory</option>
                            <option value="recommended">Recommended</option>
                            <option value="optional">Optional</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Save Policy
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const BASE_URL = '/elms_system/';

function goBack() {
    // Check if we can go back in history
    if (document.referrer && document.referrer.includes('/elms_system/')) {
        window.history.back();
    } else {
        // Default to dashboard
        window.location.href = 'index.php';
    }
}

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
    .then(response => response.text())
    .then(text => {
        console.log('Response:', text);
        try {
            const data = JSON.parse(text);
            if (data.status === 'success') {
                showAlert('Branch administrator created successfully!', 'success');
                setTimeout(() => location.reload(), 1500);
            } else showAlert(data.message, 'danger');
        } catch (e) {
            showAlert('Server error: ' + text.substring(0, 200), 'danger');
        }
    })
    .catch(err => showAlert('Error creating administrator: ' + err.message, 'danger'));
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
    fetch(BASE_URL + 'modules/school_admin/process/get_branch_admin.php?admin_id=' + adminId)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                document.getElementById('edit_user_id').value = data.admin.id;
                document.getElementById('edit_full_name').value = data.admin.full_name;
                document.getElementById('edit_email').value = data.admin.email;
                document.getElementById('edit_branch_id').value = data.admin.branch_id ?? '';

                const modal = new bootstrap.Modal(document.getElementById('editAdminModal'));
                modal.show();
            } else {
                showAlert(data.message || 'Unable to load admin details', 'danger');
            }
        })
        .catch(() => showAlert('Error loading admin details', 'danger'));
}

$('#editAdminForm').on('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    fetch(BASE_URL + 'modules/school_admin/process/update_branch_admin.php', {
        method: 'POST', body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            showAlert('Branch administrator updated successfully!', 'success');
            setTimeout(() => location.reload(), 1500);
        } else showAlert(data.message, 'danger');
    })
    .catch(() => showAlert('Error updating administrator', 'danger'));
});

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
    // Fetch branch details and show modal
    fetch(BASE_URL + 'modules/school_admin/process/get_branch_details.php?branch_id=' + branchId)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                const branch = data.branch;
                
                // Build teachers list
                let teachersList = '<p class="text-muted mb-0">No teachers assigned</p>';
                if (branch.teachers && branch.teachers.length > 0) {
                    teachersList = '<ul class="list-group list-group-flush" style="max-height: 150px; overflow-y: auto;">';
                    branch.teachers.forEach(t => {
                        teachersList += `<li class="list-group-item py-1 px-0 border-0"><small><i class="bi bi-person-badge me-1"></i>${t.name} <span class="text-muted">(${t.email})</span></small></li>`;
                    });
                    teachersList += '</ul>';
                }
                
                // Build students list
                let studentsList = '<p class="text-muted mb-0">No students enrolled</p>';
                if (branch.students && branch.students.length > 0) {
                    studentsList = '<ul class="list-group list-group-flush" style="max-height: 150px; overflow-y: auto;">';
                    branch.students.forEach(s => {
                        studentsList += `<li class="list-group-item py-1 px-0 border-0"><small><i class="bi bi-mortarboard me-1"></i>${s.name} <span class="text-muted">(${s.email})</span></small></li>`;
                    });
                    if (branch.student_count > 50) {
                        studentsList += `<li class="list-group-item py-1 px-0 border-0 text-muted"><small><em>...and ${branch.student_count - 50} more</em></small></li>`;
                    }
                    studentsList += '</ul>';
                }
                
                // Build sections list
                let sectionsList = '<p class="text-muted mb-0">No sections available</p>';
                if (branch.sections && branch.sections.length > 0) {
                    sectionsList = '<ul class="list-group list-group-flush" style="max-height: 150px; overflow-y: auto;">';
                    branch.sections.forEach(s => {
                        sectionsList += `<li class="list-group-item py-1 px-0 border-0"><small><i class="bi bi-collection me-1"></i>${s.section_name} <span class="badge bg-secondary">${s.program_code || 'N/A'}</span> <span class="text-muted">(${s.student_count} students)</span></small></li>`;
                    });
                    sectionsList += '</ul>';
                }
                
                let html = `
                    <div class="mb-3">
                        <h6 class="text-muted">Branch Information</h6>
                        <p><strong>Name:</strong> ${branch.name}</p>
                        <p><strong>Address:</strong> ${branch.address || 'N/A'}</p>
                        <p><strong>Contact:</strong> ${branch.contact_number || 'N/A'}</p>
                    </div>
                    <hr>
                    <div class="row text-center mb-4">
                        <div class="col-md-3">
                            <div class="border rounded p-3">
                                <h4 class="text-primary mb-0">${branch.student_count || 0}</h4>
                                <small class="text-muted">Students</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border rounded p-3">
                                <h4 class="text-info mb-0">${branch.teacher_count || 0}</h4>
                                <small class="text-muted">Teachers</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border rounded p-3">
                                <h4 class="text-success mb-0">${branch.section_count || 0}</h4>
                                <small class="text-muted">Sections</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border rounded p-3">
                                <h4 class="text-warning mb-0">${branch.subject_count || 0}</h4>
                                <small class="text-muted">Subjects</small>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="card">
                                <div class="card-header bg-info text-white py-2">
                                    <h6 class="mb-0"><i class="bi bi-person-badge"></i> Teachers</h6>
                                </div>
                                <div class="card-body py-2">
                                    ${teachersList}
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="card">
                                <div class="card-header bg-primary text-white py-2">
                                    <h6 class="mb-0"><i class="bi bi-mortarboard"></i> Students</h6>
                                </div>
                                <div class="card-body py-2">
                                    ${studentsList}
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header bg-success text-white py-2">
                                    <h6 class="mb-0"><i class="bi bi-collection"></i> Sections</h6>
                                </div>
                                <div class="card-body py-2">
                                    ${sectionsList}
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                document.getElementById('branchDetailsContent').innerHTML = html;
                document.getElementById('branchDetailsTitle').textContent = branch.name + ' - Details';
                const modal = new bootstrap.Modal(document.getElementById('branchDetailsModal'));
                modal.show();
            } else {
                showAlert(data.message || 'Unable to load branch details', 'danger');
            }
        })
        .catch(() => showAlert('Error loading branch details', 'danger'));
}

// Add Policy function
$('#addPolicyForm').on('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    fetch(BASE_URL + 'modules/school_admin/process/add_policy.php', {
        method: 'POST', body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            showAlert('Policy added successfully!', 'success');
            setTimeout(() => location.reload(), 1500);
        } else showAlert(data.message, 'danger');
    })
    .catch(() => showAlert('Error adding policy', 'danger'));
});

document.addEventListener('DOMContentLoaded', () => {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.forEach((tooltipTriggerEl) => {
        new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
