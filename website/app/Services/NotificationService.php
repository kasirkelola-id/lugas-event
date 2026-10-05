<?php

namespace App\Services;

final class NotificationService
{
    public static function sendPushNotification($tokens, $title, $body, $data = [])
    {
        return \Config\Services::pushTransport()->send($tokens, $title, $body, $data);
    }

    public static function isUnregistered(int $status, array $response): bool
    {
        return PushTransport::isUnregistered($status, $response);
    }

    public static function getTokensForTenant(int $tenantId, array $excludeUserIds = [], ?string $role = null, ?string $permission = null): array
    {
        return self::eligibleTokens($tenantId, null, $excludeUserIds, $role, $permission);
    }

    public static function getTokensForUsers(int $tenantId, array $userIds, ?string $permission = null): array
    {
        if ($userIds === []) return [];
        return self::eligibleTokens($tenantId, $userIds, [], null, $permission);
    }

    public static function getTokensForRoom(int $tenantId, int $roomId, int $senderId): array
    {
        $db = \Config\Database::connect();
        $room = $db->table('chat_rooms')->where('id', $roomId)->where('karang_taruna_id', $tenantId)->get()->getRowArray();
        if (!$room) return [];
        if ($room['type'] === 'default') return self::getTokensForTenant($tenantId, [$senderId], null, 'chat.read');
        return self::eligibleTokens($tenantId, null, [$senderId], null, 'chat.read', $roomId);
    }

    private static function eligibleTokens(int $tenantId, ?array $userIds, array $exclude, ?string $role, ?string $permission, ?int $roomId = null): array
    {
        return array_values(array_filter(array_column(self::eligibleDevices($tenantId, $userIds, $exclude, $role, $permission, $roomId, 0, null), 'fcm_token')));
    }

    public static function eligibleDevices(int $tenantId, ?array $userIds = null, array $exclude = [], ?string $role = null,
        ?string $permission = null, ?int $roomId = null, int $after = 0, ?int $limit = 100, ?int $deviceId = null): array
    {
        $db = \Config\Database::connect();
        $builder = $db->table('user_devices d')->select('d.id, d.user_id, d.fcm_token')->distinct()
            ->join('user_tokens t', 't.id = d.user_token_id AND t.user_id = d.user_id')
            ->join('users u', 'u.id = d.user_id')
            ->join('organization_members m', 'm.user_id = u.id')
            ->join('karang_taruna k', 'k.id = m.karang_taruna_id')
            ->where('m.karang_taruna_id', $tenantId)->where('k.status_aktif', 1)
            ->where('u.status_aktif', 1)->where('u.password_must_change', 0)
            ->where('m.status_aktif', 1)->where('m.approval_status', 'approved')
            ->where('t.revoked_at', null)->where('t.expires_at >', date('Y-m-d H:i:s'))
            ->where('d.fcm_token !=', '');
        if ($userIds !== null) $builder->whereIn('u.id', $userIds);
        if ($exclude !== []) $builder->whereNotIn('u.id', $exclude);
        if ($role !== null && $role !== 'semua') $builder->where('m.role_level', $role);
        if ($permission !== null) {
            $roles = array_keys(array_filter(config('Rbac')->permissions, static fn($permissions) => in_array($permission, $permissions, true)));
            if ($roles === []) return [];
            $builder->whereIn('m.role_level', $roles);
        }
        if ($roomId !== null) $builder->join('chat_room_members r', 'r.user_id = u.id')->where('r.chat_room_id', $roomId);
        if ($deviceId !== null) $builder->where('d.id', $deviceId);
        $builder->where('d.id >', $after)->orderBy('d.id', 'ASC');
        if ($limit !== null) $builder->limit(max(1, min($limit, 100)));
        return $builder->get()->getResultArray();
    }
}
