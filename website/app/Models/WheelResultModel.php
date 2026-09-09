<?php

namespace App\Models;

use CodeIgniter\Model;

class WheelResultModel extends Model
{
    protected $table            = 'wheel_results';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'session_id',
        'wheel_item_id',
        'result_label_snapshot',
        'spin_sequence',
        'started_at',
        'duration_seconds',
        'completed_at',
        'created_at'
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = '';
}
