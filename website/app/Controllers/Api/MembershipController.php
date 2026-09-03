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
}
