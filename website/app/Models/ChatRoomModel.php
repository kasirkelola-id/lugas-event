<?php

namespace App\Models;

use CodeIgniter\Model;

class ChatRoomModel extends Model
{
    protected $table            = 'chat_rooms';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'karang_taruna_id',
        'name',
        'type',
        'created_by',
        'created_at'
    ];

    protected $useTimestamps = false; // Using default CURRENT_TIMESTAMP on DB level is fine, but better CI4 handles it
    
    // We can define getRooms for a user
    public function getRoomsForUser($karangTarunaId, $userId)
    {
        // 1. Get default room for the karang taruna
        // 2. Get custom rooms where user is a member
        $builder = $this->db->table($this->table)
                        ->select('chat_rooms.*')
                        ->where('chat_rooms.karang_taruna_id', $karangTarunaId)
                        ->groupStart()
                            ->where('chat_rooms.type', 'default')
                            ->orGroupStart()
                                ->where('chat_rooms.type', 'custom')
                                ->join('chat_room_members', 'chat_room_members.chat_room_id = chat_rooms.id')
                                ->where('chat_room_members.user_id', $userId)
                            ->groupEnd()
                        ->groupEnd()
                        ->orderBy('chat_rooms.created_at', 'ASC');

        return $builder->get()->getResultArray();
    }
}
