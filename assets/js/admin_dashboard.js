/**
 * Admin Dashboard & Settings Helper
 * Handles all admin interactions with proper error handling and concurrency safety
 */

// ===== DASHBOARD FUNCTIONS =====
function loadDashboardStats() {
    fetch('process/dashboard_stats_api.php')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                // Update stat cards
                document.getElementById('total-users').textContent = formatNumber(data.stats.total_users);
                document.getElementById('total-branches').textContent = formatNumber(data.stats.total_branches);
                document.getElementById('failed-logins').textContent = formatNumber(data.stats.failed_logins_today);
                document.getElementById('locked-accounts').textContent = formatNumber(data.stats.locked_accounts);
                document.getElementById('system-health').textContent = data.stats.system_health;
                
                // Update maintenance mode badge
                const modeEl = document.getElementById('maintenance-mode-badge');
                if (modeEl) {
                    if (data.stats.is_maintenance) {
                        modeEl.className = 'badge bg-warning';
                        modeEl.textContent = 'MAINTENANCE MODE';
                    } else {
                        modeEl.className = 'badge bg-success';
                        modeEl.textContent = 'NORMAL';
                    }
                }
                
                // Update recent logs table
                const logsTable = document.getElementById('recent-logs-tbody');
                if (logsTable) {
                    logsTable.innerHTML = data.recent_logs.map(log => `
                        <tr>
                            <td><small>${log.user_name || 'System'}</small></td>
                            <td><small>${log.action}</small></td>
                            <td><small>${new Date(log.created_at).toLocaleString()}</small></td>
                        </tr>
                    `).join('');
                }
                
                // Update role distribution chart if exists
                if (window.roleChart) {
                    window.roleChart.data.labels = data.role_distribution.map(r => r.name);
                    window.roleChart.data.datasets[0].data = data.role_distribution.map(r => r.count);
                    window.roleChart.update();
                }
            } else {
                showAlert('Failed to load dashboard stats', 'error');
            }
        })
        .catch(err => showAlert('Error: ' + err.message, 'error'));
}

// ===== SETTINGS FUNCTIONS =====
function saveSetting(settingKey, formElement) {
    const inputElement = formElement.querySelector(`[name="${settingKey}"]`);
    if (!inputElement) {
        showAlert('Setting input not found', 'error');
        return;
    }
    
    const settingValue = inputElement.value;
    const button = formElement.querySelector('button[type="button"]');
    
    // Disable button to prevent double-click
    button.disabled = true;
    button.textContent = 'Saving...';
    
    const formData = new FormData();
    formData.append('setting_key', settingKey);
    formData.append('setting_value', settingValue);
    
    fetch('process/update_global_setting.php', {
        method: 'POST',
        body: formData
    })
        .then(r => r.json())
        .then(data => {
            button.disabled = false;
            button.innerHTML = '<i class="bi bi-save me-1"></i> Save';
            
            if (data.success) {
                showAlert(`Setting "${settingKey}" updated successfully!`, 'success');
                // Broadcast to other tabs/windows via localStorage
                broadcastSettingChange(settingKey, settingValue);
            } else {
                showAlert(data.message || 'Failed to save setting', 'error');
                // Revert input to previous value
                location.reload();
            }
        })
        .catch(err => {
            button.disabled = false;
            button.innerHTML = '<i class="bi bi-save me-1"></i> Save';
            showAlert('Error: ' + err.message, 'error');
        });
}

// Broadcast setting changes to other open windows (same origin)
function broadcastSettingChange(key, value) {
    try {
        localStorage.setItem('admin_setting_changed', JSON.stringify({
            key: key,
            value: value,
            timestamp: Date.now()
        }));
    } catch (e) {
        // localStorage might be disabled
    }
}

// Listen for setting changes from other tabs
window.addEventListener('storage', (event) => {
    if (event.key === 'admin_setting_changed') {
        const data = JSON.parse(event.newValue);
        console.log(`Setting changed by another tab: ${data.key} = ${data.value}`);
        // Auto-reload if critical setting changed
        if (['maintenance_mode', 'registration_enabled'].includes(data.key)) {
            setTimeout(() => location.reload(), 2000);
        }
    }
});

