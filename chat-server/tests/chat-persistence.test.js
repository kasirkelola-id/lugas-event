const { persistChat, validMessageId } = require('../chat-persistence');
const memoryChatStore = require('./support/chat-store');
const message = { karang_taruna_id: 101, sender_id: 10, receiver_id: 20, type: 'private',
  message: 'Synthetic', client_message_id: '01234567-89ab-4cde-8f01-23456789abcd' };

test('lost acknowledgement and concurrent replay return one stored row', async () => {
  const store = memoryChatStore();
  const results = await Promise.all([persistChat(store, message), persistChat(store, message)]);
  expect(store.rows).toHaveLength(1);
  expect(results.map(result => result.created).sort()).toEqual([false, true]);
  expect(results[0].row).toEqual(results[1].row);
});
test('same client ID has independent tenant and sender scopes', async () => {
  const store = memoryChatStore();
  for (const data of [message, { ...message, karang_taruna_id: 102 }, { ...message, sender_id: 11 }]) {
    expect((await persistChat(store, data)).created).toBe(true);
  }
  expect(store.rows).toHaveLength(3);
});
test('conflicting retry never changes the stored row', async () => {
  const store = memoryChatStore(); await persistChat(store, message);
  await expect(persistChat(store, { ...message, message: 'Different' })).rejects.toThrow('Logical message conflict');
  expect(store.rows[0].message).toBe('Synthetic');
});
test('driver or missing canonical-row failure cannot acknowledge success', async () => {
  const driver = { execute: async () => { throw new Error('Synthetic driver'); } };
  await expect(persistChat(driver, message)).rejects.toThrow();
  const missing = { execute: async sql => sql.startsWith('INSERT') ? [{ insertId: 1 }] : [[]] };
  await expect(persistChat(missing, message)).rejects.toThrow('Persisted chat unavailable');
});
test('UUID validation rejects arrays, junk suffix and overlength', () => {
  expect(validMessageId(message.client_message_id)).toBe(true);
  for (const bad of [[], null, `${message.client_message_id}x`, `${message.client_message_id}\n`, 'x'.repeat(100)]) expect(validMessageId(bad)).toBe(false);
});
