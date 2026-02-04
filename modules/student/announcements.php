<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_STUDENT) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Announcements";
$student_id = $_SESSION['user_id'];

// --- BACKEND LOGIC: UNTOUCHED ---
$student_profile = $conn->query("SELECT branch_id FROM user_profiles WHERE user_id = $student_id")->fetch_assoc();
$branch_id = $student_profile['branch_id'] ?? 0;

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

include '../../includes/header.php';
?>

<link rel="stylesheet" href="css/announcements.css">

<!-- Part 1: Fixed Header -->
<div class="header-fixed-part animate__animated animate__fadeInDown">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="fw-bold mb-0" style="color: var(--blue);"><i class="bi bi-megaphone-fill me-2 text-maroon"></i>Bulletin Board</h4>
            <p class="text-muted small mb-0">Official updates from Datamex College</p>
        </div>
        <div class="d-none d-md-block">
            <span class="badge bg-light text-dark border rounded-pill px-3 py-2 shadow-sm">
                <i class="bi bi-bell-fill me-1 text-maroon"></i> Real-time Updates Active
            </span>
        </div>
    </div>
</div>

<!-- Part 2: Scrollable Body -->
<div class="body-scroll-part">
    
    <?php if ($announcements->num_rows == 0): ?>
    <div class="text-center py-5 animate__animated animate__fadeIn">
        <i class="bi bi-bell-slash display-1 text-muted opacity-25"></i>
        <h5 class="mt-3 text-muted">No active announcements.</h5>
        <p class="small text-muted">Check back later for school and branch updates.</p>
    </div>
    <?php else: ?>
        
        <?php 
        $counter = 1;
        while ($ann = $announcements->fetch_assoc()): 
            $priority = $ann['priority'];
            
            // Fixed Icon Array
            $icons = [
                'low' => ['bi-info-circle', 'low-icon'],
                'normal' => ['bi-megaphone', 'normal-icon'],
                'high' => ['bi-exclamation-circle', 'high-icon'],
                'urgent' => ['bi-exclamation-triangle-fill', 'urgent-icon']
            ];
            $current_icon = $icons[$priority][0] ?? 'bi-bell';
            $icon_style = $icons[$priority][1] ?? 'low-icon';
        ?>
        <div class="announcement-card animate__animated animate__fadeInUp <?php echo 'priority-'.$priority; ?>" style="animation-delay: <?php echo $counter * 0.1; ?>s;">
            <div class="announcement-header">
                <!-- Left Section: Icon & Title -->
                <div class="d-flex align-items-center">
                    <div class="stat-icon me-3 <?php echo $icon_style; ?>">
                        <i class="bi <?php echo $current_icon; ?> fs-4"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold text-dark mb-2"><?php echo htmlspecialchars($ann['title']); ?></h5>
                        <div class="d-flex gap-2 align-items-center flex-wrap">
                            <span class="badge bg-<?php echo ($priority == 'urgent' || $priority == 'high') ? 'danger' : (($priority == 'normal') ? 'info' : 'secondary'); ?> rounded-pill">
                                <?php echo strtoupper($priority); ?>
                            </span>
                            <?php if ($ann['branch_name']): ?>
                                <span class="badge badge-outline-blue rounded-pill small">
                                    <i class="bi bi-building me-1"></i><?php echo htmlspecialchars($ann['branch_name']); ?>
                                </span>
                            <?php else: ?>
                                <span class="badge bg-primary rounded-pill small px-3">SCHOOL-WIDE</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <!-- Right Section: Date/Time (Matches Screenshot) -->
                <div class="header-right text-end">
                    <div class="fw-bold text-dark mb-1 small"><i class="bi bi-calendar3 me-2"></i><?php echo date('M d, Y', strtotime($ann['created_at'])); ?></div>
                    <div class="text-muted small"><?php echo date('h:i A', strtotime($ann['created_at'])); ?></div>
                </div>
            </div>

            <div class="announcement-body">
                <?php echo nl2br(htmlspecialchars($ann['content'])); ?>
            </div>

            <div class="announcement-footer">
                <div class="author-initial"><?php echo strtoupper(substr($ann['author_name'] ?? 'A', 0, 1)); ?></div>
                <div class="small fw-bold text-muted">
                    Published by: <span class="text-dark"><?php echo htmlspecialchars($ann['author_name'] ?? 'System Administrator'); ?></span>
                </div>
                <?php if ($ann['expires_at']): ?>
                <div class="ms-auto d-none d-md-block">
                    <span class="badge bg-light text-muted border small"><i class="bi bi-clock me-1"></i>Expires: <?php echo date('M d, Y', strtotime($ann['expires_at'])); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php $counter++; endwhile; ?>

    <?php endif; ?>

</div>

<?php include '../../includes/footer.php'; ?>