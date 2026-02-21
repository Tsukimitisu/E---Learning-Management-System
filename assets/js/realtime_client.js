// ELMS Real-Time Client
(function() {
  function getUserRole() {
    // Try to get from body data attribute or fallback
    return document.body.dataset.userRole || window.USER_ROLE || 'guest';
  }

  function connectSocket() {
    if (!window.io) return setTimeout(connectSocket, 200);
    const socket = io('http://localhost:3000');
    window.elmsSocket = socket;
    const role = getUserRole();
    socket.emit('join_role', role);

    // Listen for update events
    socket.on('update', function(data) {
      // Custom event for all modules to listen
      window.dispatchEvent(new CustomEvent('elms-realtime-update', { detail: data }));
    });
  }

  connectSocket();
})();
