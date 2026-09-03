<?php

namespace App\Controllers\Api;

use App\Services\AuthService;
use CodeIgniter\API\ResponseTrait;

class InternalApiController extends BaseApiController
{
    use ResponseTrait;

    public function socketAuth()
    {
        // IP Restriction: Ensure this is only called internally (e.g. from localhost where Node.js is)
        // In production, might want to check against specific private IPs or use a shared secret.
        $clientIp = $this->request->getIPAddress();
        $allowedIps = ['127.0.0.1', '::1'];
        
        // For development/testing flexibility, we can check for an internal secret header if IP isn't loopback
        $internalSecret = $this->request->getHeaderLine('X-Internal-Secret');
        $validSecret = getenv('INTERNAL_API_SECRET') ?: 'default_internal_secret_for_dev';

        // Timing-safe comparison for the secret
        $isValidSecret = hash_equals($validSecret, $internalSecret);

        if (!in_array($clientIp, $allowedIps) && !$isValidSecret) {
            return $this->sendError('Forbidden: External access denied to internal API', null, 403);
        }

        // At this point, AuthFilter has successfully validated the Bearer token 
        // AND resolved the active tenant via X-Karang-Taruna-ID header.
        $user = AuthService::getUser();
        
        if (!$user) {
            return $this->sendError('Unauthenticated', null, 401);
        }

        $role = AuthService::getRole();
        $config = config('Rbac');
        $permissions = $config->permissions[$role] ?? [];

        return $this->sendSuccess('Authenticated', [
            'user_id' => (int)$user['id'],
            'nama_lengkap' => $user['nama_lengkap'],
            'karang_taruna_id' => (int)$user['karang_taruna_id'],
            'role_level' => $role,
            'permissions' => $permissions,
            'profile_photo_url' => !empty($user['profile_photo']) ? base_url($user['profile_photo']) : null
        ]);
    }

    public function chatNotification()
    {
        $clientIp = $this->request->getIPAddress();
        $allowedIps = ['127.0.0.1', '::1'];
        $internalSecret = $this->request->getHeaderLine('X-Internal-Secret');
        $validSecret = getenv('INTERNAL_API_SECRET') ?: 'default_internal_secret_for_dev';
        $isValidSecret = hash_equals($validSecret, $internalSecret);

        if (!in_array($clientIp, $allowedIps) && !$isValidSecret) {
            return $this->sendError('Forbidden', null, 403);
        }

        $rawInput = $this->request->getJSON(true) ?? $this->request->getRawInput();
        $chatId = $rawInput['chat_id'] ?? null;
        if (!$chatId) {
            return $this->sendError('chat_id required', null, 400);
        }

        $db = \Config\Database::connect();
        $chat = $db->table('chats')->where('id', $chatId)->get()->getRowArray();
        
        if (!$chat) {
            return $this->sendError('Chat not found', null, 404);
        }

        $tenantId = (int)$chat['karang_taruna_id'];
        $senderId = (string)$chat['sender_id'];
        $type = $chat['type']; // 'private' or 'group'
        
        $sender = $db->table('users')->where('id', $senderId)->get()->getRowArray();
        $senderName = $sender ? $sender['nama_lengkap'] : 'User';

        $tokens = [];
        $title = '';

        if ($type === 'private') {
            $receiverId = (string)$chat['receiver_id'];
            $devices = $db->table('user_devices')->where('user_id', $receiverId)->get()->getResultArray();
            $tokens = array_values(array_unique(array_filter(array_column($devices, 'fcm_token'))));
            $title = "Pesan dari " . $senderName;
        } else if ($type === 'group') {
            $roomId = $chat['chat_room_id'];
            $room = $db->table('chat_rooms')->where('id', $roomId)->get()->getRowArray();
            if ($room) {
                if ($room['type'] === 'umum') {
                    $tokens = \App\Services\NotificationService::getTokensForTenant($tenantId, [$senderId]);
                } else {
                    // Custom room
                    $members = $db->table('chat_room_members')->where('chat_room_id', $roomId)->get()->getResultArray();
                    $memberIds = array_column($members, 'user_id');
                    // Exclude sender
                    $memberIds = array_filter($memberIds, fn($id) => (string)$id !== $senderId);
                    
                    if (!empty($memberIds)) {
                        $devices = $db->table('user_devices')->whereIn('user_id', $memberIds)->get()->getResultArray();
                        $tokens = array_values(array_unique(array_filter(array_column($devices, 'fcm_token'))));
                    }
                }
                $title = "Grup " . $room['name'] . " - " . $senderName;
            }
        }

        if (!empty($tokens)) {
            $body = mb_substr($chat['message'], 0, 100);
            $payload = [
                'type' => $type === 'private' ? 'private_chat' : 'group_chat',
                'tenant_id' => (string)$tenantId,
                'chat_id' => (string)$chatId,
                'sender_id' => $senderId
            ];
            if ($type === 'group') {
                $payload['room_id'] = (string)$chat['chat_room_id'];
            }

            \App\Services\NotificationService::sendPushNotification($tokens, $title, $body, $payload);
        }

        return $this->sendSuccess('Notification processed');
    }
}
