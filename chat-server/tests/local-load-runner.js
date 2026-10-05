'use strict';

// Actual localhost HTTP + Socket.IO + MySQL8. No SQL, auth, quotas or fanout are
// mocked. The PHP parent owns the newly created schema and drops it afterward.
const fs = require('node:fs');
const path = require('node:path');
const os = require('node:os');
const net = require('node:net');
const { spawn } = require('node:child_process');
const { randomUUID, randomBytes } = require('node:crypto');
const { performance } = require('node:perf_hooks');
const { io: connect } = require('socket.io-client');

if (process.env.NODE_ENV !== 'test' || process.env.CI_ENVIRONMENT !== 'testing'
    || process.env.KARTAR_LOCAL_LOAD_ENABLE !== '1' || process.env.KARTAR_MYSQL_TEST_ENABLE !== '1') {
  throw new Error('Explicit local test environment required');
}
const root = fs.realpathSync(process.env.KARTAR_LOCAL_LOAD_ROOT);
if (fs.realpathSync(path.dirname(root)) !== fs.realpathSync(os.tmpdir())
    || !/^kartar-local-load-[a-f0-9]{16}$/.test(path.basename(root))) throw new Error('Owned TEMP directory required');
const schema = process.env.KARTAR_MYSQL_APPLICATION_SCHEMA;
if (!/^kartar_batch4_test_[a-f0-9]{16}$/.test(schema)
    || JSON.parse(fs.readFileSync(path.join(root, 'owned.json'))).schema !== schema) throw new Error('Owned schema required');
const tokens = JSON.parse(fs.readFileSync(path.join(root, 'synthetic-tokens.json')));
const freePort = () => new Promise((resolve, reject) => {
  const listener = net.createServer(); listener.on('error', reject);
  listener.listen(0, '127.0.0.1', () => { const port = listener.address().port; listener.close(() => resolve(port)); });
});
const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));
const percentile = (values, p) => { const sorted = [...values].sort((a, b) => a - b); return sorted[Math.min(sorted.length - 1, Math.ceil(sorted.length * p) - 1)] ?? null; };
const summary = values => ({ p50: percentile(values, .5), p95: percentile(values, .95), p99: percentile(values, .99) });
const result = { classification: 'LOCAL DEVELOPMENT MACHINE ONLY; NOT PRODUCTION CAPACITY', stages: [],
  highest_safe_stage: 0, acknowledged_unique_messages: 0, unexpected_errors: 0, expected_rate_rejections: 0,
  security_failures: 0, production_attempts: 0, provider_requests: 0,
  internal_http: { auth_requests: 0, notification_requests: 0, transport_failures: 0, rejected_responses: 0 } };
