const storedChatRow = require('./support/chat-row');
// Local Socket.IO transport only; PHP auth/FCM and MySQL are in-memory mocks.
const ioc = require('socket.io-client');
jest.mock('mysql2/promise', () => ({ createPool: jest.fn(() => ({
  execute: jest.fn(), end: jest.fn().mockResolvedValue()
})) }));
Object.assign(process.env, {
  DB_HOST: '127.0.0.1', DB_USER: 'test', DB_PASSWORD: '', DB_NAME: 'test',
  INTERNAL_API_SECRET: 'test-secret',
  INTERNAL_API_URL: 'http://127.0.0.1/api/internal/socket-auth'
});
global.fetch = jest.fn();
const { server, io, pool, privateUserRoom, renewAuthorization, AUTH_LEASE_MS, abuse } = require('../server');
let clients, insertId, identities, eligibility;
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
function event(socket, name) {
  return new Promise((resolve, reject) => {
    const timeout = setTimeout(() => reject(new Error(`Missing ${name}`)), 1500);
    socket.once(name, data => { clearTimeout(timeout); resolve(data); });
  });
}
async function connect(user, tenant, token = `${user}:${tenant}:${clients.length}`) {
  identities.set(token, { user_id: user, karang_taruna_id: tenant });
  const socket = ioc(`http://127.0.0.1:${server.address().port}`, {
    transports: ['websocket'], autoConnect: false, reconnection: false
  });
  clients.push(socket);
  socket.on('connect', () => socket.emit('auth', { token, tenant_id: tenant }));
  const authenticated = event(socket, 'auth_success');
  socket.connect();
  await authenticated;
  return socket;
}
function track(sockets, name = 'new_message') {
  const received = sockets.map(() => []);
  sockets.forEach((socket, i) => socket.on(name, message => received[i].push(message)));
  return received;
}
async function send(socket, extra = {}) {
  const delivered = event(socket, 'new_message');
  socket.emit('send_message', { type: 'private', receiver_id: 20, message: 'Synthetic', ...extra });
  const payload = await delivered;
  await delay(30);
  return payload;
}
beforeAll(done => { server.listen(0, '127.0.0.1', done); });
afterAll(async () => { io.close(); server.close(); await pool.end(); });
beforeEach(() => {
  abuse.clear();
  clients = []; insertId = 0; identities = new Map(); eligibility = new Map();
  jest.clearAllMocks();
  global.fetch.mockImplementation(async (url, options) => {
    // Enforce mock-only allowlist: even accidental fetch changes cannot send I/O.
    expect(url).toMatch(/^http:\/\/127\.0\.0\.1\/api\/internal\/(socket-auth|chat-notification)$/);
    if (url.endsWith('chat-notification')) return { ok: true };
    const identity = identities.get(options.headers.Authorization.replace('Bearer ', ''));
    return { ok: Boolean(identity), json: async () => ({
      status: Boolean(identity), data: identity && { ...identity, permissions: ['chat.read', 'chat.send'] }
    }) };
  });
  pool.execute.mockImplementation(async (sql, params) => {
    if (sql.includes('organization_members')) {
      if (sql.includes('JOIN users')) {
        expect(sql).toContain("om.approval_status = 'approved'");
        expect(sql).toContain('om.status_aktif = 1');
        expect(sql).toContain('u.status_aktif = 1');
        expect(sql).toContain('kt.status_aktif = 1');
        expect(sql).toContain('om.karang_taruna_id = ?');
        const state = eligibility.get(`${params[0]}:${params[1]}`);
        if (state === 'missing' || (state && (
          state.approval !== 'approved' || !state.member || !state.user || !state.tenant
        ))) return [[]];
      }
      return [[{ user_id: params[0] }]];
    }
    if (sql.includes('INSERT INTO chats')) return [{ insertId: ++insertId }];
    if (sql.includes('DATE_FORMAT')) return [[storedChatRow(pool, params, '2026-10-05T00:00:00Z')]];
    if (sql.includes('chat_rooms')) return [[{ id: params[0], type: params[0] === 7 ? 'custom' : 'default', karang_taruna_id: params[1] }]];
    if (sql.includes('chat_room_members')) return [[{ user_id: params[1] }]];
    throw new Error('Unexpected SQL in isolated test');
  });
});
afterEach(async () => { clients.forEach(socket => socket.disconnect()); await delay(10); });

