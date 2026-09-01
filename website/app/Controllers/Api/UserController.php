<?php

namespace App\Controllers\Api;

use App\Models\UserModel;
use App\Services\AuthService;

class UserController extends BaseApiController
{
    private function checkAdmin()
    {
        $user = AuthService::getUser();
        if (!$user || !in_array($user['role_level'], ['admin', 'ketua'])) {
            return false;
        }
        return true;
    }

    private function checkAdminOrPengelola()
    {
        $user = AuthService::getUser();
        if (!$user || !in_array($user['role_level'], ['admin', 'ketua', 'pengelola'])) {
            return false;
        }
        return true;
    }

    private function countActiveAdmins()
    {
        $userModel = new UserModel();
        return $userModel->whereIn('role_level', ['admin', 'ketua'])
                         ->where('status_aktif', 1)
                         ->countAllResults();
    }

    public function index()
    {
        if (!$this->checkAdminOrPengelola()) {
            return $this->sendError('Forbidden', null, 403);
        }

        $currentUser = AuthService::getUser();
        $userModel = new UserModel();
        $builder = $userModel->orderBy('nama_lengkap', 'ASC');
        
        if ($currentUser['role_level'] === 'pengelola') {
            $builder->where('role_level', 'anggota');
        }

        $users = $builder->findAll();

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

        return $this->sendSuccess('Daftar pengguna', $data);
    }

    public function create()
    {
        if (!$this->checkAdmin()) {
            return $this->sendError('Forbidden', null, 403);
        }

        $rules = [
            'nama_lengkap'   => 'required|max_length[255]',
            'nama_panggilan' => 'required|max_length[100]',
            'username'       => 'required|is_unique[users.username]',
            'password'       => 'required|min_length[6]',
            'role_level'     => 'required|in_list[admin,ketua,sekretaris,bendahara,pengelola,anggota]',
            'rt'             => 'permit_empty|in_list[1,2,3,4]'
        ];

        $rawInput = $this->request->getJSON(true) ?? $this->request->getRawInput();

        if (!$this->validateData($rawInput, $rules)) {
            return $this->sendError('Validasi gagal', $this->validator->getErrors(), 422);
        }

        $userModel = new UserModel();
        $userData = [
            'nama_lengkap'   => $rawInput['nama_lengkap'],
            'nama_panggilan' => $rawInput['nama_panggilan'],
            'username'       => $rawInput['username'],
            'password'       => password_hash($rawInput['password'], PASSWORD_BCRYPT),
            'no_whatsapp'    => $rawInput['no_whatsapp'] ?? null,
            'rt'             => (int)($rawInput['rt'] ?? 1),
            'role_level'     => $rawInput['role_level'],
            'status_aktif'   => 1
        ];

        $userModel->insert($userData);
        $userId = $userModel->getInsertID();

        $userData['id'] = $userId;
        unset($userData['password']);

        return $this->sendSuccess('Pengguna berhasil dibuat', $userData, 201);
    }

    public function update($id = null)
    {
        if (!$this->checkAdmin()) {
            return $this->sendError('Forbidden', null, 403);
        }

        $userModel = new UserModel();
        $user = $userModel->find($id);

        if (!$user) {
            return $this->sendError('Pengguna tidak ditemukan', null, 404);
        }

        $rawInput = $this->request->getJSON(true) ?? $this->request->getRawInput();
        $validationData = [];
        if (isset($rawInput['nama_lengkap'])) $validationData['nama_lengkap'] = $rawInput['nama_lengkap'];
        if (isset($rawInput['nama_panggilan'])) $validationData['nama_panggilan'] = $rawInput['nama_panggilan'];
        if (isset($rawInput['no_whatsapp'])) $validationData['no_whatsapp'] = $rawInput['no_whatsapp'];
        if (isset($rawInput['username'])) $validationData['username'] = $rawInput['username'];
        if (isset($rawInput['rt'])) $validationData['rt'] = (int)$rawInput['rt'];

        if (empty($validationData)) {
            return $this->sendError('Tidak ada data yang diubah', null, 422);
        }

        $rules = [];
        if (isset($validationData['nama_lengkap'])) $rules['nama_lengkap'] = 'required|max_length[255]';
        if (isset($validationData['nama_panggilan'])) $rules['nama_panggilan'] = 'required|max_length[100]';
        if (isset($validationData['username'])) $rules['username'] = "required|is_unique[users.username,id,{$id}]";
        if (isset($validationData['rt'])) $rules['rt'] = 'required|in_list[1,2,3,4]';

        if (!$this->validateData($validationData, $rules)) {
            return $this->sendError('Validasi gagal', $this->validator->getErrors(), 422);
        }

        $userModel->update($id, $validationData);

        return $this->sendSuccess('Pengguna berhasil diperbarui');
    }

