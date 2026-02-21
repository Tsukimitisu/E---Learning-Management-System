// Socket.IO client loader for ELMS
(function() {
  if (window.io) return; // Already loaded
  var script = document.createElement('script');
  script.src = 'https://cdn.socket.io/4.7.5/socket.io.min.js';
  script.onload = function() {
    window.ioLoaded = true;
  };
  document.head.appendChild(script);
})();
