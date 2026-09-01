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
        'sender_id',
        'receiver_id',
        'message',
        'created_at'
    ];

    protected $useTimestamps = false; // We use created_at default current_timestamp

    public function getGroupChats($karangTarunaId, $limit = 50)
    {
        return $this->select('chats.*, users.nama_lengkap, users.role_level')
                    ->join('users', 'users.id = chats.sender_id')
                    ->where('chats.karang_taruna_id', $karangTarunaId)
                    ->where('chats.type', 'group')
                    ->orderBy('chats.created_at', 'ASC') // get oldest to newest to display in chat list correctly. Usually you order by DESC and reverse, but let's just do ASC for simple pulling.
                    ->findAll($limit);
    }

    public function getPrivateChats($karangTarunaId, $user1Id, $user2Id, $limit = 50)
    {
        return $this->select('chats.*, users.nama_lengkap, users.role_level')
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
                    ->groupEnd()
                    ->orderBy('chats.created_at', 'ASC')
                    ->findAll($limit);
    }
}
