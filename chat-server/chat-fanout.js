'use strict';

// A trusted job supplies only a row ID; destinations/content come from the DB.
async function fanoutChat(pool, io, chatId, currentAuthorization, privateUserRoom, eligibleMember) {
  const [rows] = await pool.execute(
    `SELECT c.*, u.nama_lengkap, m.role_level, DATE_FORMAT(c.created_at, '%Y-%m-%dT%TZ') AS created_at_iso
     FROM chats c JOIN users u ON u.id = c.sender_id
     JOIN organization_members m ON m.user_id = c.sender_id AND m.karang_taruna_id = c.karang_taruna_id WHERE c.id = ?`, [chatId]);
  const row = rows[0];
  if (!row || !row.created_at_iso) return false;
  const tenant = Number(row.karang_taruna_id);
  const candidates = new Set();
  let allowed = null;
  if (row.type === 'private') {
    for (const user of [row.sender_id, row.receiver_id]) {
      if (!await eligibleMember(user, tenant)) continue;
      for (const id of io.sockets.adapter.rooms.get(privateUserRoom(tenant, user)) || []) candidates.add(id);
    }
  } else if (row.type === 'group') {
    const [rooms] = await pool.execute('SELECT * FROM chat_rooms WHERE id = ? AND karang_taruna_id = ?', [row.chat_room_id, tenant]);
    if (!rooms[0]) return false;
    if (rooms[0].type === 'custom') {
      const [members] = await pool.execute(
        `SELECT r.user_id FROM chat_room_members r JOIN organization_members m ON m.user_id = r.user_id
         JOIN users u ON u.id = r.user_id JOIN karang_taruna k ON k.id = m.karang_taruna_id
         WHERE r.chat_room_id = ? AND m.karang_taruna_id = ? AND m.status_aktif = 1 AND m.approval_status = 'approved'
           AND u.status_aktif = 1 AND k.status_aktif = 1`, [row.chat_room_id, tenant]);
      allowed = new Set(members.map(member => Number(member.user_id)));
    }
    for (const id of io.sockets.adapter.rooms.get(`room_${row.chat_room_id}`) || []) candidates.add(id);
  } else return false;
  const payload = { ...row, created_at: row.created_at_iso, sender_photo_url: null };
  delete payload.created_at_iso;
  for (const id of candidates) {
    const socket = io.sockets.sockets.get(id);
    if (!socket || Number(socket.karangTarunaId) !== tenant || (allowed && !allowed.has(Number(socket.userId)))) continue;
    if (!await currentAuthorization(socket) || !socket.permissions?.includes('chat.read')) continue;
    io.to(id).emit('new_message', payload);
  }
  return true;
}

module.exports = { fanoutChat };
