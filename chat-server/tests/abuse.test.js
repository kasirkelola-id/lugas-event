const { WindowLimiter } = require('../abuse');
test('fixed windows expire and key capacity fails closed', () => {
  const limiter = new WindowLimiter(2);
  expect(limiter.consume('one', 2, 1000, 0)).toBe(true);
  expect(limiter.consume('one', 2, 1000, 1)).toBe(true);
  expect(limiter.consume('one', 2, 1000, 2)).toBe(false);
  expect(limiter.consume('two', 1, 1000, 2)).toBe(true);
  expect(limiter.consume('three', 1, 1000, 3)).toBe(false);
  expect(limiter.consume('three', 1, 1000, 1002)).toBe(true);
  expect(limiter.windows.size).toBe(1);
});
