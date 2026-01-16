/**
 * Real-Time Notification Polling System
 * Checks for new grades and materials every 3 seconds
 */

// Only run for student users
const userRole = document.body.dataset.userRole || null;

if (userRole === '6' || window.location.pathname.includes('/student/')) {
    let lastCheckTime = Math.floor(Date.now() / 1000);
    
    // Poll every 3 seconds
    setInterval(checkForUpdates, 3000);
    
    // Initial check
    checkForUpdates();
    
    async function checkForUpdates() {
        try {
            const response = await fetch('../../api/check_updates.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `last_check=${lastCheckTime}`
            });
            
            const data = await response.json();
            
            if (data.status === 'success') {
                const totalUpdates = data.new_grades + data.new_materials;
                
                if (totalUpdates > 0) {
                    showNotificationBadge(totalUpdates);
                    
                    // Optional: Show toast notification
                    if (data.new_grades > 0) {
                        showToast(`You have ${data.new_grades} new grade(s)!`, 'success');
                    }
                    if (data.new_materials > 0) {
                        showToast(`${data.new_materials} new learning material(s) uploaded!`, 'info');
                    }
                } else {
                    hideNotificationBadge();
                }
                
                // Update last check time
                lastCheckTime = data.current_time;
            }
        } catch (error) {
            console.error('Notification check failed:', error);
        }
    }
    
    function showNotificationBadge(count) {
        const badge = document.getElementById('notificationBadge');
        const countSpan = document.getElementById('notificationCount');
        
        if (badge && countSpan) {
            countSpan.textContent = count;
            badge.style.display = 'inline-block';
            
            // Add animation
            badge.classList.add('animate-pulse');
        }
    }
    
    function hideNotificationBadge() {
        const badge = document.getElementById('notificationBadge');
        if (badge) {
            badge.style.display = 'none';
        }
    }
    
    function showToast(message, type = 'info') {
        // Check if toast container exists
        let toastContainer = document.getElementById('toastContainer');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'toastContainer';
            toastContainer.style.cssText = 'position: fixed; top: 80px; right: 20px; z-index: 9999;';
            document.body.appendChild(toastContainer);
        }
        
        const toastId = 'toast_' + Date.now();
        const bgColor = type === 'success' ? 'bg-success' : type === 'danger' ? 'bg-danger' : 'bg-info';
        
        const toastHTML = `
            <div id="${toastId}" class="toast align-items-center text-white ${bgColor} border-0" role="alert">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="bi bi-bell"></i> ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        `;
        
        toastContainer.insertAdjacentHTML('beforeend', toastHTML);
        
        const toastElement = document.getElementById(toastId);
        const toast = new bootstrap.Toast(toastElement, { delay: 5000 });
        toast.show();
        
        // Remove from DOM after hidden
        toastElement.addEventListener('hidden.bs.toast', () => {
            toastElement.remove();
        });
    }
}

// Add pulse animation CSS
const style = document.createElement('style');
style.textContent = `
    @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.1); }
    }
    .animate-pulse {
        animation: pulse 1s infinite;
    }
`;
document.head.appendChild(style);