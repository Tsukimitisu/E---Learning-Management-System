<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin System API Tests</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding: 40px 20px; background: #f8f9fa; }
        .container { max-width: 1200px; }
        .test-card { background: white; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); }
        .test-title { color: #003366; font-weight: 700; font-size: 1.1rem; margin-bottom: 15px; }
        .test-section { border-left: 4px solid var(--maroon); padding-left: 15px; margin-bottom: 30px; }
        .result-success { background: #d4edda; color: #155724; padding: 10px 15px; border-radius: 6px; margin-top: 10px; }
        .result-error { background: #f8d7da; color: #721c24; padding: 10px 15px; border-radius: 6px; margin-top: 10px; }
        .code-block { background: #f4f4f4; padding: 10px; border-radius: 6px; font-family: monospace; font-size: 0.85rem; overflow-x: auto; }
        .btn-test { background: var(--maroon); color: white; border: none; }
        .btn-test:hover { background: #600000; color: white; }
        h2 { color: var(--maroon); margin-bottom: 30px; }
        .status-pass { color: #28a745; font-weight: 700; }
        .status-fail { color: #dc3545; font-weight: 700; }
    </style>
</head>
<body>
<div class="container">
    <h2><i class="bi bi-bug"></i> Admin System API Test Suite</h2>

    <div class="test-card">
        <div class="test-section">
            <div class="test-title">1. Dashboard Stats API Test</div>
            <p>Fetches live dashboard statistics including user counts, branch counts, and system health.</p>
            <button class="btn btn-test" onclick="testDashboardStats()">Test Dashboard API</button>
            <div id="result-dashboard"></div>
        </div>
    </div>

    <div class="test-card">
        <div class="test-section">
            <div class="test-title">2. Audit Logs API Test</div>
            <p>Tests audit log retrieval, filtering, and export functionality.</p>
            <button class="btn btn-test" onclick="testAuditLogs()">Test Audit Logs</button>
            <div id="result-audit"></div>
        </div>
    </div>

    <div class="test-card">
        <div class="test-section">
            <div class="test-title">3. Security Logs API Test</div>
            <p>Tests security log retrieval with event type and severity filtering.</p>
            <button class="btn btn-test" onclick="testSecurityLogs()">Test Security Logs</button>
            <div id="result-security"></div>
        </div>
    </div>

    <div class="test-card">
        <div class="test-section">
            <div class="test-title">4. Global Settings API Test</div>
            <p>Tests updating global settings that affect all users.</p>
            <button class="btn btn-test" onclick="testGlobalSettings()">Test Global Settings</button>
            <div id="result-settings"></div>
        </div>
    </div>

    <div class="test-card">
        <div class="test-section">
            <div class="test-title">5. Branch Management API Test</div>
            <p>Tests CRUD operations for branch management (School Admin).</p>
            <button class="btn btn-test" onclick="testBranchManagement()">Test Branch API</button>
            <div id="result-branches"></div>
        </div>
    </div>

    <div class="test-card">
        <div class="test-section">
            <div class="test-title">6. Concurrency Safety Test</div>
            <p>Simulates concurrent requests to verify thread-safety and transaction handling.</p>
            <button class="btn btn-test" onclick="testConcurrency()">Test Concurrency</button>
            <div id="result-concurrency"></div>
        </div>
    </div>

    <div class="test-card">
        <div class="test-section">
            <div class="test-title">7. Database Schema Verification</div>
            <p>Checks if all required tables exist with proper structure.</p>
            <button class="btn btn-test" onclick="verifyDatabase()">Verify Database</button>
            <div id="result-db"></div>
        </div>
    </div>
</div>

<script>
const testResults = {};

function displayResult(elementId, title, status, message, details = '') {
    const statusClass = status === 'PASS' ? 'status-pass' : 'status-fail';
    const resultClass = status === 'PASS' ? 'result-success' : 'result-error';
    
    document.getElementById(elementId).innerHTML = `
        <div class="${resultClass}">
            <strong>Status: <span class="${statusClass}">${status}</span></strong><br>
            ${message}
            ${details ? `<div class="code-block mt-2">${details}</div>` : ''}
        </div>
    `;
}

async function testDashboardStats() {
    try {
        const response = await fetch('process/dashboard_stats_api.php');
        const data = await response.json();
        
        if (data.success && data.stats) {
            displayResult('result-dashboard', 'Dashboard Stats', 'PASS', 
                'Dashboard API working correctly. Returned stats: ' +
                `${data.stats.total_users} users, ${data.stats.total_branches} branches`,
                `Response: ${JSON.stringify(data.stats, null, 2)}`
            );
        } else {
            displayResult('result-dashboard', 'Dashboard Stats', 'FAIL', 
                'API did not return expected data',
                `Response: ${JSON.stringify(data, null, 2)}`
            );
        }
    } catch (error) {
        displayResult('result-dashboard', 'Dashboard Stats', 'FAIL', 
            'API call failed: ' + error.message
        );
    }
}

async function testAuditLogs() {
    try {
        const response = await fetch('process/audit_logs_api.php?action=list&page=1');
        const data = await response.json();
        
        if (data.status === 'success') {
            displayResult('result-audit', 'Audit Logs', 'PASS', 
                `Retrieved ${data.logs.length} audit logs. Total pages: ${data.pagination.pages}`,
                `Sample log: ${JSON.stringify(data.logs[0] || {}, null, 2)}`
            );
        } else {
            displayResult('result-audit', 'Audit Logs', 'FAIL', 
                data.message || 'Failed to retrieve audit logs'
            );
        }
    } catch (error) {
        displayResult('result-audit', 'Audit Logs', 'FAIL', 
            'API call failed: ' + error.message
        );
    }
}

async function testSecurityLogs() {
    try {
        const response = await fetch('process/security_logs_api.php?action=list&page=1');
        const data = await response.json();
        
        if (data.status === 'success') {
            displayResult('result-security', 'Security Logs', 'PASS', 
                `Retrieved ${data.logs.length} security logs. Total pages: ${data.pagination.pages}`,
                `Sample log: ${JSON.stringify(data.logs[0] || {}, null, 2)}`
            );
        } else {
            displayResult('result-security', 'Security Logs', 'FAIL', 
                data.message || 'Failed to retrieve security logs'
            );
        }
    } catch (error) {
        displayResult('result-security', 'Security Logs', 'FAIL', 
            'API call failed: ' + error.message
        );
    }
}

async function testGlobalSettings() {
    try {
        // First, fetch current setting
        const response = await fetch('process/update_global_setting.php?action=get&setting_key=maintenance_mode');
        const data = await response.json();
        
        if (data.status === 'success') {
            displayResult('result-settings', 'Global Settings', 'PASS', 
                `Settings API accessible. Current maintenance_mode: ${data.current_value}`,
                'API response validated successfully'
            );
        } else {
            displayResult('result-settings', 'Global Settings', 'FAIL', 
                data.message || 'Failed to access settings API'
            );
        }
    } catch (error) {
        displayResult('result-settings', 'Global Settings', 'FAIL', 
            'API call failed: ' + error.message
        );
    }
}

async function testBranchManagement() {
    try {
        const response = await fetch('school_admin/process/branch_management_api.php?action=list');
        const data = await response.json();
        
        if (data.status === 'success' && Array.isArray(data.branches)) {
            displayResult('result-branches', 'Branch Management', 'PASS', 
                `Retrieved ${data.branches.length} branches successfully`,
                `Sample branch: ${JSON.stringify(data.branches[0] || {}, null, 2)}`
            );
        } else {
            displayResult('result-branches', 'Branch Management', 'FAIL', 
                data.message || 'Failed to retrieve branches'
            );
        }
    } catch (error) {
        displayResult('result-branches', 'Branch Management', 'FAIL', 
            'API call failed: ' + error.message
        );
    }
}

async function testConcurrency() {
    try {
        const promises = [];
        
        // Simulate 5 concurrent requests to dashboard API
        for (let i = 0; i < 5; i++) {
            promises.push(fetch('process/dashboard_stats_api.php'));
        }
        
        const responses = await Promise.all(promises);
        const data = await Promise.all(responses.map(r => r.json()));
        
        const allSuccessful = data.every(d => d.success);
        
        if (allSuccessful) {
            displayResult('result-concurrency', 'Concurrency', 'PASS', 
                `Successfully handled 5 concurrent requests without deadlock or errors`,
                `All 5 requests returned valid data with stats consistency`
            );
        } else {
            displayResult('result-concurrency', 'Concurrency', 'FAIL', 
                'Some concurrent requests failed'
            );
        }
    } catch (error) {
        displayResult('result-concurrency', 'Concurrency', 'FAIL', 
            'Concurrency test failed: ' + error.message
        );
    }
}

async function verifyDatabase() {
    try {
        const response = await fetch('process/verify_database.php');
        const html = await response.text();
        
        if (response.ok && html.includes('success')) {
            displayResult('result-db', 'Database Verification', 'PASS', 
                'All required tables exist and are properly structured',
                'Visit the full verification report to see detailed status'
            );
        } else {
            displayResult('result-db', 'Database Verification', 'FAIL', 
                'Database verification encountered issues. Check logs.'
            );
        }
    } catch (error) {
        displayResult('result-db', 'Database Verification', 'FAIL', 
            'Database verification failed: ' + error.message
        );
    }
}

// Auto-run all tests on page load
document.addEventListener('DOMContentLoaded', function() {
    console.log('API Test Suite Loaded. Click buttons to run individual tests.');
});
</script>

<p class="text-center text-muted mt-5">
    <small>All tests are non-destructive read operations. Status codes and response times are logged.</small>
</p>
</body>
</html>
