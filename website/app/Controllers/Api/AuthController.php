<?php

namespace App\Controllers\Api;

use App\Models\UserModel;
use App\Models\UserTokenModel;

class AuthController extends BaseApiController
{
    public function validatePin()
    {
        $rules = [
            'pin' => 'required|exact_length[6]'
        ];

        if (!$this->validate($rules)) {
            return $this->sendError('Validasi gagal', $this->validator->getErrors(), 422);
        }

        $pin = $this->request->getVar('pin');
        
        $ktModel = new \App\Models\KarangTarunaModel();
        $kt = $ktModel->where('kode_pin', $pin)->first();

        if (!$kt) {
            return $this->sendError('PIN tidak valid atau tidak ditemukan.', null, 404);
        }

        if ($kt['status_aktif'] != 1) {
            return $this->sendError('Karang Taruna ini sedang tidak aktif.', null, 403);
        }

        return $this->sendSuccess('PIN valid', [
            'karang_taruna_id' => (int)$kt['id'],
            'nama_organisasi'  => $kt['nama_organisasi'],
            'logo_url'         => !empty($kt['logo_path']) ? base_url($kt['logo_path']) : null,
        ]);
    }

    public function login()
    {
        $rules = [
            'karang_taruna_id' => 'required|numeric',
            'username'         => 'required',
            'password'         => 'required'
        ];

        if (!$this->validate($rules)) {
            return $this->sendError('Validasi gagal', $this->validator->getErrors(), 422);
        }

        $karangTarunaId = $this->request->getVar('karang_taruna_id');
        $username = $this->request->getVar('username');
        $password = $this->request->getVar('password');

        $userModel = new UserModel();
        
        $db = \Config\Database::connect();
        
        // Find user by joining organization_members (where the tenant-scoped username lives)
        $memberInfo = $db->table('organization_members')
                         ->select('users.*, organization_members.username as tenant_username, organization_members.role_level as tenant_role, organization_members.status_aktif as tenant_status')
                         ->join('users', 'users.id = organization_members.user_id')
                         ->where('organization_members.username', $username)
                         ->where('organization_members.karang_taruna_id', $karangTarunaId)
                         ->get()
                         ->getRowArray();

        $user = $memberInfo; // Will be null if not found

        $isSuperAdmin = false;
        $superadmin = null;

        if (!$user || !password_verify($password, (string)$user['password'])) {
            // Check if it's a superadmin
            $db = \Config\Database::connect();
            $superadmin = $db->table('superadmins')->where('username', $username)->get()->getRowArray();

            if (!$superadmin || !password_verify($password, (string)$superadmin['password'])) {
                return $this->sendError('Username atau password salah.', null, 401);
            }

            $isSuperAdmin = true;
        }

        if (!$isSuperAdmin && $user['tenant_status'] != 1) {
            return $this->sendError('Akun tidak aktif di Karang Taruna ini.', null, 401);
        }

        if (!$isSuperAdmin && $user['status_aktif'] != 1) {
            return $this->sendError('Akun pengguna dinonaktifkan secara global.', null, 401);
        }

        // Generate Token
        $plainToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $plainToken);

        $tokenModel = new UserTokenModel();
        
