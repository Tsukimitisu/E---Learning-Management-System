<?php
require_once '../../config/init.php';
require_once '../../includes/email_helper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_BRANCH_ADMIN) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Manage Registrars";
$branch_admin_id = $_SESSION['user_id'];

// Get branch admin's branch (Logic preserved)
$admin_profile = $conn->query("SELECT branch_id FROM user_profiles WHERE user_id = $branch_admin_id")->fetch_assoc();
$branch_id = $admin_profile['branch_id'] ?? 0;

// Get branch info
$branch = $conn->query("SELECT * FROM branches WHERE id = $branch_id")->fetch_assoc();

$message = '';
$error = '';

// Handle add registrar (Logic preserved)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_registrar'])) {
    $email = clean_input($_POST['email']);
    $password = $_POST['password'];
    $first_name = clean_input($_POST['first_name']);
    $last_name = clean_input($_POST['last_name']);
    $contact_no = clean_input($_POST['contact_no'] ?? '');
    
    if (empty($email) || empty($password) || empty($first_name) || empty($last_name)) {
        $error = "All required fields must be filled.";
    } else {
        // Validate email format and domain existence before creating account
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format.";
        } else {
            $email_validation = validate_email_exists($email);
            if (!$email_validation['valid']) {
                $error = $email_validation['message'];
            }
        }

        if (empty($error)) {
            $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $check->bind_param("s", $email);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $error = "Email already exists.";
            } else {
                $conn->begin_transaction();
                try {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("INSERT INTO users (email, password, status) VALUES (?, ?, 'active')");
                    $stmt->bind_param("ss", $email, $hashed_password);
                    $stmt->execute();
                    $new_user_id = $conn->insert_id;
                    
                    $role_id = ROLE_REGISTRAR;
                    $stmt = $conn->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
                    $stmt->bind_param("ii", $new_user_id, $role_id);
                    $stmt->execute();
                    
                    $stmt = $conn->prepare("INSERT INTO user_profiles (user_id, first_name, last_name, contact_no, branch_id) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("isssi", $new_user_id, $first_name, $last_name, $contact_no, $branch_id);
                    $stmt->execute();
                    
                    $conn->commit();
                    $message = "Registrar account created successfully!";

                    // Handle email notification
                    if (isset($_POST['send_email']) && $_POST['send_email'] === '1') {
                        $email_result = send_account_credentials($email, $first_name, $last_name, $password, 'Registrar', $branch_admin_id);
                        if ($email_result['success']) {
                            $message .= " Email verification sent.";
                        } else {
                            $error = "Account created, but email failed: " . ($email_result['message'] ?? 'Service unavailable');
                        }
                    }
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = "Failed to create registrar account: " . $e->getMessage();
                }
            }
        }
    }
}

// Handle toggle status (Logic preserved)
if (isset($_GET['toggle']) && isset($_GET['id'])) {
    $user_id = (int)$_GET['id'];
    $new_status = $_GET['toggle'] == 'activate' ? 'active' : 'inactive';
    
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
            $message = "Registrar account updated successfully!";
        }
    } else { $error = "Invalid registrar account."; }
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

<link rel="stylesheet" href="css/registrars.css">

