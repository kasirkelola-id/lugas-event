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

    public function getRoomChats($roomId, $limit = 50, $beforeId = null)
    {
        $builder = $this->select('chats.*, users.nama_lengkap, users.role_level, users.profile_photo')
                    ->join('users', 'users.id = chats.sender_id')
                    ->where('chats.chat_room_id', $roomId);
        
        if ($beforeId) {
            $builder->where('chats.id <', $beforeId);
        }
        
        return $builder->orderBy('chats.id', 'DESC')
                       ->findAll($limit);
    }

    public function getPrivateChats($karangTarunaId, $user1Id, $user2Id, $limit = 50, $beforeId = null)
    {
        $builder = $this->select('chats.*, users.nama_lengkap, users.role_level, users.profile_photo')
                    ->join('users', 'users.id = chats.sender_id')
                    ->where('chats.karang_taruna_id', $karangTarunaId)
                    ->where('chats.type', 'private')
                    ->groupStart()
                        ->groupStart()
                            ->where('chats.sender_id', $user1Id)
                            ->where('chats.receiver_id', $user2Id)
                        ->groupEnd()
                        ->orGroupStart()
                            ->where('chats.sender_id', $user2Id)
                            ->where('chats.receiver_id', $user1Id)
                        ->groupEnd()
                    ->groupEnd();
        
        if ($beforeId) {
            $builder->where('chats.id <', $beforeId);
        }
        
        return $builder->orderBy('chats.id', 'DESC')
                       ->findAll($limit);
    }
}
