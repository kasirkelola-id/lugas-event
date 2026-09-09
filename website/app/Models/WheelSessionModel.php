<?php

namespace App\Models;

use CodeIgniter\Model;

class WheelSessionModel extends Model
{
    protected $table            = 'wheel_sessions';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'karang_taruna_id',
        'created_by_user_id',
        'title',
        'source_type',
        'spin_duration_seconds',
        'remove_winner_after_spin',
        'status',
        'created_at',
        'updated_at'
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
