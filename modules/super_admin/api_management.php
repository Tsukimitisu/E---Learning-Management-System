<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SUPER_ADMIN) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "API Management";

/** 
 * BACKEND LOGIC - UNTOUCHED 
 */
$api_keys = $conn->query("
    SELECT ak.*, CONCAT(up.first_name, ' ', up.last_name) as created_by_name
    FROM api_keys ak
    INNER JOIN user_profiles up ON ak.created_by = up.user_id
    ORDER BY ak.created_at DESC
");

include '../../includes/header.php';
include '../../includes/sidebar.php'; // Opens wrapper and starts #content
?>

<style>
    /* --- SCROLL & LAYOUT  --- */
    html, body { height: 100%; margin: 0; overflow: hidden; }

    #content {
        height: 100vh;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .header-fixed-part {
        flex: 0 0 auto;
        background: white;
        padding: 20px 30px;
        border-bottom: 1px solid #eee;
    }

    .body-scroll-part {
        flex: 1 1 auto;
        overflow-y: auto;
        padding: 25px 30px 100px 30px; 
        background-color: #f8f9fa;
    }

    /* --- API UI COMPONENTS --- */
    .api-card {
        background: white;
        border-radius: 15px;
        border: none;
        box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        margin-bottom: 25px;
        overflow: hidden;
    }

    .api-card-header {
        background: #fcfcfc;
        padding: 15px 25px;
        border-bottom: 1px solid #eee;
        font-weight: 700;
        color: var(--blue);
        text-transform: uppercase;
        font-size: 0.8rem;
        letter-spacing: 1px;
    }

    .key-code {
        background: #f1f3f5;
        padding: 4px 8px;
        border-radius: 4px;
        font-family: 'Courier New', monospace;
        font-weight: 600;
        color: #d63384;
        font-size: 0.85rem;
    }

    .doc-box {
        background: #2d3436;
        color: #fab005;
        padding: 20px;
        border-radius: 10px;
        font-family: 'Courier New', monospace;
        font-size: 0.9rem;
        margin-bottom: 15px;
        box-shadow: inset 0 2px 10px rgba(0,0,0,0.2);
    }

    .endpoint-item {
        border-left: 4px solid var(--maroon);
        background: #fff;
        margin-bottom: 10px;
        padding: 12px 15px;
        border-radius: 0 8px 8px 0;
        display: flex;
        align-items: center;
        gap: 15px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.02);
    }

    .method-badge {
        font-size: 0.7rem;
        font-weight: 800;
        padding: 4px 8px;
        border-radius: 4px;
        min-width: 50px;
        text-align: center;
    }

    /* --- MOBILE RESPONSIVE FIXES --- */
    @media (max-width: 576px) {
        .header-fixed-part {
            padding: 15px;
            flex-direction: column;
            gap: 15px;
            text-align: center;
        }
        .header-fixed-part .d-flex { width: 100%; }
        .header-fixed-part button, .header-fixed-part a { flex: 1; }
        
        .body-scroll-part { padding: 15px 15px 100px 15px; }
        .endpoint-item { flex-direction: column; align-items: flex-start; gap: 5px; }
    }
</style>

<!-- Header -->
<div class="header-fixed-part d-flex justify-content-between align-items-center animate__animated animate__fadeInDown">
    <div>
        <h4 class="fw-bold mb-0" style="color: #003366;"><i class="bi bi-cpu-fill me-2"></i>API Management</h4>
        <p class="text-muted small mb-0">Secure access keys and developer documentation</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-maroon btn-sm px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#generateApiKeyModal">
            <i class="bi bi-plus-lg me-1"></i> Generate Key
        </button>
        <a href="dashboard.php" class="btn btn-outline-secondary btn-sm px-3 shadow-sm">
            <i class="bi bi-arrow-left"></i>
        </a>
    </div>
</div>

