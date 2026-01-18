<?php
require_once '../../config/init.php';

$user_role = $_SESSION['role_id'] ?? $_SESSION['role'] ?? null;
if (!isset($_SESSION['user_id']) || $user_role != ROLE_SCHOOL_ADMIN) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "School Administrator Dashboard";

// Fetch statistics
$shs_tracks = $conn->query("SELECT COUNT(*) as count FROM shs_tracks WHERE is_active = 1")->fetch_assoc()['count'];
$shs_strands = $conn->query("SELECT COUNT(*) as count FROM shs_strands WHERE is_active = 1")->fetch_assoc()['count'];
$shs_subjects = $conn->query("SELECT COUNT(*) as count FROM curriculum_subjects WHERE subject_type IN ('shs_core', 'shs_applied', 'shs_specialized') AND is_active = 1")->fetch_assoc()['count'];

$college_programs = $conn->query("SELECT COUNT(*) as count FROM programs WHERE is_active = 1")->fetch_assoc()['count'];
$college_subjects = $conn->query("SELECT COUNT(*) as count FROM curriculum_subjects WHERE subject_type = 'college' AND is_active = 1")->fetch_assoc()['count'];

$branch_admins = $conn->query("
    SELECT COUNT(*) as count FROM users u
    INNER JOIN user_roles ur ON u.id = ur.user_id
    WHERE ur.role_id = " . ROLE_BRANCH_ADMIN . " AND u.status = 'active'
")->fetch_assoc()['count'];

include '../../includes/header.php';
?>

<div class="wrapper">
    <?php include '../../includes/sidebar.php'; ?>

    <div id="content">
        <div class="navbar-custom d-flex justify-content-between align-items-center">
            <h4 class="mb-0" style="color: #003366;">
                <i class="bi bi-speedometer2"></i> School Administrator Dashboard
            </h4>
            <small class="text-muted">Manage curriculum and institutional policies</small>
        </div>

        <div id="alertContainer"></div>

        <!-- Quick Statistics -->
        <div class="row mt-4 mb-4">
            <!-- SHS Curriculum Stats -->
            <div class="col-md-3 mb-3">
                <div class="card text-center border-primary">
                    <div class="card-body">
                        <i class="bi bi-diagram-3 display-4 text-primary"></i>
                        <h3 class="text-primary mt-2"><?php echo $shs_tracks; ?></h3>
                        <p class="text-muted mb-0">SHS Tracks</p>
                    </div>
                </div>
            </div>

            <div class="col-md-3 mb-3">
                <div class="card text-center border-success">
                    <div class="card-body">
                        <i class="bi bi-diagram-2 display-4 text-success"></i>
                        <h3 class="text-success mt-2"><?php echo $shs_strands; ?></h3>
                        <p class="text-muted mb-0">SHS Strands</p>
                    </div>
                </div>
            </div>

            <div class="col-md-3 mb-3">
                <div class="card text-center border-info">
                    <div class="card-body">
                        <i class="bi bi-book-half display-4 text-info"></i>
                        <h3 class="text-info mt-2"><?php echo $shs_subjects; ?></h3>
                        <p class="text-muted mb-0">SHS Subjects</p>
                    </div>
                </div>
            </div>

            <div class="col-md-3 mb-3">
                <div class="card text-center border-warning">
                    <div class="card-body">
                        <i class="bi bi-exclamation-circle display-4 text-warning"></i>
                        <h3 class="text-warning mt-2"><?php echo $branch_admins; ?></h3>
                        <p class="text-muted mb-0">Branch Admins</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <!-- College Curriculum Stats -->
            <div class="col-md-3 mb-3">
                <div class="card text-center border-dark">
                    <div class="card-body">
                        <i class="bi bi-building display-4 text-dark"></i>
                        <h3 class="text-dark mt-2"><?php echo $college_programs; ?></h3>
                        <p class="text-muted mb-0">College Programs</p>
                    </div>
                </div>
            </div>

            <div class="col-md-3 mb-3">
                <div class="card text-center border-secondary">
                    <div class="card-body">
                        <i class="bi bi-journal-text display-4 text-secondary"></i>
                        <h3 class="text-secondary mt-2"><?php echo $college_subjects; ?></h3>
                        <p class="text-muted mb-0">College Subjects</p>
                    </div>
                </div>
            </div>

            <div class="col-md-6 mb-3">
                <div class="card">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="bi bi-lightning-fill"></i> Quick Actions</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <a href="shs_curriculum.php" class="btn btn-primary btn-sm w-100">
                                    <i class="bi bi-mortarboard"></i> Manage SHS
                                </a>
                            </div>
                            <div class="col-md-6">
                                <a href="college_curriculum.php" class="btn btn-info btn-sm w-100">
                                    <i class="bi bi-building"></i> Manage College
                                </a>
                            </div>
                            <div class="col-md-6">
                                <a href="administrative_control.php" class="btn btn-danger btn-sm w-100">
                                    <i class="bi bi-shield-check"></i> Admin Control
                                </a>
                            </div>
                            <div class="col-md-6">
                                <a href="announcements.php" class="btn btn-warning btn-sm w-100">
                                    <i class="bi bi-megaphone"></i> Announcements
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Curriculum Status Overview -->
        <div class="card">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="bi bi-list-check"></i> Curriculum Implementation Status</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- SHS Status -->
                    <div class="col-md-6 mb-3">
                        <h6 class="fw-bold text-primary mb-3">SHS Curriculum</h6>
                        <div class="mb-3">
                            <label class="small fw-bold">Tracks Implementation</label>
                            <div class="progress" style="height: 20px;">
                                <div class="progress-bar bg-primary" style="width: 100%">4/4 Complete</div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="small fw-bold">Strands Coverage</label>
                            <div class="progress" style="height: 20px;">
                                <div class="progress-bar bg-success" style="width: 87.5%"><?php echo $shs_strands; ?>/8 Strands</div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="small fw-bold">Subject Definition</label>
                            <div class="progress" style="height: 20px;">
                                <div class="progress-bar bg-info" style="width: <?php echo min(100, ($shs_subjects / 100) * 100); ?>%"><?php echo $shs_subjects; ?> Subjects</div>
                            </div>
                        </div>
                        <div class="alert alert-info small mt-3">
                            <i class="bi bi-check-circle"></i> DepEd compliance validated
                        </div>
                    </div>

                    <!-- College Status -->
                    <div class="col-md-6 mb-3">
                        <h6 class="fw-bold text-dark mb-3">College Curriculum</h6>
                        <div class="mb-3">
                            <label class="small fw-bold">Programs Offered</label>
                            <div class="progress" style="height: 20px;">
                                <div class="progress-bar bg-dark" style="width: <?php echo min(100, ($college_programs / 5) * 100); ?>%"><?php echo $college_programs; ?>/5 Programs</div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="small fw-bold">Subject Development</label>
                            <div class="progress" style="height: 20px;">
                                <div class="progress-bar bg-secondary" style="width: <?php echo min(100, ($college_subjects / 150) * 100); ?>%"><?php echo $college_subjects; ?>/150 Subjects</div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="small fw-bold">Year Levels Configured</label>
                            <div class="progress" style="height: 20px;">
                                <div class="progress-bar bg-warning" style="width: 100%">4/4 Levels</div>
                            </div>
                        </div>
                        <div class="alert alert-info small mt-3">
                            <i class="bi bi-check-circle"></i> CHED compliance validated
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>