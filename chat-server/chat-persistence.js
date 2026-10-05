'use strict';

const validMessageId = value => typeof value === 'string' && /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(value) && value.length === 36;

async function persistChat(pool, data) {
  const select = async (id) => {
    const [rows] = await pool.execute(
      `SELECT chats.*, DATE_FORMAT(created_at, '%Y-%m-%dT%TZ') AS created_at_iso FROM chats WHERE ${id ? 'id = ?' : 'client_message_id = ?'} AND karang_taruna_id = ? AND sender_id = ?`,
      [id || data.client_message_id, data.karang_taruna_id, data.sender_id]);
    return rows[0];
  };
  let row, created = true;
  try {
    const group = data.type === 'group';
    const [result] = await pool.execute(
      `INSERT INTO chats (karang_taruna_id, ${group ? 'chat_room_id, type, sender_id' : 'type, sender_id, receiver_id'}, message, client_message_id, created_at) VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())`,
      group ? [data.karang_taruna_id, data.chat_room_id, data.type, data.sender_id, data.message, data.client_message_id]
        : [data.karang_taruna_id, data.type, data.sender_id, data.receiver_id, data.message, data.client_message_id]);
    row = await select(result.insertId);
  } catch (error) {
    if (error.code !== 'ER_DUP_ENTRY' || !data.client_message_id) throw error;
    row = await select(null);
    created = false;
  }
  if (!row || !row.id || !row.created_at_iso) throw new Error('Persisted chat unavailable');
  for (const key of ['type', 'message', 'chat_room_id', 'receiver_id']) {
    if (String(row[key] ?? '') !== String(data[key] ?? '')) throw new Error('Logical message conflict');
  }
  return { row, created };
}

module.exports = { persistChat, validMessageId };