const sockets = [];
let php, runtime, httpBase, socketBase, inFlight = 0, maxInFlight = 0;
const rawFetch = global.fetch;
global.fetch = async (input, options = {}) => {
  const url = new URL(typeof input === 'string' ? input : input.url);
  if (url.hostname !== '127.0.0.1' || ![httpBase, socketBase].includes(url.origin)) {
    result.production_attempts++; throw new Error('External transport denied');
  }
  const internal = url.pathname.startsWith('/api/internal/');
  if (url.pathname.endsWith('/socket-auth')) result.internal_http.auth_requests++;
  if (url.pathname.endsWith('/chat-notification')) result.internal_http.notification_requests++;
  try {
    const response = await rawFetch(input, { ...options, redirect: 'error', signal: options.signal || AbortSignal.timeout(10000) });
    if (internal && !response.ok) result.internal_http.rejected_responses++;
    return response;
  } catch (error) {
    if (internal) result.internal_http.transport_failures++;
    throw error;
  }
};
async function request(userIndex, endpoint, method = 'GET', body, tenant = 101) {
  inFlight++; maxInFlight = Math.max(maxInFlight, inFlight);
  const start = performance.now();
  try {
    const response = await fetch(httpBase + endpoint, { method, headers: { Authorization: `Bearer ${tokens[userIndex]}`,
      'X-Karang-Taruna-ID': String(tenant), 'Content-Type': 'application/json', 'X-RateLimit-Test': 'local-load' },
      body: body === undefined ? undefined : JSON.stringify(body) });
    return { code: response.status, body: await response.json(), ms: performance.now() - start };
  } finally { inFlight--; }
}
async function authenticate(index) {
  return new Promise(resolve => {
    const socket = connect(socketBase, { transports: ['websocket'], reconnection: false, forceNew: true, timeout: 10000 });
    const timer = setTimeout(() => finish('timeout'), 10000);
    const finish = status => { clearTimeout(timer); socket.off('auth_success', success); socket.off('auth_error', failure);
      if (status !== 'ok') socket.disconnect(); resolve({ status, socket, index }); };
    const success = () => finish('ok');
    const failure = data => finish(data?.message === 'RATE_LIMITED' ? 'rate_limit' : 'auth_error');
    socket.once('auth_success', success); socket.once('auth_error', failure);
    socket.once('connect_error', error => finish(error.message === 'RATE_LIMITED' ? 'rate_limit' : 'connect_error'));
    socket.once('connect', () => socket.emit('auth', { token: tokens[index], tenant_id: 101 }));
  });
}
async function send(socket, message) {
  const start = performance.now();
  return new Promise((resolve, reject) => {
    socket.timeout(10000).emit('send_message', message, (error, ack) => {
      if (error || !ack?.success) return reject(new Error('Chat acknowledgement failed'));
      resolve({ ack, ms: performance.now() - start });
    });
  });
}
async function metrics() {
  const response = await fetch(socketBase + '/internal/health', { headers: { 'X-Internal-Secret': process.env.INTERNAL_API_SECRET } });
  if (response.status !== 200) throw new Error('Node readiness failed');
  return response.json();
}
(async () => {
  try {
    process.chdir(root); // dotenv can only inspect the owned empty TEMP root.
    if (fs.existsSync('.env') || fs.existsSync('.env.vault')) throw new Error('Local root must have no environment file');
    delete process.env.DOTENV_KEY;
    const phpPort = await freePort();
    httpBase = `http://127.0.0.1:${phpPort}`;
    process.env.KARTAR_LOCAL_HTTP_URL = httpBase + '/';
    process.env.INTERNAL_API_SECRET = randomBytes(32).toString('hex');
    Object.assign(process.env, { DB_HOST: '127.0.0.1', DB_PORT: process.env.KARTAR_MYSQL_TEST_PORT,
      DB_USER: process.env.KARTAR_MYSQL_TEST_USER, DB_PASSWORD: process.env.KARTAR_MYSQL_TEST_PASSWORD,
      DB_NAME: schema, DB_CONNECTION_LIMIT: '10', INTERNAL_API_URL: httpBase + '/api/internal/socket-auth' });
    const router = path.resolve(__dirname, '../../website/tests/_support/local_load_bootstrap.php');
    const phpLog = fs.openSync(path.join(root, 'php-server.log'), 'a');
    php = spawn(process.env.KARTAR_LOCAL_PHP_BINARY, ['-S', `127.0.0.1:${phpPort}`, '-t', path.join(root, 'public'), router],
      { cwd: root, env: process.env, windowsHide: true, stdio: ['ignore', phpLog, phpLog] });
    fs.closeSync(phpLog);
    let ready = false;
    const readinessDeadline = Date.now() + 60000;
    for (let i = 0; i < 50 && Date.now() < readinessDeadline; i++) {
      try { if ((await request(0, '/api/me')).code === 200) { ready = true; break; } } catch (_) {}
      if (php.exitCode !== null) break;
      await sleep(100);
    }
    if (!ready) throw new Error('Local PHP readiness failed');
    runtime = require('../server');
    await new Promise(resolve => runtime.server.listen(0, '127.0.0.1', resolve));
    socketBase = `http://127.0.0.1:${runtime.server.address().port}`;
    result.binding = { php: '127.0.0.1', node: runtime.server.address().address, mysql: '127.0.0.1', mysql_port: Number(process.env.DB_PORT) };
    result.php_workers = 1; // Windows built-in PHP server: no production worker model.
    const denied = await request(0, '/api/inventories', 'GET', undefined, 102);
    if (denied.code !== 403) { result.security_failures++; throw new Error('Tenant isolation failed'); }
    result.foreign_tenant_http = denied.code;
    const decisions = await Promise.all([1, 2].map(id => request(0, `/api/inventories/loans/${id}/status`, 'PATCH', { status: 'approved' })));
    result.inventory_http = decisions.map(d => d.code).sort();
    if (JSON.stringify(result.inventory_http) !== '[200,409]') throw new Error('Inventory decision invariant failed');
    // Stage authentication is paced at <=4 in flight. Never alter configured
    // production quotas. Abort the stage on >=1% rejection/error.
    for (const clients of [25, 50, 100, 250, 500, 1000]) {
      if (clients >= 500 && (os.freemem() < 1024 * 1024 * 1024 || process.memoryUsage().rss > 512 * 1024 * 1024)) {
        result.resource_stop = 'Insufficient local memory headroom'; break;
      }
      const stage = { clients, auth_attempts: 0, auth_errors: 0, rate_rejections: 0, status: 'running' };
      result.stages.push(stage);
      while (sockets.length < clients) {
        const start = sockets.length;
        const auth = await Promise.all(Array.from({ length: Math.min(4, clients - start) }, (_, i) => authenticate(start + i)));
        stage.auth_attempts += auth.length;
        for (const item of auth) {
          if (item.status === 'ok') sockets.push(item);
          else { stage.auth_errors++; if (item.status === 'rate_limit') { stage.rate_rejections++; result.expected_rate_rejections++; }
            else result.unexpected_errors++; }
        }
        if (stage.auth_errors / stage.auth_attempts >= .01) break;
      }
      if (stage.auth_errors) {
        stage.status = 'STOPPED'; stage.stop_reason = stage.rate_rejections === stage.auth_errors ? 'Configured auth rate limit reached' : 'Authentication errors >=1%';
        stage.auth_error_rate = stage.auth_errors / stage.auth_attempts;
        stage.metrics = await metrics(); break;
      }
      const latencies = []; let errors = 0;
      maxInFlight = 0; const began = performance.now();
      for (let round = 0; round < 2; round++) {
        await Promise.all(Array.from({ length: clients }, async (_, i) => {
          try { const res = await request(i, round ? '/api/inventories' : '/api/me'); latencies.push(res.ms); if (res.code !== 200) errors++; }
          catch (_) { errors++; }
        }));
        if (errors / ((round + 1) * clients) >= .01) break;
      }
      stage.http = { requests: latencies.length, errors, max_in_flight: maxInFlight,
        rps: latencies.length / ((performance.now() - began) / 1000), latency_ms: summary(latencies) };
      result.unexpected_errors += errors;
      if (errors) { stage.status = 'STOPPED'; stage.stop_reason = 'HTTP errors >=1%'; stage.metrics = await metrics(); break; }
      const chatLatencies = []; let chatErrors = 0, deliveryFailures = 0;
      await sleep(1100); // Workload pacing below existing per-user quotas.
      await Promise.all(sockets.slice(0, clients).map(async ({ socket, index }) => {
        const destination = index === 0 ? 2 : 1;
        const message = { type: 'private', receiver_id: destination, message: 'Synthetic local load', client_message_id: randomUUID() };
        const delivered = new Set();
        const listener = data => { if (data.client_message_id === message.client_message_id) delivered.add(data.id); };
        socket.on('new_message', listener);
        try {
          const first = await send(socket, message); const retry = await send(socket, message);
          chatLatencies.push(first.ms, retry.ms);
          if (first.ack.message.id !== retry.ack.message.id || !retry.ack.duplicate) throw new Error('Chat retry invariant');
          result.acknowledged_unique_messages++;
          await sleep(100);
          if (delivered.size !== 1) deliveryFailures++;
        } catch (_) { chatErrors++; }
        finally { socket.off('new_message', listener); }
      }));
      stage.chat = { unique_messages: clients - chatErrors, errors: chatErrors, missing_sender_fanout: deliveryFailures, latency_ms: summary(chatLatencies) };
      result.unexpected_errors += chatErrors;
      stage.metrics = await metrics();
      if (stage.metrics.rss_bytes > 512 * 1024 * 1024 || stage.metrics.event_loop_lag_ms?.p99 > 1000) {
        stage.status = 'STOPPED'; stage.stop_reason = 'Local memory/event-loop stability boundary'; break;
      }
      if (chatErrors || deliveryFailures || stage.metrics.authenticated_sockets !== clients) {
        stage.status = 'STOPPED'; stage.stop_reason = 'Chat/socket invariant failure'; break;
      }
      stage.status = 'PASS'; result.highest_safe_stage = clients;
      console.log(`LOCAL_LOAD_STAGE ${clients} PASS`);
    }
    result.ok = result.highest_safe_stage >= 25 && result.unexpected_errors === 0 && result.security_failures === 0
      && result.production_attempts === 0 && result.internal_http.transport_failures === 0 && result.internal_http.rejected_responses === 0;
    result.higher_stages = '250/500/1000 not attempted after stop threshold';
  } catch (_) { result.ok = false; result.label = 'local_load_runner_failed'; }
  finally {
    sockets.forEach(({ socket }) => socket.disconnect());
    if (runtime) { runtime.io.close(); runtime.server.close(); await runtime.pool.end(); }
    if (php && php.exitCode === null) {
      const ended = new Promise(resolve => php.once('exit', resolve)); php.kill(); await ended;
    }
    result.owned_http_services_stopped = !php || php.exitCode !== null || php.signalCode !== null;
    fs.writeFileSync(path.join(root, 'result.json'), JSON.stringify(result, null, 2));
    process.exitCode = result.ok ? 0 : 1;
  }
})();
