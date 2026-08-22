<?php

namespace App\Controllers\Api;

use App\Models\UserModel;
use App\Services\AuthService;

class ProfileController extends BaseApiController
{
    public function update()
    {
        $user = AuthService::getUser();
        if (!$user) {
            return $this->sendError('Unauthorized', null, 401);
        }

        $rawInput = $this->request->getJSON(true) ?? $this->request->getRawInput();
        $validationData = [];
        
        // Only allow these fields to be updated
        if (isset($rawInput['nama_lengkap'])) $validationData['nama_lengkap'] = $rawInput['nama_lengkap'];
        if (isset($rawInput['nama_panggilan'])) $validationData['nama_panggilan'] = $rawInput['nama_panggilan'];
        if (isset($rawInput['username'])) $validationData['username'] = $rawInput['username'];
        if (isset($rawInput['no_whatsapp'])) $validationData['no_whatsapp'] = $rawInput['no_whatsapp'];

        if (empty($validationData)) {
            return $this->sendError('Tidak ada data yang diubah', null, 422);
        }

        $rules = [];
        if (isset($validationData['nama_lengkap'])) $rules['nama_lengkap'] = 'required|max_length[255]';
        if (isset($validationData['nama_panggilan'])) $rules['nama_panggilan'] = 'required|max_length[100]';
        if (isset($validationData['username'])) {
            $rules['username'] = "required|max_length[100]|is_unique[users.username,id,{$user['id']}]";
        }
        
        if (!$this->validateData($validationData, $rules)) {
            return $this->sendError('Validasi gagal', $this->validator->getErrors(), 422);
        }

        $userModel = new UserModel();
        $userModel->update($user['id'], $validationData);

        return $this->sendSuccess('Profil berhasil diperbarui');
    }

    public function updatePassword()
    {
        $user = AuthService::getUser();
        if (!$user) {
            return $this->sendError('Unauthorized', null, 401);
        }

        $rawInput = $this->request->getJSON(true) ?? $this->request->getRawInput();

        $rules = [
            'old_password' => 'required',
            'new_password' => 'required|min_length[6]',
            'confirm_password' => 'required|matches[new_password]'
        ];

        if (!$this->validateData($rawInput, $rules)) {
            return $this->sendError('Validasi gagal', $this->validator->getErrors(), 422);
        }

        if ($rawInput['new_password'] === 'lugasjosjis') {
             return $this->sendError('Validasi gagal', ['new_password' => 'Tidak boleh menggunakan password default.'], 422);
        }

        $userModel = new UserModel();
        $dbUser = $userModel->find($user['id']);

        if (!password_verify($rawInput['old_password'], (string)$dbUser['password'])) {
            return $this->sendError('Validasi gagal', ['old_password' => 'Password lama salah.'], 422);
        }

        $userModel->update($user['id'], [
            'password' => password_hash($rawInput['new_password'], PASSWORD_BCRYPT)
        ]);

        return $this->sendSuccess('Password berhasil diubah');
    }
}
