<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SCHOOL_ADMIN) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Announcements";

// Fetch all announcements
$announcements_query = "
    SELECT 
        a.id,
        a.title,
        a.content,
        a.target_audience,
        a.priority,
        a.is_active,
        a.created_at,
        a.expires_at,
        CONCAT(up.first_name, ' ', up.last_name) as created_by_name,
        CASE 
            WHEN a.school_id IS NULL AND a.branch_id IS NULL THEN 'System-Wide'
            WHEN a.branch_id IS NOT NULL THEN CONCAT('Branch: ', b.name)
            ELSE CONCAT('School: ', s.name)
        END as scope
    FROM announcements a
    LEFT JOIN user_profiles up ON a.created_by = up.user_id
    LEFT JOIN schools s ON a.school_id = s.id
    LEFT JOIN branches b ON a.branch_id = b.id
    ORDER BY a.created_at DESC
";
$announcements_result = $conn->query($announcements_query);

// Fetch schools for dropdown
$schools_result = $conn->query("SELECT id, name FROM schools ORDER BY name");

include '../../includes/header.php';
?>

<div class="wrapper">
    <?php include '../../includes/sidebar.php'; ?>

    <div id="content">
        <div class="navbar-custom d-flex justify-content-between align-items-center">
            <h4 class="mb-0" style="color: #003366;">
                <i class="bi bi-megaphone"></i> Announcements
            </h4>
            <button class="btn btn-sm text-white" style="background-color: #800000;" data-bs-toggle="modal" data-bs-target="#addAnnouncementModal">
                <i class="bi bi-plus-circle"></i> New Announcement
            </button>
        </div>

        <div id="alertContainer"></div>

        <div class="row">
            <?php while ($announcement = $announcements_result->fetch_assoc()): 
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
                            <h5 class="mb-0">
                                <i class="bi bi-megaphone"></i> <?php echo htmlspecialchars($announcement['title']); ?>
                            </h5>
                            <div>
                                <span class="badge bg-light text-dark"><?php echo strtoupper($announcement['priority']); ?></span>
                                <?php if (!$announcement['is_active']): ?>
                                <span class="badge bg-secondary">Inactive</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <p><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></p>
                        <hr>
                        <div class="row">
                            <div class="col-md-3">
                                <small class="text-muted">
                                    <i class="bi bi-person"></i> <strong>By:</strong> <?php echo htmlspecialchars($announcement['created_by_name']); ?>
                                </small>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted">
                                    <i class="bi bi-calendar"></i> <strong>Posted:</strong> <?php echo date('M d, Y', strtotime($announcement['created_at'])); ?>
                                </small>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted">
                                    <i class="bi bi-people"></i> <strong>Audience:</strong> <?php echo ucfirst($announcement['target_audience']); ?>
                                </small>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted">
                                    <i class="bi bi-globe"></i> <strong>Scope:</strong> <?php echo htmlspecialchars($announcement['scope']); ?>
                                </small>
                            </div>
                        </div>
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
                <h5 class="modal-title"><i class="bi bi-megaphone"></i> Create New Announcement</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addAnnouncementForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="title" required placeholder="e.g. Enrollment for 2nd Semester Now Open">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Content <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="content" required rows="5" placeholder="Enter announcement details..."></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Target Audience <span class="text-danger">*</span></label>
                            <select class="form-select" name="target_audience" required>
                                <option value="all" selected>All Users</option>
                                <option value="students">Students Only</option>
                                <option value="teachers">Teachers Only</option>
                                <option value="staff">Staff Only</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Priority <span class="text-danger">*</span></label>
                            <select class="form-select" name="priority" required>
                                <option value="normal" selected>Normal</option>
                                <option value="low">Low</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Scope</label>
                        <select class="form-select" name="scope_type" id="scopeType">
                            <option value="system" selected>System-Wide (All Schools)</option>
                            <option value="school">Specific School</option>
                        </select>
                    </div>
                    
                    <div class="mb-3" id="schoolSelectDiv" style="display:none;">
                        <label class="form-label">Select School</label>
                        <select class="form-select" name="school_id">
                            <option value="">-- Select School --</option>
                            <?php 
                            $schools_result->data_seek(0);
                            while ($school = $schools_result->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $school['id']; ?>"><?php echo htmlspecialchars($school['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Expiration Date (Optional)</label>
                        <input type="datetime-local" class="form-control" name="expires_at">
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
document.getElementById('scopeType').addEventListener('change', function() {
    const schoolDiv = document.getElementById('schoolSelectDiv');
    if (this.value === 'school') {
        schoolDiv.style.display = 'block';
    } else {
        schoolDiv.style.display = 'none';
    }
});

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