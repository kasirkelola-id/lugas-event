const { resolveSecret, acceptsSecret } = require('../internal-secret');
const { execFileSync } = require('child_process');
const path = require('path');

test('actual production server refuses known fallback before opening DB or listener', () => {
  try {
    execFileSync(process.execPath, [path.join(__dirname, '../server.js')], {
      cwd: __dirname,
      env: { PATH: process.env.PATH, NODE_ENV: 'production', DB_HOST: '127.0.0.1',
        DB_USER: 'synthetic', DB_PASSWORD: '', DB_NAME: 'synthetic',
        INTERNAL_API_SECRET: 'default_internal_secret_for_dev',
        INTERNAL_API_URL: 'http://127.0.0.1/api/internal/socket-auth' },
      timeout: 3000, stdio: 'pipe',
    });
    throw new Error('Unsafe server unexpectedly started');
  } catch (error) {
    expect(error.status).toBe(1);
    expect(error.stderr.toString()).toContain('Internal service secret is not configured safely');
  }
});

test('production internal secret rejects missing, empty and known fallback', () => {
  for (const value of [undefined, '', ' ', 'default_internal_secret_for_dev']) {
    expect(resolveSecret(value, 'production')).toBeNull();
  }
  const secret = resolveSecret('synthetic-private-value', 'production');
  expect(acceptsSecret('', secret)).toBe(false);
  expect(acceptsSecret('synthetic-wrong-value', secret)).toBe(false);
  expect(acceptsSecret('synthetic-private-value', secret)).toBe(true);
});
