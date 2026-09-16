const { execSync } = require('child_process');

describe('Server Environment Hardening', () => {
  it('should fail to start if required environment variables are missing', () => {
    try {
      const path = require('path');
      execSync(`node "${path.join(__dirname, '../server.js')}"`, { cwd: __dirname, env: { PATH: process.env.PATH }, stdio: 'pipe' });
      fail('Server started despite missing env variables');
    } catch (error) {
      expect(error.stdout.toString() + error.stderr.toString()).toContain('FATAL ERROR: Environment variable');
    }
  });
});
