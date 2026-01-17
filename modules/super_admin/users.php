<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SUPER_ADMIN) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "User Management";
$users_query = "
    SELECT u.id, u.email, u.status, u.created_at,
           CONCAT(up.first_name, ' ', up.last_name) as full_name,
           up.contact_no,
           r.name as role_name
    FROM users u
    LEFT JOIN user_profiles up ON u.id = up.user_id
    LEFT JOIN user_roles ur ON u.id = ur.user_id
    LEFT JOIN roles r ON ur.role_id = r.id
    ORDER BY u.created_at DESC
";
$users_result = $conn->query($users_query);
$roles_result = $conn->query("SELECT id, name FROM roles ORDER BY id");

include '../../includes/header.php';
?>

<style>
    /* --- SCROLLBAR CONTROL --- */
    html, body {
        height: 100%;
        margin: 0;
        overflow: hidden; 
    }

    .wrapper {
        display: flex;
        height: 100vh; 
        width: 100vw;
    }

    #content {
        flex: 1;
        display: flex;
        flex-direction: column;
        height: 100vh;
        overflow: hidden; 
        background-color: #f4f7f6;
    }

    /* Scrollable Container for the Table */
    .table-scroll-container {
        flex: 1;
        overflow-y: auto; 
        padding: 0 30px 30px 30px;
    }

    /* --- MODERN STYLING --- */
    :root { --elms-maroon: #800000; }
    
    .content-header { 
        background: white; 
        padding: 20px 30px; 
        border-bottom: 1px solid #eee; 
        margin-bottom: 20px; 
    }
    
    .main-card { 
        background: white; 
        border: none; 
        border-radius: 15px; 
        box-shadow: 0 10px 30px rgba(0,0,0,0.05); 
        height: 100%; /* Take up available space */
    }
    
    .table thead th { 
        position: sticky; 
        top: 0;
        background-color: #fcfcfc; 
        z-index: 10;
        text-transform: uppercase; 
        font-size: 0.75rem; 
        letter-spacing: 1px; 
        font-weight: 700; 
        color: #777; 
        border-bottom: 2px solid #f1f1f1;
        padding: 15px 20px;
    }

    .table tbody td { padding: 15px 20px; vertical-align: middle; }
    
    .badge-status { padding: 6px 12px; border-radius: 6px; font-weight: 600; font-size: 0.75rem; }
    .badge-active { background: #e6fcf5; color: #0ca678; }
    .badge-inactive { background: #fff5f5; color: #fa5252; }
    .badge-role { background: #f1f3f5; color: #495057; border: 1px solid #dee2e6; }

    .btn-maroon { background: var(--elms-maroon); color: white; border-radius: 8px; font-weight: 600; border: none; transition: 0.3s; }
    .btn-maroon:hover { background: #600000; color: white; transform: translateY(-2px); }
    
    .action-btn { width: 35px; height: 35px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; transition: 0.2s; border: none; background: #f8f9fa; }
    .action-btn:hover { background: #eee; }
</style>

<div class="wrapper">
    <?php include '../../includes/sidebar.php'; ?>

    <div id="content">
        <!-- Static Header (No Scroll) -->
        <div class="content-header d-flex justify-content-between align-items-center animate__animated animate__fadeInDown">
            <div>
                <h4 style="color: #003366; font-weight: 700; margin:0;"><i class="bi bi-people-fill me-2"></i> User Management</h4>
                <p class="text-muted small mb-0">System access control</p>
            </div>
            <button class="btn btn-maroon py-2 px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="bi bi-person-plus me-2"></i> Add New User
            </button>
        </div>

        <!-- Scrollable Body Area -->
        <div class="table-scroll-container animate__animated animate__fadeInUp">
            
            <div id="alertContainer"></div>

            <div class="main-card">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="usersTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name / Email</th>
                                <th>Role</th>
                                <th>Contact</th>
                                <th>Status</th>
                                <th>Date Created</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($user = $users_result->fetch_assoc()): ?>
                            <tr>
                                <td class="fw-bold text-muted">#<?php echo $user['id']; ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($user['full_name'] ?? 'N/A'); ?></div>
                                    <div class="small text-muted"><?php echo htmlspecialchars($user['email']); ?></div>
                                </td>
                                <td>
                                    <span class="badge badge-status badge-role"><?php echo htmlspecialchars($user['role_name'] ?? 'No Role'); ?></span>
                                </td>
                                <td class="text-muted"><?php echo htmlspecialchars($user['contact_no'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php if ($user['status'] == 'active'): ?>
                                        <span class="badge badge-status badge-active">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-status badge-inactive">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted small"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                <td class="text-end">
                                    <button class="btn action-btn me-1" onclick="editUser(<?php echo $user['id']; ?>)" title="Edit">
                                        <i class="bi bi-pencil-square text-warning"></i>
                                    </button>
                                    <button class="btn action-btn" onclick="deleteUser(<?php echo $user['id']; ?>)" title="Delete">
                                        <i class="bi bi-trash3 text-danger"></i>
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

<!-- Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px; overflow: hidden;">
            <div class="modal-header p-4 text-white" style="background-color: var(--elms-maroon);">
                <h5 class="modal-title fw-bold">Register New User</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addUserForm">
                <div class="modal-body p-4 bg-light">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">First Name *</label>
                            <input type="text" class="form-control" name="first_name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Last Name *</label>
                            <input type="text" class="form-control" name="last_name" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Email *</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Role *</label>
                            <select class="form-select" name="role_id" required>
                                <option value="">-- Choose --</option>
                                <?php $roles_result->data_seek(0); while ($role = $roles_result->fetch_assoc()): ?>
                                    <option value="<?php echo $role['id']; ?>"><?php echo htmlspecialchars($role['name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Contact No.</label>
                            <input type="text" class="form-control" name="contact_no">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Address</label>
                            <textarea class="form-control" name="address" rows="2"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Password *</label>
                            <input type="password" class="form-control" name="password" required minlength="6">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Confirm Password *</label>
                            <input type="password" class="form-control" name="confirm_password" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer p-4 border-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-maroon px-4">Create Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('addUserForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    if (formData.get('password') !== formData.get('confirm_password')) {
        showAlert('Passwords do not match!', 'danger');
        return;
    }
    try {
        const response = await fetch('process/add_user.php', { method: 'POST', body: formData });
        const data = await response.json();
        if (data.status === 'success') {
            showAlert(data.message, 'success');
            setTimeout(() => { location.reload(); }, 1500);
        } else {
            showAlert(data.message, 'danger');
        }
    } catch (error) {
        showAlert('An error occurred. Please try again.', 'danger');
    }
});

function showAlert(message, type) {
    const alertHtml = `<div class="alert alert-${type} alert-dismissible fade show border-0 shadow-sm" role="alert">${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
    document.getElementById('alertContainer').innerHTML = alertHtml;
}

function deleteUser(userId) {
    if (confirm('Are you sure you want to delete this user?')) {
        alert('Delete user ID: ' + userId);
    }
}

function editUser(userId) {
    alert('Edit user ID: ' + userId);
}
</script>
</body>
</html>