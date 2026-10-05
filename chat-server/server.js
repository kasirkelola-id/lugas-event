require('dotenv').config();

const requiredEnvs = ['DB_HOST', 'DB_USER', 'DB_PASSWORD', 'DB_NAME', 'INTERNAL_API_SECRET', 'INTERNAL_API_URL'];
for (const env of requiredEnvs) {
  const isMissing = env === 'DB_PASSWORD' ? process.env[env] === undefined : !process.env[env];
  if (isMissing) {
    console.error(`FATAL ERROR: Environment variable ${env} is missing or empty.`);
    process.exit(1);
  }
}

const express = require('express');
const http = require('http');
const { Server } = require('socket.io');
const mysql = require('mysql2/promise');
const cors = require('cors');

const app = express();
app.use(cors());
app.use(express.json());

const server = http.createServer(app);
const io = new Server(server, {
  maxHttpBufferSize: 1e6, // 1MB payload limit
  cors: {
    origin: "*",
    methods: ["GET", "POST"]
  }
});

function boundedConnectionLimit(value) {
  const parsed = Number.parseInt(value, 10);
  if (!Number.isInteger(parsed)) return 10;
  return Math.min(Math.max(parsed, 1), 100);
}

// MySQL Connection Pool. This cap prevents an invalid environment value from
// exhausting MySQL; deployment must still budget it against max_connections.
const pool = mysql.createPool({
  host: process.env.DB_HOST,
  user: process.env.DB_USER,
  password: process.env.DB_PASSWORD,
  database: process.env.DB_NAME,
  waitForConnections: true,
  connectionLimit: boundedConnectionLimit(process.env.DB_CONNECTION_LIMIT),
  queueLimit: 0
});

// To track online users: map[socket.id] = { userId, karangTarunaId, role, permissions }
const onlineUsers = new Map();

// Rate limit tracking: map[socket.id] = { lastMessageTime, count }
const rateLimits = new Map();

const AUTH_LEASE_MS = 60000;
function privateUserRoom(tenantId, userId) {
  return `tenant_${tenantId}_user_${userId}`;
}

// Same ordinary-member eligibility as PHP AuthFilter, in one SQL statement.
async function eligibleMember(userId, tenantId) {
  const [rows] = await pool.execute(
    `SELECT om.user_id FROM organization_members om
     JOIN users u ON u.id = om.user_id
     JOIN karang_taruna kt ON kt.id = om.karang_taruna_id
     WHERE om.user_id = ? AND om.karang_taruna_id = ?
       AND om.status_aktif = 1 AND om.approval_status = 'approved'
       AND u.status_aktif = 1 AND kt.status_aktif = 1`,
    [userId, tenantId]
  );
  return rows.length > 0;
}

function scheduleAuthorizationRenewal(socket) {
  clearTimeout(socket.authorizationTimer);
  socket.authorizationTimer = setTimeout(() => renewAuthorization(socket), AUTH_LEASE_MS);
  socket.authorizationTimer.unref();
}

