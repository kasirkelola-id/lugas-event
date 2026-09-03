require('dotenv').config();
const express = require('express');
const http = require('http');
const { Server } = require('socket.io');
const mysql = require('mysql2/promise');
const cors = require('cors');

const app = express();
app.use(cors());

const server = http.createServer(app);
const io = new Server(server, {
  maxHttpBufferSize: 1e6, // 1MB payload limit
  cors: {
    origin: "*",
    methods: ["GET", "POST"]
  }
});

// MySQL Connection Pool
const pool = mysql.createPool({
  host: process.env.DB_HOST || 'localhost',
  user: process.env.DB_USER || 'root',
  password: process.env.DB_PASSWORD || '',
  database: process.env.DB_NAME || 'lugasku',
  waitForConnections: true,
  connectionLimit: 10,
  queueLimit: 0
});

// To track online users: map[socket.id] = { userId, karangTarunaId, role, permissions }
const onlineUsers = new Map();

// Rate limit tracking: map[socket.id] = { lastMessageTime, count }
const rateLimits = new Map();

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
    clearTimeout(authTimeout);
    const token = data.token;
    const karangTarunaId = data.tenant_id;

    if (!token || !karangTarunaId) {
      return socket.emit('auth_error', { message: 'Missing token or tenant_id' });
    }

    try {
      // Call Internal API
      const apiUrl = process.env.INTERNAL_API_URL || 'http://localhost:8080/api/internal/socket-auth';
      const secret = process.env.INTERNAL_API_SECRET || 'default_internal_secret_for_dev';

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
      
      socket.emit('auth_success', { message: 'Authenticated' });
      console.log(`User ${user.user_id} authenticated via internal API for tenant ${user.karang_taruna_id}`);
    } catch (error) {
      console.error('Socket Auth Error:', error.message);
      socket.emit('auth_error', { message: 'Authentication failed' });
      socket.disconnect();
    }
  });

  // Handle join room
  socket.on('join_room', async (data) => {
    if (!socket.userId || !socket.karangTarunaId) return socket.emit('auth_error', { message: 'Not authenticated' });
    
    const roomId = data.room_id;
    if (!roomId) return;

    try {
      // Validate room existence and tenant match
      const [rooms] = await pool.execute(
        `SELECT * FROM chat_rooms WHERE id = ? AND karang_taruna_id = ?`,
        [roomId, socket.karangTarunaId]
      );
      
      if (rooms.length === 0) return socket.emit('error', { message: 'Room not found' });
      const room = rooms[0];

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
      const roomName = `room_${roomId}`;
      socket.join(roomName);
      socket.emit('room_joined', { room_id: roomId });
      console.log(`User ${socket.userId} joined room ${roomName}`);
    } catch (error) {
      console.error('Error joining room:', error);
    }
  });

  // Handle incoming messages
  socket.on('send_message', async (data) => {
    if (!socket.userId || !socket.karangTarunaId) return socket.emit('auth_error', { message: 'Not authenticated' });

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

    // Membership Revalidation (cache expires in 60s)
    const userInfo = onlineUsers.get(socket.id);
    if (userInfo && (now - userInfo.authTime > 60000)) {
      try {
        const apiUrl = process.env.INTERNAL_API_URL || 'http://localhost:8080/api/internal/socket-auth';
        const secret = process.env.INTERNAL_API_SECRET || 'default_internal_secret_for_dev';
        const response = await fetch(apiUrl, {
          method: 'POST',
          headers: {
            'Authorization': `Bearer ${userInfo.token}`,
            'X-Karang-Taruna-ID': socket.karangTarunaId.toString(),
            'X-Internal-Secret': secret
          }
        });
        if (!response.ok) throw new Error('Revalidation failed');
        const json = await response.json();
        if (!json.status) throw new Error('Revalidation failed');
        
        socket.permissions = json.data.permissions;
        socket.profilePhotoUrl = json.data.profile_photo_url;
        userInfo.permissions = json.data.permissions;
        userInfo.profilePhotoUrl = json.data.profile_photo_url;
        userInfo.authTime = now;
        onlineUsers.set(socket.id, userInfo);
      } catch (err) {
        console.error('Revalidation error:', err.message);
        socket.emit('auth_error', { message: 'Session revalidation failed, please reconnect' });
        return socket.disconnect();
      }
    }

    if (!socket.permissions || !socket.permissions.includes('chat.send')) {
      return socket.emit('error', { message: 'PERMISSION_DENIED' });
    }

    const type = data.type || 'group';
    let message = data.message || '';
    const receiverId = data.receiver_id || null;
    const roomId = data.chat_room_id || null;

    if (typeof message !== 'string' || message.trim().length === 0) return;
    if (message.length > 2000) {
       message = message.substring(0, 2000); // Enforce max length
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

        const [result] = await pool.execute(
          `INSERT INTO chats (karang_taruna_id, chat_room_id, type, sender_id, message, created_at) VALUES (?, ?, ?, ?, ?, NOW())`,
          [socket.karangTarunaId, roomId, type, socket.userId, message]
        );
        chatId = result.insertId;

        const chatPayload = {
          id: chatId,
          karang_taruna_id: socket.karangTarunaId,
          chat_room_id: roomId,
          type: type,
          sender_id: socket.userId,
          message: message,
          created_at: new Date().toISOString(),
          nama_lengkap: socket.namaLengkap,
          role_level: socket.roleLevel,
          sender_photo_url: socket.profilePhotoUrl
        };

        io.to(roomName).emit('new_message', chatPayload);
        
        // Fire and forget notification
        _triggerChatNotification(chatId);
      } else if (type === 'private') {
        if (!receiverId) return socket.emit('error', { message: 'Receiver ID required for private chat' });

        const [result] = await pool.execute(
          `INSERT INTO chats (karang_taruna_id, type, sender_id, receiver_id, message, created_at) VALUES (?, ?, ?, ?, ?, NOW())`,
          [socket.karangTarunaId, type, socket.userId, receiverId, message]
        );
        chatId = result.insertId;

        const chatPayload = {
          id: chatId,
          karang_taruna_id: socket.karangTarunaId,
          type: type,
          sender_id: socket.userId,
          receiver_id: receiverId,
          message: message,
          created_at: new Date().toISOString(),
          nama_lengkap: socket.namaLengkap,
          role_level: socket.roleLevel,
          sender_photo_url: socket.profilePhotoUrl
        };

        socket.emit('new_message', chatPayload);

        // Emit to receiver's sockets
        for (const [sId, sInfo] of onlineUsers.entries()) {
          if (sInfo.userId === receiverId && sInfo.karangTarunaId === socket.karangTarunaId) {
            io.to(sId).emit('new_message', chatPayload);
          }
        }
        
        // Fire and forget notification
        _triggerChatNotification(chatId);
      }

    } catch (error) {
      console.error('Error saving message:', error);
      socket.emit('error', { message: 'Failed to send message' });
    }
  });

  socket.on('disconnect', () => {
    console.log(`User disconnected: ${socket.id}`);
    onlineUsers.delete(socket.id);
    rateLimits.delete(socket.id);
  });
});

const PORT = process.env.PORT || 3000;
server.listen(PORT, () => {
  console.log(`Node.js Socket.io Server running on port ${PORT}`);
});

function _triggerChatNotification(chatId) {
  const apiUrl = (process.env.INTERNAL_API_URL || 'http://localhost:8080/api/internal/socket-auth').replace('socket-auth', 'chat-notification');
  const secret = process.env.INTERNAL_API_SECRET || 'default_internal_secret_for_dev';
  
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
    console.error('Failed to trigger chat notification:', err.message);
  });
}
