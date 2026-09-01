<?php

namespace App\Controllers\Api;

use App\Models\ChatModel;
use App\Models\UserModel;
use App\Services\AuthService;

class ChatController extends BaseApiController
{
    public function getGroupChats()
    {
        $user = AuthService::getUser();
        if (!$user) {
            return $this->sendError('Unauthenticated', null, 401);
        }

        $chatModel = new ChatModel();
        $limit = $this->request->getVar('limit') ?? 50;
        
        $chats = $chatModel->getGroupChats($user['karang_taruna_id'], $limit);

        return $this->sendSuccess('Berhasil mengambil data grup chat', $chats);
    }

    public function getPrivateChats($receiverId)
    {
        $user = AuthService::getUser();
        if (!$user) {
            return $this->sendError('Unauthenticated', null, 401);
        }

        $userModel = new UserModel();
        $receiver = $userModel->find($receiverId);
        
        if (!$receiver || $receiver['karang_taruna_id'] != $user['karang_taruna_id']) {
            return $this->sendError('Pengguna tidak ditemukan atau di luar Karang Taruna Anda', null, 404);
        }

        $chatModel = new ChatModel();
        $limit = $this->request->getVar('limit') ?? 50;
        
        $chats = $chatModel->getPrivateChats($user['karang_taruna_id'], $user['id'], $receiverId, $limit);

        return $this->sendSuccess('Berhasil mengambil riwayat private chat', $chats);
    }
}
