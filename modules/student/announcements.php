<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_STUDENT) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Announcements";
$student_id = $_SESSION['user_id'];

// Get student's branch
$student_profile = $conn->query("SELECT branch_id FROM user_profiles WHERE user_id = $student_id")->fetch_assoc();
$branch_id = $student_profile['branch_id'] ?? 0;

// Get announcements (school-wide + branch-specific + student-targeted)
$announcements = $conn->query("
    SELECT a.*, 
           CONCAT(up.first_name, ' ', up.last_name) as author_name,
           b.name as branch_name
    FROM announcements a
    LEFT JOIN user_profiles up ON a.created_by = up.user_id
    LEFT JOIN branches b ON a.branch_id = b.id
    WHERE a.is_active = 1 
    AND (a.target_audience = 'all' OR a.target_audience = 'students')
    AND (a.expires_at IS NULL OR a.expires_at > NOW())
    AND (a.branch_id IS NULL OR a.branch_id = $branch_id)
    ORDER BY a.priority DESC, a.created_at DESC
");

$priority_colors = [
    'low' => 'secondary',
    'normal' => 'info',
    'high' => 'warning',
    'urgent' => 'danger'
];

$priority_icons = [
    'low' => 'bi-arrow-down-circle',
    'normal' => 'bi-info-circle',
    'high' => 'bi-exclamation-circle',
    'urgent' => 'bi-exclamation-triangle-fill'
];

include '../../includes/header.php';
?>

<div class="wrapper">
    <?php include '../../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1"><i class="bi bi-megaphone text-danger me-2"></i>Announcements</h4>
                <small class="text-muted">Stay updated with school announcements</small>
            </div>
        </div>

        <?php if ($announcements->num_rows == 0): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="bi bi-bell-slash display-3 text-muted"></i>
                <p class="mt-3 text-muted">No announcements at the moment</p>
            </div>
        </div>
        <?php else: ?>
        
        <div class="row">
            <?php while ($announcement = $announcements->fetch_assoc()): ?>
            <div class="col-12 mb-4">
                <div class="card border-0 shadow-sm <?php echo $announcement['priority'] == 'urgent' ? 'border-danger' : ''; ?>" 
                     style="<?php echo $announcement['priority'] == 'urgent' ? 'border-left: 4px solid #dc3545 !important;' : ''; ?>">
                    <div class="card-header bg-white py-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="d-flex align-items-center">
                                <i class="<?php echo $priority_icons[$announcement['priority']]; ?> fs-4 text-<?php echo $priority_colors[$announcement['priority']]; ?> me-3"></i>
                                <div>
                                    <h5 class="mb-1 fw-bold"><?php echo htmlspecialchars($announcement['title']); ?></h5>
                                    <div class="d-flex flex-wrap gap-2">
                                        <span class="badge bg-<?php echo $priority_colors[$announcement['priority']]; ?>">
                                            <?php echo ucfirst($announcement['priority']); ?> Priority
                                        </span>
                                        <?php if ($announcement['branch_name']): ?>
                                        <span class="badge bg-light text-dark">
                                            <i class="bi bi-building me-1"></i><?php echo htmlspecialchars($announcement['branch_name']); ?>
                                        </span>
                                        <?php else: ?>
                                        <span class="badge bg-primary">School-wide</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <small class="text-muted">
                                <?php echo date('M d, Y h:i A', strtotime($announcement['created_at'])); ?>
                            </small>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="announcement-content">
                            <?php echo nl2br(htmlspecialchars($announcement['content'])); ?>
                        </div>
                    </div>
                    <div class="card-footer bg-white border-top-0 text-muted small">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>
                                <i class="bi bi-person me-1"></i>
                                Posted by: <?php echo htmlspecialchars($announcement['author_name'] ?? 'Admin'); ?>
                            </span>
                            <?php if ($announcement['expires_at']): ?>
                            <span>
                                <i class="bi bi-clock me-1"></i>
                                Expires: <?php echo date('M d, Y', strtotime($announcement['expires_at'])); ?>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.announcement-content {
    line-height: 1.8;
    color: #555;
}
</style>

<?php include '../../includes/footer.php'; ?>