<div class="main-content-body animate__animated animate__fadeIn">
    
    <!-- 1. PAGE HEADER -->
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center animate__animated animate__fadeInDown">
        <div class="mb-2 mb-md-0">
            <h4 class="fw-bold mb-0" style="color: var(--blue);">
                <i class="bi bi-person-badge-fill me-2 text-maroon"></i>Manage Registrars
            </h4>
            <p class="text-muted small mb-0">Branch Office: <span class="fw-bold text-dark"><?php echo htmlspecialchars($branch['name'] ?? 'Unknown'); ?></span></p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-maroon btn-sm px-4 rounded-pill shadow-sm" data-bs-toggle="modal" data-bs-target="#addRegistrarModal">
                <i class="bi bi-plus-circle me-1"></i> Add Registrar
            </button>
        </div>
    </div>

    <!-- 2. ALERTS -->
    <?php if ($message): ?>
        <div class="alert alert-success border-0 shadow-sm animate__animated animate__headShake">
            <i class="bi bi-check-circle-fill me-2"></i> <?php echo $message; ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger border-0 shadow-sm animate__animated animate__shakeX">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <!-- 3. REGISTRARS LIST -->
    <div class="content-card">
        <div class="card-header-modern bg-white">
            <i class="bi bi-people me-2"></i> Authorized Registrar Accounts
        </div>
        <div class="card-body p-0">
            <?php if ($registrars->num_rows == 0): ?>
            <div class="empty-state">
                <i class="bi bi-person-x"></i>
                <p class="fw-bold">No registrar accounts found for this branch.</p>
                <button class="btn btn-outline-primary btn-sm rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addRegistrarModal">
                    Create First Account
                </button>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover table-modern mb-0">
                    <thead>
                        <tr>
                            <th>Account Name</th>
                            <th>Email Address</th>
                            <th>Contact No.</th>
                            <th class="text-center">Status</th>
                            <th>Date Created</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($reg = $registrars->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar-circle-sm me-3">
                                        <?php echo strtoupper(substr($reg['first_name'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($reg['first_name'] . ' ' . $reg['last_name']); ?></div>
                                        <small class="text-muted" style="font-size: 0.65rem;">OFFICE REGISTRAR</small>
                                    </div>
                                </div>
                            </td>
                            <td><small><?php echo htmlspecialchars($reg['email']); ?></small></td>
                            <td><?php echo htmlspecialchars($reg['contact_no'] ?? '-'); ?></td>
                            <td class="text-center">
                                <span class="status-pill <?php echo $reg['status'] == 'active' ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo ucfirst($reg['status']); ?>
                                </span>
                            </td>
                            <td><small><?php echo date('M d, Y', strtotime($reg['created_at'])); ?></small></td>
                            <td class="text-end">
                                <div class="btn-group shadow-sm">
                                    <?php if ($reg['status'] == 'active'): ?>
                                    <a href="?toggle=deactivate&id=<?php echo $reg['id']; ?>" 
                                       class="btn btn-sm btn-white border text-danger" 
                                       onclick="return confirm('Suspend this registrar account?');" title="Deactivate">
                                        <i class="bi bi-person-x-fill"></i>
                                    </a>
                                    <?php else: ?>
                                    <a href="?toggle=activate&id=<?php echo $reg['id']; ?>" 
                                       class="btn btn-sm btn-white border text-success" title="Activate">
                                        <i class="bi bi-person-check-fill"></i>
                                    </a>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-white border text-primary" onclick="resetPassword(<?php echo $reg['id']; ?>)" title="Reset Password">
                                        <i class="bi bi-key-fill"></i>
                                    </button>
                                </div>
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

<!-- Add Registrar Modal -->
<div class="modal fade" id="addRegistrarModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-maroon text-dark py-3">
                <h5 class="modal-title fs-6 fw-bold"><i class="bi bi-person-plus me-2"></i>Add Registrar Account</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?php echo csrf_field(); ?>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-uppercase opacity-75">First Name *</label>
                            <input type="text" class="form-control" name="first_name" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-uppercase opacity-75">Last Name *</label>
                            <input type="text" class="form-control" name="last_name" required>
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label small fw-bold text-uppercase opacity-75">Email Address *</label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                    <div class="mt-3">
                        <label class="form-label small fw-bold text-uppercase opacity-75">Temporary Password *</label>
                        <input type="password" class="form-control" name="password" required minlength="6">
                        <small class="text-muted">Will require update on first login.</small>
                    </div>
                    <div class="mt-3">
                        <label class="form-label small fw-bold text-uppercase opacity-75">Contact Number</label>
                        <input type="text" class="form-control" name="contact_no">
                    </div>
                    <div class="mt-3">
                        <div class="form-check form-switch p-3 bg-light rounded-3 border">
                            <input class="form-check-input ms-0 me-2" type="checkbox" name="send_email" value="1" id="sendEmail" checked>
                            <label class="form-check-label small fw-bold text-uppercase opacity-75" for="sendEmail">
                                <i class="bi bi-envelope-at me-1 text-maroon"></i> Send Welcome Email
                            </label>
                            <div class="form-text mt-1" style="font-size: 0.7rem;">Sends login credentials to the registrar's email address.</div>
                        </div>
                    </div>
                    <div class="mt-4 p-3 bg-light rounded-3 border-start border-4 border-info">
                        <small class="text-muted d-flex">
                            <i class="bi bi-info-circle-fill text-info me-2"></i>
                            Account will be restricted to <strong><?php echo htmlspecialchars($branch['name'] ?? 'current branch'); ?></strong> operations.
                        </small>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-light btn-sm px-4 fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_registrar" class="btn btn-maroon btn-sm px-4 fw-bold">Create Account</button>
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
                Swal.fire('Success', 'Password reset to: password123', 'success');
            } else {
                Swal.fire('Error', data.message, 'error');
            }
        });
    }
}
</script>

<?php include '../../includes/footer.php'; ?>