<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SCHOOL_ADMIN) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Branch Management";

include '../../includes/header.php';
include '../../includes/sidebar.php'; 
?>

<style>
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

    .mgmt-card {
        background: white;
        border-radius: 15px;
        border: none;
        box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        margin-bottom: 30px;
        overflow: hidden;
    }

    .card-header-maroon { 
        background: var(--maroon); 
        color: white; 
        padding: 15px 25px; 
        font-weight: 700; 
        text-transform: uppercase; 
        font-size: 0.85rem; 
        letter-spacing: 1px; 
    }

    .table thead th {
        background: #fcfcfc;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #888;
        padding: 15px;
        border-bottom: 2px solid #f1f1f1;
    }

    .table tbody td { 
        padding: 15px; 
        vertical-align: middle; 
    }

    .btn-maroon-action { 
        background: var(--maroon); 
        color: white; 
        border: none; 
        border-radius: 8px; 
        font-weight: 600; 
        padding: 10px 20px; 
        transition: 0.3s; 
        cursor: pointer;
    }
    
    .btn-maroon-action:hover { 
        background: #600000; 
        color: white; 
        transform: translateY(-2px); 
        box-shadow: 0 4px 10px rgba(128,0,0,0.2); 
    }

    .btn-danger-action { 
        background: #dc3545; 
        color: white; 
        border: none; 
        border-radius: 8px; 
        font-weight: 600; 
        padding: 10px 20px; 
        transition: 0.3s; 
        cursor: pointer;
    }
    
    .btn-danger-action:hover { 
        background: #c82333; 
        color: white; 
        transform: translateY(-2px); 
        box-shadow: 0 4px 10px rgba(220,53,69,0.2); 
    }

    .modal-body {
        max-height: 500px;
        overflow-y: auto;
    }
</style>

<!-- Header -->
<div class="header-fixed-part d-flex justify-content-between align-items-center animate__animated animate__fadeInDown">
    <div>
        <h4 class="fw-bold mb-0" style="color: var(--maroon);"><i class="bi bi-diagram-3 me-2"></i>Branch Management</h4>
        <p class="text-muted small mb-0">Manage your institution's branches and locations</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-maroon-action" onclick="openAddBranchModal()">
            <i class="bi bi-plus-circle me-2"></i>Add Branch
        </button>
        <a href="dashboard.php" class="btn btn-outline-secondary btn-sm px-3">
            <i class="bi bi-arrow-left"></i>
        </a>
    </div>
</div>

<!-- Body -->
<div class="body-scroll-part animate__animated animate__fadeInUp">
    
    <div id="alertContainer"></div>

    <!-- Branches Table -->
    <div class="mgmt-card">
        <div class="card-header-maroon">
            <i class="bi bi-diagram-3 me-2"></i> Registered Branches
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Branch Name</th>
                            <th>Address</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="branches-tbody">
                        <!-- Populated by JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Branch Modal -->
<div id="branch-modal" class="modal fade" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background: var(--maroon); color: white;">
                <h5 class="modal-title" id="modalTitle">Add Branch</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="branchForm">
                    <input type="hidden" id="branch-id" value="">
                    
                    <div class="mb-3">
                        <label for="branch-name" class="form-label">Branch Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control border-light shadow-sm" id="branch-name" placeholder="e.g., Main Campus, Satellite Branch" required>
                    </div>

                    <div class="mb-3">
                        <label for="branch-address" class="form-label">Address</label>
                        <textarea class="form-control border-light shadow-sm" id="branch-address" rows="3" placeholder="Branch location address"></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="branch-contact" class="form-label">Contact Number</label>
                        <input type="text" class="form-control border-light shadow-sm" id="branch-contact" placeholder="+63 9XX XXXX XXX">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-maroon-action" onclick="saveBranch()">
                    <i class="bi bi-save me-1"></i> Save Branch
                </button>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<!-- Admin Dashboard Library -->
<script src="../../assets/js/admin_dashboard.js"></script>

