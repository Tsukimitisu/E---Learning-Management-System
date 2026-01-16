<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_BRANCH_ADMIN) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Branch Announcements";
$branch_id = 1; // In production, fetch from user's assigned branch

// Fetch branch-specific announcements
$announcements = $conn->query("
    SELECT 
        a.id,
        a.title,
        a.content,
        a.target_audience,
        a.priority,
        a.is_active,
        a.created_at,
        a.expires_at,
        CONCAT(up.first_name, ' ', up.last_name) as created_by_name
    FROM announcements a
    INNER JOIN user_profiles up ON a.created_by = up.user_id
    WHERE a.branch_id = $branch_id
    ORDER BY a.created_at DESC
");

include '../../includes/header.php';
?>

<div class="wrapper">
    <?php include '../../includes/sidebar.php'; ?>

    <div id="content">
        <div class="navbar-custom d-flex justify-content-between align-items-center">
            <h4 class="mb-0" style="color: #003366;">
                <i class="bi bi-megaphone"></i> Branch Announcements
            </h4>
            <button class="btn btn-sm text-white" style="background-color: #800000;" data-bs-toggle="modal" data-bs-target="#addAnnouncementModal">
                <i class="bi bi-plus-circle"></i> New Announcement
            </button>
        </div>

        <div id="alertContainer"></div>

        <div class="row">
            <?php while ($announcement = $announcements->fetch_assoc()): 
                $priority_colors = [
                    'low' => 'secondary',
                    'normal' => 'info',
                    'high' => 'warning',
                    'urgent' => 'danger'
                ];
                $priority_color = $priority_colors[$announcement['priority']] ?? 'info';
            ?>
            <div class="col-md-12 mb-3">
                <div class="card shadow-sm border-<?php echo $priority_color; ?>">
                    <div class="card-header bg-<?php echo $priority_color; ?> text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h12:29 AM5 class="mb-0">
<i class="bi bi-megaphone"></i> <?php echo htmlspecialchars($announcement['title']); ?>
</h5>
<div>
<span class="badge bg-light text-dark"><?php echo strtoupper($announcement['priority']); ?></span>
<span class="badge bg-dark">BRANCH ONLY</span>
</div>
</div>
</div>
<div class="card-body">
<p><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></p>
<hr>
<small class="text-muted">
<i class="bi bi-person"></i> <?php echo htmlspecialchars($announcement['created_by_name']); ?> |
<i class="bi bi-calendar"></i> <?php echo date('M d, Y h:i A', strtotime($announcement['created_at'])); ?> |
<i class="bi bi-people"></i> <?php echo ucfirst($announcement['target_audience']); ?>
</small>
</div>
</div>
</div>
<?php endwhile; ?>
</div>
</div>
</div>
<!-- Add Announcement Modal -->
<div class="modal fade" id="addAnnouncementModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #800000; color: white;">
                <h5 class="modal-title"><i class="bi bi-megaphone"></i> New Branch Announcement</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addAnnouncementForm">
                <input type="hidden" name="branch_id" value="<?php echo $branch_id; ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="title" required>
                    </div>
                <div class="mb-3">
                    <label class="form-label">Content <span class="text-danger">*</span></label>
                    <textarea class="form-control" name="content" required rows="5"></textarea>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Target Audience</label>
                        <select class="form-select" name="target_audience">
                            <option value="all">All (Students & Teachers)</option>
                            <option value="students">Students Only</option>
                            <option value="teachers">Teachers Only</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Priority</label>
                        <select class="form-select" name="priority">
                            <option value="normal">Normal</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn text-white" style="background-color: #800000;">
                    <i class="bi bi-send"></i> Post Announcement
                </button>
            </div>
        </form>
    </div>
</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('addAnnouncementForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    
    try {
        const response = await fetch('process/add_announcement.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        
        if (data.status === 'success') {
            showAlert(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert(data.message, 'danger');
        }
    } catch (error) {
        showAlert('An error occurred', 'danger');
    }
});

function showAlert(message, type) {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    document.getElementById('alertContainer').innerHTML = alertHtml;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
</script>
</body>
</html>