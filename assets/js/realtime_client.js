// ELMS Real-Time Client (currently disabled unless explicitly enabled)
(function() {
  // To enable realtime again, set window.ELMS_REALTIME_ENABLED = true
  // BEFORE this script is loaded and make sure your Socket.IO server
  // is running at the configured URL.
  if (!window.ELMS_REALTIME_ENABLED) {
    return; // No-op: do not attempt any socket connection
  }

  function getUserRole() {
    // Try to get from body data attribute or fallback
    return document.body.dataset.userRole || window.USER_ROLE || 'guest';
  }

  function connectSocket() {
    if (!window.io) return setTimeout(connectSocket, 200);
    try {
      const socket = io('http://localhost:3000');
      window.elmsSocket = socket;
      const role = getUserRole();
      socket.emit('join_role', role);

      // Listen for update events
      socket.on('update', function(data) {
        try {
          window.dispatchEvent(new CustomEvent('elms-realtime-update', { detail: data }));
        } catch (e) {
          console.error('Realtime update event error:', e);
        }
      });

      // Handle socket errors
      socket.on('connect_error', function(err) {
        console.error('Socket connection error:', err);
      });
      socket.on('error', function(err) {
        console.error('Socket error:', err);
      });
    } catch (e) {
      console.error('Socket client initialization error:', e);
    }
  }

  connectSocket();
})();
