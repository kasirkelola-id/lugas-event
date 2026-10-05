const http = require('node:http');
const { createHealth } = require('../health');
jest.mock('mysql2/promise', () => ({ createPool: jest.fn(() => ({ execute: jest.fn(), end: jest.fn().mockResolvedValue() })) }));
Object.assign(process.env, { DB_HOST: '127.0.0.1', DB_USER: 'synthetic', DB_PASSWORD: '', DB_NAME: 'synthetic',
  INTERNAL_API_SECRET: 'synthetic-health-private-secret', INTERNAL_API_URL: 'http://127.0.0.1/api/internal/socket-auth' });
global.fetch = jest.fn(() => { throw new Error('No auth/provider transport expected'); });
const { server, io, pool, databasePort } = require('../server');
const fakeIo = { sockets: { sockets: new Map() }, engine: { clientsCount: 0 } };
const get = secret => new Promise((resolve, reject) => {
  const headers = secret ? { 'X-Internal-Secret': secret } : {};
  http.get({ host: '127.0.0.1', port: server.address().port, path: '/internal/health', headers }, response => {
    let body = ''; response.on('data', chunk => { body += chunk; });
    response.on('end', () => resolve({ code: response.statusCode, headers: response.headers, body: JSON.parse(body) }));
  }).on('error', reject);
});
beforeAll(done => { server.listen(0, '127.0.0.1', done); });
afterAll(async () => { io.close(); server.close(); await pool.end(); });

test('database port defaults only when absent and rejects invalid explicit values', () => {
  expect(databasePort(undefined)).toBe(3306);
  expect(databasePort('3309')).toBe(3309);
  for (const value of ['', '0', '-1', '65536', '3309extra', '3.5', ' 3309']) {
    expect(() => databasePort(value)).toThrow('Invalid database port configuration');
  }
});

test('internal HTTP health denies missing/wrong secret before SQL and serves safe no-store metrics', async () => {
  expect((await get()).code).toBe(403); expect((await get('wrong')).code).toBe(403);
  expect(pool.execute).not.toHaveBeenCalled();
  pool.execute.mockResolvedValue([[{ healthy: 1 }]]);
  const result = await get(process.env.INTERNAL_API_SECRET);
  expect(result.code).toBe(200); expect(result.headers['cache-control']).toBe('no-store');
  expect(result.body.database_ready).toBe(true); expect(result.body.rss_bytes).toBeGreaterThan(0);
  expect(result.body.sockets).toBe(0); expect(result.body.authenticated_sockets).toBe(0);
  expect(JSON.stringify(result.body)).not.toContain(process.env.INTERNAL_API_SECRET);
  expect(result.body.database_pool.queue_pending).toBeNull(); // No fabricated gauge for mocked/changed driver.
  expect(global.fetch).not.toHaveBeenCalled();
});

test('unresolved database check stays single in-flight across timeout callers and can recover', async () => {
  let finish;
  const db = { execute: jest.fn(() => new Promise(resolve => { finish = resolve; })) };
  const health = createHealth(db, fakeIo, () => 0, { timeoutMs: 20, cacheMs: 100 });
  expect(await Promise.all(Array.from({ length: 6 }, () => health.databaseReady()))).toEqual(Array(6).fill(false));
  expect(db.execute).toHaveBeenCalledTimes(1);
  finish([[{ healthy: 1 }]]);
  expect(await health.databaseReady()).toBe(true);
  expect(await health.databaseReady()).toBe(true);
  expect(db.execute).toHaveBeenCalledTimes(1);
  health.stop();
});

test('database failure produces degraded safe snapshot without driver message', async () => {
  const db = { execute: jest.fn().mockRejectedValue(new Error('synthetic-private-query-and-password')) };
  const health = createHealth(db, fakeIo, () => 0);
  const ready = await health.databaseReady(); expect(ready).toBe(false);
  const result = health.snapshot(ready); expect(result.status).toBe('degraded');
  expect(JSON.stringify(result)).not.toContain('synthetic-private'); expect(result.event_loop_lag_ms).toBeNull();
  health.stop();
});

test('pool adapter reads only numeric gauges and fails to unknown after unsupported layout', () => {
  const health = createHealth({ pool: { _allConnections: { length: 5 }, _freeConnections: { length: 2 }, _connectionQueue: { length: 3 } } }, fakeIo, () => 0);
  expect(health.snapshot(true).database_pool).toEqual({ connections: 5, in_use: 3, queue_pending: 3 });
  health.stop();
});