test('message quota is shared across two devices and tenant contexts', async () => {
  const one = await connect(10, 101);
  const two = await connect(10, 102);
  for (let i = 0; i < 5; i++) await send(i % 2 ? two : one);
  const rejected = event(two, 'error');
  two.emit('send_message', { type: 'private', receiver_id: 20, message: 'Synthetic' });
  expect((await rejected).message).toBe('RATE_LIMITED');
  expect(insertId).toBe(5);
});

test('join in-flight guard stops query multiplication', async () => {
  const socket = await connect(10, 101);
  const original = pool.execute.getMockImplementation();
  let release;
  pool.execute.mockImplementationOnce(() => new Promise(resolve => { release = resolve; }));
  socket.emit('join_room', { room_id: 1 });
  await delay(20);
  const rejected = event(socket, 'error');
  socket.emit('join_room', { room_id: 2 });
  expect((await rejected).message).toBe('RATE_LIMITED');
  expect(pool.execute).toHaveBeenCalledTimes(1);
  const joined = event(socket, 'room_joined');
  pool.execute.mockImplementation(original);
  release([[{ id: 1, type: 'default' }]]);
  expect((await joined).room_id).toBe(1);
});

test('authentication is single-flight and disconnect aborts upstream work', async () => {
  let upstreamSignal;
  global.fetch.mockImplementationOnce((url, options) => new Promise((resolve, reject) => {
    upstreamSignal = options.signal;
    options.signal.addEventListener('abort', () => reject(new Error('Synthetic abort')), { once: true });
  }));
  const socket = ioc(`http://127.0.0.1:${server.address().port}`, { transports: ['websocket'], autoConnect: false, reconnection: false });
  clients.push(socket);
  socket.on('connect', () => socket.emit('auth', { token: 'pending', tenant_id: 101 }));
  socket.connect();
  await delay(30);
  expect(upstreamSignal).toBeDefined();
  const denied = event(socket, 'auth_error');
  socket.emit('auth', { token: 'pending', tenant_id: 101 });
  expect((await denied).message).toBe('Socket is already authenticated');
  expect(global.fetch).toHaveBeenCalledTimes(1);
  socket.disconnect();
  await delay(30);
  expect(upstreamSignal.aborted).toBe(true);
});

test('sixth socket for one global user is rejected across tenants', async () => {
  for (let i = 0; i < 5; i++) await connect(10, i % 2 ? 102 : 101);
  identities.set('sixth', { user_id: 10, karang_taruna_id: 101 });
  const socket = ioc(`http://127.0.0.1:${server.address().port}`, { transports: ['websocket'], autoConnect: false, reconnection: false });
  clients.push(socket);
  socket.on('connect', () => socket.emit('auth', { token: 'sixth', tenant_id: 101 }));
  const denied = event(socket, 'auth_error');
  socket.connect();
  expect((await denied).message).toBe('Authentication failed');
});

