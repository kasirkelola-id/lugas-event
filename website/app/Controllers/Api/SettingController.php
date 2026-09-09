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

        // Validate settings bounds
        $errors = [];
        if (isset($rawInput['attendance_before_minutes'])) {
            $val = (int)$rawInput['attendance_before_minutes'];
            if ($val < 0 || $val > 240) {
                $errors['attendance_before_minutes'] = 'Batas waktu absen sebelum acara harus antara 0 dan 240 menit.';
            }
        }
        if (isset($rawInput['attendance_after_minutes'])) {
            $val = (int)$rawInput['attendance_after_minutes'];
            if ($val < 0 || $val > 240) {
                $errors['attendance_after_minutes'] = 'Batas waktu absen sesudah acara harus antara 0 dan 240 menit.';
            }
        }
        if (isset($rawInput['default_geofence_radius'])) {
            $val = (int)$rawInput['default_geofence_radius'];
            if ($val <= 0 || $val > 5000) {
                $errors['default_geofence_radius'] = 'Radius lokasi default harus lebih dari 0 dan maksimal 5000 meter.';
            }
        }
        
        // Also prevent Kas Backdate limit from being extreme if present
        if (isset($rawInput['kas_backdate_limit'])) {
            $val = (int)$rawInput['kas_backdate_limit'];
            if ($val < 0 || $val > 365) {
                $errors['kas_backdate_limit'] = 'Batas hari backdate kas harus antara 0 dan 365 hari.';
            }
        }

        if (!empty($errors)) {
            return $this->sendError('Validasi gagal', $errors, 422);
        }

        $settingModel = new SettingModel();

        $db = \Config\Database::connect();
        $db->transStart();

        $tenantId = AuthService::getTenantId();
        foreach ($rawInput as $key => $value) {
            $existing = $settingModel->where('karang_taruna_id', $tenantId)->where('setting_key', $key)->first();
            if ($existing) {
                $settingModel->update($existing['id'], ['setting_value' => (string)$value]);
            } else {
                $settingModel->insert([
                    'karang_taruna_id' => $tenantId,
                    'setting_key' => $key,
                    'setting_value' => (string)$value
                ]);
            }
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->sendError('Terjadi kesalahan saat menyimpan pengaturan.', null, 500);
        }

        return $this->sendSuccess('Pengaturan berhasil diperbarui.');
    }
}
