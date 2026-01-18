<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_STUDENT) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "My Profile";
$student_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $contact_no = trim($_POST['contact_no'] ?? '');
    $address = trim($_POST['address'] ?? '');
    
    $stmt = $conn->prepare("UPDATE user_profiles SET contact_no = ?, address = ? WHERE user_id = ?");
    $stmt->bind_param("ssi", $contact_no, $address, $student_id);
    
    if ($stmt->execute()) {
        $message = "Profile updated successfully!";
    } else {
        $error = "Failed to update profile.";
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Get current password hash
    $user = $conn->query("SELECT password FROM users WHERE id = $student_id")->fetch_assoc();
    
    if (!password_verify($current_password, $user['password'])) {
        $error = "Current password is incorrect.";
    } elseif (strlen($new_password) < 6) {
        $error = "New password must be at least 6 characters.";
    } elseif ($new_password !== $confirm_password) {
        $error = "New passwords do not match.";
    } else {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed, $student_id);
        
        if ($stmt->execute()) {
            $message = "Password changed successfully!";
        } else {
            $error = "Failed to change password.";
        }
    }
}

// Get current academic year
$current_ay = $conn->query("SELECT id, year_name FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$current_ay_id = $current_ay['id'] ?? 0;

// Get user and profile info
$user_info = $conn->query("
    SELECT u.*, up.*,
           b.name as branch_name,
           COALESCE(p.program_name, ss.strand_name) as program_name,
           COALESCE(p.program_code, ss.strand_code) as program_code,
           st.student_no
    FROM users u
    LEFT JOIN user_profiles up ON u.id = up.user_id
    LEFT JOIN branches b ON up.branch_id = b.id
    LEFT JOIN students st ON u.id = st.user_id
    LEFT JOIN programs p ON st.course_id = p.id
    LEFT JOIN shs_strands ss ON st.course_id = ss.id
    WHERE u.id = $student_id
")->fetch_assoc();

// Get section info
$section_info = $conn->query("
    SELECT s.*, 
           COALESCE(p.program_name, ss.strand_name) as program_name,
           COALESCE(pyl.year_name, sgl.grade_name) as year_level
    FROM section_students stu
    INNER JOIN sections s ON stu.section_id = s.id
    LEFT JOIN programs p ON s.program_id = p.id
    LEFT JOIN shs_strands ss ON s.shs_strand_id = ss.id
    LEFT JOIN program_year_levels pyl ON s.year_level_id = pyl.id
    LEFT JOIN shs_grade_levels sgl ON s.shs_grade_level_id = sgl.id
    WHERE stu.student_id = $student_id AND stu.status = 'active' AND s.academic_year_id = $current_ay_id
    LIMIT 1
")->fetch_assoc();

include '../../includes/header.php';
?>

<div class="wrapper">
    <?php include '../../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1"><i class="bi bi-person-circle text-primary me-2"></i>My Profile</h4>
                <small class="text-muted">View and manage your profile information</small>
            </div>
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

        <div class="row">
            <!-- Profile Info Card -->
            <div class="col-lg-4 mb-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center mb-3" 
                             style="width: 100px; height: 100px; font-size: 2.5rem;">
                            <?php echo strtoupper(substr($user_info['first_name'], 0, 1) . substr($user_info['last_name'], 0, 1)); ?>
                        </div>
                        <h4 class="fw-bold mb-1">
                            <?php echo htmlspecialchars($user_info['first_name'] . ' ' . $user_info['last_name']); ?>
                        </h4>
                        <p class="text-muted mb-3"><?php echo htmlspecialchars($user_info['email']); ?></p>
                        
                        <?php if ($section_info): ?>
                        <div class="border-top pt-3">
                            <div class="badge bg-primary mb-2"><?php echo htmlspecialchars($section_info['section_name']); ?></div>
                            <p class="small text-muted mb-1"><?php echo htmlspecialchars($section_info['program_name']); ?></p>
                            <p class="small text-muted mb-0"><?php echo htmlspecialchars($section_info['year_level']); ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <div class="border-top pt-3 mt-3">
                            <p class="small text-muted mb-1">
                                <i class="bi bi-building me-1"></i>
                                <?php echo htmlspecialchars($user_info['branch_name'] ?? 'Not assigned'); ?>
                            </p>
                            <p class="small text-muted mb-0">
                                <i class="bi bi-calendar me-1"></i>
                                Member since <?php echo date('F Y', strtotime($user_info['created_at'] ?? 'now')); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Profile Details -->
            <div class="col-lg-8 mb-4">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 fw-bold"><i class="bi bi-person-lines-fill text-info me-2"></i>Personal Information</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-muted small">First Name</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user_info['first_name'] ?? ''); ?>" readonly>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-muted small">Last Name</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user_info['last_name'] ?? ''); ?>" readonly>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-muted small">Email Address</label>
                                    <input type="email" class="form-control" value="<?php echo htmlspecialchars($user_info['email'] ?? ''); ?>" readonly>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-muted small">Contact Number</label>
                                    <input type="text" class="form-control" name="contact_no" value="<?php echo htmlspecialchars($user_info['contact_no'] ?? ''); ?>" placeholder="Enter contact number">
                                </div>
                                <div class="col-12 mb-3">
                                    <label class="form-label text-muted small">Address</label>
                                    <textarea class="form-control" name="address" rows="2" placeholder="Enter your address"><?php echo htmlspecialchars($user_info['address'] ?? ''); ?></textarea>
                                </div>
                            </div>
                            <button type="submit" name="update_profile" class="btn btn-primary">
                                <i class="bi bi-save me-1"></i> Save Changes
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Change Password -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 fw-bold"><i class="bi bi-shield-lock text-warning me-2"></i>Change Password</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label text-muted small">Current Password</label>
                                    <input type="password" class="form-control" name="current_password" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label text-muted small">New Password</label>
                                    <input type="password" class="form-control" name="new_password" required minlength="6">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label text-muted small">Confirm New Password</label>
                                    <input type="password" class="form-control" name="confirm_password" required>
                                </div>
                            </div>
                            <button type="submit" name="change_password" class="btn btn-warning">
                                <i class="bi bi-key me-1"></i> Change Password
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Academic Info -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold"><i class="bi bi-mortarboard text-success me-2"></i>Academic Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label text-muted small">Student ID</label>
                        <p class="fw-bold mb-0"><?php echo htmlspecialchars($user_info['student_id'] ?? 'N/A'); ?></p>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label text-muted small">Program</label>
                        <p class="fw-bold mb-0"><?php echo htmlspecialchars($user_info['program_name'] ?? 'N/A'); ?></p>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label text-muted small">Section</label>
                        <p class="fw-bold mb-0"><?php echo htmlspecialchars($section_info['section_name'] ?? 'N/A'); ?></p>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label text-muted small">Year Level</label>
                        <p class="fw-bold mb-0"><?php echo htmlspecialchars($section_info['year_level'] ?? 'N/A'); ?></p>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label text-muted small">Branch</label>
                        <p class="fw-bold mb-0"><?php echo htmlspecialchars($user_info['branch_name'] ?? 'N/A'); ?></p>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label text-muted small">Academic Year</label>
                        <p class="fw-bold mb-0"><?php echo htmlspecialchars($current_ay['year_name'] ?? 'N/A'); ?></p>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label text-muted small">Account Status</label>
                        <p class="mb-0">
                            <span class="badge bg-<?php echo $user_info['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                <?php echo ucfirst($user_info['status'] ?? 'active'); ?>
                            </span>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