    public function toggleStatus($id = null)
    {
        if (!$this->checkAdmin()) {
            return $this->sendError('Forbidden', null, 403);
        }

        $userModel = new UserModel();
        $user = $userModel->find($id);

        if (!$user) {
            return $this->sendError('Pengguna tidak ditemukan', null, 404);
        }

        $newStatus = (int)$user['status_aktif'] === 1 ? 0 : 1;

        // Lockout prevention
        if ($newStatus === 0 && in_array($user['role_level'], ['admin', 'ketua'])) {
            if ($this->countActiveAdmins() <= 1) {
                return $this->sendError('Validasi gagal', ['status_aktif' => 'Tidak dapat menonaktifkan akun admin/ketua terakhir yang aktif.'], 400);
            }
        }

        $userModel->update($id, ['status_aktif' => $newStatus]);

        return $this->sendSuccess('Status pengguna berhasil diubah', ['status_aktif' => $newStatus]);
    }

    public function changeRole($id = null)
    {
        if (!$this->checkAdmin()) {
            return $this->sendError('Forbidden', null, 403);
        }

        $userModel = new UserModel();
        $user = $userModel->find($id);

        if (!$user) {
            return $this->sendError('Pengguna tidak ditemukan', null, 404);
        }

        $rawInput = $this->request->getJSON(true) ?? $this->request->getRawInput();
        if (!isset($rawInput['role_level']) || !in_array($rawInput['role_level'], ['admin', 'ketua', 'sekretaris', 'bendahara', 'pengelola', 'anggota'])) {
            return $this->sendError('Validasi gagal', ['role_level' => 'Role tidak valid'], 422);
        }

        $newRole = $rawInput['role_level'];

        // Lockout prevention
        if (in_array($user['role_level'], ['admin', 'ketua']) && !in_array($newRole, ['admin', 'ketua']) && (int)$user['status_aktif'] === 1) {
            if ($this->countActiveAdmins() <= 1) {
                return $this->sendError('Validasi gagal', ['role_level' => 'Tidak dapat mengubah role dari akun admin/ketua terakhir yang aktif.'], 400);
            }
        }

        $userModel->update($id, ['role_level' => $newRole]);

        return $this->sendSuccess('Role pengguna berhasil diubah', ['role_level' => $newRole]);
    }

    public function resetPassword($id = null)
    {
        if (!$this->checkAdminOrPengelola()) {
            return $this->sendError('Forbidden', null, 403);
        }

        $userModel = new UserModel();
        $user = $userModel->find($id);

        if (!$user) {
            return $this->sendError('Pengguna tidak ditemukan', null, 404);
        }

        $currentUser = AuthService::getUser();
        if ($currentUser['role_level'] === 'pengelola') {
            if ($user['role_level'] !== 'anggota') {
                return $this->sendError('Forbidden: Hanya dapat mereset password anggota', null, 403);
            }
        }
        
        $newPassword = 'lugasjosjis';

        $userModel->update($id, [
            'password' => password_hash($newPassword, PASSWORD_BCRYPT),
            'password_must_change' => 1
        ]);

        return $this->sendSuccess('Password pengguna berhasil direset ke password default.');
    }

    public function rolesSummary()
    {
        if (!$this->checkAdmin()) {
            return $this->sendError('Forbidden', null, 403);
        }

        $userModel = new UserModel();
        
        $totalAdmin = $userModel->whereIn('role_level', ['admin', 'ketua'])->countAllResults();
        $totalPengelola = $userModel->where('role_level', 'pengelola')->countAllResults();
        $totalAnggota = $userModel->where('role_level', 'anggota')->countAllResults();

        $data = [
            'admin' => $totalAdmin,
            'pengelola' => $totalPengelola,
            'anggota' => $totalAnggota,
            'total' => $totalAdmin + $totalPengelola + $totalAnggota
        ];

        return $this->sendSuccess('Ringkasan role', $data);
    }
}
