<?php

namespace App\Models;

use CodeIgniter\Model;

class ChatRoomMemberModel extends Model
{
    protected $table            = 'chat_room_members';
    // No primary key, composite key handling is manual or not strictly needed by CI4 model if we don't use find()
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'chat_room_id',
        'user_id'
    ];
}
