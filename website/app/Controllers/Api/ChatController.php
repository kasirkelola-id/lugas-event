<?php

namespace App\Controllers\Api;

use App\Models\ChatModel;
use App\Models\UserModel;
use App\Models\ChatRoomModel;
use App\Models\ChatRoomMemberModel;
use App\Services\AuthService;

class ChatController extends BaseApiController
{
    public function getRooms()
    {
        $tenantId = AuthService::getTenantId();
        $userId = AuthService::getGlobalUserId();
        if (!$tenantId) {
            return $this->sendError('Unauthenticated', null, 401);
        }

        $roomModel = new ChatRoomModel();
        $rooms = $roomModel->getRoomsForUser($tenantId, $userId);

        return $this->sendSuccess('Berhasil mengambil daftar grup chat', $rooms);
    }

    public function createRoom()
    {
        $tenantId = AuthService::getTenantId();
        $userId = AuthService::getGlobalUserId();
        $role = AuthService::getRole();
        if (!$tenantId) {
            return $this->sendError('Unauthenticated', null, 401);
        }

        if (!AuthService::can('chat.manage')) {
            return $this->sendError('Akses ditolak', null, 403);
        }

        $name = $this->request->getVar('name');
        $memberIds = $this->request->getVar('members'); // array of user IDs

        if (empty($name)) {
            return $this->sendError('Nama grup tidak boleh kosong', null, 400);
        }

        $roomModel = new ChatRoomModel();
        
        $roomId = $roomModel->insert([
            'karang_taruna_id' => $tenantId,
            'name' => $name,
            'type' => 'custom',
            'created_by' => $userId,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        if (is_array($memberIds) && count($memberIds) > 0) {
            $memberModel = new ChatRoomMemberModel();
            
            // Add creator implicitly
            if (!in_array($userId, $memberIds)) {
                $memberIds[] = $userId;
            }

            foreach ($memberIds as $mId) {
                $memberModel->insert([
                    'chat_room_id' => $roomId,
                    'user_id' => $mId
                ]);
            }
        }

        return $this->sendSuccess('Grup berhasil dibuat', ['id' => $roomId]);
    }

    public function deleteRoom($roomId)
    {
        $tenantId = AuthService::getTenantId();
        $role = AuthService::getRole();
        if (!$tenantId) {
            return $this->sendError('Unauthenticated', null, 401);
        }

        $roomModel = new ChatRoomModel();
        $room = $roomModel->find($roomId);

        if (!$room || $room['karang_taruna_id'] != $tenantId) {
            return $this->sendError('Grup tidak ditemukan', null, 404);
        }

        if ($room['type'] === 'default') {
            return $this->sendError('Grup default tidak dapat dihapus', null, 403);
        }

        if (!AuthService::can('chat.manage')) {
            return $this->sendError('Anda tidak memiliki izin menghapus grup ini', null, 403);
        }

        $roomModel->delete($roomId);

        return $this->sendSuccess('Grup berhasil dihapus', null);
    }

    public function getRoomChats($roomId)
    {
        $tenantId = AuthService::getTenantId();
        $userId = AuthService::getGlobalUserId();
        if (!$tenantId) {
            return $this->sendError('Unauthenticated', null, 401);
        }

        // Validate if user has access to this room
        $roomModel = new ChatRoomModel();
        $room = $roomModel->find($roomId);
        
        if (!$room || $room['karang_taruna_id'] != $tenantId) {
            return $this->sendError('Grup tidak ditemukan', null, 404);
        }

        if ($room['type'] === 'custom') {
            $memberModel = new ChatRoomMemberModel();
            $isMember = $memberModel->where('chat_room_id', $roomId)->where('user_id', $userId)->first();
            if (!$isMember) {
                return $this->sendError('Anda bukan anggota grup ini', null, 403);
            }
        }

        $chatModel = new ChatModel();
        $limit = $this->request->getVar('limit') ?? 50;
        $beforeId = $this->request->getVar('before_id');
        
        $chats = $chatModel->getRoomChats($roomId, $limit, $beforeId);
        
        // Reverse array because it was fetched DESC and client expects ASC
        $chats = array_reverse($chats);

        // Add sender_photo_url to each chat
        foreach ($chats as &$c) {
            $c['sender_photo_url'] = !empty($c['profile_photo']) ? base_url($c['profile_photo']) : null;
            unset($c['profile_photo']); // Don't expose raw DB path
        }

        return $this->sendSuccess('Berhasil mengambil data grup chat', $chats);
    }

    public function getPrivateChats($receiverId)
    {
        $tenantId = AuthService::getTenantId();
        $userId = AuthService::getGlobalUserId();
        if (!$tenantId) {
            return $this->sendError('Unauthenticated', null, 401);
        }

        $userModel = new UserModel();
        $receiver = $userModel->find($receiverId);
        
        if (!$receiver || $receiver['karang_taruna_id'] != $tenantId) {
            return $this->sendError('Pengguna tidak ditemukan atau di luar Karang Taruna Anda', null, 404);
        }

        $chatModel = new ChatModel();
        $limit = $this->request->getVar('limit') ?? 50;
        $beforeId = $this->request->getVar('before_id');
        
        $chats = $chatModel->getPrivateChats($tenantId, $userId, $receiverId, $limit, $beforeId);
        
        // Reverse array because it was fetched DESC and client expects ASC
        $chats = array_reverse($chats);

        // Add sender_photo_url to each chat
        foreach ($chats as &$c) {
            $c['sender_photo_url'] = !empty($c['profile_photo']) ? base_url($c['profile_photo']) : null;
            unset($c['profile_photo']);
        }

        return $this->sendSuccess('Berhasil mengambil riwayat private chat', $chats);
    }

    public function sendMessage()
    {
        $tenantId = AuthService::getTenantId();
        $userId = AuthService::getGlobalUserId();
        $user = AuthService::getUser();
        if (!$tenantId) {
            return $this->sendError('Unauthenticated', null, 401);
        }

        $type = $this->request->getVar('type'); // 'group' or 'private'
        $roomId = $this->request->getVar('chat_room_id');
        $receiverId = $this->request->getVar('receiver_id');
        $message = $this->request->getVar('message');

        if (empty($message)) {
            return $this->sendError('Pesan tidak boleh kosong', null, 400);
        }

        $chatModel = new ChatModel();
        
        $data = [
            'karang_taruna_id' => $tenantId,
            'sender_id' => $userId,
            'message' => $message,
            'type' => $type,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        if ($type === 'group') {
            // Validate room
            $roomModel = new ChatRoomModel();
            $room = $roomModel->where('karang_taruna_id', $tenantId)->find($roomId);
            if (!$room) {
                return $this->sendError('Grup tidak ditemukan', null, 404);
            }
            $data['chat_room_id'] = $roomId;
            // TODO: Trigger Notification to all members
            $this->sendGroupNotification($roomId, $user['nama_lengkap'], $message, clone (object)$data);
        } else {
            // Validate receiver
            $userModel = new UserModel();
            $receiver = $userModel->where('karang_taruna_id', $tenantId)->find($receiverId);
            if (!$receiver) {
                return $this->sendError('Pengguna tidak ditemukan', null, 404);
            }
            $data['receiver_id'] = $receiverId;
            // TODO: Trigger Notification to receiver
            $this->sendPrivateNotification($receiverId, $user['nama_lengkap'], $message, clone (object)$data);
        }

        $chatModel->insert($data);

        return $this->sendSuccess('Pesan terkirim', $data);
    }

    private function sendGroupNotification($roomId, $senderName, $message, $chatData)
    {
        $roomModel = new ChatRoomModel();
        $room = $roomModel->find($roomId);
        if (!$room) return;

        $db = \Config\Database::connect();
        
        if ($room['type'] === 'default') {
            // Get all user IDs in this karang_taruna
            $users = $db->table('users')->where('karang_taruna_id', $room['karang_taruna_id'])->get()->getResultArray();
            $userIds = array_column($users, 'id');
        } else {
            // Get from chat_room_members
            $members = $db->table('chat_room_members')->where('chat_room_id', $roomId)->get()->getResultArray();
            $userIds = array_column($members, 'user_id');
        }

        // Get devices
        if (!empty($userIds)) {
            $devices = $db->table('user_devices')->whereIn('user_id', $userIds)->get()->getResultArray();
            $tokens = array_filter(array_column($devices, 'fcm_token'));
            if (!empty($tokens)) {
                $title = "Grup " . $room['name'] . " - " . $senderName;
                \App\Services\NotificationService::sendPushNotification($tokens, $title, $message, ['type' => 'group_chat', 'room_id' => (string)$roomId]);
            }
        }
    }

    private function sendPrivateNotification($receiverId, $senderName, $message, $chatData)
    {
        $db = \Config\Database::connect();
        $devices = $db->table('user_devices')->where('user_id', $receiverId)->get()->getResultArray();
        $tokens = array_filter(array_column($devices, 'fcm_token'));
        
        if (!empty($tokens)) {
            $title = "Pesan dari " . $senderName;
            \App\Services\NotificationService::sendPushNotification($tokens, $title, $message, ['type' => 'private_chat', 'sender_id' => (string)$chatData['sender_id']]);
        }
    }
}
