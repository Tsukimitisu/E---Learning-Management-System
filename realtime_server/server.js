const express = require('express');
const http = require('http');
const { Server } = require('socket.io');

const app = express();
app.use(express.json());
app.set('trust proxy', process.env.TRUST_PROXY === '1');

const PORT = Number(process.env.PORT) || 3000;
const SOCKET_PATH = process.env.SOCKET_PATH || '/socket.io';
const PING_TIMEOUT = Number(process.env.PING_TIMEOUT_MS) || 30000;
const PING_INTERVAL = Number(process.env.PING_INTERVAL_MS) || 25000;

function parseAllowedOrigins() {
  const raw = process.env.CORS_ORIGIN || process.env.ALLOWED_ORIGINS || '*';
  const list = String(raw)
    .split(',')
    .map((entry) => entry.trim())
    .filter(Boolean);

  if (list.length === 0 || list.includes('*')) {
    return true;
  }

  return list;
}

const ALLOWED_ORIGINS = parseAllowedOrigins();

const ROLE_ROOMS = [
  'super_admin',
  'school_admin',
  'branch_admin',
  'registrar',
  'teacher',
  'student'
];
const USER_ROOM_PREFIX = 'user:';

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

function normalizeRole(rawRole) {
  if (rawRole === undefined || rawRole === null) return null;
  const normalized = String(rawRole).trim().toLowerCase().replace(/[\s-]+/g, '_');
  if (!normalized) return null;
  const mapped = ROLE_ALIASES[normalized] || normalized;
  return ROLE_ROOMS.includes(mapped) ? mapped : null;
}

function normalizeUserId(rawUserId) {
  if (rawUserId === undefined || rawUserId === null || rawUserId === '') return null;
  const userId = Number(rawUserId);
  if (!Number.isInteger(userId) || userId <= 0) return null;
  return userId;
}

function normalizeUserIds(rawUserIds) {
  if (rawUserIds === undefined || rawUserIds === null) return [];

  let list = rawUserIds;
  if (!Array.isArray(list)) {
    list = [list];
  }

  const unique = new Set();
  list.forEach((candidate) => {
    const userId = normalizeUserId(candidate);
    if (userId) unique.add(userId);
  });

  return Array.from(unique);
}

function getUserRoom(userId) {
  return `${USER_ROOM_PREFIX}${userId}`;
}

function emitToRole(role, eventName, data) {
  const safeEvent = typeof eventName === 'string' && eventName.trim() ? eventName.trim() : 'update';
  io.to(role).emit(safeEvent, data);

  // Keep backward compatibility with pages listening only to "update".
  if (safeEvent !== 'update') {
    io.to(role).emit('update', {
      event: safeEvent,
      data
    });
  }
}

function emitToUser(userId, eventName, data) {
  const safeEvent = typeof eventName === 'string' && eventName.trim() ? eventName.trim() : 'update';
  const room = getUserRoom(userId);
  io.to(room).emit(safeEvent, data);

  // Keep backward compatibility with pages listening only to "update".
  if (safeEvent !== 'update') {
    io.to(room).emit('update', {
      event: safeEvent,
      data
    });
  }
}

function emitToTargets(eventName, data, rawRole, rawUserIds) {
  const userIds = normalizeUserIds(rawUserIds);
  if (userIds.length > 0) {
    userIds.forEach((userId) => emitToUser(userId, eventName, data));
    return {
      scope: 'users',
      role: null,
      users: userIds
    };
  }

  const role = normalizeRole(rawRole);
  if (role) {
    emitToRole(role, eventName, data);
    return {
      scope: 'role',
      role,
      users: []
    };
  }

  ROLE_ROOMS.forEach((targetRole) => emitToRole(targetRole, eventName, data));
  return {
    scope: 'all',
    role: null,
    users: []
  };
}

const server = http.createServer(app);
const io = new Server(server, {
  path: SOCKET_PATH,
  cors: {
    origin: ALLOWED_ORIGINS,
    methods: ['GET', 'POST']
  },
  pingTimeout: PING_TIMEOUT,
  pingInterval: PING_INTERVAL
});

app.get('/healthz', (req, res) => {
  res.json({
    status: 'ok',
    uptime: process.uptime(),
    socket_path: SOCKET_PATH
  });
});

app.post('/api/broadcast', (req, res) => {
  const payload = req.body || {};
  const eventName = payload.event;
  const data = payload.data ?? {};
  const rawUserIds = payload.user_ids ?? payload.users ?? payload.user_id ?? null;
  const target = emitToTargets(eventName, data, payload.role, rawUserIds);

  res.json({
    status: 'ok',
    scope: target.scope,
    role: target.role || null,
    users: target.users,
    event: typeof eventName === 'string' && eventName.trim() ? eventName.trim() : 'update'
  });
});

io.on('connection', (socket) => {
  socket.on('join_role', (rawRole) => {
    const role = normalizeRole(rawRole);
    if (!role) return;
    socket.join(role);
    socket.emit('joined_role', { role });
  });

  socket.on('join_user', (rawUserId) => {
    const userId = normalizeUserId(rawUserId);
    if (!userId) return;
    const room = getUserRoom(userId);
    socket.join(room);
    socket.emit('joined_user', { user_id: userId });
  });

  socket.on('broadcast_update', (data) => {
    emitToTargets('update', data ?? {}, null, null);
  });

  socket.on('update_role', (payload) => {
    if (!payload || typeof payload !== 'object') return;
    emitToTargets('update', payload.data ?? {}, payload.role, null);
  });
});

server.listen(PORT, () => {
  const originInfo = ALLOWED_ORIGINS === true ? '*' : ALLOWED_ORIGINS.join(', ');
  console.log(`Socket.IO server running on port ${PORT}`);
  console.log(`Socket path: ${SOCKET_PATH}`);
  console.log(`Allowed origins: ${originInfo}`);
});
