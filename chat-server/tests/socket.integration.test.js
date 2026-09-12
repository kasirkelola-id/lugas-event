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

global.fetch = jest.fn().mockResolvedValue({
  ok: true,
  json: async () => ({ status: true })
});

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
    ]).mockResolvedValueOnce([
      [{ user_id: 10, status_aktif: 1 }] // Active member check
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
      [{ user_id: 11, status_aktif: 1 }] // Active member check
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

    pool.execute
      .mockResolvedValueOnce([ [{ user_id: 10, status_aktif: 1 }] ]) // sender validation
      .mockResolvedValueOnce([ [{ user_id: 20, status_aktif: 1 }] ]) // receiver validation
      .mockResolvedValueOnce([ { insertId: 500 } ]) // insert
      .mockResolvedValueOnce([ [{ created_at_iso: '2026-09-12T10:15:30Z' }] ]); // timestamp

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

  // NEW SECURITY TESTS

  // PRIVATE SEND
  it('PRIVATE: cross tenant receiver => rejected, insert 0', (done) => {
    global.fetch.mockResolvedValueOnce({ ok: true, json: async () => ({ status: true, data: { user_id: 10, karang_taruna_id: 1, permissions: ['chat.send', 'chat.read'] } }) });
    pool.execute.mockResolvedValueOnce([ [{ user_id: 10, status_aktif: 1 }] ]); // Sender active
    pool.execute.mockResolvedValueOnce([ [] ]); // Receiver cross-tenant (empty)

    const port = server.address().port;
    clientSocket = ioc(`http://localhost:${port}`);
    clientSocket.on('connect', () => clientSocket.emit('auth', { token: 'valid', tenant_id: 1 }));

    clientSocket.once('auth_success', () => {
      clientSocket.emit('send_message', { type: 'private', receiver_id: 99, message: 'secret' });
    });

    clientSocket.once('error', (data) => {
      expect(data.message).toBe('Receiver not found or not active in this tenant');
      expect(pool.execute).not.toHaveBeenCalledWith(expect.stringContaining('INSERT INTO chats'), expect.anything());
      done();
    });
  });

  it('PRIVATE: inactive receiver => rejected, insert 0', (done) => {
    global.fetch.mockResolvedValueOnce({ ok: true, json: async () => ({ status: true, data: { user_id: 10, karang_taruna_id: 1, permissions: ['chat.send', 'chat.read'] } }) });
    pool.execute.mockResolvedValueOnce([ [{ user_id: 10, status_aktif: 1 }] ]); // Sender active
    pool.execute.mockResolvedValueOnce([ [] ]); // Receiver inactive (empty)

    const port = server.address().port;
    clientSocket = ioc(`http://localhost:${port}`);
    clientSocket.on('connect', () => clientSocket.emit('auth', { token: 'valid', tenant_id: 1 }));

    clientSocket.once('auth_success', () => {
      clientSocket.emit('send_message', { type: 'private', receiver_id: 99, message: 'secret' });
    });

    clientSocket.once('error', (data) => {
      expect(data.message).toBe('Receiver not found or not active in this tenant');
      expect(pool.execute).not.toHaveBeenCalledWith(expect.stringContaining('INSERT INTO chats'), expect.anything());
      done();
    });
  });

  it('PRIVATE: nonexistent receiver => rejected, insert 0', (done) => {
    global.fetch.mockResolvedValueOnce({ ok: true, json: async () => ({ status: true, data: { user_id: 10, karang_taruna_id: 1, permissions: ['chat.send', 'chat.read'] } }) });
    pool.execute.mockResolvedValueOnce([ [{ user_id: 10, status_aktif: 1 }] ]); // Sender active
    pool.execute.mockResolvedValueOnce([ [] ]); // Receiver nonexistent (empty)

    const port = server.address().port;
    clientSocket = ioc(`http://localhost:${port}`);
    clientSocket.on('connect', () => clientSocket.emit('auth', { token: 'valid', tenant_id: 1 }));

    clientSocket.once('auth_success', () => {
      clientSocket.emit('send_message', { type: 'private', receiver_id: 999, message: 'secret' });
    });

    clientSocket.once('error', (data) => {
      expect(data.message).toBe('Receiver not found or not active in this tenant');
      expect(pool.execute).not.toHaveBeenCalledWith(expect.stringContaining('INSERT INTO chats'), expect.anything());
      done();
    });
  });

  // GROUP SEND
  it('GROUP SEND: custom non-member => rejected, insert 0', (done) => {
    global.fetch.mockResolvedValueOnce({ ok: true, json: async () => ({ status: true, data: { user_id: 10, karang_taruna_id: 1, permissions: ['chat.send', 'chat.read'] } }) });

    const port = server.address().port;
    clientSocket = ioc(`http://localhost:${port}`);
    clientSocket.on('connect', () => clientSocket.emit('auth', { token: 'valid', tenant_id: 1 }));

    clientSocket.once('auth_success', () => {
      // Must join room first for group send
      pool.execute.mockResolvedValueOnce([ [{ id: 102, type: 'custom', karang_taruna_id: 1 }] ]) // join room query
                  .mockResolvedValueOnce([ [{ user_id: 10, status_aktif: 1 }] ]) // global active check
                  .mockResolvedValueOnce([ [{ user_id: 10 }] ]); // custom member check -> allow join
      clientSocket.emit('join_room', { room_id: 102 });
    });

    clientSocket.once('room_joined', () => {
      // Now send message but simulate not being a member during send revalidation
      pool.execute.mockResolvedValueOnce([ [{ id: 102, type: 'custom', karang_taruna_id: 1 }] ]) // send room query
                  .mockResolvedValueOnce([ [{ user_id: 10, status_aktif: 1 }] ]) // send global check
                  .mockResolvedValueOnce([ [] ]); // send custom member check -> REJECT
      clientSocket.emit('send_message', { type: 'group', chat_room_id: 102, message: 'secret' });
    });

    clientSocket.on('error', (data) => {
      if (data.message === 'You are not a member of this custom room') {
        expect(pool.execute).not.toHaveBeenCalledWith(expect.stringContaining('INSERT INTO chats'), expect.anything());
        done();
      }
    });
  });

  it('GROUP SEND: revoked member => rejected, insert 0', (done) => {
    global.fetch.mockResolvedValueOnce({ ok: true, json: async () => ({ status: true, data: { user_id: 10, karang_taruna_id: 1, permissions: ['chat.send', 'chat.read'] } }) });

    const port = server.address().port;
    clientSocket = ioc(`http://localhost:${port}`);
    clientSocket.on('connect', () => clientSocket.emit('auth', { token: 'valid', tenant_id: 1 }));

    clientSocket.once('auth_success', () => {
      pool.execute.mockResolvedValueOnce([ [{ id: 102, type: 'default', karang_taruna_id: 1 }] ]) // join room query
                  .mockResolvedValueOnce([ [{ user_id: 10, status_aktif: 1 }] ]); // join active check
      clientSocket.emit('join_room', { room_id: 102 });
    });

    clientSocket.once('room_joined', () => {
      // Revoked during send
      pool.execute.mockResolvedValueOnce([ [{ id: 102, type: 'default', karang_taruna_id: 1 }] ]) // send room query
                  .mockResolvedValueOnce([ [] ]); // send global check -> REJECT (inactive)
      clientSocket.emit('send_message', { type: 'group', chat_room_id: 102, message: 'secret' });
    });

    clientSocket.on('error', (data) => {
      if (data.message === 'You are not an active member of this tenant') {
        expect(pool.execute).not.toHaveBeenCalledWith(expect.stringContaining('INSERT INTO chats'), expect.anything());
        done();
      }
    });
  });

  it('GROUP SEND: other tenant room => rejected, insert 0', (done) => {
    global.fetch.mockResolvedValueOnce({ ok: true, json: async () => ({ status: true, data: { user_id: 10, karang_taruna_id: 1, permissions: ['chat.send', 'chat.read'] } }) });

    const port = server.address().port;
    clientSocket = ioc(`http://localhost:${port}`);
    clientSocket.on('connect', () => clientSocket.emit('auth', { token: 'valid', tenant_id: 1 }));

    clientSocket.once('auth_success', () => {
      pool.execute.mockResolvedValueOnce([ [{ id: 105, type: 'default', karang_taruna_id: 1 }] ])
                  .mockResolvedValueOnce([ [{ user_id: 10, status_aktif: 1 }] ]);
      clientSocket.emit('join_room', { room_id: 105 });
    });

    clientSocket.once('room_joined', () => {
      // Simulate room changed tenant or querying wrong tenant
      pool.execute.mockResolvedValueOnce([ [] ]); // room query returns empty because karang_taruna_id doesn't match
      clientSocket.emit('send_message', { type: 'group', chat_room_id: 105, message: 'secret' });
    });

    clientSocket.on('error', (data) => {
      if (data.message === 'Room not found or belongs to another tenant') {
        expect(pool.execute).not.toHaveBeenCalledWith(expect.stringContaining('INSERT INTO chats'), expect.anything());
        done();
      }
    });
  });

  // JOIN
  it('JOIN: revoked/default-room join rejected', (done) => {
    global.fetch.mockResolvedValueOnce({ ok: true, json: async () => ({ status: true, data: { user_id: 10, karang_taruna_id: 1, permissions: ['chat.send', 'chat.read'] } }) });
    pool.execute.mockResolvedValueOnce([ [{ id: 100, type: 'default', karang_taruna_id: 1 }] ]) // Rooms
                .mockResolvedValueOnce([ [] ]); // Revoked (empty active members)

    const port = server.address().port;
    clientSocket = ioc(`http://localhost:${port}`);
    clientSocket.on('connect', () => clientSocket.emit('auth', { token: 'valid', tenant_id: 1 }));

    clientSocket.once('auth_success', () => clientSocket.emit('join_room', { room_id: 100 }));

    clientSocket.once('error', (data) => {
      expect(data.message).toBe('You are not an active member of this tenant');
      done();
    });
  });
});
