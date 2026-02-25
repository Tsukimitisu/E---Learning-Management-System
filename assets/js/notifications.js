/**
 * Push-only student notifications (no polling).
 * Requires realtime_client.js + running Socket.IO server.
 */
(function() {
    const userRole = document.body.dataset.userRole || null;
    const userRoleId = document.body.dataset.userRoleId || null;
    const rawUserId = document.body.dataset.userId || window.USER_ID || null;
    const userId = Number(rawUserId);
    const isStudent = userRole === 'student' || userRoleId === '6' || window.location.pathname.includes('/student/');

    if (!isStudent) {
        return;
    }

    let unreadCount = 0;

    function isForCurrentUser(payload) {
        if (!payload || typeof payload !== 'object') return false;
        if (!Number.isInteger(userId) || userId <= 0) return true;

        if (payload.student_id !== undefined && payload.student_id !== null) {
            return Number(payload.student_id) === userId;
        }

        if (Array.isArray(payload.user_ids) && payload.user_ids.length > 0) {
            return payload.user_ids.map(Number).includes(userId);
        }

        return true;
    }

    function resolveNotificationPayload(eventName, eventData) {
        if (!eventData || typeof eventData !== 'object') return null;

        // Skip compatibility wrappers to avoid duplicate toasts.
        if (eventName === 'update' && eventData.event && eventData.data) {
            return null;
        }

        const payload = eventData.data && typeof eventData.data === 'object' ? eventData.data : eventData;
        if (!payload || typeof payload !== 'object') return null;

        if (!isForCurrentUser(payload)) return null;

        if (payload.type === 'grade_updated') {
            return {
                increment: 1,
                message: payload.message || 'A new grade has been posted.',
                tone: 'success'
            };
        }

        if (payload.type === 'material_uploaded') {
            return {
                increment: 1,
                message: payload.message || 'A new learning material was uploaded.',
                tone: 'info'
            };
        }

        if (payload.type === 'material_deleted') {
            return {
                increment: 0,
                message: payload.message || 'A learning material was removed.',
                tone: 'danger'
            };
        }

        return null;
    }

    function showNotificationBadge(count) {
        const badge = document.getElementById('notificationBadge');
        const countSpan = document.getElementById('notificationCount');

        if (badge && countSpan) {
            countSpan.textContent = count;
            badge.style.display = 'inline-block';
            badge.classList.add('animate-pulse');
        }
    }

    function hideNotificationBadge() {
        const badge = document.getElementById('notificationBadge');
        if (badge) {
            badge.style.display = 'none';
            badge.classList.remove('animate-pulse');
        }
    }

    function showToast(message, type) {
        let toastContainer = document.getElementById('toastContainer');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'toastContainer';
            toastContainer.style.cssText = 'position: fixed; top: 80px; right: 20px; z-index: 9999;';
            document.body.appendChild(toastContainer);
        }

        const toastId = 'toast_' + Date.now() + '_' + Math.floor(Math.random() * 10000);
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

        toastElement.addEventListener('hidden.bs.toast', function() {
            toastElement.remove();
        });
    }

    window.addEventListener('elms-realtime-update', function(e) {
        const detail = e.detail || {};
        const notification = resolveNotificationPayload(detail.event, detail.data);

        if (!notification) {
            return;
        }

        if (notification.increment > 0) {
            unreadCount += notification.increment;
            showNotificationBadge(unreadCount);
        }

        if (notification.message) {
            showToast(notification.message, notification.tone || 'info');
        }
    });

    const notificationWrapper = document.querySelector('.notification-wrapper');
    if (notificationWrapper) {
        notificationWrapper.addEventListener('click', function() {
            unreadCount = 0;
            hideNotificationBadge();
        });
    }
})();

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
