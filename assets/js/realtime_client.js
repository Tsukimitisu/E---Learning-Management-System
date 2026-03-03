// ELMS Real-Time Client
(function () {
  if (!window.ELMS_REALTIME_ENABLED) {
    return;
  }

  const ROLE_ALIASES = {
    '1': 'super_admin',
    '2': 'school_admin',
    '3': 'branch_admin',
    '4': 'registrar',
    '5': 'teacher',
    '6': 'student',
    superadmin: 'super_admin',
    super_admin: 'super_admin',
    'super admin': 'super_admin',
    schooladmin: 'school_admin',
    school_admin: 'school_admin',
    'school admin': 'school_admin',
    school_head: 'school_admin',
    'school head': 'school_admin',
    branchadmin: 'branch_admin',
    branch_admin: 'branch_admin',
    'branch admin': 'branch_admin',
    registrar: 'registrar',
    teacher: 'teacher',
    student: 'student'
  };

  const INTERNAL_EVENTS = new Set([
    'connect',
    'disconnect',
    'joined_role',
    'joined_user',
    'connect_error',
    'error',
    'reconnect',
    'reconnect_attempt',
    'reconnect_error',
    'reconnect_failed',
    'ping',
    'pong'
  ]);

  function normalizeRole(rawRole) {
    if (rawRole === undefined || rawRole === null) return 'guest';
    const normalized = String(rawRole).trim().toLowerCase().replace(/[\s-]+/g, '_');
    return ROLE_ALIASES[normalized] || normalized || 'guest';
  }

  function getUserRole() {
    const bodyRole = document.body && document.body.dataset ? document.body.dataset.userRole : null;
    const bodyRoleId = document.body && document.body.dataset ? document.body.dataset.userRoleId : null;
    return normalizeRole(bodyRole || window.USER_ROLE || bodyRoleId || window.USER_ROLE_ID || 'guest');
  }

  function getUserId() {
    const bodyUserId = document.body && document.body.dataset ? document.body.dataset.userId : null;
    const rawUserId = bodyUserId || window.USER_ID || null;
    const userId = Number(rawUserId);
    return Number.isInteger(userId) && userId > 0 ? userId : null;
  }

  function getRealtimeServerUrl() {
    if (window.ELMS_REALTIME_SERVER_URL) {
      return window.ELMS_REALTIME_SERVER_URL;
    }
    const protocol = window.location.protocol === 'https:' ? 'https' : 'http';
    const host = window.location.hostname || 'localhost';
    const port = window.ELMS_REALTIME_SERVER_PORT || 3000;
    return `${protocol}://${host}:${port}`;
  }

  function getRealtimeSocketPath() {
    const rawPath = window.ELMS_REALTIME_SOCKET_PATH || '/socket.io';
    if (typeof rawPath !== 'string') {
      return '/socket.io';
    }
    return rawPath.startsWith('/') ? rawPath : `/${rawPath}`;
  }

  function dispatchRealtimeEvent(eventName, data) {
    try {
      window.dispatchEvent(new CustomEvent('elms-realtime-update', {
        detail: {
          event: eventName,
          data: data,
          received_at: Date.now()
        }
      }));
    } catch (e) {
      console.error('Realtime update event error:', e);
    }
  }

  function connectSocket() {
    if (!window.io) return setTimeout(connectSocket, 200);
    try {
      const socket = io(getRealtimeServerUrl(), {
        path: getRealtimeSocketPath(),
        transports: ['websocket', 'polling'],
        reconnection: true,
        reconnectionDelay: 2000,
        reconnectionAttempts: Infinity,
        reconnectionDelayMax: 30000,
        randomizationFactor: 0.5,
        timeout: 10000
      });

      window.elmsSocket = socket;

      // Shared helper for pages that need to publish updates.
      window.elmsEmitRealtime = function (targetRole, data) {
        const payload = data || {};
        const role = normalizeRole(targetRole);

        if (role && role !== 'all') {
          socket.emit('update_role', { role: role, data: payload });
          return;
        }

        socket.emit('broadcast_update', payload);
      };

      socket.on('connect', function () {
        socket.emit('join_role', getUserRole());

        const userId = getUserId();
        if (userId) {
          socket.emit('join_user', userId);
        }

        dispatchRealtimeEvent('realtime_connected', {
          socket_id: socket.id || null,
          connected_at: Date.now()
        });
      });

      socket.onAny(function (eventName, data) {
        if (INTERNAL_EVENTS.has(eventName)) return;
        dispatchRealtimeEvent(eventName, data);
      });

      socket.on('connect_error', function (err) {
        // Silent — notifications work via polling regardless
      });

      socket.on('error', function (err) {
        // Silent — notifications work via polling regardless
      });

      socket.on('disconnect', function (reason) {
        dispatchRealtimeEvent('realtime_disconnected', {
          reason: reason || 'unknown',
          disconnected_at: Date.now()
        });
      });

      // Handle maintenance mode force-logout
      socket.on('maintenance_mode', function (data) {
        if (data && data.enabled) {
          // Show notification and force logout non-super-admin users
          const roleId = window.USER_ROLE_ID || 0;
          if (roleId != 1) { // Not super admin
            // Show prominent alert
            if (typeof Swal !== 'undefined') {
              Swal.fire({
                icon: 'warning',
                title: 'System Maintenance',
                text: data.message || 'The system is entering maintenance mode. You will be logged out.',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                timer: 5000,
                timerProgressBar: true
              }).then(() => {
                window.location.href = window.ELMS_BASE_URL ? window.ELMS_BASE_URL + 'logout.php' : 'logout.php';
              });
            } else {
              alert('System is entering maintenance mode. You will be logged out.');
              window.location.href = window.ELMS_BASE_URL ? window.ELMS_BASE_URL + 'logout.php' : 'logout.php';
            }
          } else {
            // Super admin just gets notified
            dispatchRealtimeEvent('maintenance_mode', data);
          }
        }
      });

      // Handle data updates - dispatch events for page refresh
      socket.on('data_updated', function (data) {
        dispatchRealtimeEvent('data_updated', data);
      });
    } catch (e) {
      console.error('Socket client initialization error:', e);
    }
  }

  connectSocket();
})();
