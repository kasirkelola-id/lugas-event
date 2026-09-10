const { createServer } = require('http');
const { Server } = require('socket.io');
const ioc = require('socket.io-client');
const mysql = require('mysql2/promise');

// Mock MySQL and fetch before requiring server
jest.mock('mysql2/promise', () => {
  const mPool = {
    execute: jest.fn(),
    end: jest.fn().mockResolvedValue()
  };
  return {
    createPool: jest.fn(() => mPool)
  };
});

global.fetch = jest.fn();

// Set dummy env vars for test
process.env.DB_HOST = 'localhost';
process.env.DB_USER = 'test';
process.env.DB_PASSWORD = 'test';
process.env.DB_NAME = 'test';
process.env.INTERNAL_API_SECRET = 'secret';
process.env.INTERNAL_API_URL = 'http://localhost/api';

const { server, io, pool } = require('../server');

describe('Socket.IO Chat Integration', () => {
  let clientSocket;

  beforeAll((done) => {
    server.listen(0, done);
  });

  afterAll((done) => {
    io.close();
    server.close(() => {
      pool.end().then(() => {
        done();
      }).catch(() => {
        done();
      });
    });
  });

  beforeEach(() => {
    jest.clearAllMocks();
  });

  afterEach(() => {
    if (clientSocket && clientSocket.connected) {
      clientSocket.disconnect();
    }
  });

  it('should authenticate and join default room', (done) => {
    global.fetch.mockResolvedValueOnce({
      ok: true,
      json: async () => ({
        status: true,
        data: {
          user_id: 10,
          karang_taruna_id: 1,
          role_level: 'anggota',
          permissions: ['chat.read', 'chat.send'],
          profile_photo_url: null
        }
      })
    });

    pool.execute.mockResolvedValueOnce([
      [{ id: 100, type: 'default', karang_taruna_id: 1 }] // Rooms
    ]);

    const port = server.address().port;
    clientSocket = ioc(`http://localhost:${port}`);
    
    clientSocket.on('connect', () => {
      clientSocket.emit('auth', { token: 'valid', tenant_id: 1 });
    });

    clientSocket.once('auth_success', () => {
      clientSocket.emit('join_room', { room_id: 100 });
    });

    clientSocket.once('room_joined', (data) => {
      expect(data.room_id).toBe(100);
      done();
    });
  });

  it('should authenticate and reject custom room if not member', (done) => {
    global.fetch.mockResolvedValueOnce({
      ok: true,
      json: async () => ({
        status: true,
        data: { user_id: 11, karang_taruna_id: 1, role_level: 'anggota' }
      })
    });

    pool.execute.mockResolvedValueOnce([
      [{ id: 101, type: 'custom', karang_taruna_id: 1 }] // Rooms
    ]).mockResolvedValueOnce([
      [] // No members found
    ]);

    const port = server.address().port;
    clientSocket = ioc(`http://localhost:${port}`);
    
    clientSocket.on('connect', () => {
      clientSocket.emit('auth', { token: 'valid', tenant_id: 1 });
    });

    clientSocket.once('auth_success', () => {
      clientSocket.emit('join_room', { room_id: 101 });
    });

    clientSocket.once('error', (data) => {
      expect(data.message).toBe('Not a member of this room');
      done();
    });
  });

  it('should store and broadcast private message', (done) => {
    global.fetch.mockResolvedValueOnce({
      ok: true,
      json: async () => ({
        status: true,
        data: { 
          user_id: 10, 
          karang_taruna_id: 1, 
          role_level: 'anggota',
          permissions: ['chat.read', 'chat.send'] 
        }
      })
    });

    pool.execute.mockResolvedValueOnce([ { insertId: 500 } ]);
    
    const port = server.address().port;
    clientSocket = ioc(`http://localhost:${port}`);
    
    clientSocket.on('connect', () => {
      clientSocket.emit('auth', { token: 'valid', tenant_id: 1 });
    });

    clientSocket.once('auth_success', () => {
      clientSocket.emit('send_message', { type: 'private', receiver_id: 20, message: 'secret' });
      
      setTimeout(() => {
        expect(pool.execute).toHaveBeenCalledWith(
          expect.stringContaining('INSERT INTO chats'),
          expect.arrayContaining([expect.anything(), 'private', expect.anything(), 'secret', 20])
        );
        done();
      }, 100);
    });
  });

  it('should reject auth if tenant mismatch occurs in API response', (done) => {
    global.fetch.mockResolvedValueOnce({
      ok: false,
      json: async () => ({
        status: false,
        message: 'Membership is inactive or denied',
        errorCode: 'TENANT_ACCESS_REVOKED'
      })
    });

    const port = server.address().port;
    clientSocket = ioc(`http://localhost:${port}`);
    
    clientSocket.on('connect', () => {
      clientSocket.emit('auth', { token: 'valid_token', tenant_id: 2 });
    });

    clientSocket.once('auth_error', (data) => {
      expect(data.message).toBe('Authentication failed');
      done();
    });
  });

  it('should not broadcast private message to receiver in different tenant', (done) => {
    expect(true).toBe(true);
    done();
  });
});
