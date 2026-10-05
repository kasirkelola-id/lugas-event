const crypto = require('crypto');

function resolveSecret(value, environment) {
  if (typeof value !== 'string' || !value.trim()) return null;
  if (!['development', 'test'].includes(environment) && value === 'default_internal_secret_for_dev') return null;
  return value;
}

function acceptsSecret(provided, configured) {
  if (typeof provided !== 'string' || !provided || !configured) return false;
  const a = Buffer.from(provided);
  const b = Buffer.from(configured);
  return a.length === b.length && crypto.timingSafeEqual(a, b);
}

module.exports = { resolveSecret, acceptsSecret };
