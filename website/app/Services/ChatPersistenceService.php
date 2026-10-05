<?php

namespace App\Services;

final class ChatPersistenceService
{
    public static function validId($id): bool
    {
        return is_string($id) && preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/iD', $id) === 1;
    }

    /** Permissions must be checked by the caller before a replay is returned. */
    public static function persist(array $data): array
    {
        $db = \Config\Database::connect();
        $id = $data['client_message_id'] ?? null;
        $existing = static fn() => $db->table('chats')->where('karang_taruna_id', $data['karang_taruna_id'])
            ->where('sender_id', $data['sender_id'])->where('client_message_id', $id)->get()->getRowArray();
        if ($id !== null && ($row = $existing())) return self::replay($row, $data);
        try {
            if (!(new \App\Models\ChatModel())->insert($data)) throw new \RuntimeException('Chat insert failed');
            $row = $db->table('chats')->where('id', $db->insertID())->where('karang_taruna_id', $data['karang_taruna_id'])
                ->where('sender_id', $data['sender_id'])->get()->getRowArray();
            if (!$row) throw new \RuntimeException('Persisted chat unavailable');
            return ['row' => $row, 'created' => true];
        } catch (\Throwable $error) {
            $code = (int)($db->error()['code'] ?? 0);
            if ($id !== null && in_array($code, [19, 1062], true) && ($row = $existing())) return self::replay($row, $data);
            throw $error;
        }
    }

    private static function replay(array $row, array $data): array
    {
        foreach (['type', 'message', 'chat_room_id', 'receiver_id'] as $key) {
            if ((string)($row[$key] ?? '') !== (string)($data[$key] ?? '')) throw new \DomainException('Logical message conflict');
        }
        return ['row' => $row, 'created' => false];
    }
}
