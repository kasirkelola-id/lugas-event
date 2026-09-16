<?php

namespace App\Controllers\Superadmin;

use App\Controllers\BaseController;
use App\Models\SettingModel;

class SettingController extends BaseController
{
    public function index()
    {
        $settingModel = new SettingModel();
        // karang_taruna_id = 0 is for global/superadmin settings
        $settings = $settingModel->where('karang_taruna_id', 0)->findAll();
        
        $data = [
            'title' => 'Pengaturan Global',
            'settings' => []
        ];
        
        foreach ($settings as $setting) {
            $data['settings'][$setting['setting_key']] = $setting['setting_value'];
        }

        return view('superadmin/settings/index', $data);
    }

    public function update()
    {
        $settingModel = new SettingModel();
        $input = $this->request->getPost();

        if (isset($input['temporary_reset_password']) && strlen(trim($input['temporary_reset_password'])) > 0) {
            if (strlen(trim($input['temporary_reset_password'])) < 8) {
                return redirect()->back()->with('error', 'Password sementara harus minimal 8 karakter.');
            }
            
            $existing = $settingModel->where('karang_taruna_id', 0)->where('setting_key', 'temporary_reset_password')->first();
            if ($existing) {
                $settingModel->where('karang_taruna_id', 0)->where('setting_key', 'temporary_reset_password')->set(['setting_value' => trim($input['temporary_reset_password'])])->update();
            } else {
                $settingModel->insert(['karang_taruna_id' => 0, 'setting_key' => 'temporary_reset_password', 'setting_value' => trim($input['temporary_reset_password']), 'description' => 'Password reset sementara global']);
            }
        }

        // Process Android App Update settings
        $appUpdateKeys = [
            'android_version_name' => 'Versi aplikasi Android terbaru untuk tampilan',
            'android_version_code' => 'Version Code Android terbaru (harus angka)',
            'android_download_url' => 'URL link download APK',
            'android_release_notes' => 'Catatan rilis update aplikasi'
        ];

        // Validation for update_enabled
        $updateEnabled = isset($input['android_update_enabled']) && $input['android_update_enabled'] === 'true' ? 'true' : 'false';
        
        // If update is enabled, validate required fields
        if ($updateEnabled === 'true') {
            if (empty(trim($input['android_version_name'] ?? ''))) {
                return redirect()->back()->with('error', 'Versi Terbaru wajib diisi jika Update Aktif.');
            }
            if (empty(trim($input['android_version_code'] ?? '')) || !is_numeric($input['android_version_code']) || intval($input['android_version_code']) < 1) {
                return redirect()->back()->with('error', 'Version Code wajib diisi angka valid (>= 1) jika Update Aktif.');
            }
            if (empty(trim($input['android_download_url'] ?? '')) || !filter_var($input['android_download_url'], FILTER_VALIDATE_URL)) {
                return redirect()->back()->with('error', 'Link Download APK wajib diisi URL valid jika Update Aktif.');
            }
        }

        foreach ($appUpdateKeys as $key => $desc) {
            if (isset($input[$key])) {
                $val = trim($input[$key]);
                $existing = $settingModel->where('karang_taruna_id', 0)->where('setting_key', $key)->first();
                if ($existing) {
                    $settingModel->where('karang_taruna_id', 0)->where('setting_key', $key)->set(['setting_value' => $val])->update();
                } else {
                    $settingModel->insert(['karang_taruna_id' => 0, 'setting_key' => $key, 'setting_value' => $val, 'description' => $desc]);
                }
            }
        }
        
        // Save the enabled status
        $existing = $settingModel->where('karang_taruna_id', 0)->where('setting_key', 'android_update_enabled')->first();
        if ($existing) {
            $settingModel->where('karang_taruna_id', 0)->where('setting_key', 'android_update_enabled')->set(['setting_value' => $updateEnabled])->update();
        } else {
            $settingModel->insert(['karang_taruna_id' => 0, 'setting_key' => 'android_update_enabled', 'setting_value' => $updateEnabled, 'description' => 'Status apakah update wajib diaktifkan']);
        }

        return redirect()->back()->with('success', 'Pengaturan berhasil disimpan.');
    }
}
