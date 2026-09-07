<?php

namespace App\Models;

use CodeIgniter\Model;

class MembershipApprovalHistoryModel extends Model
{
    protected $table            = 'membership_approval_history';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'organization_member_id',
        'karang_taruna_id',
        'action',
        'actor_user_id',
        'actor_type',
        'note',
        'created_at'
    ];

    protected $useTimestamps = false; // We will manually insert or set created_at

    public function getHistoryForMembership($membershipId)
    {
        return $this->where('organization_member_id', $membershipId)
                    ->orderBy('created_at', 'DESC')
                    ->findAll();
    }
}
