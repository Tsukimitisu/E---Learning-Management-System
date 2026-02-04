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

<link rel="stylesheet" href="css/users.css">

<!-- Main Container that fits inside header's .main-content-body -->
<div class="user-management-container animate__animated animate__fadeIn">
    
    <!-- Static Header (No Scroll) -->
    <div class="content-header d-flex justify-content-between align-items-center">
        <div>
            <h4 style="color: #003366; font-weight: 700; margin:0;"><i class="bi bi-people-fill me-2"></i> User Management</h4>
            <p class="text-muted small mb-0">System access control</p>
        </div>
        <button class="btn btn-maroon py-2 px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="bi bi-person-plus me-2"></i> Add New User
        </button>
    </div>

    <!-- Scrollable Body Area -->
    <div class="table-scroll-container">
        
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

<!-- Modal: Add User -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px; overflow: hidden;">
            <div class="modal-header p-4 text-white" style="background-color: #800000;">
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

<!-- Modal: Edit User -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px; overflow: hidden;">
            <div class="modal-header p-4 text-white" style="background-color: #003366;">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Edit User</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="editUserForm">
                <input type="hidden" name="user_id" id="edit_user_id">
                <div class="modal-body p-4 bg-light">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">First Name *</label>
                            <input type="text" class="form-control" name="first_name" id="edit_first_name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Last Name *</label>
                            <input type="text" class="form-control" name="last_name" id="edit_last_name" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Email *</label>
                            <input type="email" class="form-control" name="email" id="edit_email" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Role *</label>
                            <select class="form-select" name="role_id" id="edit_role_id" required>
                                <option value="">-- Choose --</option>
                                <?php $roles_result->data_seek(0); while ($role = $roles_result->fetch_assoc()): ?>
                                    <option value="<?php echo $role['id']; ?>"><?php echo htmlspecialchars($role['name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Status *</label>
                            <select class="form-select" name="status" id="edit_status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Contact No.</label>
                            <input type="text" class="form-control" name="contact_no" id="edit_contact_no">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Address</label>
                            <input type="text" class="form-control" name="address" id="edit_address">
                        </div>
                        <div class="col-12">
                            <hr class="my-2">
                            <p class="text-muted small mb-2"><i class="bi bi-info-circle me-1"></i>Leave password blank to keep current password</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">New Password</label>
                            <input type="password" class="form-control" name="password" id="edit_password" minlength="6">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Confirm Password</label>
                            <input type="password" class="form-control" name="confirm_password" id="edit_confirm_password">
                        </div>
                    </div>
                </div>
                <div class="modal-footer p-4 border-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-maroon px-4">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Delete Confirmation -->
<div class="modal fade" id="deleteUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px; overflow: hidden;">
            <div class="modal-header p-4 text-white bg-danger">
                <h5 class="modal-title fw-bold"><i class="bi bi-exclamation-triangle me-2"></i>Delete User</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <i class="bi bi-person-x text-danger" style="font-size: 4rem;"></i>
                <h5 class="mt-3 mb-2">Are you sure?</h5>
                <p class="text-muted mb-0">You are about to delete user: <strong id="delete_user_email"></strong></p>
                <p class="text-danger small mt-2"><i class="bi bi-exclamation-circle me-1"></i>This action cannot be undone!</p>
                <input type="hidden" id="delete_user_id">
            </div>
            <div class="modal-footer p-4 border-0 justify-content-center">
                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger px-4" onclick="confirmDelete()">
                    <i class="bi bi-trash3 me-1"></i>Delete User
                </button>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<!-- Custom Scripts only (Libraries loaded in footer) -->
<script>
// Add User Form Submit
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

// Edit User Form Submit
document.getElementById('editUserForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const password = formData.get('password');
    const confirmPassword = formData.get('confirm_password');
    
    if (password && password !== confirmPassword) {
        showAlert('Passwords do not match!', 'danger');
        return;
    }
    
    try {
        const response = await fetch('process/edit_user.php', { method: 'POST', body: formData });
        const data = await response.json();
        if (data.status === 'success') {
            bootstrap.Modal.getInstance(document.getElementById('editUserModal')).hide();
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

// Edit User - Fetch and populate data
async function editUser(userId) {
    try {
        const response = await fetch(`process/get_user.php?id=${userId}`);
        const data = await response.json();
        
        if (data.status === 'success') {
            const user = data.user;
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_first_name').value = user.first_name || '';
            document.getElementById('edit_last_name').value = user.last_name || '';
            document.getElementById('edit_email').value = user.email || '';
            document.getElementById('edit_role_id').value = user.role_id || '';
            document.getElementById('edit_status').value = user.status || 'active';
            document.getElementById('edit_contact_no').value = user.contact_no || '';
            document.getElementById('edit_address').value = user.address || '';
            document.getElementById('edit_password').value = '';
            document.getElementById('edit_confirm_password').value = '';
            
            // Show modal
            new bootstrap.Modal(document.getElementById('editUserModal')).show();
        } else {
            showAlert(data.message, 'danger');
        }
    } catch (error) {
        showAlert('Failed to load user data.', 'danger');
    }
}

// Delete User - Show confirmation modal
function deleteUser(userId) {
    // Find user email from the table
    const row = document.querySelector(`button[onclick="deleteUser(${userId})"]`).closest('tr');
    const email = row.querySelector('td:nth-child(2) .small').textContent;
    
    document.getElementById('delete_user_id').value = userId;
    document.getElementById('delete_user_email').textContent = email;
    
    new bootstrap.Modal(document.getElementById('deleteUserModal')).show();
}

// Confirm Delete
async function confirmDelete() {
    const userId = document.getElementById('delete_user_id').value;
    
    try {
        const formData = new FormData();
        formData.append('user_id', userId);
        
        const response = await fetch('process/delete_user.php', { method: 'POST', body: formData });
        const data = await response.json();
        
        if (data.status === 'success') {
            bootstrap.Modal.getInstance(document.getElementById('deleteUserModal')).hide();
            showAlert(data.message, 'success');
            setTimeout(() => { location.reload(); }, 1500);
        } else {
            showAlert(data.message, 'danger');
        }
    } catch (error) {
        showAlert('Failed to delete user.', 'danger');
    }
}
</script>
</body>
</html>