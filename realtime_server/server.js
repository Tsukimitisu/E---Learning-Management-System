// Basic Socket.IO server for ELMS real-time updates
const express = require('express');
const http = require('http');
const { Server } = require('socket.io');

const app = express();

const server = http.createServer(app);
const io = new Server(server, {
  cors: {
    origin: '*',
    methods: ['GET', 'POST']
  }
});

// REST endpoint for PHP backend to broadcast events
app.use(express.json());
app.post('/api/broadcast', (req, res) => {
  const { event, data, role } = req.body;
  if (role && roles.includes(role)) {
    io.to(role).emit(event, data);
  } else {
    roles.forEach(r => io.to(r).emit(event, data));
  }
  res.json({ status: 'ok' });
});

// User roles
const roles = ['super_admin', 'school_head', 'branch_admin', 'registrar', 'teacher', 'student'];

io.on('connection', (socket) => {
  // Join role-specific room
  socket.on('join_role', (role) => {
    if (roles.includes(role)) {
      socket.join(role);
    }
  });

  // Broadcast update to all roles
  socket.on('broadcast_update', (data) => {
    roles.forEach(role => {
      io.to(role).emit('update', data);
    });
  });

  // Broadcast update to specific role
  socket.on('update_role', ({ role, data }) => {
    if (roles.includes(role)) {
      io.to(role).emit('update', data);
    }
  });
});

const PORT = 3000;
server.listen(PORT, () => {
  console.log(`Socket.IO server running on port ${PORT}`);
});
