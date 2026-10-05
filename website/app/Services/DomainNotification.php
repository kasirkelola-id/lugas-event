<?php

namespace App\Services;

final class DomainNotification
{
    /** Resolve content and eligibility from the current tenant-scoped domain row. */
    public static function envelope(array $job): ?array
    {
        $db = \Config\Database::connect();
        $tenant = (int)$job['karang_taruna_id'];
        $table = ['chat' => 'chats', 'event' => 'events', 'announcement' => 'pengumuman', 'loan' => 'inventory_loans'][$job['kind']] ?? null;
        if (!$table) return null;
        $builder = $db->table($table)->where($table . '.id', $job['entity_id']);
        if ($job['kind'] === 'loan') $builder->select('inventory_loans.*, inventories.name')->join('inventories', 'inventories.id = inventory_loans.inventory_id')->where('inventories.karang_taruna_id', $tenant);
        else $builder->where($table . '.karang_taruna_id', $tenant);
        $row = $builder->get()->getRowArray();
        $organization = $db->table('karang_taruna')->where('id', $tenant)->where('status_aktif', 1)->get()->getRowArray();
        if (!$row || !$organization) return null;
        $selector = ['tenantId' => $tenant, 'userIds' => null, 'exclude' => [], 'role' => null, 'permission' => null, 'roomId' => null];
        $data = ['tenant_id' => (string)$tenant];
        if ($job['kind'] === 'chat') {
            if ($row['created_at'] < (new \App\Models\ChatModel())->getRetentionCutoff()) return null;
            $sender = $db->table('users')->where('id', $row['sender_id'])->get()->getRowArray();
            if (!$sender) return null;
            $selector['permission'] = 'chat.read';
            $data += ['chat_id' => (string)$row['id'], 'sender_id' => (string)$row['sender_id']];
            if ($row['type'] === 'private') {
                $selector['userIds'] = [(int)$row['receiver_id']];
                $title = 'Pesan dari ' . $sender['nama_lengkap'];
                $data['type'] = 'private_chat';
            } elseif ($row['type'] === 'group') {
                $room = $db->table('chat_rooms')->where('id', $row['chat_room_id'])->where('karang_taruna_id', $tenant)->get()->getRowArray();
                if (!$room) return null;
                $selector['exclude'] = [(int)$row['sender_id']];
                $selector['roomId'] = $room['type'] === 'custom' ? (int)$room['id'] : null;
                $title = 'Grup ' . $room['name'] . ' - ' . $sender['nama_lengkap'];
                $data += ['type' => 'group_chat', 'room_id' => (string)$room['id']];
            } else return null;
            $body = mb_substr($row['message'], 0, 100);
        } elseif ($job['kind'] === 'event') {
            if (!in_array((string)$row['status_aktif'], ['aktif', '1'], true)) return null;
            $selector['exclude'] = [(int)$row['dibuat_oleh']]; $selector['permission'] = 'event.view';
            $title = 'Event Baru: ' . $organization['nama_organisasi']; $body = mb_substr($row['nama_acara'], 0, 100);
            $data += ['type' => 'event', 'event_id' => (string)$row['id']];
        } elseif ($job['kind'] === 'announcement') {
            if ((int)$row['status_aktif'] !== 1) return null;
            $selector['exclude'] = [(int)$row['dibuat_oleh']]; $selector['permission'] = 'announcement.view'; $selector['role'] = $row['target_role'];
            $title = 'Pengumuman: ' . $organization['nama_organisasi']; $body = mb_substr($row['judul'], 0, 100);
            $data += ['type' => 'announcement', 'announcement_id' => (string)$row['id']];
        } else {
            if ($row['status'] !== $job['action']) return null;
            $selector['userIds'] = [(int)$row['user_id']]; $selector['permission'] = 'inventory.view';
            $status = ['approved' => 'disetujui', 'rejected' => 'ditolak', 'returned' => 'dikembalikan'][$row['status']] ?? null;
            if (!$status) return null;
            $title = 'Peminjaman Barang: ' . $organization['nama_organisasi']; $body = 'Status peminjaman Anda untuk barang ' . $row['name'] . ' telah ' . $status . '.';
            $data += ['type' => 'inventory_loan', 'loan_id' => (string)$row['id'], 'status' => $row['status']];
        }
        return ['selector' => $selector, 'title' => $title, 'body' => $body, 'data' => $data];
    }

    public static function devices(array $envelope, int $after = 0, ?int $deviceId = null): array
    {
        return NotificationService::eligibleDevices(...($envelope['selector'] + ['after' => $after, 'limit' => 100, 'deviceId' => $deviceId]));
    }
}
