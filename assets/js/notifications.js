/**
 * Notification system for ALL roles.
 * Fully realtime via short-polling (no Socket.IO dependency).
 * If Socket.IO is available it also listens for push events.
 */
(function () {
    const userId = Number(document.body.dataset.userId || window.USER_ID || 0);
    if (!userId) return;

    const API_BASE = '/elms_system/api/notifications.php';
    const POLL_INTERVAL = 5000;          // 5-second polling — near-realtime
    let dropdownOpen = false;
    let lastKnownCount = -1;             // Track to detect new arrivals
    let lastSeenId = 0;                  // Highest notification ID we've seen
    let cachedNotifications = [];        // Latest fetched list

    // ---------- DOM refs ----------
    const bell = document.getElementById('notificationBell');
    const badge = document.getElementById('notificationBadge');
    const countSpan = document.getElementById('notificationCount');
    const dropdown = document.getElementById('notificationDropdown');
    const listContainer = document.getElementById('notificationList');
    const markAllBtn = document.getElementById('markAllReadBtn');

    if (!bell || !dropdown) return;

    // ---------- Badge ----------
    function setBadge(count) {
        if (!badge || !countSpan) return;
        if (count > 0) {
            countSpan.textContent = count > 99 ? '99+' : count;
            badge.style.display = 'inline-block';
            badge.classList.add('animate-pulse');
        } else {
            badge.style.display = 'none';
            badge.classList.remove('animate-pulse');
        }
    }

    // ---------- Icon & tone maps ----------
    const ICON_MAP = {
        info: 'bi-info-circle',
        enrollment: 'bi-person-check',
        grade: 'bi-clipboard-data',
        material: 'bi-file-earmark-text',
        announcement: 'bi-megaphone',
        payment: 'bi-credit-card',
        system: 'bi-gear'
    };

    const TONE_MAP = {
        grade: 'success',
        enrollment: 'success',
        material: 'info',
        announcement: 'info',
        payment: 'info',
        system: 'danger',
        info: 'info'
    };

    function notifIcon(type) {
        const icon = ICON_MAP[type] || ICON_MAP.info;
        const cls = type && ICON_MAP[type] ? type : 'info';
        return `<div class="notif-icon ${cls}"><i class="bi ${icon}"></i></div>`;
    }

    // ---------- Render ----------
    function renderNotifications(notifications) {
        if (!listContainer) return;
        if (!notifications.length) {
            listContainer.innerHTML = '<div class="notif-empty"><i class="bi bi-bell-slash"></i>No notifications yet</div>';
            return;
        }

        listContainer.innerHTML = notifications.map(n => {
            const unread = !n.is_read;
            return `
                <div class="notif-item ${unread ? 'unread' : ''}" data-id="${n.id}" data-link="${n.link || ''}">
                    ${notifIcon(n.type)}
                    <div class="notif-content">
                        <div class="notif-title">${escHtml(n.title)}</div>
                        <div class="notif-msg">${escHtml(n.message)}</div>
                        <div class="notif-time">${n.time_ago || ''}</div>
                    </div>
                    ${unread ? '<div class="notif-unread-dot"></div>' : ''}
                </div>`;
        }).join('');

        listContainer.querySelectorAll('.notif-item').forEach(item => {
            item.addEventListener('click', function () {
                const id = this.dataset.id;
                const link = this.dataset.link;
                markRead(id, () => {
                    this.classList.remove('unread');
                    const dot = this.querySelector('.notif-unread-dot');
                    if (dot) dot.remove();
                    pollNow();
                    if (link) window.location.href = link;
                });
            });
        });
    }

    // ---------- API calls ----------
    function fetchNotifications(cb) {
        fetch(API_BASE + '?action=fetch&limit=20')
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    cachedNotifications = d.notifications;
                    renderNotifications(d.notifications);
                    setBadge(d.unread_count);
                    if (cb) cb(d);
                }
            })
            .catch(() => {});
    }

    /**
     * Core polling function — fetches latest notifications,
     * detects new ones, and shows toasts automatically.
     */
    function pollNow() {
        fetch(API_BASE + '?action=fetch&limit=20')
            .then(r => r.json())
            .then(d => {
                if (!d.success) return;

                const newCount = d.unread_count;
                const notifications = d.notifications || [];

                // Detect brand-new notifications by comparing IDs
                if (lastSeenId > 0) {
                    const newOnes = notifications.filter(n => Number(n.id) > lastSeenId && !n.is_read);
                    newOnes.forEach(n => {
                        const tone = TONE_MAP[n.type] || 'info';
                        showToast(n.message || n.title, tone);
                    });
                }

                // Update last-seen ID to the highest in the list
                if (notifications.length > 0) {
                    const maxId = Math.max(...notifications.map(n => Number(n.id)));
                    if (maxId > lastSeenId) lastSeenId = maxId;
                }

                // Update badge
                setBadge(newCount);
                lastKnownCount = newCount;

                // Update cached list
                cachedNotifications = notifications;

                // If dropdown is open, re-render
                if (dropdownOpen) {
                    renderNotifications(notifications);
                }
            })
            .catch(() => {});
    }

    function markRead(id, cb) {
        const fd = new FormData();
        fd.append('action', 'mark_read');
        fd.append('id', id);
        fetch(API_BASE, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => { if (d.success && cb) cb(); })
            .catch(() => {});
    }

    function markAllRead() {
        const fd = new FormData();
        fd.append('action', 'mark_all_read');
        fetch(API_BASE, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    setBadge(0);
                    lastKnownCount = 0;
                    listContainer.querySelectorAll('.notif-item.unread').forEach(item => {
                        item.classList.remove('unread');
                        const dot = item.querySelector('.notif-unread-dot');
                        if (dot) dot.remove();
                    });
                }
            })
            .catch(() => {});
    }

    // ---------- Toggle dropdown ----------
    function openDropdown() {
        dropdown.classList.add('show');
        dropdownOpen = true;
        fetchNotifications();
    }

    function closeDropdown() {
        dropdown.classList.remove('show');
        dropdownOpen = false;
    }

    bell.addEventListener('click', function (e) {
        e.stopPropagation();
        dropdownOpen ? closeDropdown() : openDropdown();
    });

    document.addEventListener('click', function (e) {
        if (dropdownOpen && !dropdown.contains(e.target)) closeDropdown();
    });

    dropdown.addEventListener('click', function (e) { e.stopPropagation(); });

    if (markAllBtn) markAllBtn.addEventListener('click', markAllRead);

    // ---------- Toast system ----------
    function showToast(message, type) {
        let tc = document.getElementById('toastContainer');
        if (!tc) {
            tc = document.createElement('div');
            tc.id = 'toastContainer';
            tc.style.cssText = 'position:fixed;top:80px;right:20px;z-index:9999;';
            document.body.appendChild(tc);
        }

        const id = 'toast_' + Date.now() + '_' + Math.floor(Math.random() * 10000);
        const bg = type === 'success' ? 'bg-success' : type === 'danger' ? 'bg-danger' : 'bg-info';
        tc.insertAdjacentHTML('beforeend', `
            <div id="${id}" class="toast align-items-center text-white ${bg} border-0" role="alert">
                <div class="d-flex">
                    <div class="toast-body"><i class="bi bi-bell"></i> ${message}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        `);

        const el = document.getElementById(id);
        const t = new bootstrap.Toast(el, { delay: 5000 });
        t.show();
        el.addEventListener('hidden.bs.toast', () => el.remove());
    }

    // ---------- Socket.IO push (bonus — enhances speed if server is available) ----------
    window.addEventListener('elms-realtime-update', function (e) {
        // When a push arrives, just poll immediately to sync from DB
        // This avoids duplicating toast logic — pollNow() handles it
        pollNow();
    });

    // ---------- Helpers ----------
    function escHtml(str) {
        if (!str) return '';
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    // ---------- Initial load ----------
    // First fetch: seed lastSeenId without showing toasts
    fetch(API_BASE + '?action=fetch&limit=20')
        .then(r => r.json())
        .then(d => {
            if (!d.success) return;
            const notifications = d.notifications || [];
            cachedNotifications = notifications;

            if (notifications.length > 0) {
                lastSeenId = Math.max(...notifications.map(n => Number(n.id)));
            }

            setBadge(d.unread_count);
            lastKnownCount = d.unread_count;
        })
        .catch(() => {});

    // Poll every 5 seconds — true realtime without any server dependency
    setInterval(pollNow, POLL_INTERVAL);
})();

// Pulse animation
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