<!-- Scrollable Content -->
<div class="body-scroll-part animate__animated animate__fadeInUp">
    
    <div id="alertContainer"></div>

    <!-- API Keys Table -->
    <div class="api-card">
        <div class="api-card-header">
            <i class="bi bi-key-fill me-2"></i>Active Credentials
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Identity</th>
                        <th>Service</th>
                        <th>Key Snippet</th>
                        <th>Status</th>
                        <th>Last Activity</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($key = $api_keys->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <div class="fw-bold"><?php echo htmlspecialchars($key['key_name']); ?></div>
                            <small class="text-muted">By <?php echo htmlspecialchars($key['created_by_name']); ?></small>
                        </td>
                        <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($key['service_name'] ?? 'General'); ?></span></td>
                        <td><code class="key-code"><?php echo substr($key['api_key'], 0, 12) . '...'; ?></code></td>
                        <td>
                            <?php if ($key['is_active']): ?>
                                <span class="badge bg-success rounded-pill px-3">Active</span>
                            <?php else: ?>
                                <span class="badge bg-secondary rounded-pill px-3">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <small class="d-block text-muted">Used: <?php echo $key['last_used'] ? date('M d, Y', strtotime($key['last_used'])) : 'Never'; ?></small>
                            <small class="d-block text-danger">Exp: <?php echo $key['expires_at'] ? date('M d, Y', strtotime($key['expires_at'])) : 'Never'; ?></small>
                        </td>
                        <td class="text-end">
                            <button class="btn btn-light btn-sm border me-1" onclick="viewApiKey(<?php echo $key['id']; ?>)">
                                <i class="bi bi-eye text-primary"></i>
                            </button>
                            <button class="btn btn-light btn-sm border" onclick="revokeApiKey(<?php echo $key['id']; ?>)">
                                <i class="bi bi-trash text-danger"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- API Documentation Section -->
    <div class="api-card p-4">
        <h5 class="fw-bold mb-3" style="color: var(--blue);">Developer Documentation</h5>
        <p class="text-muted small">Integrate ELMS data into your external applications using the endpoints below.</p>
        
        <div class="mb-4">
            <label class="form-label fw-bold small text-uppercase">Environment Base URL</label>
            <div class="doc-box">https://api.datamex-elms.edu.ph/v1</div>
        </div>

        <div class="mb-4">
            <label class="form-label fw-bold small text-uppercase">Header Authentication</label>
            <div class="doc-box" style="color: #a29bfe;">Authorization: Bearer <span style="color: #fab005;">{your_api_key}</span></div>
        </div>

        <label class="form-label fw-bold small text-uppercase mb-3">Available Resources</label>
        <div class="endpoint-item">
            <span class="method-badge bg-primary text-white">GET</span>
            <code class="text-dark">/users</code>
            <span class="text-muted small ms-auto">Retrieve all active system users</span>
        </div>
        <div class="endpoint-item">
            <span class="method-badge bg-success text-white">POST</span>
            <code class="text-dark">/users</code>
            <span class="text-muted small ms-auto">Programmatically create new accounts</span>
        </div>
        <div class="endpoint-item">
            <span class="method-badge bg-primary text-white">GET</span>
            <code class="text-dark">/classes</code>
            <span class="text-muted small ms-auto">Fetch real-time class schedules</span>
        </div>
    </div>

</div>

<!-- Generate APIKey Modal -->
<div class="modal fade" id="generateApiKeyModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header p-4 text-white" style="background-color: var(--maroon); border: none;">
                <h5 class="modal-title fw-bold"><i class="bi bi-shield-plus me-2"></i>Generate API Credentials</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="generateApiKeyForm">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">KEY NAME *</label>
                        <input type="text" class="form-control shadow-sm border-light" name="key_name" placeholder="e.g. Mobile App Integration" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">TARGET SERVICE</label>
                        <input type="text" class="form-control shadow-sm border-light" name="service_name" placeholder="e.g. Moodle Sync">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">EXPIRATION DATE (OPTIONAL)</label>
                        <input type="date" class="form-control shadow-sm border-light" name="expires_at">
                    </div>
                </div>
                <div class="modal-footer border-0 p-4">
                    <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-maroon px-4 shadow-sm">Generate Key</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<!-- --- JAVASCRIPT LOGIC  -->
<script>
document.getElementById('generateApiKeyForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    try {
        const response = await fetch('process/generate_api_key.php', {
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
        showAlert('Failed to generate API key', 'danger');
    }
});

function viewApiKey(id) { alert('View API Key Details - ID: ' + id); }

function revokeApiKey(id) {
    if (confirm('Are you sure you want to revoke this API key? Access will be terminated immediately.')) {
        alert('API Key Revoked - ID: ' + id);
    }
}

function showAlert(message, type) {
    const alertHtml = `<div class="alert alert-${type} alert-dismissible fade show border-0 shadow-sm animate__animated animate__shakeX" role="alert"><i class="bi ${type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'} me-2"></i>${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
    document.getElementById('alertContainer').innerHTML = alertHtml;
    document.querySelector('.body-scroll-part').scrollTo({ top: 0, behavior: 'smooth' });
}
</script>
</body>
</html>