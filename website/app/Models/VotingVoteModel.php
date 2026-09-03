<?php

namespace App\Models;

use CodeIgniter\Model;

class VotingVoteModel extends Model
{
    protected $table            = 'voting_votes';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['voting_id', 'option_id', 'user_id', 'created_at'];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = '';
}
