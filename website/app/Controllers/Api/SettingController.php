<?php

namespace App\Controllers\Api;

use App\Models\SettingModel;
use App\Services\AuthService;

class SettingController extends BaseApiController
{
    private function checkAdminAtauKetua()
    {
        $user = AuthService::getUser();
        if (!$user || !in_array($user['role_level'], ['admin', 'ketua'])) {
            return false;
        }
        return true;
    }

    public function index()
    {
        // Publicly readable if authenticated, so any user can fetch settings for validation
        $user = AuthService::getUser();
        if (!$user) {
            return $this->sendError('Unauthorized', null, 401);
        }

        $settingModel = new SettingModel();
        $settings = $settingModel->findAll();

        $data = [];
        foreach ($settings as $setting) {
            $data[$setting['setting_key']] = $setting['setting_value'];
        }

        return $this->sendSuccess('Daftar Pengaturan', $data);
    }

    public function update()
    {
        if (!$this->checkAdminAtauKetua()) {
            return $this->sendError('Forbidden: Akses khusus Admin dan Ketua.', null, 403);
        }

        $rawInput = $this->request->getJSON(true) ?? $this->request->getRawInput();
        
        if (empty($rawInput) || !is_array($rawInput)) {
             return $this->sendError('Validasi gagal', ['settings' => 'Payload tidak valid.'], 422);
        }

        $settingModel = new SettingModel();
        
        $db = \Config\Database::connect();
        $db->transStart();
        
        foreach ($rawInput as $key => $value) {
            $existing = $settingModel->find($key);
            if ($existing) {
                $settingModel->update($key, ['setting_value' => (string)$value]);
            }
        }
        
        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->sendError('Terjadi kesalahan saat menyimpan pengaturan.', null, 500);
        }

        return $this->sendSuccess('Pengaturan berhasil diperbarui.');
    }
}