<script>
    // Minimal page-specific helpers — keep IDs consistent with `admin_dashboard.js`
    function openAddBranchModal() {
        document.getElementById('branch-id').value = '';
        document.getElementById('branch-name').value = '';
        document.getElementById('branch-address').value = '';
        document.getElementById('branch-contact').value = '';
        document.getElementById('modalTitle').textContent = 'Add Branch';
        const modal = new bootstrap.Modal(document.getElementById('branch-modal'));
        modal.show();
    }

    // Fix: Edit branch logic
    function editBranch(id) {
        const row = Array.from(document.querySelectorAll('#branches-tbody tr')).find(tr => tr.children[0].textContent == id);
        if (!row || row.children.length < 3) return showAlert('Branch not found', 'danger');
        document.getElementById('branch-id').value = id;
        document.getElementById('branch-name').value = (row.children[1].textContent || '').trim();
        document.getElementById('branch-address').value = (row.children[2].textContent || '').trim();
        document.getElementById('modalTitle').textContent = 'Edit Branch';
        const modalEl = document.getElementById('branch-modal');
        if (!modalEl.classList.contains('show')) {
            const modal = new bootstrap.Modal(modalEl);
            modal.show();
        }
    }

    // Fetch and display branches
    async function loadBranches() {
        const tbody = document.getElementById('branches-tbody');
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">Loading...</td></tr>';
        try {
            const res = await fetch('process/branch_management_api.php?action=list');
            const data = await res.json();
                if (data.success && Array.isArray(data.branches)) {
                    if (data.branches.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No branches found.</td></tr>';
                    } else {
                        tbody.innerHTML = data.branches.map(b => `
                            <tr>
                                <td>${b.id}</td>
                                <td>${b.name}</td>
                                <td>${b.address || ''}</td>
                                <td>
                                    <button class="btn btn-warning btn-sm" title="Edit" onclick="editBranch(${b.id})"><i class="bi bi-pencil"></i></button>
                                    <button class="btn btn-danger btn-sm" title="Delete" onclick="deleteBranch(${b.id})"><i class="bi bi-trash"></i></button>
                                </td>
                            </tr>
                        `).join('');
                    }
                } else {
                    let msg = data.message || 'Failed to load branches.';
                    if (data.message && data.message.includes('Unauthorized')) {
                        msg = 'Session expired or unauthorized. Please log in again.';
                    }
                    tbody.innerHTML = `<tr><td colspan="5" class="text-center text-danger">${msg}</td></tr>`;
                }
            } catch (err) {
                tbody.innerHTML = `<tr><td colspan="5" class="text-center text-danger">Error loading branches: ${err.message}</td></tr>`;
            }
    }

    // Add branch via AJAX
    async function saveBranch() {
        const name = document.getElementById('branch-name').value.trim();
        const address = document.getElementById('branch-address').value.trim();
        if (!name) {
            showAlert('Branch name is required', 'danger');
            return;
        }
        const btn = document.querySelector('#branch-modal .btn-maroon-action');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving...';
        try {
            const formData = new FormData();
            formData.append('action', 'create');
            formData.append('name', name);
            formData.append('address', address);
            const res = await fetch('process/branch_management_api.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                showAlert(data.message || 'Branch added!', 'success');
                bootstrap.Modal.getInstance(document.getElementById('branch-modal')).hide();
                setTimeout(() => loadBranches(), 400);
            } else {
                showAlert(data.message || 'Failed to add branch', 'danger');
            }
        } catch (err) {
            showAlert('Error: ' + err.message, 'danger');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-save me-1"></i> Save Branch';
        }
    }

    // Show alert in alertContainer
    function showAlert(msg, type = 'info') {
        const html = `<div class="alert alert-${type} alert-dismissible fade show border-0 shadow-sm animate__animated animate__shakeX" role="alert">${msg}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
        document.getElementById('alertContainer').innerHTML = html;
    }

    // Ensure add button uses our helper and load branches on page load
    document.addEventListener('DOMContentLoaded', function() {
        const addBtn = document.querySelector('.btn-maroon-action[onclick*="openAddBranchModal"]');
        if (addBtn) addBtn.addEventListener('click', openAddBranchModal);
        loadBranches();
    });
</script>

</body>
</html>