test('same identities in A/B and multiple devices: only A sender/receiver devices receive A', async () => {
  const sockets = [];
  for (const [user, tenant] of [[10,101],[10,101],[10,102],[20,101],[20,101],[20,102]]) {
    sockets.push(await connect(user, tenant));
  }
  const received = track(sockets);
  await send(sockets[0]);
  expect(received.map(rows => rows.length)).toEqual([1,1,0,1,1,0]);
  expect(pool.execute.mock.calls).toHaveLength(4);
  for (const socket of sockets) {
    const identity = io.sockets.sockets.get(socket.id);
    expect([...identity.rooms].sort()).toEqual([
      socket.id, `tenant_${identity.karangTarunaId}`,
      privateUserRoom(identity.karangTarunaId, identity.userId)
    ].sort());
    expect(identity.rooms.has(`user_${identity.userId}`)).toBe(false);
  }
});
test('spoofed sender and tenant cannot override persistence or either destination', async () => {
  const sender = await connect(10,101);
  const receiverA = await connect(20,101);
  const receiverB = await connect(20,102);
  const received = track([receiverA,receiverB]);
  const message = await send(sender, { sender_id: 999, karang_taruna_id: 102, tenant_id: 102 });
  expect(message.sender_id).toBe(10);
  expect(message.karang_taruna_id).toBe(101);
  expect(pool.execute.mock.calls.find(([sql]) => sql.includes('INSERT INTO chats'))[1]).toEqual([101,'private',10,20,'Synthetic',null]);
  expect(received.map(rows => rows.length)).toEqual([1,0]);
});
test.each([
  ['nonmember', 'missing'],
  ['pending', { approval:'pending',member:1,user:1,tenant:1 }],
  ['rejected', { approval:'rejected',member:1,user:1,tenant:1 }],
  ['inactive membership', { approval:'approved',member:0,user:1,tenant:1 }],
  ['inactive user', { approval:'approved',member:1,user:0,tenant:1 }],
  ['inactive organization', { approval:'approved',member:1,user:1,tenant:0 }]
])('receiver %s is denied without insert or delivery', async (_, state) => {
  const sender = await connect(10,101);
  const receiver = await connect(20,101);
  const received = track([sender,receiver]);
  eligibility.set('20:101', state);
  const rejected = event(sender,'error');
  sender.emit('send_message', { type:'private', receiver_id:20, message:'Synthetic' });
  expect((await rejected).message).toBe('Receiver not found or not active in this tenant');
  await delay(30);
  expect(insertId).toBe(0);
  expect(received).toEqual([[],[]]);
});
test('inactive organization denies sender before insert', async () => {
  const sender = await connect(10,101);
  eligibility.set('10:101', { approval:'approved',member:1,user:1,tenant:0 });
  const rejected = event(sender,'error');
  sender.emit('send_message', { type:'private', receiver_id:20, message:'Synthetic' });
  expect((await rejected).message).toBe('You are not an active member of this tenant');
  expect(insertId).toBe(0);
});
test.each([7,8])('custom/default group %i delivery remains scoped and functional', async room => {
  const sender = await connect(10,101);
  const receiver = await connect(20,101);
  const outsider = await connect(20,102);
  for (const socket of [sender,receiver]) {
    const joined = event(socket,'room_joined');
    socket.emit('join_room',{ room_id:room });
    await joined;
  }
  const received = track([sender,receiver,outsider]);
  await send(sender,{ type:'group',chat_room_id:room });
  expect(received.map(rows => rows.length)).toEqual([1,1,0]);
});
test('wheel endpoint and tenant broadcasts retain their tenant rooms', async () => {
  const a = await connect(20,101), b = await connect(20,102);
  for (const socket of [a,b]) {
    const joined = event(socket,'wheel_joined');
    socket.emit('join_wheel',{session_id:7}); await joined;
  }
  const received = track([a,b],'wheel_closed');
  // A real request is allowed only to this ephemeral localhost listener.
  const http = require('http');
  await new Promise((resolve,reject) => {
    const request = http.request({ host:'127.0.0.1',port:server.address().port,
      path:'/internal/wheel-event',method:'POST',headers:{
        'Content-Type':'application/json','X-Internal-Secret':'test-secret'
      } }, response => { expect(response.statusCode).toBe(200); response.resume(); response.on('end',resolve); });
    request.on('error',reject);
    request.end(JSON.stringify({session_id:7,karang_taruna_id:101,event:'wheel_closed',payload:{session_id:7}}));
  });
  await delay(30);
  expect(received.map(rows => rows.length)).toEqual([1,0]);
  const tenantEvents = track([a,b],'tenant_test');
  io.to('tenant_101').emit('tenant_test',{karang_taruna_id:101});
  await delay(30);
  expect(tenantEvents.map(rows => rows.length)).toEqual([1,0]);
});
test('reconnect in B drops all A rooms for the same identity', async () => {
  const sender = await connect(10,101);
  const receiver = await connect(20,101,'receiver');
  receiver.removeAllListeners('connect'); receiver.disconnect(); await delay(10);
  identities.set('receiver',{user_id:20,karang_taruna_id:102});
  receiver.on('connect',() => receiver.emit('auth',{token:'receiver',tenant_id:102}));
  const authenticated = event(receiver,'auth_success'); receiver.connect(); await authenticated;
  const received = track([receiver]);
  await send(sender);
  expect(received[0]).toEqual([]);
  expect(io.sockets.sockets.get(receiver.id).rooms.has('tenant_101_user_20')).toBe(false);
});
test.each(['membership removed','membership rejected/inactive','organization disabled','user disabled','token revoked'])('expired receiving lease evicts %s', async reason => {
  const receiver = await connect(20,101);
  const serverSocket = io.sockets.sockets.get(receiver.id);
  global.fetch.mockImplementationOnce(async () => ({ok:false})); // PHP denies this state.
  await renewAuthorization(serverSocket);
  expect(serverSocket.connected).toBe(false);
  expect(io.sockets.adapter.rooms.has(privateUserRoom(101,20))).toBe(false);
  expect(pool.execute).not.toHaveBeenCalled();
});
test('renewal suspends reception, uses one in-flight fetch, then restores validated rooms', async () => {
  const receiver = await connect(20,101);
  const serverSocket = io.sockets.sockets.get(receiver.id);
  const originalFetch = global.fetch.getMockImplementation();
  let release;
  global.fetch.mockImplementationOnce((url, options) => new Promise(resolve => {
    release = () => resolve(originalFetch(url,options));
  }));
  const renewal = renewAuthorization(serverSocket);
  const secondRenewal = renewAuthorization(serverSocket);
  expect(serverSocket.rooms.has(privateUserRoom(101,20))).toBe(false);
  expect(global.fetch).toHaveBeenCalledTimes(2); // initial auth + one renewal
  release();
  expect(await renewal).toBe(true); expect(await secondRenewal).toBe(true);
  expect(serverSocket.rooms.has(privateUserRoom(101,20))).toBe(true);
  expect(AUTH_LEASE_MS).toBe(60000);
});
test.each(['wrong user','wrong tenant','chat.read removed'])('renewal rejects %s', async state => {
  const receiver = await connect(20,101);
  const data = {user_id:state === 'wrong user' ? 99 : 20,
    karang_taruna_id:state === 'wrong tenant' ? 102 : 101,
    permissions:state === 'chat.read removed' ? ['chat.send'] : ['chat.read']};
  global.fetch.mockResolvedValueOnce({ok:true,json:async () => ({status:true,data})});
  expect(await renewAuthorization(io.sockets.sockets.get(receiver.id))).toBe(false);
});
test('idle authorization timer renews without any receiving-side send', async () => {
  const timers = jest.spyOn(global,'setTimeout');
  try {
    const receiver = await connect(20,101);
    const serverSocket = io.sockets.sockets.get(receiver.id);
    const scheduled = timers.mock.calls.find(([,ms]) => ms === AUTH_LEASE_MS);
    expect(scheduled).toBeDefined();
    global.fetch.mockResolvedValueOnce({ok:false});
    await scheduled[0]();
    expect(serverSocket.connected).toBe(false);
    expect(serverSocket.rooms.size).toBe(0);
  } finally { timers.mockRestore(); }
});
test('renewal timeout fails closed with a five-second deadline', async () => {
  const receiver = await connect(20,101);
  const serverSocket = io.sockets.sockets.get(receiver.id);
  const timers = jest.spyOn(global,'setTimeout');
  try {
    global.fetch.mockImplementationOnce((_url,options) => new Promise((_,reject) => {
      options.signal.addEventListener('abort',() => reject(new Error('Aborted')));
    }));
    const renewal = renewAuthorization(serverSocket);
    const deadline = timers.mock.calls.find(([,ms]) => ms === 5000);
    expect(deadline).toBeDefined();
    expect(serverSocket.rooms.has(privateUserRoom(101,20))).toBe(false);
    deadline[0]();
    expect(await renewal).toBe(false);
    expect(serverSocket.connected).toBe(false);
  } finally { timers.mockRestore(); }
});
test('concurrent auth cannot add private rooms from two tenants', async () => {
  let release;
  const firstFetch = global.fetch.getMockImplementation();
  global.fetch.mockImplementationOnce((url,options) => new Promise(resolve => {
    release = () => resolve(firstFetch(url,options));
  }));
  identities.set('one',{user_id:20,karang_taruna_id:101});
  identities.set('two',{user_id:20,karang_taruna_id:102});
  const socket = ioc(`http://127.0.0.1:${server.address().port}`, {transports:['websocket'],autoConnect:false});
  clients.push(socket);
  const connected = event(socket,'connect'); socket.connect(); await connected;
  socket.emit('auth',{token:'one',tenant_id:101});
  await delay(10);
  const rejected = event(socket,'auth_error');
  socket.emit('auth',{token:'two',tenant_id:102}); await rejected;
  const authenticated = event(socket,'auth_success'); release(); await authenticated;
  const rooms = io.sockets.sockets.get(socket.id).rooms;
  expect(rooms.has(privateUserRoom(101,20))).toBe(true);
  expect(rooms.has(privateUserRoom(102,20))).toBe(false);
});


