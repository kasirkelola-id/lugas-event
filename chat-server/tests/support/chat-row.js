// Stored-row fixture from a previously recorded INSERT, not a server fallback.
module.exports = function storedChatRow(pool, params, timestamp) {
  const inserts = pool.execute.mock.calls.filter(([sql]) => sql.includes('INSERT INTO chats'));
  const [sql, values] = inserts[params[0] >= 1000 ? params[0] - 1000 : inserts.length - 1];
  const group = sql.includes('chat_room_id');
  return { id: params[0], karang_taruna_id: values[0], type: group ? values[2] : values[1],
    sender_id: group ? values[3] : values[2], receiver_id: group ? null : values[3],
    chat_room_id: group ? values[1] : null, message: values[4], client_message_id: values[5], created_at_iso: timestamp };
};
