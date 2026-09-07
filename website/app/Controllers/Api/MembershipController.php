<?php

namespace App\Controllers\Api;

use App\Services\AuthService;
use App\Models\OrganizationMemberModel;

class MembershipController extends BaseApiController
{
    public function index()
    {
        $userId = AuthService::getGlobalUserId();
        if (!$userId) {
            return $this->sendError('Unauthorized', null, 401);
        }

        $memberModel = new OrganizationMemberModel();
        
        $builder = $memberModel->builder();
        $builder->select('organization_members.id as membership_id, organization_members.karang_taruna_id, organization_members.role_level as role, organization_members.status_aktif as status, karang_taruna.nama_organisasi as nama');
        $builder->join('karang_taruna', 'karang_taruna.id = organization_members.karang_taruna_id');
        $builder->where('organization_members.user_id', $userId);
        
        // Return only active memberships
        $builder->where('organization_members.status_aktif', 1);
        
        $memberships = $builder->get()->getResultArray();
        
        // Cast types
        $memberships = array_map(function($m) {
            $m['membership_id'] = (int)$m['membership_id'];
            $m['karang_taruna_id'] = (int)$m['karang_taruna_id'];
            $m['status'] = (int)$m['status'];
            return $m;
        }, $memberships);

        return $this->sendSuccess('Daftar membership', $memberships);
    }

    public function pending()
    {
        if (!AuthService::can('members.approve')) {
            return $this->sendError('Forbidden', null, 403);
        }

        $tenantId = AuthService::getTenantId();
        
        $db = \Config\Database::connect();
        $pendingMembers = $db->table('organization_members')
            ->select('organization_members.id as membership_id, users.nama_lengkap, users.username, users.rt, organization_members.created_at, organization_members.approval_status')
            ->join('users', 'users.id = organization_members.user_id')
            ->where('organization_members.karang_taruna_id', $tenantId)
            ->where('organization_members.approval_status', 'pending')
            ->get()
            ->getResultArray();

        return $this->sendSuccess('Daftar anggota menunggu persetujuan', $pendingMembers);
    }

    public function approve($membershipId)
    {
        if (!AuthService::can('members.approve')) {
            return $this->sendError('Forbidden', null, 403);
        }
        return $this->processApproval($membershipId, 'approved');
    }

    public function reject($membershipId)
    {
        if (!AuthService::can('members.approve')) {
            return $this->sendError('Forbidden', null, 403);
        }
        return $this->processApproval($membershipId, 'rejected');
    }

    private function processApproval($membershipId, $action)
    {
        $tenantId = AuthService::getTenantId();
        $db = \Config\Database::connect();
        
        $db->transStart();
        
        $memberModel = new OrganizationMemberModel();
        
        $membership = $db->table('organization_members')
            ->where('id', $membershipId)
            ->where('karang_taruna_id', $tenantId)
            ->get()
            ->getRowArray();

        if (!$membership) {
            $db->transRollback();
            return $this->sendError('Membership tidak ditemukan', null, 404);
        }

        if ($membership['approval_status'] !== 'pending') {
            $db->transRollback();
            return $this->sendError('Membership ini tidak dalam status pending', null, 422);
        }

        $memberModel->update($membershipId, [
            'approval_status' => $action
        ]);

        $historyModel = new \App\Models\MembershipApprovalHistoryModel();
        $historyModel->insert([
            'organization_member_id' => $membershipId,
            'karang_taruna_id' => $tenantId,
            'action' => $action,
            'actor_user_id' => AuthService::getGlobalUserId(),
            'actor_type' => AuthService::getRole() === 'superadmin' ? 'superadmin' : 'user',
            'note' => null,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->sendError('Gagal memproses approval', null, 500);
        }

        return $this->sendSuccess("Berhasil mengubah status pendaftaran menjadi $action");
    }

    public function filterOptions()
    {
        $tenantId = AuthService::getTenantId();
        
        $db = \Config\Database::connect();
        
        // Distinct RTs for the active tenant
        $rtQuery = $db->table('organization_members')
            ->select('users.rt')
            ->join('users', 'users.id = organization_members.user_id')
            ->where('organization_members.karang_taruna_id', $tenantId)
            ->where('users.rt IS NOT NULL')
            ->where("users.rt != ''")
            ->distinct()
            ->get()
            ->getResultArray();

        $rts = array_map(function($row) {
            return (int)$row['rt'];
        }, $rtQuery);

        // Sort numerically
        sort($rts, SORT_NUMERIC);
        
        // Return string representations if needed, but int is fine. 
        // Flutter will handle strings or ints. Let's send strings to match form inputs typically.
        $rts = array_map('strval', $rts);

        return $this->sendSuccess('Filter options', [
            'rt' => $rts
        ]);
    }
}
