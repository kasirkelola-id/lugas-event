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

// To track online users: map[userId] = socketId
const onlineUsers = new Map();

io.on('connection', (socket) => {
  console.log(`User connected: ${socket.id}`);

  // Authenticate and join room
  socket.on('auth', async (data) => {
    const userId = data.user_id;
    const karangTarunaId = data.karang_taruna_id;

    if (userId && karangTarunaId) {
      socket.userId = userId;
      socket.karangTarunaId = karangTarunaId;
      onlineUsers.set(userId, socket.id);
      
      // Join a socket.io room specific to this Karang Taruna
      const roomName = `kt_${karangTarunaId}`;
      socket.join(roomName);
      
      console.log(`User ${userId} authenticated and joined room ${roomName}`);
    }
  });

  // Handle incoming messages
  socket.on('send_message', async (data) => {
    if (!socket.userId || !socket.karangTarunaId) return;

    const type = data.type || 'group';
    const message = data.message || '';
    const receiverId = data.receiver_id || null;

    if (!message.trim()) return;

    try {
      // 1. Insert to MySQL
      const [result] = await pool.execute(
        `INSERT INTO chats (karang_taruna_id, type, sender_id, receiver_id, message, created_at) VALUES (?, ?, ?, ?, ?, NOW())`,
        [socket.karangTarunaId, type, socket.userId, type === 'private' ? receiverId : null, message]
      );
      
      const chatId = result.insertId;

      // 2. Fetch sender details to enrich payload
      const [users] = await pool.execute(
        `SELECT nama_lengkap, role_level FROM users WHERE id = ?`,
        [socket.userId]
      );
      
      const sender = users[0] || { nama_lengkap: 'Unknown', role_level: 'anggota' };

      const chatPayload = {
        id: chatId,
        karang_taruna_id: socket.karangTarunaId,
        type: type,
        sender_id: socket.userId,
        receiver_id: type === 'private' ? receiverId : null,
        message: message,
        created_at: new Date().toISOString(),
        nama_lengkap: sender.nama_lengkap,
        role_level: sender.role_level
      };

      // 3. Broadcast message
      if (type === 'group') {
        const roomName = `kt_${socket.karangTarunaId}`;
        io.to(roomName).emit('new_message', chatPayload);
      } else if (type === 'private') {
        // Emit to sender
        socket.emit('new_message', chatPayload);
        // Emit to receiver if online
        const receiverSocketId = onlineUsers.get(receiverId);
        if (receiverSocketId) {
          io.to(receiverSocketId).emit('new_message', chatPayload);
        }
      }

    } catch (error) {
      console.error('Error saving message:', error);
    }
  });

  socket.on('disconnect', () => {
    console.log(`User disconnected: ${socket.id}`);
    if (socket.userId) {
      onlineUsers.delete(socket.userId);
    }
  });
});

const PORT = process.env.PORT || 3000;
server.listen(PORT, () => {
  console.log(`Node.js Socket.io Server running on port ${PORT}`);
});
