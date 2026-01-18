<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_BRANCH_ADMIN) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Manage Registrars";
$branch_admin_id = $_SESSION['user_id'];

// Get branch admin's branch
$admin_profile = $conn->query("SELECT branch_id FROM user_profiles WHERE user_id = $branch_admin_id")->fetch_assoc();
$branch_id = $admin_profile['branch_id'] ?? 0;

// Get branch info
$branch = $conn->query("SELECT * FROM branches WHERE id = $branch_id")->fetch_assoc();

$message = '';
$error = '';

// Handle add registrar
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_registrar'])) {
    $email = clean_input($_POST['email']);
    $password = $_POST['password'];
    $first_name = clean_input($_POST['first_name']);
    $last_name = clean_input($_POST['last_name']);
    $contact_no = clean_input($_POST['contact_no'] ?? '');
    
    // Validate
    if (empty($email) || empty($password) || empty($first_name) || empty($last_name)) {
        $error = "All required fields must be filled.";
    } else {
        // Check if email exists
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = "Email already exists.";
        } else {
            $conn->begin_transaction();
            try {
                // Create user
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (email, password, status) VALUES (?, ?, 'active')");
                $stmt->bind_param("ss", $email, $hashed_password);
                $stmt->execute();
                $new_user_id = $conn->insert_id;
                
                // Assign role
                $role_id = ROLE_REGISTRAR;
                $stmt = $conn->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
                $stmt->bind_param("ii", $new_user_id, $role_id);
                $stmt->execute();
                
                // Create profile
                $stmt = $conn->prepare("INSERT INTO user_profiles (user_id, first_name, last_name, contact_no, branch_id) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("isssi", $new_user_id, $first_name, $last_name, $contact_no, $branch_id);
                $stmt->execute();
                
                $conn->commit();
                $message = "Registrar account created successfully!";
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Failed to create registrar account: " . $e->getMessage();
            }
        }
    }
}

// Handle toggle status
if (isset($_GET['toggle']) && isset($_GET['id'])) {
    $user_id = (int)$_GET['id'];
    $new_status = $_GET['toggle'] == 'activate' ? 'active' : 'inactive';
    
    // Verify this registrar belongs to this branch
    $verify = $conn->query("
        SELECT u.id FROM users u 
        INNER JOIN user_profiles up ON u.id = up.user_id 
        INNER JOIN user_roles ur ON u.id = ur.user_id
        WHERE u.id = $user_id AND ur.role_id = " . ROLE_REGISTRAR . " AND up.branch_id = $branch_id
    ");
    
    if ($verify->num_rows > 0) {
        $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $user_id);
        if ($stmt->execute()) {
            $message = "Registrar account " . ($new_status ? "activated" : "deactivated") . " successfully!";
        }
    } else {
        $error = "Invalid registrar account.";
    }
}

// Get registrars for this branch
$registrars = $conn->query("
    SELECT u.id, u.email, u.status, u.created_at,
           up.first_name, up.last_name, up.contact_no
    FROM users u
    INNER JOIN user_profiles up ON u.id = up.user_id
    INNER JOIN user_roles ur ON u.id = ur.user_id
    WHERE ur.role_id = " . ROLE_REGISTRAR . " AND up.branch_id = $branch_id
    ORDER BY up.last_name, up.first_name
");

include '../../includes/header.php';
?>

<div class="wrapper">
    <?php include '../../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1"><i class="bi bi-person-badge me-2"></i>Manage Registrars</h4>
                <small class="text-muted">Branch: <?php echo htmlspecialchars($branch['name'] ?? 'Unknown'); ?></small>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRegistrarModal">
                <i class="bi bi-plus-circle me-1"></i> Add Registrar
            </button>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle me-2"></i><?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-circle me-2"></i><?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Registrars List -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold"><i class="bi bi-people me-2"></i>Registrar Accounts</h5>
            </div>
            <div class="card-body p-0">
                <?php if ($registrars->num_rows == 0): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-person-x display-4"></i>
                    <p class="mt-2">No registrar accounts found for this branch.</p>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addRegistrarModal">
                        <i class="bi bi-plus-circle me-1"></i> Add First Registrar
                    </button>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Contact</th>
                                <th class="text-center">Status</th>
                                <th>Created</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($reg = $registrars->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="rounded-circle bg-info text-white d-flex align-items-center justify-content-center me-2" style="width: 40px; height: 40px;">
                                            <?php echo strtoupper(substr($reg['first_name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <strong><?php echo htmlspecialchars($reg['first_name'] . ' ' . $reg['last_name']); ?></strong>
                                            <br><small class="text-muted">Registrar</small>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($reg['email']); ?></td>
                                <td><?php echo htmlspecialchars($reg['contact_no'] ?? '-'); ?></td>
                                <td class="text-center">
                                    <?php if ($reg['status'] == 'active'): ?>
                                    <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                    <span class="badge bg-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($reg['created_at'])); ?></td>
                                <td class="text-center">
                                    <?php if ($reg['status'] == 'active'): ?>
                                    <a href="?toggle=deactivate&id=<?php echo $reg['id']; ?>" 
                                       class="btn btn-sm btn-outline-danger" 
                                       onclick="return confirm('Are you sure you want to deactivate this account?');">
                                        <i class="bi bi-x-circle"></i> Deactivate
                                    </a>
                                    <?php else: ?>
                                    <a href="?toggle=activate&id=<?php echo $reg['id']; ?>" 
                                       class="btn btn-sm btn-outline-success">
                                        <i class="bi bi-check-circle"></i> Activate
                                    </a>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-outline-primary" onclick="resetPassword(<?php echo $reg['id']; ?>)">
                                        <i class="bi bi-key"></i> Reset Password
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add Registrar Modal -->
<div class="modal fade" id="addRegistrarModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Add Registrar Account</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">First Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="first_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Last Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="last_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email Address <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="password" required minlength="6">
                        <small class="text-muted">Minimum 6 characters</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contact Number</label>
                        <input type="text" class="form-control" name="contact_no">
                    </div>
                    <div class="alert alert-info mb-0">
                        <i class="bi bi-info-circle me-2"></i>
                        This registrar will be assigned to <strong><?php echo htmlspecialchars($branch['name'] ?? 'this branch'); ?></strong>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_registrar" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-1"></i> Create Account
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function resetPassword(userId) {
    if (confirm('Reset password to default (password123)?')) {
        fetch('process/reset_registrar_password.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({user_id: userId})
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('Password reset successfully! New password: password123');
            } else {
                alert('Error: ' + data.message);
            }
        });
    }
}
</script>

<?php include '../../includes/footer.php'; ?>
