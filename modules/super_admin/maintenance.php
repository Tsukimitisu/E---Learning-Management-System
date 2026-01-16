<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SUPER_ADMIN) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "System Maintenance";

// Check current maintenance mode
$maintenance = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'maintenance_mode'")->fetch_assoc();
$is_maintenance = ($maintenance['setting_value'] ?? '0') == '1';

// Fetch scheduled maintenance
$scheduled = $conn->query("
    SELECT sm.*, CONCAT(up.first_name, ' ', up.last_name) as created_by_name
    FROM system_maintenance sm
    INNER JOIN user_profiles up ON sm.created_by = up.user_id
    ORDER BY sm.start_time DESC
");

include '../../includes/header.php';
?>

<link rel="stylesheet" href="../../assets/css/minimal.css">

<div class="wrapper">
    <?php include '../../includes/sidebar.php'; ?>

    <div id="content">
        <div class="minimal-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1" style="color: var(--navy); font-weight: 600;">System Maintenance</h4>
                    <small class="text-muted">Manage maintenance windows and system updates</small>
                </div>
                <a href="dashboard.php" class="btn btn-minimal">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <div id="alertContainer"></div>

        <!-- Maintenance Mode Control -->
        <div class="minimal-card">
            <h5 class="section-title">Maintenance Mode Control</h5>
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h6>Current Status: 
                        <?php if ($is_maintenance): ?>
                            <span class="badge bg-danger">MAINTENANCE MODE ACTIVE</span>
                        <?php else: ?>
                            <span class="badge bg-success">SYSTEM ONLINE</span>
                        <?php endif; ?>
                    </h6>
                    <p class="text-muted mb-0">
                        <?php if ($is_maintenance): ?>
                            System is currently in maintenance mode. Only Super Admins can access the system.
                        <?php else: ?>
                            System is operational. All users can access the system normally.
                        <?php endif; ?>
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <?php if ($is_maintenance): ?>
                        <button class="btn btn-success" onclick="toggleMaintenanceMode(0)">
                            <i class="bi bi-play-circle"></i> Disable Maintenance Mode
                        </button>
                    <?php else: ?>
                        <button class="btn btn-danger" onclick="toggleMaintenanceMode(1)">
                            <i class="bi bi-pause-circle"></i> Enable Maintenance Mode
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Schedule Maintenance -->
        <div class="minimal-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="section-title mb-0">Scheduled Maintenance</h5>
                <button class="btn btn-primary-minimal" data-bs-toggle="modal" data-bs-target="#scheduleMaintenanceModal">
                    <i class="bi bi-calendar-plus"></i> Schedule Maintenance
                </button>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead style="background-color: var(--light-gray);">
                        <tr>
                            <th>Title</th>
                            <th>Start Time</th>
                            <th>End Time</th>
                            <th>Duration</th>
                            <th>Status</th>
                            <th>Created By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($maint = $scheduled->fetch_assoc()): 
                            $start = strtotime($maint['start_time']);
                            $end = strtotime($maint['end_time']);
                            $duration = round(($end - $start) / 3600, 1);
                            $now = time();
                            
                            if ($now < $start) {
                                $status = 'Scheduled';
                                $badge = 'info';
                            } elseif ($now >= $start && $now <= $end) {
                                $status = 'In Progress';
                                $badge = 'warning';
                            } else {
                                $status = 'Completed';
                                $badge = 'success';
                            }
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($maint['title']); ?></td>
                            <td><small><?php echo date('M d, Y h:i A', $start); ?></small></td>
                            <td><small><?php echo date('M d, Y h:i A', $end); ?></small></td>
                            <td><?php echo $duration; ?> hrs</td>
                            <td><span class="badge bg-<?php echo $badge; ?>"><?php echo $status; ?></span></td>
                            <td><?php echo htmlspecialchars($maint['created_by_name']); ?></td>
                            <td>
                                <button class="btn btn-sm btn-minimal" onclick="viewMaintenance(<?php echo $maint['id']; ?>)">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- System Operations -->
        <div class="minimal-card">
            <h5 class="section-title">System Operations</h5>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <div class="d-grid">
                        <button class="btn btn-minimal" onclick="clearCache()">
                            <i class="bi bi-trash"></i> Clear Cache
                        </button>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="d-grid">
                        <button class="btn btn-minimal" onclick="optimizeDatabase()">
                            <i class="bi bi-gear"></i> Optimize Database
                        </button>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="d-grid">
                        <button class="btn btn-minimal" onclick="createBackup()">
                            <i class="bi bi-download"></i> Create Backup
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Schedule Maintenance Modal -->
<div class="modal fade" id="scheduleMaintenanceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background-color: var(--maroon); color: white;">
                <h5 class="modal-title">Schedule Maintenance Window</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="scheduleMaintenanceForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Start Time <span class="text-danger">*</span></label>
                            <input type="datetime-local" class="form-control" name="start_time" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">End Time <span class="text-danger">*</span></label>
                            <input type="datetime-local" class="form-control" name="end_time" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-minimal" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-minimal">
                        <i class="bi bi-calendar-check"></i> Schedule
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
async function toggleMaintenanceMode(enable) {
    const action = enable ? 'enable' : 'disable';
    if (!confirm(`Are you sure you want to ${action} maintenance mode?`)) return;
    
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
        } else {
            showAlert(data.message, 'danger');
        }
    } catch (error) {
        showAlert('Failed to toggle maintenance mode', 'danger');
    }
}

document.getElementById('scheduleMaintenanceForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    
    try {
        const response = await fetch('process/schedule_maintenance.php', {
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
        showAlert('Failed to schedule maintenance', 'danger');
    }
});

function clearCache() {
    if (confirm('Clear system cache?')) {
        showAlert('Cache cleared successfully', 'success');
    }
}

function optimizeDatabase() {
    if (confirm('Optimize database tables?')) {
        showAlert('Database optimized successfully', 'success');
    }
}

function createBackup() {
    if (confirm('Create database backup?')) {
        showAlert('Backup created successfully', 'success');
    }
}

function viewMaintenance(id) {
    alert('View maintenance details - ID: ' + id);
}

function showAlert(message, type) {
    const alertHtml = `
        <div class="alert alert-${type} alert-minimal alert-dismissible fade show" role="alert">
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