// One renewal in flight per socket; no authorization query per received packet.
async function renewAuthorization(socket) {
  if (socket.authorizationRenewal) return socket.authorizationRenewal;
  const info = onlineUsers.get(socket.id);
  if (!info || !socket.connected) return false;
  clearTimeout(socket.authorizationTimer);
  const rooms = [...socket.rooms].filter(room => room !== socket.id);
  // Expired authorization cannot receive while PHP is slow or unavailable.
  for (const room of rooms) socket.leave(room);
  const controller = new AbortController();
  const deadline = setTimeout(() => controller.abort(), 5000);
  socket.authorizationRenewal = (async () => {
    try {
      const response = await fetch(process.env.INTERNAL_API_URL, {
        method: 'POST', signal: controller.signal,
        headers: {
          Authorization: `Bearer ${info.token}`,
          'X-Karang-Taruna-ID': String(info.karangTarunaId),
          'X-Internal-Secret': process.env.INTERNAL_API_SECRET
        }
      });
      if (!response.ok) throw new Error('Authorization expired');
      const json = await response.json();
      const user = json.data;
      if (!json.status || !user || String(user.user_id) !== String(info.userId) ||
          String(user.karang_taruna_id) !== String(info.karangTarunaId) ||
          !user.permissions?.includes('chat.read')) throw new Error('Authorization expired');
      if (!socket.connected || onlineUsers.get(socket.id) !== info) return false;
      socket.permissions = info.permissions = user.permissions;
      socket.profilePhotoUrl = info.profilePhotoUrl = user.profile_photo_url;
      info.authTime = Date.now();
      for (const room of rooms) socket.join(room);
      scheduleAuthorizationRenewal(socket);
      return true;
    } catch (_) {
      if (socket.connected) {
        socket.emit('auth_error', { message: 'Session revalidation failed, please reconnect' });
        socket.disconnect(true);
      }
      return false;
    } finally {
      clearTimeout(deadline);
      socket.authorizationRenewal = null;
    }
  })();
  return socket.authorizationRenewal;
}

async function currentAuthorization(socket) {
  const info = onlineUsers.get(socket.id);
  if (!info || !socket.connected) return false;
  if (socket.authorizationRenewal || Date.now() - info.authTime >= AUTH_LEASE_MS) {
    return renewAuthorization(socket);
  }
  return true;
}