// ===== AUDIT LOGS FUNCTIONS =====
function loadAuditLogs(page = 1, filters = {}) {
    const params = new URLSearchParams({
        action: 'list',
        page: page,
        limit: filters.limit || 50
    });
    
    if (filters.search) params.append('search', filters.search);
    if (filters.date_from) params.append('date_from', filters.date_from);
    if (filters.date_to) params.append('date_to', filters.date_to);
    if (filters.user_id) params.append('user_id', filters.user_id);
    
    const spinner = document.getElementById('audit-logs-spinner');
    if (spinner) spinner.style.display = 'block';
    
    fetch(`process/audit_logs_api.php?${params}`)
        .then(r => r.json())
        .then(data => {
            if (spinner) spinner.style.display = 'none';
            
            if (data.success) {
                const tbody = document.getElementById('audit-logs-tbody');
                if (tbody) {
                    tbody.innerHTML = data.data.map(log => `
                        <tr>
                            <td>${log.user_name || 'System'}</td>
                            <td><small>${log.action}</small></td>
                            <td>${log.ip_address}</td>
                            <td><small>${new Date(log.created_at).toLocaleString()}</small></td>
                        </tr>
                    `).join('');
                }
                
                // Update pagination
                updatePagination('audit-logs', page, data.pagination);
            } else {
                showAlert(data.message || 'Failed to load audit logs', 'error');
            }
        })
        .catch(err => {
            if (spinner) spinner.style.display = 'none';
            showAlert('Error: ' + err.message, 'error');
        });
}

function exportAuditLogs(filters = {}) {
    const params = new URLSearchParams({
        action: 'export'
    });
    
    if (filters.date_from) params.append('date_from', filters.date_from);
    if (filters.date_to) params.append('date_to', filters.date_to);
    if (filters.user_id) params.append('user_id', filters.user_id);
    
    window.location.href = `process/audit_logs_api.php?${params}`;
}

// ===== SECURITY LOGS FUNCTIONS =====
function loadSecurityLogs(page = 1, filters = {}) {
    const params = new URLSearchParams({
        action: 'list',
        page: page,
        limit: filters.limit || 50
    });
    
    if (filters.search) params.append('search', filters.search);
    if (filters.event_type) params.append('event_type', filters.event_type);
    if (filters.severity) params.append('severity', filters.severity);
    if (filters.date_from) params.append('date_from', filters.date_from);
    if (filters.date_to) params.append('date_to', filters.date_to);
    if (filters.user_id) params.append('user_id', filters.user_id);
    
    const spinner = document.getElementById('security-logs-spinner');
    if (spinner) spinner.style.display = 'block';
    
    fetch(`process/security_logs_api.php?${params}`)
        .then(r => r.json())
        .then(data => {
            if (spinner) spinner.style.display = 'none';
            
            if (data.success) {
                const tbody = document.getElementById('security-logs-tbody');
                if (tbody) {
                    tbody.innerHTML = data.data.map(log => `
                        <tr>
                            <td>${log.user_name || 'System'}</td>
                            <td><span class="badge bg-info">${log.event_type}</span></td>
                            <td><small>${log.details}</small></td>
                            <td>${log.ip_address}</td>
                            <td><span class="badge bg-${getSeverityColor(log.severity)}">${log.severity || 'info'}</span></td>
                            <td><small>${new Date(log.created_at).toLocaleString()}</small></td>
                        </tr>
                    `).join('');
                }
                
                // Update pagination
                updatePagination('security-logs', page, data.pagination);
            } else {
                showAlert(data.message || 'Failed to load security logs', 'error');
            }
        })
        .catch(err => {
            if (spinner) spinner.style.display = 'none';
            showAlert('Error: ' + err.message, 'error');
        });
}

function exportSecurityLogs(filters = {}) {
    const params = new URLSearchParams({
        action: 'export'
    });
    
    if (filters.event_type) params.append('event_type', filters.event_type);
    if (filters.date_from) params.append('date_from', filters.date_from);
    if (filters.date_to) params.append('date_to', filters.date_to);
    if (filters.user_id) params.append('user_id', filters.user_id);
    
    window.location.href = `process/security_logs_api.php?${params}`;
}

function getSeverityColor(severity) {
    const colors = {
        'critical': 'danger',
        'high': 'warning',
        'medium': 'info',
        'low': 'secondary',
        'info': 'info'
    };
    return colors[severity] || 'secondary';
}

// ===== FORCE LOGOUT FUNCTION =====
function forceLogoutUser(userId, userName) {
    if (!confirm(`Force logout user "${userName}"? All their active sessions will be terminated.`)) {
        return;
    }
    
    const formData = new FormData();
    formData.append('user_id', userId);
    
    fetch('process/force_logout.php', {
        method: 'POST',
        body: formData
    })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showAlert(data.message, 'success');
                // Reload security logs
                setTimeout(() => loadSecurityLogs(1), 1000);
            } else {
                showAlert(data.message || 'Force logout failed', 'error');
            }
        })
        .catch(err => showAlert('Error: ' + err.message, 'error'));
}

