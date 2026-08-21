<?php

namespace App\Controllers\Api;

use App\Models\UserModel;
use App\Models\UserTokenModel;

class AuthController extends BaseApiController
{
    public function login()
    {
        $rules = [
            'username' => 'required',
            'password' => 'required'
        ];

        if (!$this->validate($rules)) {
            return $this->sendError('Validasi gagal', $this->validator->getErrors(), 422);
        }

        $username = $this->request->getVar('username');
        $password = $this->request->getVar('password');

        $userModel = new UserModel();
        $user = $userModel->where('username', $username)->first();

        if (!$user || !password_verify($password, (string)$user['password'])) {
            return $this->sendError('Username atau password salah.', null, 401);
        }

        if ($user['status_aktif'] != 1) {
            return $this->sendError('Username atau password salah.', null, 401);
        }

        // Generate Token
        $plainToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $plainToken);

        $tokenModel = new UserTokenModel();
        
        // Expiration in 30 days
        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));

        $tokenModel->insert([
            'user_id'    => $user['id'],
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Build Response User without sensitive data
        $userData = [
            'id'             => (int)$user['id'],
            'nama_lengkap'   => $user['nama_lengkap'],
            'nama_panggilan' => $user['nama_panggilan'],
            'username'       => $user['username'],
            'no_whatsapp'    => $user['no_whatsapp'],
            'role_level'     => $user['role_level'],
            'status_aktif'   => (int)$user['status_aktif'],
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
            'nama_lengkap'   => $user['nama_lengkap'],
            'nama_panggilan' => $user['nama_panggilan'],
            'username'       => $user['username'],
            'no_whatsapp'    => $user['no_whatsapp'],
            'role_level'     => $user['role_level'],
            'status_aktif'   => (int)$user['status_aktif'],
        ];

        return $this->sendSuccess('Data user', $userData);
    }
}
