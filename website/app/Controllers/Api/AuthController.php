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
        $user = $userModel->where('username', $username)
                          ->where('karang_taruna_id', $karangTarunaId)
                          ->first();

        if (!$user || !password_verify($password, (string)$user['password'])) {
            return $this->sendError('Username atau password salah.', null, 401);
        }

        if ($user['status_aktif'] != 1) {
            return $this->sendError('Akun tidak aktif.', null, 401);
        }

        // Generate Token
        $plainToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $plainToken);

        $tokenModel = new UserTokenModel();
        
        // Expiration in 30 days
        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));

        $tokenModel->insert([
            'karang_taruna_id' => $karangTarunaId,
            'user_id'          => $user['id'],
            'token_hash'       => $tokenHash,
            'expires_at'       => $expiresAt,
            'created_at'       => date('Y-m-d H:i:s'),
        ]);

        // Build Response User without sensitive data
        $userData = [
            'id'             => (int)$user['id'],
            'karang_taruna_id' => (int)$user['karang_taruna_id'],
            'nama_lengkap'   => $user['nama_lengkap'],
            'nama_panggilan' => $user['nama_panggilan'],
            'username'       => $user['username'],
            'no_whatsapp'    => $user['no_whatsapp'],
            'rt'             => (int)($user['rt'] ?? 1),
            'role_level'     => $user['role_level'],
            'status_aktif'   => (int)$user['status_aktif'],
            'password_must_change' => (int)($user['password_must_change'] ?? 0) === 1,
        ];

        return $this->sendSuccess('Login berhasil', [
            'token' => $plainToken,
            'user'  => $userData
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

        $userData = [
            'id'             => (int)$user['id'],
            'karang_taruna_id' => (int)$user['karang_taruna_id'],
            'nama_lengkap'   => $user['nama_lengkap'],
            'nama_panggilan' => $user['nama_panggilan'],
            'username'       => $user['username'],
            'no_whatsapp'    => $user['no_whatsapp'],
            'rt'             => (int)($user['rt'] ?? 1),
            'role_level'     => $user['role_level'],
            'status_aktif'   => (int)$user['status_aktif'],
            'password_must_change' => (int)($user['password_must_change'] ?? 0) === 1,
        ];

        return $this->sendSuccess('Data user', $userData);
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

        if (!$this->validateData($rawInput, $rules)) {
            return $this->sendError('Validasi gagal', $this->validator->getErrors(), 422);
        }

        $userModel = new UserModel();
        
        // Manual check for unique username within tenant
        $existing = $userModel->where('username', $rawInput['username'])
                              ->where('karang_taruna_id', $rawInput['karang_taruna_id'])
                              ->first();
        if ($existing) {
            return $this->sendError('Validasi gagal', ['username' => 'Username ini sudah digunakan di Karang Taruna Anda.'], 422);
        }

        $userData = [
            'karang_taruna_id' => $rawInput['karang_taruna_id'],
            'nama_lengkap'     => $rawInput['nama_lengkap'],
            'nama_panggilan'   => $rawInput['nama_panggilan'],
            'username'         => $rawInput['username'],
            'password'         => password_hash($rawInput['password'], PASSWORD_BCRYPT),
            'no_whatsapp'      => $rawInput['no_whatsapp'],
            'rt'               => (int)($rawInput['rt'] ?? 1),
            'role_level'       => 'anggota',
            'status_aktif'     => 1,
            'password_must_change' => 0
        ];

        $userModel->insert($userData);
        
        return $this->sendSuccess('Registrasi berhasil. Silakan login.', null, 201);
    }
}
