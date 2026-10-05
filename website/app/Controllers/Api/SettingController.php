<?php

namespace App\Controllers\Api;

use App\Models\SettingModel;
use App\Services\AuthService;

class SettingController extends BaseApiController
{
    // checkAdminAtauKetua removed

    public function index()
    {
        // Publicly readable if authenticated, so any user can fetch settings for validation
        $tenantId = AuthService::getTenantId();
        if (!$tenantId) {
            return $this->sendError('Unauthorized', null, 401);
        }

        $settingModel = new SettingModel();
        $settings = $settingModel->where('karang_taruna_id', $tenantId)->findAll();

        $data = [];
        foreach ($settings as $setting) {
            $data[$setting['setting_key']] = $setting['setting_value'];
        }

        return $this->sendSuccess('Daftar Pengaturan', $data);
    }

    public function update($id = null)
    {
        if (!AuthService::can('settings.manage')) {
            return $this->sendError('Forbidden: Akses khusus Admin dan Ketua.', null, 403);
        }

        $rawInput = $this->request->getJSON(true) ?? $this->request->getRawInput();

        if (empty($rawInput) || !is_array($rawInput)) {
             return $this->sendError('Validasi gagal', ['settings' => 'Payload tidak valid.'], 422);
        }

        $bounds = \App\Services\SettingsPolicy::TENANT_BOUNDS;
        if (count($rawInput) > count($bounds)) return $this->sendError('Terlalu banyak pengaturan.', null, 422);
        foreach ($rawInput as $key => $value) {
            if (!isset($bounds[$key]) || !\App\Services\SettingsPolicy::integer($value, ...$bounds[$key])) {
                return $this->sendError('Pengaturan tidak valid.', ['settings' => 'Kunci atau nilai pengaturan tidak valid.'], 422);
            }
        }
        $db = \Config\Database::connect();
        if (!$db->transBegin()) return $this->sendError('Pengaturan tidak dapat disimpan.', null, 503);
        try {
            $tenantId = AuthService::getTenantId();
            foreach ($rawInput as $key => $value) {
                if (!$db->table('settings')->onConstraint(['setting_key', 'karang_taruna_id'])
                    ->updateFields(['setting_value', 'updated_at'])->upsert([
                        'karang_taruna_id' => $tenantId, 'setting_key' => $key, 'setting_value' => (string)(int)$value,
                        'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
                    ])) throw new \RuntimeException('Settings write failed');
            }
            if (!$db->transStatus() || !$db->transCommit()) throw new \RuntimeException('Settings commit failed');
        } catch (\Throwable $error) {
            $db->transRollback();
            return $this->sendError('Pengaturan tidak dapat disimpan.', null, 503);
        }
        \App\Services\SettingService::clearCache();

        return $this->sendSuccess('Pengaturan berhasil diperbarui.');
    }
}
