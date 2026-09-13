<?php

namespace App\Models;

use CodeIgniter\Model;

class ChatModel extends Model
{
    protected $table            = 'chats';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'karang_taruna_id',
        'type',
        'chat_room_id',
        'sender_id',
        'receiver_id',
        'message',
        'created_at'
    ];

    protected $useTimestamps = false; // We use created_at default current_timestamp

    const RETENTION_DAYS = 30;

    public function getRetentionCutoff(?\DateTimeInterface $now = null): string
    {
        $now = $now ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return $now->setTimezone(new \DateTimeZone('UTC'))
            ->modify('-' . self::RETENTION_DAYS . ' days')
            ->format('Y-m-d H:i:s');
    }

    public function getRoomChats($roomId, $limit = 50, $beforeId = null)
    {
        $builder = $this->select('chats.*, users.nama_lengkap, users.role_level, users.profile_photo')
                    ->join('users', 'users.id = chats.sender_id')
                    ->where('chats.chat_room_id', $roomId);

        if ($beforeId) {
            $builder->where('chats.id <', $beforeId);
        }

        // 30-day retention filter
        $builder->where('chats.created_at >=', $this->getRetentionCutoff());

        return $builder->orderBy('chats.id', 'DESC')
                       ->findAll($limit);
    }

    public function getPrivateChats($karangTarunaId, $user1Id, $user2Id, $limit = 50, $beforeId = null)
    {
        $db = \Config\Database::connect();

        $sql = "
            SELECT chats.*, users.nama_lengkap, users.role_level, users.profile_photo
            FROM (
                SELECT * FROM (
                    SELECT * FROM chats
                    WHERE karang_taruna_id = ? AND type = 'private' AND sender_id = ? AND receiver_id = ? AND created_at >= ? " . ($beforeId ? "AND id < ? " : "") . "
                    ORDER BY id DESC LIMIT ?
                ) as branch1
                UNION ALL
                SELECT * FROM (
                    SELECT * FROM chats
                    WHERE karang_taruna_id = ? AND type = 'private' AND sender_id = ? AND receiver_id = ? AND created_at >= ? " . ($beforeId ? "AND id < ? " : "") . "
                    ORDER BY id DESC LIMIT ?
                ) as branch2
            ) AS chats
            JOIN users ON users.id = chats.sender_id
            ORDER BY chats.id DESC LIMIT ?
        ";

        $cutoff = $this->getRetentionCutoff();
        $params = [];

        // First branch
        $params[] = $karangTarunaId;
        $params[] = $user1Id;
        $params[] = $user2Id;
        $params[] = $cutoff;
        if ($beforeId) {
            $params[] = $beforeId;
        }
        $params[] = (int)$limit;

        // Second branch
        $params[] = $karangTarunaId;
        $params[] = $user2Id;
        $params[] = $user1Id;
        $params[] = $cutoff;
        if ($beforeId) {
            $params[] = $beforeId;
        }
        $params[] = (int)$limit;

        // Final limit
        $params[] = (int)$limit;

        return $db->query($sql, $params)->getResultArray();
    }

    public function getPrivateChatContacts($karangTarunaId, $userId, $limit = 500, $offset = 0)
    {
        $db = \Config\Database::connect();

        $sql = "SELECT
                    u.id as contact_id,
                    u.nama_lengkap as contact_name,
                    u.profile_photo as contact_photo,
                    u.role_level as contact_role,
                    m.message as last_message,
                    m.created_at as last_message_time
                FROM users u
                JOIN organization_members om ON u.id = om.user_id AND om.karang_taruna_id = ? AND om.status_aktif = 1
                JOIN (
                    SELECT
                        CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END as contact_id,
                        MAX(id) as max_id
                    FROM chats
                    WHERE type = 'private'
                      AND karang_taruna_id = ?
                      AND (sender_id = ? OR receiver_id = ?)
                      AND created_at >= ?
                    GROUP BY CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END
                ) last_chat ON u.id = last_chat.contact_id
                JOIN chats m ON m.id = last_chat.max_id
                ORDER BY m.id DESC
                LIMIT ? OFFSET ?";

        $cutoff = $this->getRetentionCutoff();
        $query = $db->query($sql, [$karangTarunaId, $userId, $karangTarunaId, $userId, $userId, $cutoff, $userId, (int)$limit, (int)$offset]);
        return $query->getResultArray();
    }
}
