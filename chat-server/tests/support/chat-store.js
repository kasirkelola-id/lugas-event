module.exports = function memoryChatStore() {
  const rows = [];
  return {
    rows,
    async execute(sql, args) {
      if (sql.startsWith('INSERT INTO chats')) {
        const group = sql.includes('chat_room_id');
        const row = { id: rows.length + 1, karang_taruna_id: args[0], type: group ? args[2] : args[1],
          sender_id: group ? args[3] : args[2], receiver_id: group ? null : args[3],
          chat_room_id: group ? args[1] : null, message: args[4], client_message_id: args[5],
          created_at_iso: '2026-10-05T00:00:00Z' };
        if (row.client_message_id && rows.some(other => other.karang_taruna_id === row.karang_taruna_id &&
            other.sender_id === row.sender_id && other.client_message_id === row.client_message_id)) {
          throw Object.assign(new Error('Synthetic duplicate'), { code: 'ER_DUP_ENTRY' });
        }
        rows.push(row); return [{ insertId: row.id }];
      }
      const field = sql.includes('WHERE id = ?') ? 'id' : 'client_message_id';
      return [rows.filter(row => row[field] === args[0] && row.karang_taruna_id === args[1] && row.sender_id === args[2])];
    }
  };
};
