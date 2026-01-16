<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SUPER_ADMIN) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "System Settings";

// Fetch all settings grouped by category
$settings_query = "
    SELECT * FROM system_settings 
    ORDER BY category, setting_key
";
$settings_result = $conn->query($settings_query);

// Group settings by category
$grouped_settings = [];
while ($setting = $settings_result->fetch_assoc()) {
    $category = $setting['category'] ?? 'general';
    $grouped_settings[$category][] = $setting;
}

include '../../includes/header.php';
?>

<link rel="stylesheet" href="../../assets/css/minimal.css">

<div class="wrapper">
    <?php include '../../includes/sidebar.php'; ?>

    <div id="content">
        <div class="minimal-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1" style="color: var(--navy); font-weight: 600;">System Configuration</h4>
                    <small class="text-muted">Manage system-wide settings and policies</small>
                </div>
                <a href="dashboard.php" class="btn btn-minimal">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <div id="alertContainer"></div>

        <?php foreach ($grouped_settings as $category => $settings): ?>
        <div class="minimal-card">
            <h5 class="section-title"><?php echo ucfirst($category); ?> Settings</h5>
            <form class="settings-form" data-category="<?php echo $category; ?>">
                <?php foreach ($settings as $setting): ?>
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold"><?php echo ucwords(str_replace('_', ' ', $setting['setting_key'])); ?></label>
                        <small class="d-block text-muted"><?php echo htmlspecialchars($setting['description'] ?? ''); ?></small>
                    </div>
                    <div class="col-md-6">
                        <?php if ($setting['setting_type'] == 'boolean'): ?>
                            <select class="form-select" name="<?php echo $setting['setting_key']; ?>">
                                <option value="1" <?php echo $setting['setting_value'] == '1' ? 'selected' : ''; ?>>Enabled</option>
                                <option value="0" <?php echo $setting['setting_value'] == '0' ? 'selected' : ''; ?>>Disabled</option>
                            </select>
                        <?php elseif ($setting['setting_type'] == 'number'): ?>
                            <input type="number" class="form-control" name="<?php echo $setting['setting_key']; ?>" value="<?php echo htmlspecialchars($setting['setting_value']); ?>">
                        <?php else: ?>
                            <input type="text" class="form-control" name="<?php echo $setting['setting_key']; ?>" value="<?php echo htmlspecialchars($setting['setting_value']); ?>">
                        <?php endif; ?>
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-primary-minimal w-100" onclick="saveSetting('<?php echo $setting['setting_key']; ?>', this.closest('form'))">
                            <i class="bi bi-save"></i> Save
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
async function saveSetting(key, form) {
    const formData = new FormData(form);
    const value = formData.get(key);
    
    try {
        const response = await fetch('process/update_setting.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `setting_key=${key}&setting_value=${encodeURIComponent(value)}`
        });
        
        const data = await response.json();
        
        if (data.status === 'success') {
            showAlert(data.message, 'success');
        } else {
            showAlert(data.message, 'danger');
        }
    } catch (error) {
        showAlert('Failed to update setting', 'danger');
    }
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