test('lost ACK retry after reconnect acknowledges one persisted row with one broadcast and notification', async () => {
  const store = require('./support/chat-store')();
  const original = pool.execute.getMockImplementation();
  pool.execute.mockImplementation((sql, args) => sql.includes('chats') ? store.execute(sql, args) : original(sql, args));
  const first = await connect(10, 101);
  const peer = await connect(20, 101);
  const received = track([peer]);
  const data = { type: 'private', receiver_id: 20, message: 'Retry', client_message_id: '01234567-89ab-4cde-8f01-23456789abcd' };
  // Intentionally ignore the first server acknowledgement.
  first.emit('send_message', data);
  await delay(40);
  first.disconnect();
  const second = await connect(10, 101);
  const ack = await new Promise(resolve => second.emit('send_message', data, resolve));
  await delay(30);
  expect(ack.success).toBe(true); expect(ack.duplicate).toBe(true);
  expect(ack.message.id).toBe(store.rows[0].id);
  expect(ack.message.created_at).toBe(store.rows[0].created_at_iso);
  expect(store.rows).toHaveLength(1); expect(received[0]).toHaveLength(1);
  expect(global.fetch.mock.calls.filter(([url]) => url.endsWith('chat-notification'))).toHaveLength(1);
});

test('no socket ACK or fanout precedes successful canonical row read', async () => {
  const store = require('./support/chat-store')();
  const original = pool.execute.getMockImplementation();
  let release;
  pool.execute.mockImplementation((sql, args) => {
    if (!sql.includes('chats')) return original(sql, args);
    if (sql.startsWith('INSERT')) return store.execute(sql, args);
    return new Promise(resolve => { release = () => resolve(store.execute(sql, args)); });
  });
  const sender = await connect(10, 101);
  const received = track([sender]); let acknowledged = false;
  const result = new Promise(resolve => sender.emit('send_message', { type: 'private', receiver_id: 20, message: 'Synthetic' }, ack => {
    acknowledged = true; resolve(ack);
  }));
  await delay(30);
  expect(acknowledged).toBe(false); expect(received[0]).toEqual([]);
  expect(global.fetch.mock.calls.filter(([url]) => url.endsWith('chat-notification'))).toHaveLength(0);
  release(); expect((await result).success).toBe(true);
});


