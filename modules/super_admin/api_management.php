<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SUPER_ADMIN) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "API Management";

// Fetch all API keys
$api_keys = $conn->query("
    SELECT ak.*, CONCAT(up.first_name, ' ', up.last_name) as created_by_name
    FROM api_keys ak
    INNER JOIN user_profiles up ON ak.created_by = up.user_id
    ORDER BY ak.created_at DESC
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
                    <h4 class="mb-1" style="color: var(--navy); font-weight: 600;">API Management</h4>
                    <small class="text-muted">Manage API keys and integrations</small>
                </div>
                <div>
                    <button class="btn btn-primary-minimal" data-bs-toggle="modal" data-bs-target="#generateApiKeyModal">
                        <i class="bi bi-plus-circle"></i> Generate API Key
                    </button>
                    <a href="dashboard.php" class="btn btn-minimal ms-2">
                        <i class="bi bi-arrow-left"></i> Back
                    </a>
                </div>
            </div>
        </div>

        <div id="alertContainer"></div>

        <!-- API Keys Table -->
        <div class="minimal-card">
            <h5 class="section-title">API Keys</h5>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead style="background-color: var(--light-gray);">
                        <tr>
                            <th>Key Name</th>
                            <th>Service</th>
                            <th>API Key</th>
                            <th>Status</th>
                            <th>Last Used</th>
                            <th>Expires</th>
                            <th>Created By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($key = $api_keys->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($key['key_name']); ?></td>
                            <td><?php echo htmlspecialchars($key['service_name'] ?? '-'); ?></td>
                            <td><code><?php echo substr($key['api_key'], 0, 20) . '...'; ?></code></td>
                            <td>
                                <?php if ($key['is_active']): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td><small><?php echo $key['last_used'] ? date('M d, Y', strtotime($key['last_used'])) : 'Never'; ?></small></td>
                            <td><small><?php echo $key['expires_at'] ? date('M d, Y', strtotime($key['expires_at'])) : 'Never'; ?></small></td>
                            <td><?php echo htmlspecialchars($key['created_by_name']); ?></td>
                            <td>
                                <button class="btn btn-sm btn-minimal" onclick="viewApiKey(<?php echo $key['id']; ?>)">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="revokeApiKey(<?php echo $key['id']; ?>)">
                                    <i class="bi bi-x-circle"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- API Documentation -->
        <div class="minimal-card">
            <h5 class="section-title">API Documentation</h5>
            <p class="text-muted">Base URL: <code>https://your-domain.com/api/v1</code></p>
            
            <h6 class="mt-3">Authentication</h6>
            <pre class="bg-light p-3 rounded"><code>Authorization: Bearer {your_api_key}</code></pre>
            
            <h6 class="mt-3">Available Endpoints</h6>
            <ul class="list-group">
                <li class="list-group-item"><code>GET /users</code> - Get all users</li>
                <li class="list-group-item"><code>POST /users</code> - Create user</li>
                <li class="list-group-item"><code>GET /classes</code> - Get all classes</li>
                <li class="list-group-item"><code>POST /enrollments</code> - Enroll student</li>
            </ul>
        </div>
    </div>
</div>

<!-- Generate APIKey Modal -->
<div class="modal fade" id="generateApiKeyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background-color: var(--maroon); color: white;">
                <h5 class="modal-title">Generate New API Key</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="generateApiKeyForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Key Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="key_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Service Name</label>
                        <input type="text" class="form-control" name="service_name" placeholder="e.g. Payment Gateway">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Expiration Date (Optional)</label>
                        <input type="date" class="form-control" name="expires_at">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-minimal" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-minimal">
                        <i class="bi bi-key"></i> Generate
                    </button>
                </div>
            </form>
        </div>
    </div>
</div><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('generateApiKeyForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);try {
    const response = await fetch('process/generate_api_key.php', {
        method: 'POST',
        body: formData
    });    const data = await response.json();    if (data.status === 'success') {
        showAlert(data.message, 'success');
        setTimeout(() => location.reload(), 1500);
    } else {
        showAlert(data.message, 'danger');
    }
} catch (error) {
    showAlert('Failed to generate API key', 'danger');
}
});function viewApiKey(id) {
alert('View API Key details - ID: ' + id);
}function revokeApiKey(id) {
if (confirm('Are you sure you want to revoke this API key?')) {
alert('API Key revoked - ID: ' + id);
}
}function showAlert(message, type) {
const alertHtml =         <div class="alert alert-${type} alert-minimal alert-dismissible fade show" role="alert">             ${message}             <button type="button" class="btn-close" data-bs-dismiss="alert"></button>         </div>    ;
document.getElementById('alertContainer').innerHTML = alertHtml;
window.scrollTo({ top: 0, behavior: 'smooth' });
}
</script>
</body>
</html>