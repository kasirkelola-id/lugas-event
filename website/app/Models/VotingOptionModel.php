<?php

namespace App\Models;

use CodeIgniter\Model;

class VotingOptionModel extends Model
{
    protected $table            = 'voting_options';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['voting_id', 'option_name'];
}
