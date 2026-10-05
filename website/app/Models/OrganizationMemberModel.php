<?php

namespace App\Models;

use CodeIgniter\Model;

class OrganizationMemberModel extends Model
{
    protected $table            = 'organization_members';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'user_id',
        'karang_taruna_id',
        'username', // NEW tenant-scoped username
        'role_level',
        'approval_status',
        'status_aktif',
        'joined_at'
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * Get all members of a specific tenant.
     */
    public function getMembers($karangTarunaId)
    {
        return $this->select('organization_members.*, users.nama_lengkap, users.nama_panggilan, users.no_whatsapp, organization_members.username')
                    ->join('users', 'users.id = organization_members.user_id')
                    ->where('organization_members.karang_taruna_id', $karangTarunaId)
                    ->findAll();
    }

    /**
     * Get a user's membership in a specific tenant.
     */
    public function getMembership($userId, $karangTarunaId)
    {
        return $this->where('user_id', $userId)
                    ->where('karang_taruna_id', $karangTarunaId)
                    ->first();
    }

    /**
     * Memberships that may establish an ordinary user's tenant context.
     * A legacy users.karang_taruna_id value is never authorization.
     */
    public function getEligibleMemberships(int $userId, ?int $tenantId = null): array
    {
        $this->select('organization_members.*')
             ->join('users', 'users.id = organization_members.user_id')
             ->join('karang_taruna', 'karang_taruna.id = organization_members.karang_taruna_id')
             ->where('organization_members.user_id', $userId)
             ->where('users.status_aktif', 1)
             ->where('karang_taruna.status_aktif', 1)
             ->where('organization_members.status_aktif', 1)
             ->where('organization_members.approval_status', 'approved');

        if ($tenantId !== null) {
            $this->where('organization_members.karang_taruna_id', $tenantId);
        }

        return $this->findAll();
    }

    /**
     * Get all memberships for a user.
     */
    public function getUserMemberships($userId)
    {
        return $this->select('organization_members.*, karang_taruna.nama_organisasi')
                    ->join('karang_taruna', 'karang_taruna.id = organization_members.karang_taruna_id')
                    ->where('organization_members.user_id', $userId)
                    ->findAll();
    }
}
