<?php

namespace App\Models;

use CodeIgniter\Model;

class WheelItemModel extends Model
{
    protected $table            = 'wheel_items';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'session_id',
        'member_user_id',
        'label_snapshot',
        'is_active'
    ];

    protected $useTimestamps = false;
}
