const storedChatRow = require('./support/chat-row');
// SEC-02 regression: a pass requires tenant-context isolation.
const ioc = require('socket.io-client');
jest.mock('mysql2/promise', () => ({ createPool: jest.fn(() => ({
  execute: jest.fn(), end: jest.fn().mockResolvedValue()
})) }));
process.env.DB_HOST = '127.0.0.1';
process.env.DB_USER = 'audit';
process.env.DB_PASSWORD = '';
process.env.DB_NAME = 'audit';
process.env.INTERNAL_API_SECRET = 'audit-test-secret';
process.env.INTERNAL_API_URL = 'http://127.0.0.1/api/internal/socket-auth';
global.fetch = jest.fn(async (_url, options) => {
  const tenant = Number(options.headers['X-Karang-Taruna-ID']);
  const user = options.headers.Authorization === 'Bearer sender' ? 10 : 20;
  return { ok: true, json: async () => ({ status: true, data: {
    user_id: user, karang_taruna_id: tenant, nama_lengkap: 'Synthetic',
    role_level: 'anggota', permissions: ['chat.read', 'chat.send']
  }}) };
});
const { server, io, pool } = require('../server');
const clients = [];
function authenticated(token, tenant) {
  return new Promise((resolve, reject) => {
    const socket = ioc(`http://127.0.0.1:${server.address().port}`, { transports: ['websocket'] });
    clients.push(socket);
    socket.on('connect', () => socket.emit('auth', { token, tenant_id: tenant }));
    socket.once('auth_success', () => resolve(socket));
    socket.once('auth_error', reject);
  });
}
beforeAll(done => { server.listen(0, '127.0.0.1', done); });
afterAll(async () => { clients.forEach(s => s.disconnect()); io.close(); server.close(); await pool.end(); });
test('SEC-02: private message for tenant A never arrives on receiver socket authenticated only for tenant B', async () => {
  const sender = await authenticated('sender', 101);
  const receiverInB = await authenticated('receiver', 102);
  pool.execute.mockResolvedValueOnce([[{ user_id: 10, status_aktif: 1 }]])
    .mockResolvedValueOnce([[{ user_id: 20, status_aktif: 1 }]])
    .mockResolvedValueOnce([{ insertId: 123 }])
    .mockImplementationOnce(async (sql, params) => [[storedChatRow(pool, params, '2026-10-05T00:00:00Z')]]);
  const received = [];
  receiverInB.on('new_message', payload => received.push(payload));
  const sent = new Promise(resolve => sender.once('new_message', resolve));
  sender.emit('send_message', { type: 'private', receiver_id: 20, message: 'Synthetic audit message' });
  const payload = await sent;
  await new Promise(resolve => setTimeout(resolve, 50));
  expect(payload.karang_taruna_id).toBe(101);
  expect(payload.receiver_id).toBe(20);
  expect(received).toEqual([]);
});
