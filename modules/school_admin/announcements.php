<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SCHOOL_ADMIN) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Announcements";

/** 
 * ==========================================
 * BACKEND LOGIC - ABSOLUTELY UNTOUCHED
 * ==========================================
 */
$announcements_query = "
    SELECT 
        a.id, a.title, a.content, a.target_audience, a.priority, a.is_active,
        a.created_at, a.expires_at,
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
$schools_result = $conn->query("SELECT id, name FROM schools ORDER BY name");

include '../../includes/header.php';
include '../../includes/sidebar.php'; 
?>

<style>
    /* --- SCROLL & LAYOUT ENGINE --- */
    html, body { height: 100%; margin: 0; overflow: hidden; }
    #content { height: 100vh; display: flex; flex-direction: column; overflow: hidden; }
    .header-fixed-part { flex: 0 0 auto; background: white; padding: 15px 30px; border-bottom: 1px solid #eee; z-index: 10; }
    .body-scroll-part { flex: 1 1 auto; overflow-y: auto; padding: 25px 30px 100px 30px; background-color: #f8f9fa; }

    /* --- FANTASTIC ANNOUNCEMENT UI --- */
    .ann-card {
        background: white; border-radius: 20px; border: none;
        box-shadow: 0 5px 20px rgba(0,0,0,0.05); transition: 0.3s;
        overflow: hidden; margin-bottom: 25px; position: relative;
    }
    .ann-card:hover { transform: translateY(-5px); box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
    
    /* Priority Left Accents */
    .prio-urgent { border-left: 6px solid var(--maroon) !important; }
    .prio-high { border-left: 6px solid #ffc107 !important; }
    .prio-normal { border-left: 6px solid var(--blue) !important; }
    .prio-low { border-left: 6px solid #6c757d !important; }

    .ann-header { padding: 20px 25px; border-bottom: 1px solid #f9f9f9; display: flex; justify-content: space-between; align-items: center; }
    .ann-body { padding: 25px; line-height: 1.8; color: #444; font-size: 1rem; }
    .ann-footer { background: #fcfcfc; padding: 15px 25px; border-top: 1px solid #f1f1f1; }

    .btn-maroon-pill { background-color: var(--maroon); color: white !important; border: none; border-radius: 50px; font-weight: 700; padding: 8px 25px; transition: 0.3s; font-size: 0.85rem; }
    .btn-maroon-pill:hover { background-color: #600000; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(128,0,0,0.2); }

    .meta-item { display: flex; align-items: center; gap: 8px; font-size: 0.75rem; color: #777; font-weight: 600; }
    .meta-item i { color: var(--maroon); font-size: 1rem; }

    /* Staggered Delays */
    <?php for($i=1; $i<=10; $i++): ?>
    .delay-<?php echo $i; ?> { animation-delay: <?php echo $i * 0.1; ?>s; }
    <?php endfor; ?>

    @media (max-width: 768px) { .header-fixed-part { flex-direction: column; gap: 10px; text-align: center; } .ann-header { flex-direction: column; align-items: flex-start; gap: 10px; } }
</style>

<!-- Part 1: Fixed Header -->
<div class="header-fixed-part animate__animated animate__fadeInDown">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-0" style="color: var(--blue);"><i class="bi bi-megaphone-fill me-2 text-maroon"></i>Official Bulletins</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item"><a href="dashboard.php" class="text-maroon text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item active">Announcements</li>
                </ol>
            </nav>
        </div>
        <button class="btn btn-maroon-pill shadow-sm" data-bs-toggle="modal" data-bs-target="#addAnnouncementModal">
            <i class="bi bi-plus-circle me-1"></i> Post New Announcement
        </button>
    </div>
</div>

<!-- Part 2: Scrollable Body -->
<div class="body-scroll-part">
    
    <div id="alertContainer"></div>

    <div class="row">
        <?php 
        $counter = 1;
        while ($ann = $announcements_result->fetch_assoc()): 
            $priority = $ann['priority'];
            $p_colors = ['low' => 'secondary', 'normal' => 'info', 'high' => 'warning', 'urgent' => 'danger'];
            $p_color = $p_colors[$priority] ?? 'info';
        ?>
        <div class="col-12 animate__animated animate__fadeInUp delay-<?php echo $counter; ?>">
            <div class="ann-card prio-<?php echo $priority; ?>">
                <div class="ann-header">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle d-flex align-items-center justify-content-center bg-<?php echo $p_color; ?> bg-opacity-10 text-<?php echo $p_color; ?>" style="width: 45px; height: 45px;">
                            <i class="bi bi-megaphone fs-5"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-1 text-dark"><?php echo htmlspecialchars($ann['title']); ?></h5>
                            <div class="d-flex gap-2">
                                <span class="badge rounded-pill bg-<?php echo $p_color; ?>"><?php echo strtoupper($priority); ?> PRIORITY</span>
                                <?php if (!$ann['is_active']): ?>
                                    <span class="badge bg-dark rounded-pill">INACTIVE</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="text-end">
                        <small class="text-muted fw-bold d-block"><i class="bi bi-calendar3 me-1"></i><?php echo date('M d, Y', strtotime($ann['created_at'])); ?></small>
                        <small class="text-muted"><?php echo date('h:i A', strtotime($ann['created_at'])); ?></small>
                    </div>
                </div>
                
                <div class="ann-body">
                    <?php echo nl2br(htmlspecialchars($ann['content'])); ?>
                </div>

                <div class="ann-footer">
                    <div class="row g-3">
                        <div class="col-md-3 meta-item">
                            <i class="bi bi-person-badge"></i> 
                            <span>BY: <?php echo htmlspecialchars($ann['created_by_name']); ?></span>
                        </div>
                        <div class="col-md-3 meta-item">
                            <i class="bi bi-people"></i> 
                            <span>AUDIENCE: <?php echo ucfirst($ann['target_audience']); ?></span>
                        </div>
                        <div class="col-md-3 meta-item">
                            <i class="bi bi-globe"></i> 
                            <span>SCOPE: <?php echo htmlspecialchars($ann['scope']); ?></span>
                        </div>
                        <?php if ($ann['expires_at']): ?>
                        <div class="col-md-3 meta-item">
                            <i class="bi bi-clock-history"></i> 
                            <span>EXPIRES: <?php echo date('M d, Y', strtotime($ann['expires_at'])); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php $counter++; endwhile; ?>
        
        <?php if ($announcements_result->num_rows == 0): ?>
        <div class="col-12 text-center py-5 opacity-50">
            <i class="bi bi-bell-slash display-1"></i>
            <p class="mt-3">No official announcements have been published yet.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Announcement Modal -->
<div class="modal fade" id="addAnnouncementModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header p-4 text-white" style="background-color: var(--maroon); border: none;">
                <h5 class="modal-title fw-bold"><i class="bi bi-megaphone me-2"></i>Create Institutional Bulletin</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addAnnouncementForm">
                <div class="modal-body p-4 bg-light">
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-muted">ANNOUNCEMENT TITLE *</label>
                        <input type="text" class="form-control border-light shadow-sm" name="title" required placeholder="e.g. Campus Holiday Notice">
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-muted">DETAILED CONTENT *</label>
                        <textarea class="form-control border-light shadow-sm" name="content" required rows="5" placeholder="Enter the full message for the students/staff..."></textarea>
                    </div>
                    
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">TARGET AUDIENCE *</label>
                            <select class="form-select border-light shadow-sm" name="target_audience" required>
                                <option value="all" selected>All Platform Users</option>
                                <option value="students">Students Only</option>
                                <option value="teachers">Teachers Only</option>
                                <option value="staff">Administrative Staff</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">PRIORITY LEVEL *</label>
                            <select class="form-select border-light shadow-sm" name="priority" required>
                                <option value="normal" selected>Normal</option>
                                <option value="low">Low</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">PUBLICATION SCOPE</label>
                            <select class="form-select border-light shadow-sm" name="scope_type" id="scopeType">
                                <option value="system" selected>System-Wide (All Branches)</option>
                                <option value="school">Specific Institution</option>
                            </select>
                        </div>
                        <div class="col-md-6" id="schoolSelectDiv" style="display:none;">
                            <label class="form-label small fw-bold text-muted">SELECT SCHOOL</label>
                            <select class="form-select border-light shadow-sm" name="school_id">
                                <option value="">-- Choose School --</option>
                                <?php $schools_result->data_seek(0); while ($school = $schools_result->fetch_assoc()): ?>
                                    <option value="<?php echo $school['id']; ?>"><?php echo htmlspecialchars($school['name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <label class="form-label small fw-bold text-muted">EXPIRATION DATE (OPTIONAL)</label>
                        <input type="datetime-local" class="form-control border-light shadow-sm" name="expires_at">
                    </div>
                </div>
                <div class="modal-footer border-0 p-4">
                    <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-maroon-pill px-4 shadow-sm">Post Announcement</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<!-- --- JAVASCRIPT LOGIC - UNTOUCHED & RE-WIRED --- -->
<script>
document.getElementById('scopeType').addEventListener('change', function() {
    document.getElementById('schoolSelectDiv').style.display = (this.value === 'school') ? 'block' : 'none';
});

document.getElementById('addAnnouncementForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const btn = e.target.querySelector('button[type="submit"]');
    const original = btn.innerHTML;
    
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Publishing...';
    
    try {
        const response = await fetch('process/add_announcement.php', { method: 'POST', body: formData });
        const data = await response.json();
        if (data.status === 'success') {
            showAlert(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert(data.message, 'danger');
            btn.disabled = false; btn.innerHTML = original;
        }
    } catch (error) {
        showAlert('System error occurred', 'danger');
        btn.disabled = false; btn.innerHTML = original;
    }
});

function goBack() {
    if (document.referrer && document.referrer.includes('/elms_system/')) window.history.back();
    else window.location.href = 'index.php';
}

function showAlert(message, type) {
    const html = `<div class="alert alert-${type} alert-dismissible fade show border-0 shadow-sm animate__animated animate__shakeX" role="alert"><i class="bi ${type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'} me-2"></i>${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
    document.getElementById('alertContainer').innerHTML = html;
    document.querySelector('.body-scroll-part').scrollTo({ top: 0, behavior: 'smooth' });
}
</script>
</body>
</html>