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
                         ->select('users.*, organization_members.username as tenant_username, organization_members.role_level as tenant_role, organization_members.status_aktif as tenant_status, organization_members.approval_status as tenant_approval')
                         ->join('users', 'users.id = organization_members.user_id')
                         ->where('organization_members.username', $username)
                         ->where('organization_members.karang_taruna_id', $karangTarunaId)
                         ->get()
                         ->getRowArray();

        $user = $memberInfo; // Will be null if not found

        $isSuperAdmin = false;
        $superadmin = null;

        if (!$user || !\App\Services\CredentialPolicy::verify((string) $password, $user)) {
            // Check if it's a superadmin
            $db = \Config\Database::connect();
            $superadmin = $db->table('superadmins')->where('username', $username)->get()->getRowArray();

            if (!$superadmin || !\App\Services\CredentialPolicy::verify((string) $password, $superadmin)) {
                return $this->sendError('Username atau password salah.', null, 401);
            }

            $isSuperAdmin = true;
        }

        if (!$isSuperAdmin && $user['tenant_approval'] === 'pending') {
            return $this->sendError('Pendaftaran Anda masih menunggu persetujuan pengurus.', null, 401, 'MEMBERSHIP_PENDING_APPROVAL');
        }

        if (!$isSuperAdmin && $user['tenant_approval'] === 'rejected') {
            return $this->sendError('Pendaftaran belum disetujui. Silakan hubungi pengurus Karang Taruna.', null, 401, 'MEMBERSHIP_REJECTED');
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

        $userId = $isSuperAdmin ? null : $user['id']; // Superadmin has no user_id (NULL)

        $tokenModel->insert([
            'karang_taruna_id' => $karangTarunaId,
            'user_id'          => $userId,
            'superadmin_id'    => $isSuperAdmin ? $superadmin['id'] : null,
            'credential_version' => $isSuperAdmin ? hash('sha256', $superadmin['password']) : null,
            'token_hash'       => $tokenHash,
            'expires_at'       => $expiresAt,
            'created_at'       => date('Y-m-d H:i:s'),
        ]);

        // Build Response User without sensitive data
        if ($isSuperAdmin) {
            $userData = [
                'id'             => -1 * (int)$superadmin['id'], // Virtual ID to prevent collision
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
            if (!\App\Services\BearerLogoutService::revoke($tokenData)) {
                return $this->sendError('Logout tidak dapat diproses. Silakan coba lagi.', null, 503);
            }
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
            'password_must_change' => (int)($user['password_must_change'] ?? 0),
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
            'password'         => 'required|min_length[12]|max_length[72]',
            'confirm_password' => 'required|matches[password]',
            'no_whatsapp'      => 'required|max_length[20]',
            'rt'               => 'permit_empty|in_list[1,2,3,4]',
        ];

        $rawInput = $this->request->getJSON(true) ?? $this->request->getRawInput();
        $karangTarunaId = $rawInput['karang_taruna_id'] ?? null;

        if (!$this->validateData($rawInput, $rules)) {
            return $this->sendError('Validasi gagal', $this->validator->getErrors(), 422);
        }

        if (!\App\Services\CredentialPolicy::validNewPassword($rawInput['password'])
            || $rawInput['password'] === $rawInput['username']) {
            return $this->sendError('Validasi gagal', ['password' => 'Gunakan password pribadi minimal 12 karakter, maksimal 72 byte, berbeda dari username/default.'], 422);
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
            return $this->sendError('Validasi gagal', ['no_whatsapp' => 'Nomor WhatsApp ini sudah terdaftar di sistem.'], 422);
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

        $memberData = ['username' => $rawInput['username'], 'role_level' => 'anggota',
            'approval_status' => 'pending', 'status_aktif' => 1, 'joined_at' => date('Y-m-d H:i:s')];
        try {
            \App\Services\IdentityCreationService::create((int)$karangTarunaId, $userData, $memberData, true);
        } catch (\DomainException $error) {
            return $this->sendError($error->getMessage(), null, $error->getCode());
        } catch (\Throwable $error) {
            log_message('error', 'Membership identity creation failed');
            return $this->sendError('Registrasi tidak dapat diproses.', null, 500);
        }

        return $this->sendSuccess('Registrasi berhasil. Silakan login.', null, 201);
    }

    public function updateFcmToken()
    {
        $input = $this->request->getJSON(true) ?? $this->request->getRawInput();
        $value = $input['fcm_token'] ?? null;
        $type = $input['device_type'] ?? 'android';
        if (!is_string($value) || trim($value) === '' || strlen($value) > 255
            || !in_array($type, ['android', 'ios', 'web', 'desktop'], true)) {
            return $this->sendError('Token perangkat tidak valid', null, 422);
        }
        $userId = (int)\App\Services\AuthService::getGlobalUserId();
        $token = \App\Services\AuthService::getToken();
        if ($userId < 1 || !$token) return $this->sendError('Unauthenticated', null, 401);
        $now = date('Y-m-d H:i:s');
        $db = \Config\Database::connect();
        try {
            $ok = $db->table('user_devices')->onConstraint('fcm_token')
                ->updateFields(['user_id', 'user_token_id', 'device_type', 'updated_at'])
                ->upsert(['user_id' => (string)$userId, 'user_token_id' => (int)$token['id'],
                    'fcm_token' => $value, 'device_type' => $type, 'created_at' => $now, 'updated_at' => $now]);
            if (!$ok) throw new \RuntimeException('Device binding failed');
        } catch (\Throwable $error) {
            log_message('error', 'Push device binding failed');
            return $this->sendError('Perangkat tidak dapat didaftarkan. Silakan coba lagi.', null, 503);
        }
        return $this->sendSuccess('Token berhasil diupdate');
    }

    public function removeFcmToken()
    {
        $rawInput = $this->request->getJSON(true) ?? $this->request->getRawInput();
        $value = $rawInput['fcm_token'] ?? null;
        if (!is_string($value) || trim($value) === '' || strlen($value) > 255) {
            return $this->sendError('Token perangkat tidak valid', null, 422);
        }

        $user = \App\Services\AuthService::getUser();
        if (!$user) {
            return $this->sendError('Unauthenticated', null, 401);
        }

        $deviceModel = new \App\Models\UserDeviceModel();

        // Only allow deleting token if it belongs to the current user
        $deviceModel->where('user_id', (string)$user['id'])
                    ->where('fcm_token', $rawInput['fcm_token'])
                    ->where('user_token_id', \App\Services\AuthService::getToken()['id'])
                    ->delete();

        return $this->sendSuccess('Token berhasil dihapus');
    }
}
