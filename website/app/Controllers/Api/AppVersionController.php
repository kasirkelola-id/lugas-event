<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\SettingModel;

class AppVersionController extends BaseController
{
    public function index()
    {
        $settingModel = new SettingModel();
        // Only fetch global settings (karang_taruna_id = 0)
        $settings = $settingModel->where('karang_taruna_id', 0)->findAll();
        
        $data = [
            'platform' => 'android',
            'update_enabled' => false,
            'version_name' => '',
            'version_code' => 1,
            'download_url' => '',
            'release_notes' => ''
        ];

        foreach ($settings as $s) {
            $key = $s['setting_key'];
            $val = $s['setting_value'];
            
            if ($key === 'android_update_enabled') {
                $data['update_enabled'] = ($val === 'true');
            } elseif ($key === 'android_version_name') {
                $data['version_name'] = $val;
            } elseif ($key === 'android_version_code') {
                $data['version_code'] = (int)$val;
            } elseif ($key === 'android_download_url') {
                $data['download_url'] = $val;
            } elseif ($key === 'android_release_notes') {
                $data['release_notes'] = $val;
            }
        }

        return $this->response->setJSON([
            'status' => true,
            'data' => $data
        ]);
    }
}
