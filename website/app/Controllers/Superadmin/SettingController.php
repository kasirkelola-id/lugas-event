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
            if ($setting['setting_key'] === 'temporary_reset_password') {
                continue; // Deprecated shared credential is never displayed again.
            }
            $data['settings'][$setting['setting_key']] = $setting['setting_value'];
        }

        return view('superadmin/settings/index', $data);
    }

    public function update()
    {
        $settingModel = new SettingModel();
        $input = $this->request->getPost();

        // Process Android App Update settings
        $appUpdateKeys = [
            'android_version_name' => 'Versi aplikasi Android terbaru untuk tampilan',
            'android_version_code' => 'Version Code Android terbaru (harus angka)',
            'android_download_url' => 'URL link download APK',
            'android_release_notes' => 'Catatan rilis update aplikasi'
        ];

        $policy = \App\Services\SettingsPolicy::class;
        $enabledInput = $input['android_update_enabled'] ?? 'false';
        if (!in_array($enabledInput, ['true', 'false'], true)) return redirect()->back()->with('error', 'Status update tidak valid.');
        $updateEnabled = $enabledInput;
        if (isset($input['android_version_name']) && !$policy::text($input['android_version_name'], 64, $updateEnabled === 'true')) {
            return redirect()->back()->with('error', 'Versi Terbaru wajib diisi dengan maksimal 64 karakter.');
        }
        if (($updateEnabled === 'true' && !isset($input['android_version_name']))
            || (isset($input['android_release_notes']) && !$policy::text($input['android_release_notes'], 4096, false))) {
            return redirect()->back()->with('error', 'Versi atau catatan rilis tidak valid.');
        }
        if (($updateEnabled === 'true' || isset($input['android_version_code']))
            && !$policy::integer($input['android_version_code'] ?? null, 1, 2100000000)) {
            return redirect()->back()->with('error', 'Version Code wajib diisi angka valid (>= 1).');
        }
        $url = $input['android_download_url'] ?? '';
        if (($updateEnabled === 'true' || $url !== '') && !$policy::trustedDownloadUrl($url)) {
            return redirect()->back()->with('error', 'Link Download APK wajib diisi URL valid HTTPS pada host tepercaya.');
        }
        $db = \Config\Database::connect();
        if (!$db->transBegin()) return redirect()->back()->with('error', 'Pengaturan tidak dapat disimpan.');
        try {
            foreach ($appUpdateKeys as $key => $desc) {
                if (isset($input[$key])) {
                    $val = trim($input[$key]);
                    $existing = $settingModel->where('karang_taruna_id', 0)->where('setting_key', $key)->first();
                    if ($existing) {
                        if (!$settingModel->where('karang_taruna_id', 0)->where('setting_key', $key)->set(['setting_value' => $val])->update()) throw new \RuntimeException('Settings write failed');
                    } else {
                        if (!$settingModel->insert(['karang_taruna_id' => 0, 'setting_key' => $key, 'setting_value' => $val, 'description' => $desc])) throw new \RuntimeException('Settings write failed');
                    }
                }
            }

            // Save the enabled status
            $existing = $settingModel->where('karang_taruna_id', 0)->where('setting_key', 'android_update_enabled')->first();
            if ($existing) {
                if (!$settingModel->where('karang_taruna_id', 0)->where('setting_key', 'android_update_enabled')->set(['setting_value' => $updateEnabled])->update()) throw new \RuntimeException('Settings write failed');
            } else {
                if (!$settingModel->insert(['karang_taruna_id' => 0, 'setting_key' => 'android_update_enabled', 'setting_value' => $updateEnabled, 'description' => 'Status apakah update wajib diaktifkan'])) throw new \RuntimeException('Settings write failed');
            }

            if (!$db->transStatus() || !$db->transCommit()) throw new \RuntimeException('Settings commit failed');
        } catch (\Throwable $error) {
            $db->transRollback();
            return redirect()->back()->with('error', 'Pengaturan tidak dapat disimpan.');
        }
        \App\Services\SettingService::clearCache();
        return redirect()->back()->with('success', 'Pengaturan berhasil disimpan.');
    }
}
