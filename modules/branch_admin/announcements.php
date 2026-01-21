<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_BRANCH_ADMIN) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Branch Announcements";
$branch_id = get_user_branch_id();
if ($branch_id === null) {
    echo "Error: Your account is not assigned to any branch. Please contact the School Administrator.";
    exit();
}
require_branch_assignment();

// Fetch branch-specific announcements (Logic Untouched)
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
include '../../includes/sidebar.php'; 
?>

<style>
    /* --- SHARED UI DESIGN SYSTEM --- */
    .page-header {
        background: white; padding: 20px; border-radius: 15px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.03); margin-bottom: 25px;
    }

    .announcement-card {
        background: white; border-radius: 15px; border: none;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05); transition: 0.3s;
        margin-bottom: 20px; overflow: hidden; position: relative;
        border-left: 5px solid transparent;
    }
    .announcement-card:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }

    /* Priority Colors */
    .priority-urgent { border-left-color: var(--maroon); }
    .priority-high { border-left-color: #fd7e14; }
    .priority-normal { border-left-color: var(--blue); }
    .priority-low { border-left-color: #6c757d; }

    .card-header-modern {
        padding: 15px 20px; display: flex; justify-content: space-between; align-items: center;
        background: #fcfcfc; border-bottom: 1px solid #f5f5f5;
    }

    .stat-pill {
        display: inline-flex; align-items: center; padding: 4px 12px;
        border-radius: 20px; font-size: 0.65rem; font-weight: 800; text-transform: uppercase;
    }

    .meta-text { font-size: 0.75rem; color: #888; font-weight: 500; }
    .meta-text i { margin-right: 4px; color: var(--blue); }

    .btn-maroon { background-color: var(--maroon); color: white; font-weight: 700; border: none; }
    .btn-maroon:hover { background-color: #600000; color: white; transform: translateY(-1px); }

    .empty-state { text-align: center; padding: 60px 20px; color: #adb5bd; background: white; border-radius: 15px; }
    .empty-state i { font-size: 3.5rem; margin-bottom: 15px; display: block; opacity: 0.5; }
</style>

<div class="main-content-body animate__animated animate__fadeIn">
    
    <!-- 1. PAGE HEADER -->
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center animate__animated animate__fadeInDown">
        <div class="mb-2 mb-md-0">
            <h4 class="fw-bold mb-0" style="color: var(--blue);">
                <i class="bi bi-megaphone-fill me-2 text-maroon"></i>Branch Announcements
            </h4>
            <p class="text-muted small mb-0">Publish important updates and news for students and faculty.</p>
        </div>
        <button class="btn btn-maroon btn-sm px-4 rounded-pill shadow-sm" data-bs-toggle="modal" data-bs-target="#addAnnouncementModal">
            <i class="bi bi-plus-circle me-1"></i> New Announcement
        </button>
    </div>

    <div id="alertContainer"></div>

    <!-- 2. ANNOUNCEMENT FEED -->
    <div class="row">
        <div class="col-lg-10 mx-auto">
            <?php if ($announcements->num_rows == 0): ?>
                <div class="empty-state shadow-sm">
                    <i class="bi bi- megaphone text-muted"></i>
                    <h5 class="fw-bold text-dark">No Announcements Yet</h5>
                    <p class="small">Broadcast branch news, event reminders, or urgent notices to your staff and students.</p>
                    <button class="btn btn-outline-primary btn-sm rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addAnnouncementModal">
                        Create Post
                    </button>
                </div>
            <?php else: ?>
                <?php while ($ann = $announcements->fetch_assoc()): 
                    $priority_classes = [
                        'low' => ['priority-low', 'bg-secondary text-white'],
                        'normal' => ['priority-normal', 'bg-blue text-white'],
                        'high' => ['priority-high', 'bg-warning text-dark'],
                        'urgent' => ['priority-urgent', 'bg-danger text-white']
                    ];
                    $style = $priority_classes[$ann['priority']] ?? $priority_classes['normal'];
                ?>
                <div class="announcement-card <?php echo $style[0]; ?> animate__animated animate__fadeInUp">
                    <div class="card-header-modern">
                        <div class="d-flex align-items-center">
                            <span class="stat-pill <?php echo $style[1]; ?> me-3">
                                <?php echo $ann['priority']; ?>
                            </span>
                            <h5 class="mb-0 fw-bold text-dark" style="font-size: 1rem;"><?php echo htmlspecialchars($ann['title']); ?></h5>
                        </div>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-light rounded-circle" data-bs-toggle="dropdown">
                                <i class="bi bi-three-dots-vertical"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                <li><a class="dropdown-item small fw-bold text-danger" href="#"><i class="bi bi-trash me-2"></i>Remove Post</a></li>
                                <li><a class="dropdown-item small fw-bold" href="#"><i class="bi bi-pencil-square me-2"></i>Edit Content</a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <div class="text-dark mb-4" style="font-size: 0.95rem; line-height: 1.6;">
                            <?php echo nl2br(htmlspecialchars($ann['content'])); ?>
                        </div>
                        <div class="d-flex flex-wrap gap-4 border-top pt-3">
                            <div class="meta-text">
                                <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($ann['created_by_name']); ?>
                            </div>
                            <div class="meta-text">
                                <i class="bi bi-calendar3"></i> <?php echo date('M d, Y | h:i A', strtotime($ann['created_at'])); ?>
                            </div>
                            <div class="meta-text">
                                <i class="bi bi-people-fill"></i> Audience: <span class="badge bg-light text-dark border"><?php echo ucfirst($ann['target_audience']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Announcement Modal -->
<div class="modal fade" id="addAnnouncementModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-maroon text-white py-3">
                <h5 class="modal-title fs-6 fw-bold"><i class="bi bi-megaphone me-2"></i>New Branch Announcement</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addAnnouncementForm">
                <input type="hidden" name="branch_id" value="<?php echo $branch_id; ?>">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase opacity-75">Broadcast Title *</label>
                        <input type="text" class="form-control shadow-sm" name="title" placeholder="Enter headline..." required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase opacity-75">Announcement Content *</label>
                        <textarea class="form-control shadow-sm" name="content" required rows="6" placeholder="Write your message here..."></textarea>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-uppercase opacity-75">Target Audience</label>
                            <select class="form-select shadow-sm" name="target_audience">
                                <option value="all">All Staff & Students</option>
                                <option value="students">Students Only</option>
                                <option value="teachers">Faculty Members Only</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-uppercase opacity-75">Priority Level</label>
                            <select class="form-select shadow-sm" name="priority">
                                <option value="normal">Normal Priority</option>
                                <option value="high">High Priority</option>
                                <option value="urgent">Urgent / Emergency</option>
                                <option value="low">Informational / Low</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-light btn-sm px-4 fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-maroon btn-sm px-4 fw-bold shadow-sm">
                        <i class="bi bi-send me-2"></i>Post to Feed
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Logic preserved exactly as requested
document.getElementById('addAnnouncementForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const submitBtn = e.target.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Posting...';
    
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
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-send me-2"></i>Post to Feed';
        }
    } catch (error) {
        showAlert('An error occurred while publishing.', 'danger');
        submitBtn.disabled = false;
    }
});

function showAlert(message, type) {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show border-0 shadow-sm rounded-3 mb-4" role="alert">
            <i class="bi ${type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    document.getElementById('alertContainer').innerHTML = alertHtml;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
</script>

<?php include '../../includes/footer.php'; ?>