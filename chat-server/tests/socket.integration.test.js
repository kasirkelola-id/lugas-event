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

const { server, io, pool, boundedConnectionLimit } = require('../server');

describe('Socket.IO Chat Integration', () => {
  let clientSocket;
  let extraSockets = [];

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
    for (const socket of extraSockets) {
      if (socket.connected) socket.disconnect();
    }
    extraSockets = [];
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

  it('USER ROOM: server assigns the authenticated room and ignores arbitrary join_user_room', (done) => {
    global.fetch.mockResolvedValueOnce({
      ok: true,
      json: async () => ({ status: true, data: { user_id: 10, karang_taruna_id: 1, permissions: ['chat.send', 'chat.read'] } })
    });
    const port = server.address().port;
    clientSocket = ioc(`http://localhost:${port}`);
    clientSocket.on('connect', () => clientSocket.emit('auth', { token: 'valid', tenant_id: 1 }));
    clientSocket.once('auth_success', () => {
      expect(io.sockets.adapter.rooms.get('user_10').has(clientSocket.id)).toBe(true);
      clientSocket.emit('join_user_room', { user_id: 99 });
      setTimeout(() => {
        expect(io.sockets.adapter.rooms.has('user_99')).toBe(false);
        done();
      }, 25);
    });
  });

  it('PRIVATE: rejects 2001 Unicode code points before any database insert', (done) => {
    global.fetch.mockResolvedValueOnce({
      ok: true,
      json: async () => ({ status: true, data: { user_id: 10, karang_taruna_id: 1, permissions: ['chat.send', 'chat.read'] } })
    });
    const port = server.address().port;
    clientSocket = ioc(`http://localhost:${port}`);
    clientSocket.on('connect', () => clientSocket.emit('auth', { token: 'valid', tenant_id: 1 }));
    clientSocket.once('auth_success', () => {
      clientSocket.emit('send_message', { type: 'private', receiver_id: 11, message: '🙂'.repeat(2001) });
    });
    clientSocket.once('error', (data) => {
      expect(data.message).toBe('Message exceeds 2000 characters limit');
      expect(pool.execute).not.toHaveBeenCalledWith(expect.stringContaining('INSERT INTO chats'), expect.anything());
      done();
    });
  });

  it('RATE LIMIT: sixth message in one second is rejected and only five insert attempts occur', (done) => {
    global.fetch.mockResolvedValueOnce({
      ok: true,
      json: async () => ({ status: true, data: { user_id: 10, karang_taruna_id: 1, permissions: ['chat.send', 'chat.read'] } })
    });
    let insertId = 1000;
    pool.execute.mockImplementation((sql) => {
      if (sql.includes('organization_members')) return Promise.resolve([[{ user_id: 10, status_aktif: 1 }]]);
      if (sql.includes('INSERT INTO chats')) return Promise.resolve([{ insertId: insertId++ }]);
      if (sql.includes('DATE_FORMAT')) return Promise.resolve([[{ created_at_iso: '2026-09-12T10:15:30Z' }]]);
      return Promise.resolve([[]]);
    });
    const port = server.address().port;
    clientSocket = ioc(`http://localhost:${port}`);
    clientSocket.on('connect', () => clientSocket.emit('auth', { token: 'valid', tenant_id: 1 }));
    clientSocket.once('auth_success', () => {
      for (let i = 0; i < 6; i++) clientSocket.emit('send_message', { type: 'private', receiver_id: 11, message: `m${i}` });
    });
    clientSocket.once('error', (data) => {
      expect(data.message).toBe('RATE_LIMITED');
      setTimeout(() => {
        expect(pool.execute.mock.calls.filter(([sql]) => sql.includes('INSERT INTO chats'))).toHaveLength(5);
        done();
      }, 25);
    });
  });

  it('bounds DB_CONNECTION_LIMIT to a safe 1..100 range with a default of 10', () => {
    expect(boundedConnectionLimit(undefined)).toBe(10);
    expect(boundedConnectionLimit('0')).toBe(1);
    expect(boundedConnectionLimit('999')).toBe(100);
    expect(boundedConnectionLimit('25')).toBe(25);
  });

  it('PRIVATE MATRIX: A1/A2 and B1/B2 receive exactly one; unrelated and cross-tenant sockets receive zero', async () => {
    const identities = {
      A1: { user_id: 10, karang_taruna_id: 1 },
      A2: { user_id: 10, karang_taruna_id: 1 },
      B1: { user_id: 20, karang_taruna_id: 1 },
      B2: { user_id: 20, karang_taruna_id: 1 },
      C1: { user_id: 21, karang_taruna_id: 1 },
      D1: { user_id: 30, karang_taruna_id: 2 },
    };
    global.fetch.mockImplementation(async (_url, options) => {
      const token = options.headers.Authorization.replace('Bearer ', '');
      const identity = identities[token];
      return { ok: Boolean(identity), json: async () => ({
        status: Boolean(identity),
        data: identity && { ...identity, permissions: ['chat.send', 'chat.read'] },
      }) };
    });
    let insertCount = 0;
    pool.execute.mockImplementation((sql, params) => {
      if (sql.includes('organization_members')) {
        // Receiver 30 belongs to tenant 2, so A -> D is rejected.
        return Promise.resolve([[params[0] === 30 ? [] : { user_id: params[0], status_aktif: 1 }].flat()]);
      }
      if (sql.includes('INSERT INTO chats')) {
        insertCount++;
        return Promise.resolve([{ insertId: 500 }]);
      }
      if (sql.includes('DATE_FORMAT')) return Promise.resolve([[{ created_at_iso: '2026-09-12T10:15:30Z' }]]);
      return Promise.resolve([[]]);
    });
    const port = server.address().port;
    const connect = (token, tenant) => new Promise((resolve, reject) => {
      const socket = ioc(`http://localhost:${port}`);
      extraSockets.push(socket);
      socket.once('connect', () => socket.emit('auth', { token, tenant_id: tenant }));
      socket.once('auth_success', () => resolve(socket));
      socket.once('auth_error', reject);
    });
    const [a1, a2, b1, b2, c1, d1] = await Promise.all([
      connect('A1', 1), connect('A2', 1), connect('B1', 1), connect('B2', 1), connect('C1', 1), connect('D1', 2),
    ]);
    const received = new Map([[a1, 0], [a2, 0], [b1, 0], [b2, 0], [c1, 0], [d1, 0]]);
    for (const socket of received.keys()) socket.on('new_message', () => received.set(socket, received.get(socket) + 1));

    a1.emit('send_message', { type: 'private', receiver_id: 20, message: 'one' });
    await new Promise((resolve) => setTimeout(resolve, 50));
    expect(insertCount).toBe(1);
    expect(received.get(a1)).toBe(1);
    expect(received.get(a2)).toBe(1);
    expect(received.get(b1)).toBe(1);
    expect(received.get(b2)).toBe(1);
    expect(received.get(c1)).toBe(0);
    expect(received.get(d1)).toBe(0);

    const rejection = new Promise((resolve) => a1.once('error', resolve));
    a1.emit('send_message', { type: 'private', receiver_id: 30, message: 'cross tenant' });
    await rejection;
    await new Promise((resolve) => setTimeout(resolve, 25));
    expect(insertCount).toBe(1);
    expect(received.get(d1)).toBe(0);
  });
});
