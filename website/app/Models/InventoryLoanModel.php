<?php

namespace App\Models;

use CodeIgniter\Model;

class InventoryLoanModel extends Model
{
    protected $table            = 'inventory_loans';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['inventory_id', 'user_id', 'quantity', 'borrow_date', 'return_date', 'status', 'created_at', 'updated_at'];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
