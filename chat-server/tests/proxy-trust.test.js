const http = require('node:http');
const proxyaddr = require('proxy-addr');

jest.mock('express', () => {
  const real = jest.requireActual('express');
  const capture = (...args) => {
    const app = real(...args);
    global.__proxyTestApp = app;
    return app;
  };
  return Object.assign(capture, real);
});

jest.mock('mysql2/promise', () => ({ createPool: jest.fn(() => ({
  execute: jest.fn(), end: jest.fn().mockResolvedValue()
})) }));
Object.assign(process.env, {
  DB_HOST: '127.0.0.1', DB_USER: 'synthetic', DB_PASSWORD: '', DB_NAME: 'synthetic',
  INTERNAL_API_SECRET: 'synthetic-proxy-test-secret',
  INTERNAL_API_URL: 'http://127.0.0.1/api/internal/socket-auth'
});
global.fetch = jest.fn(() => { throw new Error('No external transport permitted'); });
const { server, io, pool } = require('../server');
const app = global.__proxyTestApp;
// Fixture-only route on this isolated Jest server, never application source.
app.get('/__test_ip', (req, res) => res.json({ ip: req.ip, ips: req.ips }));
beforeAll(done => { server.listen(0, '127.0.0.1', done); });
afterAll(async () => { io.close(); server.close(); await pool.end(); delete global.__proxyTestApp; });

test('configured application ignores attacker forwarded IP and retains socket peer authority', async () => {
  expect(app.get('trust proxy')).toBe(false);
  const result = await new Promise((resolve, reject) => {
    http.get({ host: '127.0.0.1', port: server.address().port, path: '/__test_ip',
      headers: { 'X-Forwarded-For': '198.51.100.23, 10.0.0.1' }
    }, response => {
      let body = '';
      response.on('data', chunk => { body += chunk; });
      response.on('end', () => resolve(JSON.parse(body)));
    }).on('error', reject);
  });
  expect(result).toEqual({ ip: '127.0.0.1', ips: [] });
  expect(pool.execute).not.toHaveBeenCalled();
  expect(global.fetch).not.toHaveBeenCalled();
});

test('patched mapped IPv6 subnet cannot trust arbitrary IPv4 peers or spoof forwarded authority', () => {
  const short = proxyaddr.compile('::ffff:10.0.0.0/8');
  const broad = proxyaddr.compile('::/1');
  for (const trust of [short, broad]) {
    expect(trust('198.51.100.23', 0)).toBe(false);
    expect(proxyaddr({ socket: { remoteAddress: '198.51.100.23' },
      headers: { 'x-forwarded-for': '10.0.0.2' } }, trust)).toBe('198.51.100.23');
  }
  const valid = proxyaddr.compile('::ffff:10.0.0.0/104');
  expect(valid('10.1.2.3', 0)).toBe(true);
  expect(valid('198.51.100.23', 0)).toBe(false);
});
