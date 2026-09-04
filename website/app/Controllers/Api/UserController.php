<?php

namespace App\Controllers\Api;

use App\Models\UserModel;
use App\Services\AuthService;

class UserController extends BaseApiController
{
    // Legacy role check helpers removed in favor of RBAC

    private function countActiveKetua($karangTarunaId)
    {
        $memberModel = new \App\Models\OrganizationMemberModel();
        return $memberModel->where('karang_taruna_id', $karangTarunaId)
                         ->where('role_level', 'ketua')
                         ->where('status_aktif', 1)
                         ->countAllResults();
    }

    public function index()
    {
        if (!AuthService::can('members.view')) {
            return $this->sendError('Forbidden', null, 403);
        }

        $tenantId = AuthService::getTenantId();
        
        $page = (int)($this->request->getVar('page') ?? 1);
        $limit = (int)($this->request->getVar('limit') ?? 100);
        if ($limit > 100) $limit = 100;
        $offset = ($page - 1) * $limit;
        
        $search = $this->request->getVar('search');
        $status = $this->request->getVar('status');
        $roleFilter = $this->request->getVar('role');

        $db = \Config\Database::connect();
        $builder = $db->table('organization_members')
                      ->select('users.id, users.nama_lengkap, users.nama_panggilan, organization_members.username, users.no_whatsapp, users.rt, organization_members.role_level, organization_members.status_aktif, organization_members.created_at')
                      ->join('users', 'users.id = organization_members.user_id')
                      ->where('organization_members.karang_taruna_id', $tenantId)
                      ->orderBy('organization_members.status_aktif', 'DESC')
                      ->orderBy('users.nama_lengkap', 'ASC');
        
        if (!AuthService::can('members.manage')) {
            $builder->where('organization_members.role_level', 'anggota');
        }

        if (!empty($search)) {
            $builder->groupStart()
                    ->like('users.nama_lengkap', $search)
                    ->orLike('organization_members.username', $search)
                    ->groupEnd();
        }

        if ($status !== null && $status !== '') {
            $builder->where('organization_members.status_aktif', (int)$status);
        }

        if (!empty($roleFilter) && $roleFilter !== 'Semua') {
            $builder->where('organization_members.role_level', strtolower($roleFilter));
        }

        $totalBuilder = clone $builder;
        $total = $totalBuilder->countAllResults(false);

        $builder->limit($limit, $offset);
        $users = $builder->get()->getResultArray();

        $data = array_map(function ($u) {
            return [
                'id' => (int)$u['id'],
                'nama_lengkap' => $u['nama_lengkap'],
                'nama_panggilan' => $u['nama_panggilan'],
                'username' => $u['username'],
                'no_whatsapp' => $u['no_whatsapp'],
                'rt' => (int)($u['rt'] ?? 1),
                'role_level' => $u['role_level'],
                'status_aktif' => (int)$u['status_aktif'],
                'created_at' => $u['created_at'],
            ];
        }, $users);

        return $this->respond([
            'status' => true,
            'message' => 'Daftar pengguna',
            'users' => $data,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => ceil($total / $limit)
            ]
        ], 200);
    }

    public function create()
    {
        if (!AuthService::can('members.create')) {
            return $this->sendError('Forbidden', null, 403);
        }

        $rules = [
            'nama_lengkap'   => 'required|max_length[255]',
            'nama_panggilan' => 'required|max_length[100]',
            'username'       => 'required',
            'role_level'     => 'required|in_list[ketua,sekretaris,bendahara,pengelola,anggota]',
            'rt'             => 'permit_empty|in_list[1,2,3,4]'
        ];

        $rawInput = $this->request->getJSON(true) ?? $this->request->getRawInput();

        if (!$this->validateData($rawInput, $rules)) {
            return $this->sendError('Validasi gagal', $this->validator->getErrors(), 422);
        }

        $tenantId = AuthService::getTenantId();
        
        $userModel = new UserModel();
        $memberModel = new \App\Models\OrganizationMemberModel();
        
        // Check if username already exists IN THIS TENANT
        $existingMember = $memberModel->where('username', $rawInput['username'])
                                      ->where('karang_taruna_id', $tenantId)
                                      ->first();
        if ($existingMember) {
            return $this->sendError('Username sudah terdaftar', ['username' => 'Akun dengan username ini sudah ada di Karang Taruna Anda.'], 409);
        }

        $userData = [
            'nama_lengkap'   => $rawInput['nama_lengkap'],
            'nama_panggilan' => $rawInput['nama_panggilan'],
            'username'       => $rawInput['username'],
            'password'       => password_hash($rawInput['username'], PASSWORD_BCRYPT),
            'no_whatsapp'    => $rawInput['no_whatsapp'] ?? null,
            'rt'             => (int)($rawInput['rt'] ?? 1),
            'status_aktif'   => 1
        ];

        $db = \Config\Database::connect();
        $db->transStart();

        $userModel->insert($userData);
        $userId = $userModel->getInsertID();

        // Insert to organization_members
        $memberData = [
            'user_id' => $userId,
            'karang_taruna_id' => $tenantId,
            'username' => $rawInput['username'], // Save username in member table
            'role_level' => $rawInput['role_level'],
            'status_aktif' => 1,
            'joined_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $memberModel->insert($memberData);
        
        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->sendError('Gagal membuat pengguna', null, 500);
        }

        $userData['id'] = $userId;
        unset($userData['password']);

        return $this->sendSuccess('Pengguna berhasil dibuat', $userData, 201);
    }

    public function update($id = null)
    {
        if (!AuthService::can('members.manage')) {
            return $this->sendError('Forbidden', null, 403);
        }

        $tenantId = AuthService::getTenantId();
        $memberModel = new \App\Models\OrganizationMemberModel();
        
        $membership = $memberModel->where('user_id', $id)
                                  ->where('karang_taruna_id', $tenantId)
                                  ->first();
                                  
        if (!$membership) {
            return $this->sendError('Bukan anggota Karang Taruna ini.', null, 403);
        }

        $rawInput = $this->request->getJSON(true) ?? $this->request->getRawInput();
        
        if (isset($rawInput['username'])) {
            // Check if new username conflicts with another member in this tenant
            $existingMember = $memberModel->where('username', $rawInput['username'])
                                          ->where('karang_taruna_id', $tenantId)
                                          ->where('id !=', $membership['id'])
                                          ->first();
            if ($existingMember) {
                return $this->sendError('Username sudah terdaftar', ['username' => 'Akun dengan username ini sudah ada di Karang Taruna Anda.'], 409);
            }
            $memberModel->update($membership['id'], ['username' => $rawInput['username']]);
        }

        $userModel = new UserModel();
        $userData = [];
        if (isset($rawInput['nama_lengkap'])) $userData['nama_lengkap'] = $rawInput['nama_lengkap'];
        if (isset($rawInput['nama_panggilan'])) $userData['nama_panggilan'] = $rawInput['nama_panggilan'];
        if (isset($rawInput['no_whatsapp'])) $userData['no_whatsapp'] = $rawInput['no_whatsapp'];
        if (isset($rawInput['rt'])) $userData['rt'] = (int)$rawInput['rt'];
        if (isset($rawInput['username'])) $userData['username'] = $rawInput['username'];

        if (!empty($userData)) {
            $userModel->update($id, $userData);
        }

        return $this->sendSuccess('Pengguna berhasil diperbarui');
    }

    public function toggleStatus($id = null)
    {
        if (!AuthService::can('members.deactivate')) {
            return $this->sendError('Forbidden', null, 403);
        }

        $tenantId = AuthService::getTenantId();
        $memberModel = new \App\Models\OrganizationMemberModel();
        $membership = $memberModel->where('user_id', $id)->where('karang_taruna_id', $tenantId)->first();
        if (!$membership) {
            return $this->sendError('Membership tidak ditemukan', null, 404);
        }

        $newStatus = (int)$membership['status_aktif'] === 1 ? 0 : 1;

        // Lockout prevention
        if ($newStatus === 0 && $membership['role_level'] === 'ketua') {
            if ($this->countActiveKetua($tenantId) <= 1) {
                return $this->sendError('Validasi gagal', ['status_aktif' => 'Tidak dapat menonaktifkan akun ketua terakhir yang aktif.'], 400);
            }
        }

        $memberModel->update($membership['id'], ['status_aktif' => $newStatus]);

        return $this->sendSuccess('Status pengguna berhasil diubah', ['status_aktif' => $newStatus]);
    }

    public function changeRole($id = null)
    {
        if (!AuthService::can('members.change_role')) {
            return $this->sendError('Forbidden', null, 403);
        }

        $tenantId = AuthService::getTenantId();
        $userModel = new UserModel();
        $user = $userModel->find($id);

        if (!$user) {
            return $this->sendError('Pengguna tidak ditemukan', null, 404);
        }

        $rawInput = $this->request->getJSON(true) ?? $this->request->getRawInput();
        if (!isset($rawInput['role_level']) || !in_array($rawInput['role_level'], ['ketua', 'sekretaris', 'bendahara', 'pengelola', 'anggota'])) {
            return $this->sendError('Validasi gagal', ['role_level' => 'Role tidak valid'], 422);
        }

        $newRole = $rawInput['role_level'];

        // Fetch membership
        $memberModel = new \App\Models\OrganizationMemberModel();
        $membership = $memberModel->where('user_id', $user['id'])->where('karang_taruna_id', $tenantId)->first();
        if (!$membership) {
            return $this->sendError('Membership tidak ditemukan', null, 404);
        }
        
        $currentUserRole = AuthService::getRole();
        if ($currentUserRole !== 'ketua' && in_array($newRole, ['ketua', 'pengelola'])) {
            return $this->sendError('Forbidden: Anda tidak memiliki akses untuk memberikan role ini', null, 403);
        }

        // Lockout prevention
        if ($membership['role_level'] === 'ketua' && $newRole !== 'ketua' && (int)$membership['status_aktif'] === 1) {
            if ($this->countActiveKetua($tenantId) <= 1) {
                return $this->sendError('Validasi gagal', ['role_level' => 'Tidak dapat mengubah role dari akun ketua terakhir yang aktif.'], 400);
            }
        }

        $memberModel->update($membership['id'], ['role_level' => $newRole]);

        return $this->sendSuccess('Role pengguna berhasil diubah', ['role_level' => $newRole]);
    }

    public function resetPassword($id = null)
    {
        if (!AuthService::can('members.reset_password')) {
            return $this->sendError('Forbidden', null, 403);
        }

        $tenantId = AuthService::getTenantId();
        
        $userModel = new UserModel();
        $user = $userModel->find($id);

        if (!$user) {
            return $this->sendError('Pengguna tidak ditemukan', null, 404);
        }

        // Fetch membership
        $memberModel = new \App\Models\OrganizationMemberModel();
        $membership = $memberModel->where('user_id', $user['id'])->where('karang_taruna_id', $tenantId)->first();
        
        if (!$membership || (int)$membership['status_aktif'] !== 1) {
            return $this->sendError('Pengguna tidak aktif di Karang Taruna ini', null, 403);
        }

        $currentUserRole = AuthService::getRole();
        if ($currentUserRole !== 'ketua' && $membership['role_level'] === 'ketua') {
            return $this->sendError('Forbidden: Tidak dapat mereset password ketua', null, 403);
        }

        $settingModel = new \App\Models\SettingModel();
        $tempPassSetting = $settingModel->where('karang_taruna_id', 0)->where('setting_key', 'temporary_reset_password')->first();
        
        if (!$tempPassSetting || empty(trim($tempPassSetting['setting_value']))) {
            return $this->sendError('Sistem error', ['message' => 'Password sementara global belum dikonfigurasi oleh Superadmin.'], 500);
        }

        $temporaryPassword = trim($tempPassSetting['setting_value']);

        // Update Exact Global User password
        $userModel->update($user['id'], [
            'password' => password_hash($temporaryPassword, PASSWORD_BCRYPT),
            'password_must_change' => 1
        ]);

        return $this->sendSuccess('Password berhasil direset.', ['temporary_password' => $temporaryPassword]);
    }

    public function rolesSummary()
    {
        if (!AuthService::can('members.view')) {
            return $this->sendError('Forbidden', null, 403);
        }

        $tenantId = AuthService::getTenantId();
        $memberModel = new \App\Models\OrganizationMemberModel();
        
        $totalKetua = $memberModel->where('karang_taruna_id', $tenantId)->where('role_level', 'ketua')->countAllResults();
        $totalPengelola = $memberModel->where('karang_taruna_id', $tenantId)->where('role_level', 'pengelola')->countAllResults();
        $totalAnggota = $memberModel->where('karang_taruna_id', $tenantId)->where('role_level', 'anggota')->countAllResults();

        $data = [
            'ketua' => $totalKetua,
            'pengelola' => $totalPengelola,
            'anggota' => $totalAnggota,
            'total' => $totalKetua + $totalPengelola + $totalAnggota
        ];

        return $this->sendSuccess('Ringkasan role', $data);
    }
}
