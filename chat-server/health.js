const { monitorEventLoopDelay } = require('node:perf_hooks');

function createHealth(pool, io, authenticatedCount, { timeoutMs = 2000, cacheMs = 5000 } = {}) {
  const lag = monitorEventLoopDelay({ resolution: 20 });
  let inFlight = null;
  let lastChecked = null;
  let lastResult = false;
  let monitoring = false;
  function start() { if (!monitoring) { monitoring = true; lag.enable(); } }
  function stop() { monitoring = false; lag.disable(); lag.reset(); }
  async function databaseReady() {
    if (lastChecked !== null && Date.now() - lastChecked < cacheMs) return lastResult;
    if (!inFlight) {
      // One underlying check stays shared even if an HTTP caller times out.
      inFlight = Promise.resolve().then(() => pool.execute('SELECT 1 AS healthy'))
        .then(() => true, () => false).then(result => {
          lastResult = result; lastChecked = Date.now(); inFlight = null; return result;
        });
    }
    let timer;
    try {
      return await Promise.race([inFlight, new Promise(resolve => { timer = setTimeout(() => resolve(false), timeoutMs); })]);
    } finally { clearTimeout(timer); }
  }
  function poolCounts() {
    // Read-only adapter for the installed mysql2 PromisePool. Fail to unknown if
    // an upgrade changes internals; never expose connection/config objects.
    const core = pool.pool;
    const count = key => Number.isSafeInteger(core?.[key]?.length) ? core[key].length : null;
    const total = count('_allConnections'); const free = count('_freeConnections');
    return { connections: total, in_use: total === null || free === null ? null : Math.max(0, total - free),
      queue_pending: count('_connectionQueue') };
  }
  function snapshot(ready) {
    const ms = value => Number.isFinite(value) ? Math.round(value / 1e6 * 1000) / 1000 : null;
    const observed = monitoring && lag.count > 0;
    const result = { status: ready ? 'ready' : 'degraded', database_ready: ready,
      database_last_checked_at: lastChecked === null ? null : new Date(lastChecked).toISOString(),
      uptime_seconds: Math.floor(process.uptime()), rss_bytes: process.memoryUsage().rss,
      sockets: io.sockets.sockets.size, engine_connections: io.engine.clientsCount,
      authenticated_sockets: authenticatedCount(), database_pool: poolCounts(),
      event_loop_lag_ms: observed ? { p50: ms(lag.percentile(50)), p95: ms(lag.percentile(95)), p99: ms(lag.percentile(99)), max: ms(lag.max) } : null };
    lag.reset(); // Percentiles cover the interval since the prior sample, not all uptime.
    return result;
  }
  return { start, stop, databaseReady, snapshot };
}

module.exports = { createHealth };
