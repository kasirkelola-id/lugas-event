<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAndroidUpdateSettings extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();
        
        $settings = [
            [
                'karang_taruna_id' => 0,
                'setting_key' => 'android_version_name',
                'setting_value' => '1.0.0',
                'description' => 'Versi aplikasi Android terbaru untuk tampilan (misal 1.0.0)',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'karang_taruna_id' => 0,
                'setting_key' => 'android_version_code',
                'setting_value' => '1',
                'description' => 'Version Code Android terbaru (harus angka, misal 1)',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'karang_taruna_id' => 0,
                'setting_key' => 'android_download_url',
                'setting_value' => '',
                'description' => 'URL link download APK',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'karang_taruna_id' => 0,
                'setting_key' => 'android_release_notes',
                'setting_value' => '',
                'description' => 'Catatan rilis update aplikasi',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'karang_taruna_id' => 0,
                'setting_key' => 'android_update_enabled',
                'setting_value' => 'false',
                'description' => 'Status apakah update wajib diaktifkan',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
        ];

        foreach ($settings as $setting) {
            // Cek jika sudah ada
            $existing = $db->table('settings')
                           ->where('karang_taruna_id', 0)
                           ->where('setting_key', $setting['setting_key'])
                           ->countAllResults();
                           
            if ($existing == 0) {
                $db->table('settings')->insert($setting);
            }
        }
    }

    public function down()
    {
        $db = \Config\Database::connect();
        $db->table('settings')->where('karang_taruna_id', 0)
             ->whereIn('setting_key', [
                 'android_version_name', 
                 'android_version_code', 
                 'android_download_url', 
                 'android_release_notes', 
                 'android_update_enabled'
             ])->delete();
    }
}
