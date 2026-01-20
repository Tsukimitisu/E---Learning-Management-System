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

/** 
 * BACKEND LOGIC: PROFILE UPDATE - UNTOUCHED 
 */
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

/** 
 * BACKEND LOGIC: PASSWORD CHANGE - UNTOUCHED 
 */
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
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

/** 
 * BACKEND LOGIC: DATA FETCHING - UNTOUCHED 
 */
$current_ay = $conn->query("SELECT id, year_name FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$user_info = $conn->query("
    SELECT u.*, up.*, b.name as branch_name, 
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
    WHERE stu.student_id = $student_id AND stu.status = 'active' AND s.academic_year_id = " . ($current_ay['id'] ?? 0) . "
    LIMIT 1
")->fetch_assoc();

include '../../includes/header.php';
include '../../includes/sidebar.php'; 
?>

<style>
    /* --- SCROLL & LAYOUT ENGINE --- */
    html, body { height: 100%; margin: 0; overflow: hidden; }
    #content { height: 100vh; display: flex; flex-direction: column; overflow: hidden; }
    .header-fixed-part { flex: 0 0 auto; background: white; padding: 15px 30px; border-bottom: 1px solid #eee; z-index: 10; }
    .body-scroll-part { flex: 1 1 auto; overflow-y: auto; padding: 25px 30px 100px 30px; background-color: #f8f9fa; }

    /* --- FANTASTIC PROFILE UI --- */
    .profile-hero-card {
        background: white; border-radius: 20px; border: none;
        box-shadow: 0 5px 20px rgba(0,0,0,0.05); overflow: hidden;
    }
    .profile-banner-color { background: linear-gradient(135deg, var(--blue) 0%, #001a33 100%); height: 100px; }
    .profile-avatar-container { margin-top: -50px; position: relative; z-index: 2; }
    
    .avatar-big {
        width: 100px; height: 100px; background: var(--maroon); color: white;
        border-radius: 50%; border: 5px solid white; display: flex;
        align-items: center; justify-content: center; font-size: 2.5rem; font-weight: 800;
        margin: 0 auto; box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }

    .form-card {
        background: white; border-radius: 20px; border: none;
        box-shadow: 0 5px 20px rgba(0,0,0,0.05); overflow: hidden;
    }
    .form-header { background: #fcfcfc; padding: 15px 25px; border-bottom: 1px solid #f1f1f1; font-weight: 700; color: var(--blue); text-transform: uppercase; font-size: 0.8rem; letter-spacing: 1px; }

    .btn-maroon-save {
        background-color: var(--maroon); color: white; border: none; border-radius: 10px;
        font-weight: 700; padding: 10px 25px; transition: 0.3s;
    }
    .btn-maroon-save:hover { background-color: #600000; transform: translateY(-2px); color: white; }

    .readonly-field { background-color: #f8f9fa !important; border-color: #eee !important; font-weight: 600; color: #777; }

    .academic-info-box {
        border-left: 4px solid var(--maroon); padding: 10px 15px; background: #fff;
        border-radius: 0 8px 8px 0; height: 100%; box-shadow: 0 2px 5px rgba(0,0,0,0.02);
    }

    @media (max-width: 768px) {
        .header-fixed-part { flex-direction: column; gap: 10px; text-align: center; }
    }
</style>

<!-- Part 1: Fixed Header -->
<div class="header-fixed-part animate__animated animate__fadeInDown">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-0 text-blue"><i class="bi bi-person-circle me-2 text-maroon"></i>My Profile</h4>
            <p class="text-muted small mb-0">Manage your personal and security information</p>
        </div>
        <div class="text-end">
            <span class="badge bg-light text-dark border px-3 py-2 rounded-pill shadow-sm small">
                <i class="bi bi-shield-check me-1 text-success"></i> Secure Session
            </span>
        </div>
    </div>
</div>

<!-- Part 2: Scrollable Body -->
<div class="body-scroll-part">
    
    <!-- Alert Messages -->
    <?php if ($message): ?>
        <div class="alert alert-success border-0 shadow-sm mb-4 animate__animated animate__headShake">
            <i class="bi bi-check-circle-fill me-2"></i><?php echo $message; ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger border-0 shadow-sm mb-4 animate__animated animate__shakeX">
            <i class="bi bi-exclamation-circle-fill me-2"></i><?php echo $error; ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Profile Summary Column -->
        <div class="col-lg-4 animate__animated animate__fadeInLeft">
            <div class="profile-hero-card">
                <div class="profile-banner-color"></div>
                <div class="card-body p-4 text-center">
                    <div class="profile-avatar-container">
                        <div class="avatar-big">
                            <?php echo strtoupper(substr($user_info['first_name'], 0, 1) . substr($user_info['last_name'], 0, 1)); ?>
                        </div>
                    </div>
                    <h4 class="fw-bold mt-3 mb-1 text-dark">
                        <?php echo htmlspecialchars($user_info['first_name'] . ' ' . $user_info['last_name']); ?>
                    </h4>
                    <p class="text-muted small mb-4"><?php echo htmlspecialchars($user_info['email']); ?></p>
                    
                    <div class="d-grid gap-2 text-start mt-4">
                        <div class="p-3 rounded-3 bg-light border">
                            <label class="text-muted small text-uppercase fw-bold d-block mb-1" style="font-size: 0.6rem;">Current Standing</label>
                            <div class="fw-bold text-blue"><?php echo htmlspecialchars($section_info['section_name'] ?? 'No Section'); ?></div>
                            <small class="text-muted"><?php echo htmlspecialchars($section_info['program_name'] ?? ''); ?></small>
                        </div>
                        <div class="p-3 rounded-3 bg-light border">
                            <label class="text-muted small text-uppercase fw-bold d-block mb-1" style="font-size: 0.6rem;">Institution</label>
                            <div class="fw-bold text-dark small"><i class="bi bi-building me-2"></i><?php echo htmlspecialchars($user_info['branch_name'] ?? 'Not assigned'); ?></div>
                            <div class="small opacity-75 mt-1"><i class="bi bi-calendar3 me-2"></i>Joined <?php echo date('F Y', strtotime($user_info['created_at'] ?? 'now')); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Forms Column -->
        <div class="col-lg-8 animate__animated animate__fadeInRight">
            
            <!-- Personal Info Form -->
            <div class="form-card mb-4">
                <div class="form-header"><i class="bi bi-person-lines-fill me-2"></i>Update Personal Details</div>
                <div class="card-body p-4">
                    <form method="POST">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted text-uppercase">First Name</label>
                                <input type="text" class="form-control readonly-field" value="<?php echo htmlspecialchars($user_info['first_name'] ?? ''); ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted text-uppercase">Last Name</label>
                                <input type="text" class="form-control readonly-field" value="<?php echo htmlspecialchars($user_info['last_name'] ?? ''); ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted text-uppercase">Email Address</label>
                                <input type="email" class="form-control readonly-field" value="<?php echo htmlspecialchars($user_info['email'] ?? ''); ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-dark text-uppercase">Contact Number</label>
                                <input type="text" class="form-control border-light shadow-sm" name="contact_no" value="<?php echo htmlspecialchars($user_info['contact_no'] ?? ''); ?>" placeholder="Enter number">
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold text-dark text-uppercase">Permanent Address</label>
                                <textarea class="form-control border-light shadow-sm" name="address" rows="2" placeholder="Street, Barangay, City, Province"><?php echo htmlspecialchars($user_info['address'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        <div class="mt-4 pt-2 border-top">
                            <button type="submit" name="update_profile" class="btn btn-maroon-save shadow-sm">
                                <i class="bi bi-save me-2"></i> Update Profile
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Security Form -->
            <div class="form-card animate__animated animate__fadeInUp" style="animation-delay: 0.2s;">
                <div class="form-header"><i class="bi bi-shield-lock-fill me-2 text-warning"></i>Security Credentials</div>
                <div class="card-body p-4">
                    <form method="POST">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-dark text-uppercase">Current Password</label>
                                <input type="password" class="form-control border-light shadow-sm" name="current_password" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-dark text-uppercase">New Password</label>
                                <input type="password" class="form-control border-light shadow-sm" name="new_password" required minlength="6">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-dark text-uppercase">Confirm New</label>
                                <input type="password" class="form-control border-light shadow-sm" name="confirm_password" required>
                            </div>
                        </div>
                        <div class="mt-4 pt-2 border-top">
                            <button type="submit" name="change_password" class="btn btn-dark shadow-sm px-4">
                                <i class="bi bi-key me-2"></i> Update Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Academic Info Grid -->
        <div class="col-12 animate__animated animate__fadeInUp" style="animation-delay: 0.3s;">
            <div class="form-card">
                <div class="form-header bg-white"><i class="bi bi-mortarboard-fill me-2 text-success"></i>Academic Master Record</div>
                <div class="card-body p-4">
                    <div class="row g-4">
                        <div class="col-6 col-md-3">
                            <div class="academic-info-box">
                                <label class="small text-muted text-uppercase fw-bold" style="font-size: 0.6rem;">Student No.</label>
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($user_info['student_no'] ?? 'N/A'); ?></div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="academic-info-box">
                                <label class="small text-muted text-uppercase fw-bold" style="font-size: 0.6rem;">Enrolled Program</label>
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($user_info['program_code'] ?? 'N/A'); ?></div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="academic-info-box">
                                <label class="small text-muted text-uppercase fw-bold" style="font-size: 0.6rem;">Year Level</label>
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($section_info['year_level'] ?? 'N/A'); ?></div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="academic-info-box">
                                <label class="small text-muted text-uppercase fw-bold" style="font-size: 0.6rem;">Account Status</label>
                                <div>
                                    <span class="badge rounded-pill bg-<?php echo ($user_info['status'] ?? 'active') == 'active' ? 'success' : 'secondary'; ?> px-3">
                                        <?php echo strtoupper($user_info['status'] ?? 'active'); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<?php include '../../includes/footer.php'; ?>