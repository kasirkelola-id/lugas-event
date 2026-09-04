<?php

namespace App\Controllers\Api;

use App\Models\UserModel;
use App\Services\AuthService;

class ProfileController extends BaseApiController
{
    public function updateProfile()
    {
        $userId = AuthService::getGlobalUserId();
        if (!$userId) {
            return $this->sendError('Unauthorized', null, 401);
        }

        $rawInput = $this->request->getJSON(true) ?? $this->request->getRawInput();
        $validationData = [];
        
        // Only allow these fields to be updated
        if (isset($rawInput['nama_lengkap'])) $validationData['nama_lengkap'] = $rawInput['nama_lengkap'];
        if (isset($rawInput['nama_panggilan'])) $validationData['nama_panggilan'] = $rawInput['nama_panggilan'];
        if (isset($rawInput['username'])) $validationData['username'] = $rawInput['username'];
        if (isset($rawInput['no_whatsapp'])) $validationData['no_whatsapp'] = $rawInput['no_whatsapp'];
        if (isset($rawInput['rt'])) $validationData['rt'] = (int)$rawInput['rt'];

        if (empty($validationData)) {
            return $this->sendError('Tidak ada data yang diubah', null, 422);
        }

        $rules = [];
        if (isset($validationData['nama_lengkap'])) $rules['nama_lengkap'] = 'required|max_length[255]';
        if (isset($validationData['nama_panggilan'])) $rules['nama_panggilan'] = 'required|max_length[100]';
        if (isset($validationData['username'])) {
            $rules['username'] = "required|max_length[100]";
        }
        if (isset($validationData['rt'])) $rules['rt'] = 'required|in_list[1,2,3,4]';
        
        if (!$this->validateData($validationData, $rules)) {
            return $this->sendError('Validasi gagal', $this->validator->getErrors(), 422);
        }

        if (isset($validationData['username'])) {
            $tenantId = AuthService::getTenantId();
            $memberModel = new \App\Models\OrganizationMemberModel();
            
            // Validate unique inside tenant namespace
            $existing = $memberModel->where('username', $validationData['username'])
                                    ->where('karang_taruna_id', $tenantId)
                                    ->where('user_id !=', $userId)
                                    ->first();
            
            if ($existing) {
                return $this->sendError('Validasi gagal', ['username' => 'Username ini sudah digunakan di Karang Taruna Anda.'], 409);
            }
            
            // Get current membership to update
            $membership = $memberModel->where('user_id', $userId)
                                      ->where('karang_taruna_id', $tenantId)
                                      ->first();
                                      
            if ($membership) {
                $memberModel->update($membership['id'], ['username' => $validationData['username']]);
            }
            
            // Remove username from array so it doesn't update the global users table
            unset($validationData['username']);
        }

        if (!empty($validationData)) {
            $userModel = new UserModel();
            $userModel->update($userId, $validationData);
        }

        return $this->sendSuccess('Profil berhasil diperbarui');
    }

    public function changePassword()
    {
        $userId = AuthService::getGlobalUserId();
        if (!$userId) {
            return $this->sendError('Unauthorized', null, 401);
        }

        $rawInput = $this->request->getJSON(true) ?? $this->request->getRawInput();

        $rules = [
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

        $userModel->update($userId, [
            'password' => password_hash($rawInput['new_password'], PASSWORD_BCRYPT),
            'password_must_change' => 0
        ]);

        return $this->sendSuccess('Password berhasil diubah');
    }

    public function updateProfilePhoto()
    {
        $userId = AuthService::getGlobalUserId();
        if (!$userId) {
            return $this->sendError('Unauthorized', null, 401);
        }

        $file = $this->request->getFile('photo');
        
        // Handle Delete Photo
        if (strtolower($this->request->getMethod()) === 'delete') {
            $userModel = new UserModel();
            $user = $userModel->find($userId);
            
            // Delete old photo safely ONLY if DB update is successful
            $oldPath = $user['profile_photo'] ?? null;
            if ($userModel->update($userId, ['profile_photo' => null])) {
                $this->safeDeleteOldPhoto($oldPath);
            }
            
            return $this->sendSuccess('Foto profil berhasil dihapus', null);
        }

        if (!$file || !$file->isValid()) {
            return $this->sendError('File tidak valid atau tidak ada file yang diunggah', null, 400);
        }

        $validationRule = [
            'photo' => [
                'label' => 'Photo',
                'rules' => 'uploaded[photo]'
                    . '|is_image[photo]'
                    . '|mime_in[photo,image/jpeg,image/png,image/webp]'
                    . '|max_size[photo,5120]', // max 5MB
            ],
        ];

        if (!$this->validate($validationRule)) {
            return $this->sendError('Validasi gambar gagal', $this->validator->getErrors(), 422);
        }

        $newName = $file->getRandomName();
        $uploadDir = FCPATH . 'uploads/users/profile/';
        
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        try {
            // Resize and crop to 512x512
            $image = \Config\Services::image()
                ->withFile($file->getTempName())
                ->fit(512, 512, 'center')
                ->save($uploadDir . $newName, 85);
                
            $photoPath = 'uploads/users/profile/' . $newName;
        } catch (\Exception $e) {
            log_message('error', 'Profile photo processing failed: ' . $e->getMessage());
            $file->move($uploadDir, $newName);
            $photoPath = 'uploads/users/profile/' . $newName;
        }

        $userModel = new UserModel();
        $user = $userModel->find($userId);
        $oldPath = $user['profile_photo'] ?? null;
        
        // Update DB first
        if ($userModel->update($userId, ['profile_photo' => $photoPath])) {
            // DB success, delete old photo safely
            $this->safeDeleteOldPhoto($oldPath);
        } else {
            // DB failure, clean up the newly generated file
            $newFullPath = FCPATH . $photoPath;
            if (file_exists($newFullPath)) {
                @unlink($newFullPath);
            }
            return $this->sendError('Gagal memperbarui database foto profil', null, 500);
        }

        return $this->sendSuccess('Foto profil berhasil diperbarui', [
            'profile_photo_url' => base_url($photoPath)
        ]);
    }

    private function safeDeleteOldPhoto(?string $path)
    {
        if (empty($path)) return;
        
        // Ensure it's in the managed uploads directory and doesn't have directory traversal
        if (strpos($path, 'uploads/users/profile/') !== 0) return;
        if (strpos($path, '..') !== false) return;
        
        $fullPath = FCPATH . $path;
        if (file_exists($fullPath)) {
            @unlink($fullPath);
        }
    }
}
