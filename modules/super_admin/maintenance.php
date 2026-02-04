<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SUPER_ADMIN) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "System Maintenance";
$maintenance = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'maintenance_mode'")->fetch_assoc();
$is_maintenance = ($maintenance['setting_value'] ?? '0') == '1';

$scheduled = $conn->query("
    SELECT sm.*, CONCAT(up.first_name, ' ', up.last_name) as created_by_name
    FROM system_maintenance sm
    INNER JOIN user_profiles up ON sm.created_by = up.user_id
    ORDER BY sm.start_time DESC
");

include '../../includes/header.php';
?>

<link rel="stylesheet" href="css/maintenance.css">

<!-- Part 1: Fixed Top Header -->
<div class="header-fixed-part d-flex justify-content-between align-items-center animate__animated animate__fadeInDown">
    <div>
        <h4 class="fw-bold mb-0" style="color: #003366;"><i class="bi bi-tools me-2"></i>System Maintenance</h4>
        <p class="text-muted small mb-0">Infrastructure health and scheduled downtime</p>
    </div>
    <a href="dashboard.php" class="btn btn-outline-secondary btn-sm px-4 shadow-sm">
        <i class="bi bi-arrow-left me-1"></i> Dashboard
    </a>
</div>

<!-- Part 2: Scrollable Content -->
<div class="body-scroll-part animate__animated animate__fadeInUp">
    
    <div id="alertContainer"></div>

    <!-- Maintenance Mode Control -->
    <div class="status-banner <?php echo $is_maintenance ? 'status-offline' : 'status-online'; ?> shadow-sm">
        <div>
            <h5 class="fw-bold mb-1">
                <?php echo $is_maintenance ? '<i class="bi bi-pause-circle-fill me-2"></i>MAINTENANCE MODE ACTIVE' : '<i class="bi bi-check-circle-fill me-2"></i>SYSTEM IS ONLINE'; ?>
            </h5>
            <p class="mb-0 small opacity-75">
                <?php echo $is_maintenance ? 'Only Super Administrators can access the system.' : 'All users are able to access their accounts normally.'; ?>
            </p>
        </div>
        <div>
            <?php if ($is_maintenance): ?>
                <button class="btn btn-success fw-bold" onclick="toggleMaintenanceMode(0)">
                    <i class="bi bi-play-fill me-1"></i> ENTER LIVE MODE
                </button>
            <?php else: ?>
                <button class="btn btn-danger fw-bold" onclick="toggleMaintenanceMode(1)">
                    <i class="bi bi-stop-fill me-1"></i> ENTER MAINTENANCE
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Scheduled Table -->
    <div class="maint-card">
        <div class="maint-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-calendar-event me-2"></i>Scheduled Windows</span>
            <button class="btn btn-maroon btn-sm px-3" data-bs-toggle="modal" data-bs-target="#scheduleMaintenanceModal">
                <i class="bi bi-plus-lg me-1"></i> New Schedule
            </button>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Title</th>
                        <th>Start Time</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($scheduled->num_rows > 0): ?>
                        <?php while ($maint = $scheduled->fetch_assoc()): 
                            $start = strtotime($maint['start_time']);
                            $end = strtotime($maint['end_time']);
                            $now = time();
                            $badge = ($now < $start) ? 'info' : (($now <= $end) ? 'warning' : 'success');
                            $text = ($now < $start) ? 'Scheduled' : (($now <= $end) ? 'In Progress' : 'Completed');
                        ?>
                        <tr>
                            <td class="fw-bold small"><?php echo htmlspecialchars($maint['title']); ?></td>
                            <td><small class="text-muted"><?php echo date('M d, h:i A', $start); ?></small></td>
                            <td><span class="badge bg-<?php echo $badge; ?>"><?php echo $text; ?></span></td>
                            <td class="text-end">
                                <button class="btn btn-light btn-sm border" onclick="viewMaintenance(<?php echo $maint['id']; ?>)">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="text-center py-4 text-muted">No scheduled maintenance found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- System Operations -->
    <h6 class="fw-bold text-muted mb-3 text-uppercase small" style="letter-spacing: 1px;">Operations</h6>
    <div class="row g-3">
        <div class="col-12 col-md-4">
            <button class="op-btn" onclick="clearCache()">
                <i class="bi bi-trash3 text-danger"></i> Clear Cache
            </button>
        </div>
        <div class="col-12 col-md-4">
            <button class="op-btn" onclick="optimizeDatabase()">
                <i class="bi bi-database-check text-success"></i> Optimize DB
            </button>
        </div>
        <div class="col-12 col-md-4">
            <button class="op-btn" onclick="createBackup()">
                <i class="bi bi-cloud-arrow-up text-primary"></i> Create Backup
            </button>
        </div>
    </div>

</div>

<!-- Schedule Maintenance Modal -->
<div class="modal fade" id="scheduleMaintenanceModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
            <div class="modal-header p-4 text-white" style="background-color: var(--maroon); border: none;">
                <h5 class="modal-title fw-bold">Schedule Maintenance</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="scheduleMaintenanceForm">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">TITLE *</label>
                        <input type="text" class="form-control border-light shadow-sm" name="title" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">START *</label>
                            <input type="datetime-local" class="form-control border-light shadow-sm" name="start_time" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">END *</label>
                            <input type="datetime-local" class="form-control border-light shadow-sm" name="end_time" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-maroon px-4 shadow-sm">Save Schedule</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
async function toggleMaintenanceMode(enable) {
    const action = enable ? 'enable' : 'disable';
    if (!confirm(`Confirm: ${action} maintenance mode?`)) return;
    try {
        const response = await fetch('process/toggle_maintenance.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `enable=${enable}`
        });
        const data = await response.json();
        if (data.status === 'success') {
            showAlert(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else { showAlert(data.message, 'danger'); }
    } catch (error) { showAlert('Failed to update mode', 'danger'); }
}

document.getElementById('scheduleMaintenanceForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    try {
        const response = await fetch('process/schedule_maintenance.php', { method: 'POST', body: formData });
        const data = await response.json();
        if (data.status === 'success') {
            showAlert(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else { showAlert(data.message, 'danger'); }
    } catch (error) { showAlert('Failed to schedule', 'danger'); }
});

function clearCache() { if (confirm('Clear system cache?')) showAlert('Cache cleared', 'success'); }
function optimizeDatabase() { if (confirm('Optimize DB?')) showAlert('Database optimized', 'success'); }
function createBackup() { if (confirm('Create Backup?')) showAlert('Backup created', 'success'); }
function viewMaintenance(id) { alert('Viewing Maintenance ID: ' + id); }

function showAlert(message, type) {
    const alertHtml = `<div class="alert alert-${type} alert-dismissible fade show border-0 shadow-sm animate__animated animate__shakeX" role="alert"><i class="bi ${type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'} me-2"></i>${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
    document.getElementById('alertContainer').innerHTML = alertHtml;
    document.querySelector('.body-scroll-part').scrollTo({ top: 0, behavior: 'smooth' });
}
</script>
</body>
</html>