        // Expiration in 30 days
        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));

        $userId = $isSuperAdmin ? 0 : $user['id']; // Token table user_id is INT

        $tokenModel->insert([
            'karang_taruna_id' => $karangTarunaId,
            'user_id'          => $userId,
            'token_hash'       => $tokenHash,
            'expires_at'       => $expiresAt,
            'created_at'       => date('Y-m-d H:i:s'),
        ]);

        // Build Response User without sensitive data
        if ($isSuperAdmin) {
            $userData = [
                'id'             => 's_' . $superadmin['id'], // Virtual ID to prevent collision
                'karang_taruna_id' => (int)$karangTarunaId,
                'nama_lengkap'   => $superadmin['nama_lengkap'],
                'nama_panggilan' => 'Superadmin',
                'username'       => $superadmin['username'],
                'no_whatsapp'    => '-',
                'rt'             => 1,
                'role_level'     => 'superadmin',
                'status_aktif'   => 1,
                'password_must_change' => false,
            ];
        } else {
            $userData = [
                'id'             => (int)$user['id'],
                'karang_taruna_id' => (int)$karangTarunaId, // The tenant they logged into
                'nama_lengkap'   => $user['nama_lengkap'],
                'nama_panggilan' => $user['nama_panggilan'],
                'username'       => $user['tenant_username'], // Use tenant-scoped username
                'no_whatsapp'    => $user['no_whatsapp'],
                'rt'             => (int)($user['rt'] ?? 1),
                'role_level'     => $user['tenant_role'],
                'status_aktif'   => (int)$user['tenant_status'],
                'password_must_change' => (int)($user['password_must_change'] ?? 0) === 1,
            ];
        }

        $memberModel = new \App\Models\OrganizationMemberModel();
        $memberships = [];
        if (!$isSuperAdmin) {
            $builder = $memberModel->builder();
            $builder->select('organization_members.id as membership_id, organization_members.karang_taruna_id, organization_members.role_level as role, organization_members.status_aktif as status, karang_taruna.nama_organisasi as nama');
            $builder->join('karang_taruna', 'karang_taruna.id = organization_members.karang_taruna_id');
            $builder->where('organization_members.user_id', $user['id']);
            $builder->where('organization_members.status_aktif', 1);
            $memberships = $builder->get()->getResultArray();
            $memberships = array_map(function($m) {
                $m['membership_id'] = (int)$m['membership_id'];
                $m['karang_taruna_id'] = (int)$m['karang_taruna_id'];
                $m['status'] = (int)$m['status'];
                return $m;
            }, $memberships);
        }

        $requiresTenantSelection = count($memberships) > 1;

        return $this->sendSuccess('Login berhasil', [
            'token' => $plainToken,
            'user'  => $userData,
            'memberships' => $memberships,
            'requires_tenant_selection' => $requiresTenantSelection
        ], 200);
    }

    public function logout()
    {
        $tokenData = \App\Services\AuthService::getToken();
        if ($tokenData) {
            $tokenModel = new UserTokenModel();
            $tokenModel->update($tokenData['id'], [
                'revoked_at' => date('Y-m-d H:i:s')
            ]);
        }

        return $this->sendSuccess('Logout berhasil');
    }

    public function me()
    {
        $user = \App\Services\AuthService::getUser();
        $tenantId = $user['karang_taruna_id'] ?? null;
        $tenantName = null;

        if ($tenantId) {
            $ktModel = new \App\Models\KarangTarunaModel();
            $kt = $ktModel->find($tenantId);
            $tenantName = $kt['nama_organisasi'] ?? null;
        }

        return $this->sendSuccess('Berhasil mengambil profil', [
            'id' => (int)$user['id'],
            'nama_lengkap' => $user['nama_lengkap'],
            'nama_panggilan' => $user['nama_panggilan'],
            'username' => $user['username'], 
            'no_whatsapp' => $user['no_whatsapp'],
            'role_level' => $user['role_level'],
            'rt' => (int)$user['rt'],
            'profile_photo_url' => !empty($user['profile_photo']) ? base_url($user['profile_photo']) : null,
            'karang_taruna' => [
                'id' => (int)$tenantId,
                'nama_organisasi' => $tenantName,
            ]
        ]);
    }

    public function register()
    {
        $rules = [
            'karang_taruna_id' => 'required|numeric',
            'nama_lengkap'     => 'required|max_length[255]',
            'nama_panggilan'   => 'required|max_length[100]',
            'username'         => 'required',
            'password'         => 'required|min_length[6]',
            'confirm_password' => 'required|matches[password]',
            'no_whatsapp'      => 'required|max_length[20]',
            'rt'               => 'permit_empty|in_list[1,2,3,4]',
        ];

        $rawInput = $this->request->getJSON(true) ?? $this->request->getRawInput();
        $karangTarunaId = $rawInput['karang_taruna_id'] ?? null;

        if (!$this->validateData($rawInput, $rules)) {
            return $this->sendError('Validasi gagal', $this->validator->getErrors(), 422);
        }

        $memberModel = new \App\Models\OrganizationMemberModel();
        
        // Check if username already exists IN THIS TENANT
        $existingMember = $memberModel->where('username', $rawInput['username'])
                                      ->where('karang_taruna_id', $karangTarunaId)
                                      ->first();
        if ($existingMember) {
            return $this->sendError('Username sudah terdaftar', ['username' => 'Username ini sudah digunakan di Karang Taruna Anda.'], 409);
        }

        $userModel = new UserModel();

        // Check if phone number already exists
        $existingUserByPhone = $userModel->where('no_whatsapp', $rawInput['no_whatsapp'])->first();
        if ($existingUserByPhone) {
            return $this->sendError('Validasi gagal', ['username' => 'Username ini sudah digunakan di Karang Taruna Anda.'], 422);
        }

        $userData = [
            'nama_lengkap'   => $rawInput['nama_lengkap'],
            'nama_panggilan' => $rawInput['nama_panggilan'],
            'username'       => $rawInput['username'], // Keep globally for now as fallback/legacy
            'password'       => password_hash($rawInput['password'], PASSWORD_BCRYPT),
            'no_whatsapp'    => $rawInput['no_whatsapp'],
            'rt'             => (int)($rawInput['rt'] ?? 1),
            'status_aktif'   => 1
        ];

        $userModel->insert($userData);
        $userId = $userModel->getInsertID();

        // Insert into organization_members
        $memberData = [
            'user_id' => $userId,
            'karang_taruna_id' => $karangTarunaId,
            'username' => $rawInput['username'], // The real tenant-scoped username
            'role_level' => 'anggota',
            'status_aktif' => 1,
            'joined_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $memberModel->insert($memberData);
        
        return $this->sendSuccess('Registrasi berhasil. Silakan login.', null, 201);
    }

    public function updateFcmToken()
    {
        $rules = [
            'fcm_token' => 'required',
            'device_type' => 'permit_empty'
        ];

        $rawInput = $this->request->getJSON(true) ?? $this->request->getRawInput();

        if (!$this->validateData($rawInput, $rules)) {
            return $this->sendError('Validasi gagal', $this->validator->getErrors(), 422);
        }

        $user = \App\Services\AuthService::getUser();
        if (!$user) {
            return $this->sendError('Unauthenticated', null, 401);
        }

        $deviceModel = new \App\Models\UserDeviceModel();
        
        // Find existing token
        $existing = $deviceModel->where('fcm_token', $rawInput['fcm_token'])->first();
        
        if ($existing) {
            // Update owner and device type if token already exists (handles logout/login to another account on same device)
            $deviceModel->update($existing['id'], [
                'user_id' => (string)$user['id'],
                'device_type' => $rawInput['device_type'] ?? 'android'
            ]);
        } else {
            $deviceModel->insert([
                'user_id' => (string)$user['id'],
                'fcm_token' => $rawInput['fcm_token'],
                'device_type' => $rawInput['device_type'] ?? 'android'
            ]);
        }

        return $this->sendSuccess('Token berhasil diupdate');
    }

    public function removeFcmToken()
    {
        $rules = [
            'fcm_token' => 'required'
        ];

        $rawInput = $this->request->getJSON(true) ?? $this->request->getRawInput();

        if (!$this->validateData($rawInput, $rules)) {
            return $this->sendError('Validasi gagal', $this->validator->getErrors(), 422);
        }

        $user = \App\Services\AuthService::getUser();
        if (!$user) {
            return $this->sendError('Unauthenticated', null, 401);
        }

        $deviceModel = new \App\Models\UserDeviceModel();
        
        // Only allow deleting token if it belongs to the current user
        $deviceModel->where('user_id', (string)$user['id'])
                    ->where('fcm_token', $rawInput['fcm_token'])
                    ->delete();

        return $this->sendSuccess('Token berhasil dihapus');
    }
}
