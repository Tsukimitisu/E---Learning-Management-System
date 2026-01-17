<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SUPER_ADMIN) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "System Settings";
$settings_query = "SELECT * FROM system_settings ORDER BY category, setting_key";
$settings_result = $conn->query($settings_query);

$grouped_settings = [];
while ($setting = $settings_result->fetch_assoc()) {
    $category = $setting['category'] ?? 'general';
    $grouped_settings[$category][] = $setting;
}

include '../../includes/header.php';
include '../../includes/sidebar.php'; 
?>

<style>
    html, body {
        height: 100%;
        margin: 0;
        padding: 0;
        overflow: hidden; 
    }

    #content {
        height: 100vh; 
        display: flex;
        flex-direction: column; 
        overflow: hidden;
        background-color: #f4f7f6;
    }

    /* 1. This header stays at the top */
    .static-header {
        flex: 0 0 auto; 
        background: white;
        padding: 20px 30px;
        border-bottom: 1px solid #eee;
        z-index: 10;
    }

    /* 2. This area fills the rest of the screen and SCROLLS */
    .scrollable-settings-body {
        flex: 1 1 auto; 
        overflow-y: auto; 
        padding: 20px 30px 100px 30px; 
    }
    .settings-group-card {
        background: white;
        border: none;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        margin-bottom: 25px;
        overflow: hidden;
    }

    .group-title-bar {
        background: #f8f9fa;
        padding: 12px 20px;
        border-bottom: 1px solid #eee;
        color: #003366;
        font-weight: 700;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .setting-row {
        padding: 15px 20px;
        border-bottom: 1px solid #f9f9f9;
        transition: 0.2s;
    }
    .setting-row:hover { background-color: #fcfcfc; }

    .setting-name { font-weight: 600; color: #333; display: block; margin-bottom: 2px; }
    .setting-help { font-size: 0.75rem; color: #888; display: block; }

    .btn-save-setting {
        background-color: #800000;
        color: white;
        border: none;
        font-weight: 600;
        padding: 8px 15px;
        border-radius: 6px;
        transition: 0.2s;
        width: 100%;
    }
    .btn-save-setting:hover {
        background-color: #600000;
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(128,0,0,0.2);
    }
</style>

<!-- Top Header (Locked) -->
<div class="static-header d-flex justify-content-between align-items-center animate__animated animate__fadeInDown">
    <div>
        <h4 class="fw-bold mb-0" style="color: #003366;">System Configuration</h4>
        <p class="text-muted small mb-0">Adjust system-wide policies and application settings</p>
    </div>
    <a href="dashboard.php" class="btn btn-outline-secondary btn-sm px-4">
        <i class="bi bi-arrow-left me-2"></i>Back
    </a>
</div>

<!-- Scrollable Settings Container -->
<div class="scrollable-settings-body animate__animated animate__fadeInUp">
    
    <div id="alertContainer"></div>

    <?php foreach ($grouped_settings as $category => $settings): ?>
    <div class="settings-group-card">
        <div class="group-title-bar">
            <i class="bi bi-sliders me-2"></i> <?php echo ucfirst($category); ?> Settings
        </div>
        
        <div class="card-body p-0">
            <?php foreach ($settings as $setting): ?>
            <form class="settings-form">
                <div class="row setting-row align-items-center g-3">
                    <!-- Description -->
                    <div class="col-lg-5 col-md-4">
                        <span class="setting-name"><?php echo ucwords(str_replace('_', ' ', $setting['setting_key'])); ?></span>
                        <span class="setting-help"><?php echo htmlspecialchars($setting['description'] ?? ''); ?></span>
                    </div>
                    
                    <!-- Input -->
                    <div class="col-lg-5 col-md-5">
                        <?php if ($setting['setting_type'] == 'boolean'): ?>
                            <select class="form-select border-light shadow-sm" name="<?php echo $setting['setting_key']; ?>">
                                <option value="1" <?php echo $setting['setting_value'] == '1' ? 'selected' : ''; ?>>Enabled</option>
                                <option value="0" <?php echo $setting['setting_value'] == '0' ? 'selected' : ''; ?>>Disabled</option>
                            </select>
                        <?php elseif ($setting['setting_type'] == 'number'): ?>
                            <input type="number" class="form-control border-light shadow-sm" name="<?php echo $setting['setting_key']; ?>" value="<?php echo htmlspecialchars($setting['setting_value']); ?>">
                        <?php else: ?>
                            <input type="text" class="form-control border-light shadow-sm" name="<?php echo $setting['setting_key']; ?>" value="<?php echo htmlspecialchars($setting['setting_value']); ?>">
                        <?php endif; ?>
                    </div>
                    
                    <!-- Save Button -->
                    <div class="col-lg-2 col-md-3">
                        <button type="button" class="btn btn-save-setting" onclick="saveSetting('<?php echo $setting['setting_key']; ?>', this.closest('form'))">
                            <i class="bi bi-save me-1"></i> Save
                        </button>
                    </div>
                </div>
            </form>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
    <div class="text-center pb-5">
        <p class="text-muted small">All changes take effect immediately.</p>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

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
        <div class="alert alert-${type} alert-dismissible fade show border-0 shadow-sm animate__animated animate__shakeX" role="alert">
            <i class="bi ${type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    document.getElementById('alertContainer').innerHTML = alertHtml;

    document.querySelector('.scrollable-settings-body').scrollTo({ top: 0, behavior: 'smooth' });
}
</script>