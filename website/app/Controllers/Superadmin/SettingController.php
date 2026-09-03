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

        if (!isset($input['temporary_reset_password']) || strlen(trim($input['temporary_reset_password'])) < 8) {
            return redirect()->back()->with('error', 'Password sementara harus minimal 8 karakter.');
        }

        $existing = $settingModel->where('karang_taruna_id', 0)->where('setting_key', 'temporary_reset_password')->first();
        
        if ($existing) {
            $settingModel->where('karang_taruna_id', 0)
                         ->where('setting_key', 'temporary_reset_password')
                         ->set(['setting_value' => trim($input['temporary_reset_password'])])
                         ->update();
        } else {
            $settingModel->insert([
                'karang_taruna_id' => 0,
                'setting_key' => 'temporary_reset_password',
                'setting_value' => trim($input['temporary_reset_password']),
                'description' => 'Password reset sementara global'
            ]);
        }

        return redirect()->back()->with('success', 'Pengaturan berhasil disimpan.');
    }
}