// ===== BRANCH MANAGEMENT FUNCTIONS =====
function loadBranches() {
    fetch('process/branch_management_api.php?action=list')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const tbody = document.getElementById('branches-tbody');
                if (tbody) {
                    const branches = data.branches || data.data || [];
                    tbody.innerHTML = branches.map(branch => `
                        <tr>
                            <td>${branch.name}</td>
                            <td>${branch.address}</td>
                            <td><span class="badge bg-info">${branch.staff_count} staff</span></td>
                            <td><small>${new Date(branch.created_at).toLocaleString()}</small></td>
                            <td>
                                <button class="btn btn-sm btn-warning" onclick="editBranch(${branch.id}, '${branch.name}')">
                                    <i class="bi bi-pencil"></i> Edit
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="deleteBranch(${branch.id}, '${branch.name}')">
                                    <i class="bi bi-trash"></i> Delete
                                </button>
                            </td>
                        </tr>
                    `).join('');
                }
            } else {
                showAlert(data.message || 'Failed to load branches', 'error');
            }
        })
        .catch(err => showAlert('Error: ' + err.message, 'error'));
}

function saveBranch() {
    const branchId = document.getElementById('branch-id').value;
    const name = document.getElementById('branch-name').value;
    const address = document.getElementById('branch-address').value;
    
    if (!name.trim()) {
        showAlert('Branch name is required', 'error');
        return;
    }
    
    const formData = new FormData();
    formData.append('branch_id', branchId);
    formData.append('name', name);
    formData.append('address', address);
    
    // Use a compatibility create endpoint to avoid audit-schema mismatches on some installs
    const endpoint = branchId ? 'process/branch_management_api.php' : 'process/branch_create_compat.php';
    fetch(endpoint, {
        method: 'POST',
        body: formData
    })
        .then(r => r.json())
        .then(data => {
                if (data.success) {
                showAlert(data.message, 'success');
                try { bootstrap.Modal.getInstance(document.getElementById('branch-modal'))?.hide(); } catch(e) {}
                loadBranches();
            } else {
                showAlert(data.message || 'Failed to save branch', 'error');
            }
        })
        .catch(err => showAlert('Error: ' + err.message, 'error'));
}

function editBranch(branchId, branchName) {
    document.getElementById('branch-id').value = branchId;
    document.getElementById('branch-name').value = branchName;
    // Load full details if needed
    const modal = new bootstrap.Modal(document.getElementById('branch-modal'));
    modal.show();
}

function deleteBranch(branchId, branchName) {
    if (!confirm(`Delete branch "${branchName}"?`)) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('branch_id', branchId);
    
    fetch('process/branch_management_api.php', {
        method: 'POST',
        body: formData
    })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showAlert(data.message, 'success');
                loadBranches();
            } else {
                showAlert(data.message || 'Failed to delete branch', 'error');
            }
        })
        .catch(err => showAlert('Error: ' + err.message, 'error'));
}

// ===== UTILITY FUNCTIONS =====
function showAlert(message, type = 'info') {
    const alertContainer = document.getElementById('alertContainer');
    if (!alertContainer) {
        alert(message);
        return;
    }
    
    const alertId = 'alert-' + Date.now();
    const alert = document.createElement('div');
    alert.id = alertId;
    alert.className = `alert alert-${type} alert-dismissible fade show`;
    alert.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    alertContainer.appendChild(alert);
    
    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        const el = document.getElementById(alertId);
        if (el) el.remove();
    }, 5000);
}

function formatNumber(num) {
    return new Intl.NumberFormat().format(num);
}

function updatePagination(prefix, currentPage, pagination) {
    const container = document.getElementById(`${prefix}-pagination`);
    if (!container) return;
    
    let html = '<nav><ul class="pagination pagination-sm">';
    
    // Previous button
    html += `<li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="load${capitalize(prefix)}(${currentPage - 1}); return false;">Previous</a>
    </li>`;
    
    // Page numbers
    for (let i = Math.max(1, currentPage - 2); i <= Math.min(pagination.pages, currentPage + 2); i++) {
        html += `<li class="page-item ${i === currentPage ? 'active' : ''}">
            <a class="page-link" href="#" onclick="load${capitalize(prefix)}(${i}); return false;">${i}</a>
        </li>`;
    }
    
    // Next button
    html += `<li class="page-item ${currentPage >= pagination.pages ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="load${capitalize(prefix)}(${currentPage + 1}); return false;">Next</a>
    </li>`;
    
    html += '</ul></nav>';
    container.innerHTML = html;
}

function capitalize(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
}

// Auto-load on page ready
document.addEventListener('DOMContentLoaded', () => {
    // Determine which page we're on and load appropriate data
    if (document.getElementById('dashboard-stats')) loadDashboardStats();
    if (document.getElementById('audit-logs-tbody')) loadAuditLogs(1);
    if (document.getElementById('security-logs-tbody')) loadSecurityLogs(1);
    if (document.getElementById('branches-tbody')) loadBranches();
    
    // Refresh dashboard every 30 seconds
    if (document.getElementById('dashboard-stats')) {
        setInterval(loadDashboardStats, 30000);
    }
});