io.on('connection', (socket) => {
  console.log(`User connected: ${socket.id}`);

  // Handshake Timeout: Disconnect if not authenticated in 5 seconds
  const authTimeout = setTimeout(() => {
    if (!socket.userId) {
      console.log(`Socket ${socket.id} disconnected due to auth timeout`);
      socket.disconnect(true);
    }
  }, 5000);

  // Authenticate and join room
  socket.on('auth', async (data) => {
    if (socket.userId || socket.authenticating) return socket.emit('auth_error', { message: 'Socket is already authenticated' });
    if (!data || typeof data !== 'object') return socket.emit('auth_error', { message: 'Missing token or tenant_id' });
    const token = data.token;
    const karangTarunaId = data.tenant_id;

    if (!token || !karangTarunaId) {
      return socket.emit('auth_error', { message: 'Missing token or tenant_id' });
    }

    socket.authenticating = true;
    try {
      // Call Internal API
      const apiUrl = process.env.INTERNAL_API_URL;
      const secret = process.env.INTERNAL_API_SECRET;

      const response = await fetch(apiUrl, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`,
          'X-Karang-Taruna-ID': karangTarunaId.toString(),
          'X-Internal-Secret': secret
        }
      });

      if (!response.ok) {
        throw new Error('Authentication failed');
      }

      const json = await response.json();
      if (!json.status) throw new Error('Authentication failed');

      const user = json.data;
      if (!user || String(user.karang_taruna_id) !== String(karangTarunaId)) {
        throw new Error('Authentication tenant mismatch');
      }
      if (!socket.connected) return;

      socket.userId = user.user_id;
      socket.karangTarunaId = user.karang_taruna_id;
      socket.roleLevel = user.role_level;
      socket.permissions = user.permissions;
      socket.namaLengkap = user.nama_lengkap;
      socket.profilePhotoUrl = user.profile_photo_url;

      // We map socket.id to user info, so one user can have multiple sockets
      onlineUsers.set(socket.id, {
        userId: user.user_id,
        karangTarunaId: user.karang_taruna_id,
        role: user.role_level,
        permissions: user.permissions,
        profilePhotoUrl: user.profile_photo_url,
        authTime: Date.now(), // For revalidation cache
        token: token // Needed for revalidation
      });

      // Rooms derive only from the identity validated by PHP.
      socket.join(`tenant_${user.karang_taruna_id}`);
      socket.join(privateUserRoom(user.karang_taruna_id, user.user_id));
      scheduleAuthorizationRenewal(socket);

      clearTimeout(authTimeout);
      socket.emit('auth_success', { message: 'Authenticated' });
      console.log(`User ${user.user_id} authenticated via internal API for tenant ${user.karang_taruna_id}`);
    } catch (error) {
      console.error('Socket authentication failed');
      socket.emit('auth_error', { message: 'Authentication failed' });
      socket.disconnect();
    } finally {
      socket.authenticating = false;
    }
  });

  // Handle join room
  socket.on('join_room', async (data) => {
    if (!socket.userId || !socket.karangTarunaId) return socket.emit('auth_error', { message: 'Not authenticated' });

    const roomId = data && data.room_id;
    if (!roomId) return;
    if (!await currentAuthorization(socket)) return;

    try {
      // Validate room existence and tenant match
      const [rooms] = await pool.execute(
        `SELECT * FROM chat_rooms WHERE id = ? AND karang_taruna_id = ?`,
        [roomId, socket.karangTarunaId]
      );

      if (rooms.length === 0) return socket.emit('error', { message: 'Room not found' });
      const room = rooms[0];

      // Check active membership globally for this tenant
      const [activeMembers] = await pool.execute(
        `SELECT * FROM organization_members WHERE user_id = ? AND karang_taruna_id = ? AND status_aktif = 1`,
        [socket.userId, socket.karangTarunaId]
      );
      if (activeMembers.length === 0) return socket.emit('error', { message: 'You are not an active member of this tenant' });

      // Validate membership if custom room
      if (room.type === 'custom') {
        const [members] = await pool.execute(
          `SELECT * FROM chat_room_members WHERE chat_room_id = ? AND user_id = ?`,
          [roomId, socket.userId]
        );
        if (members.length === 0) return socket.emit('error', { message: 'Not a member of this room' });
      }

      // Check permissions
      if (!socket.permissions || !socket.permissions.includes('chat.read')) {
        return socket.emit('error', { message: 'Permission denied to read chat' });
      }

      // Join the room
      if (!await currentAuthorization(socket)) return;
      const roomName = `room_${roomId}`;
      socket.join(roomName);
      socket.emit('room_joined', { room_id: roomId });
      console.log(`User ${socket.userId} joined room ${roomName}`);
    } catch (error) {
      console.error('Error joining room');
    }
  });

  // Handle incoming messages
  socket.on('send_message', async (data) => {
    if (!socket.userId || !socket.karangTarunaId) return socket.emit('auth_error', { message: 'Not authenticated' });
    if (!data || typeof data !== 'object') return socket.emit('error', { message: 'Invalid message payload' });

    // Rate Limiting
    const now = Date.now();
    const rateData = rateLimits.get(socket.id) || { lastMessageTime: now, count: 0 };
    if (now - rateData.lastMessageTime < 1000) {
      rateData.count++;
      if (rateData.count > 5) {
        rateLimits.set(socket.id, rateData);
        return socket.emit('error', { message: 'RATE_LIMITED' });
      }
    } else {
      rateData.count = 1;
      rateData.lastMessageTime = now;
    }
    rateLimits.set(socket.id, rateData);

    // Share the receiving authorization lease with the send path.
    const userInfo = onlineUsers.get(socket.id);
    if (socket.authorizationRenewal || (userInfo && now - userInfo.authTime >= AUTH_LEASE_MS)) {
      if (!await renewAuthorization(socket)) return;
    }

    if (!socket.permissions || !socket.permissions.includes('chat.send')) {
      return socket.emit('error', { message: 'PERMISSION_DENIED' });
    }

    const type = data.type || 'group';
    let message = data.message || '';
    const receiverId = data.receiver_id || null;
    const roomId = data.chat_room_id || null;

    if (typeof message !== 'string' || message.trim().length === 0) {
      return socket.emit('error', { message: 'Message cannot be empty' });
    }
    if (Array.from(message).length > 2000) {
       return socket.emit('error', { message: 'Message exceeds 2000 characters limit' });
    }
    if (type !== 'group' && type !== 'private') {
      return socket.emit('error', { message: 'Invalid message type' });
    }

    try {
      let chatId;
      if (type === 'group') {
        if (!roomId) return socket.emit('error', { message: 'Room ID required for group chat' });

        // Ensure user is in the socket room
        const roomName = `room_${roomId}`;
        if (!socket.rooms.has(roomName)) {
           return socket.emit('error', { message: 'You must join the room first' });
        }

        // H. NODE GROUP SEND MEMBERSHIP VALIDATION
        const [rooms] = await pool.execute(
          `SELECT * FROM chat_rooms WHERE id = ? AND karang_taruna_id = ?`,
          [roomId, socket.karangTarunaId]
        );
        if (rooms.length === 0) return socket.emit('error', { message: 'Room not found or belongs to another tenant' });

        // Check active membership globally for this tenant
        const [activeMembers] = await pool.execute(
          `SELECT * FROM organization_members WHERE user_id = ? AND karang_taruna_id = ? AND status_aktif = 1`,
          [socket.userId, socket.karangTarunaId]
        );
        if (activeMembers.length === 0) return socket.emit('error', { message: 'You are not an active member of this tenant' });

        if (rooms[0].type === 'custom') {
          const [members] = await pool.execute(
            `SELECT * FROM chat_room_members WHERE chat_room_id = ? AND user_id = ?`,
            [roomId, socket.userId]
          );
          if (members.length === 0) return socket.emit('error', { message: 'You are not a member of this custom room' });
        }

        const [result] = await pool.execute(
          `INSERT INTO chats (karang_taruna_id, chat_room_id, type, sender_id, message, created_at) VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())`,
          [socket.karangTarunaId, roomId, type, socket.userId, message]
        );
        chatId = result.insertId;

        const [chatRows] = await pool.execute(`SELECT DATE_FORMAT(created_at, '%Y-%m-%dT%TZ') as created_at_iso FROM chats WHERE id = ?`, [chatId]);
        let canonicalTimestamp = new Date().toISOString();
        if (chatRows.length > 0 && chatRows[0].created_at_iso) {
           canonicalTimestamp = chatRows[0].created_at_iso;
        }

        const chatPayload = {
          id: chatId,
          karang_taruna_id: socket.karangTarunaId,
          chat_room_id: roomId,
          type: type,
          sender_id: socket.userId,
          message: message,
          created_at: canonicalTimestamp,
          nama_lengkap: socket.namaLengkap,
          role_level: socket.roleLevel,
          sender_photo_url: socket.profilePhotoUrl
        };

        io.to(roomName).emit('new_message', chatPayload);

        // Fire and forget notification
        _triggerChatNotification(chatId);
      } else if (type === 'private') {
        if (!receiverId) return socket.emit('error', { message: 'Receiver ID required for private chat' });

        if (socket.userId.toString() === receiverId.toString()) {
          return socket.emit('error', { message: 'Cannot send private message to yourself' });
        }

        // Check active membership for sender globally for this tenant
        if (!await eligibleMember(socket.userId, socket.karangTarunaId)) {
          return socket.emit('error', { message: 'You are not an active member of this tenant' });
        }
        if (!await eligibleMember(receiverId, socket.karangTarunaId)) {
          return socket.emit('error', { message: 'Receiver not found or not active in this tenant' });
        }

        const [result] = await pool.execute(
          `INSERT INTO chats (karang_taruna_id, type, sender_id, receiver_id, message, created_at) VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())`,
          [socket.karangTarunaId, type, socket.userId, receiverId, message]
        );
        chatId = result.insertId;

        const [chatRows] = await pool.execute(`SELECT DATE_FORMAT(created_at, '%Y-%m-%dT%TZ') as created_at_iso FROM chats WHERE id = ?`, [chatId]);
        let canonicalTimestamp = new Date().toISOString();
        if (chatRows.length > 0 && chatRows[0].created_at_iso) {
           canonicalTimestamp = chatRows[0].created_at_iso;
        }

        const chatPayload = {
          id: chatId,
          karang_taruna_id: socket.karangTarunaId,
          type: type,
          sender_id: socket.userId,
          receiver_id: receiverId,
          message: message,
          created_at: canonicalTimestamp,
          nama_lengkap: socket.namaLengkap,
          role_level: socket.roleLevel,
          sender_photo_url: socket.profilePhotoUrl
        };

        // Emit to sender's sockets (all devices) and receiver's sockets (all devices)
        // using the user rooms they joined during auth.
        io.to(privateUserRoom(socket.karangTarunaId, socket.userId)).emit('new_message', chatPayload);
        io.to(privateUserRoom(socket.karangTarunaId, receiverId)).emit('new_message', chatPayload);

        // Fire and forget notification
        _triggerChatNotification(chatId);
      }

    } catch (error) {
      // Driver error objects can contain SQL and private message parameters.
      console.error('Error saving message');
      socket.emit('error', { message: 'Failed to send message' });
    }
  });

  socket.on('disconnect', () => {
    clearTimeout(authTimeout);
    clearTimeout(socket.authorizationTimer);
    console.log(`User disconnected: ${socket.id}`);
    onlineUsers.delete(socket.id);
    rateLimits.delete(socket.id);
  });

  socket.on('join_wheel', async (data) => {
    if (!socket.userId || !socket.karangTarunaId) return socket.emit('auth_error', { message: 'Not authenticated' });

    const sessionId = data && data.session_id;
    if (!sessionId) return;
    if (!await currentAuthorization(socket)) return;

    // Since session IDs are unique globally and we validate tenant id in PHP API,
    // we can just use wheel_session_${sessionId}.
    const roomName = `wheel_session_${socket.karangTarunaId}_${sessionId}`;
    socket.join(roomName);
    socket.emit('wheel_joined', { session_id: sessionId });
    console.log(`User ${socket.userId} joined wheel session ${sessionId}`);
  });
});

app.post('/internal/wheel-event', (req, res) => {
  const secret = req.headers['x-internal-secret'];
  const validSecret = process.env.INTERNAL_API_SECRET;

  if (secret !== validSecret) {
    return res.status(403).json({ error: 'Forbidden' });
  }

  const { session_id, karang_taruna_id, event, payload } = req.body;

  if (!session_id || !karang_taruna_id || !event) {
    return res.status(400).json({ error: 'Missing parameters' });
  }

  const roomName = `wheel_session_${karang_taruna_id}_${session_id}`;
  io.to(roomName).emit(event, payload);
  console.log(`Broadcasted wheel event ${event} to ${roomName}`);

  res.json({ success: true });
});

const PORT = process.env.PORT || 3000;
if (require.main === module) {
  server.listen(PORT, () => {
    console.log(`Socket.IO Server running on port ${PORT}`);
  });
}

module.exports = { server, io, pool, boundedConnectionLimit, privateUserRoom, renewAuthorization, AUTH_LEASE_MS };

function _triggerChatNotification(chatId) {
  const apiUrl = process.env.INTERNAL_API_URL.replace('socket-auth', 'chat-notification');
  const secret = process.env.INTERNAL_API_SECRET;

  const controller = new AbortController();
  const timeoutId = setTimeout(() => controller.abort(), 3000); // 3-second timeout

  fetch(apiUrl, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-Internal-Secret': secret
    },
    body: JSON.stringify({ chat_id: chatId }),
    signal: controller.signal
  })
  .then(res => {
    clearTimeout(timeoutId);
    if (!res.ok) {
      console.error(`Chat notification failed with status: ${res.status}`);
    }
  })
  .catch(err => {
    clearTimeout(timeoutId);
    console.error('Failed to trigger chat notification');
  });
}