test('trusted persisted REST chat fanout ignores spoofed body tenant and stays in scoped device rooms', async () => {
  const http = require('http');
  const sockets = [await connect(10, 101), await connect(20, 101), await connect(20, 102)];
  const received = track(sockets);
  const original = pool.execute.getMockImplementation();
  pool.execute.mockImplementation((sql, args) => sql.startsWith('SELECT c.') ? [[{
    id: 99, karang_taruna_id: 101, sender_id: 10, receiver_id: 20, type: 'private', message: 'Persisted REST',
    created_at_iso: '2026-10-05T00:00:00Z', client_message_id: '01234567-89ab-4cde-8f01-23456789abcd'
  }]] : original(sql, args));
  const request = secret => new Promise((resolve, reject) => {
    const body = JSON.stringify({chat_id:99,tenant_id:102});
    const req = http.request({host:'127.0.0.1',port:server.address().port,path:'/internal/chat-event',method:'POST',
      headers:{'Content-Type':'application/json','Content-Length':Buffer.byteLength(body),'X-Internal-Secret':secret}},res => {
      res.resume();res.on('end',()=>resolve(res.statusCode));
    });req.on('error',reject);req.end(body);
  });
  expect(await request('wrong')).toBe(403);
  expect(await request('test-secret')).toBe(200);
  await delay(30);
  expect(received.map(messages=>messages.length)).toEqual([1,1,0]);
  expect(received[0][0].message).toBe('Persisted REST');
  expect(received[0][0].karang_taruna_id).toBe(101);
});
