// Process-local quotas: TTL pruning and a hard key bound prevent limiter growth.
// Deployments with multiple Node processes need a shared limiter before scaling.
class WindowLimiter {
  constructor(maxKeys = 20000) { this.windows = new Map(); this.maxKeys = maxKeys; }
  consume(key, limit, milliseconds, now = Date.now()) {
    let state = this.windows.get(key);
    if (!state || state.until <= now) {
      for (const [name, entry] of this.windows) if (entry.until <= now) this.windows.delete(name);
      if (!this.windows.has(key) && this.windows.size >= this.maxKeys) return false;
      state = { until: now + milliseconds, count: 0 };
      this.windows.set(key, state);
    }
    if (state.count >= limit) return false;
    state.count++;
    return true;
  }
  clear() { this.windows.clear(); }
}
module.exports = { WindowLimiter };
