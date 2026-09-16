<?php

namespace Tests\Api;

use Tests\Support\BaseTest;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\DatabaseTestTrait;
use App\Models\SettingModel;
use App\Models\SuperadminModel;

class AppVersionTest extends BaseTest
{
    use FeatureTestTrait;
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $migrateOnce = true;
    protected $refresh = false;
    protected $namespace = 'App';
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $settingModel = new SettingModel();
        // Bersihkan pengaturan global untuk test
        $settingModel->where('karang_taruna_id', 0)->delete();
    }

    public function testPublicEndpointReturnsValidFormatAndNoSensitiveData()
    {
        $settingModel = new SettingModel();
        $settingModel->insertBatch([
            ['karang_taruna_id' => 0, 'setting_key' => 'android_version_name', 'setting_value' => '1.5.0'],
            ['karang_taruna_id' => 0, 'setting_key' => 'android_version_code', 'setting_value' => '15'],
            ['karang_taruna_id' => 0, 'setting_key' => 'android_download_url', 'setting_value' => 'https://example.com/app.apk'],
            ['karang_taruna_id' => 0, 'setting_key' => 'android_release_notes', 'setting_value' => 'Test Note'],
            ['karang_taruna_id' => 0, 'setting_key' => 'android_update_enabled', 'setting_value' => 'true'],
            ['karang_taruna_id' => 0, 'setting_key' => 'temporary_reset_password', 'setting_value' => 'secret123'], // Sensitive!
        ]);

        $result = $this->get('api/app-version');
        $result->assertStatus(200);
        $result->assertJSONExact([
            'status' => true,
            'data' => [
                'platform' => 'android',
                'update_enabled' => true,
                'version_name' => '1.5.0',
                'version_code' => 15,
                'download_url' => 'https://example.com/app.apk',
                'release_notes' => 'Test Note'
            ]
        ]);

        $json = json_decode($result->getJSON(), true);
        $this->assertArrayNotHasKey('temporary_reset_password', $json['data']);
    }

    public function testDisabledUpdateState()
    {
        $settingModel = new SettingModel();
        $settingModel->insertBatch([
            ['karang_taruna_id' => 0, 'setting_key' => 'android_update_enabled', 'setting_value' => 'false'],
            ['karang_taruna_id' => 0, 'setting_key' => 'android_version_code', 'setting_value' => '10'],
        ]);

        $result = $this->get('api/app-version');
        $result->assertStatus(200);
        
        $json = json_decode($result->getJSON(), true);
        $this->assertFalse($json['data']['update_enabled']);
        $this->assertEquals(10, $json['data']['version_code']);
    }

    public function testSuperadminCanUpdateSettings()
    {
        // Mock session superadmin login
        $superadmin = [
            'is_superadmin_logged_in' => true,
            'superadmin_id' => 1,
            'superadmin_username' => 'superadmin'
        ];
        
        $result = $this->withSession($superadmin)->post('superadmin/settings', [
            'android_update_enabled' => 'true',
            'android_version_name' => '2.0',
            'android_version_code' => '20',
            'android_download_url' => 'https://github.com/test/app.apk',
            'android_release_notes' => 'New release'
        ]);
        
        $result->assertRedirect();
        
        $settingModel = new SettingModel();
        $this->assertEquals('true', $settingModel->where(['karang_taruna_id' => 0, 'setting_key' => 'android_update_enabled'])->first()['setting_value']);
        $this->assertEquals('20', $settingModel->where(['karang_taruna_id' => 0, 'setting_key' => 'android_version_code'])->first()['setting_value']);
    }

    public function testSuperadminUpdateValidation()
    {
        $superadmin = [
            'is_superadmin_logged_in' => true,
            'superadmin_id' => 1,
            'superadmin_username' => 'superadmin'
        ];
        
        // Enabled tapi URL kosong
        $result = $this->withSession($superadmin)->post('superadmin/settings', [
            'android_update_enabled' => 'true',
            'android_version_name' => '2.0',
            'android_version_code' => '20',
            'android_download_url' => '', // ERROR!
        ]);
        
        $result->assertRedirect();
        $this->assertStringContainsString('Link Download APK wajib diisi', session('error'));
        
        // Enabled tapi URL tidak valid
        $result = $this->withSession($superadmin)->post('superadmin/settings', [
            'android_update_enabled' => 'true',
            'android_version_name' => '2.0',
            'android_version_code' => '20',
            'android_download_url' => 'bukan_url', // ERROR!
        ]);
        
        $result->assertRedirect();
        $this->assertStringContainsString('wajib diisi URL valid', session('error'));
        
        // Enabled tapi version code string/invalid
        $result = $this->withSession($superadmin)->post('superadmin/settings', [
            'android_update_enabled' => 'true',
            'android_version_name' => '2.0',
            'android_version_code' => '-5', // ERROR!
            'android_download_url' => 'https://example.com/app',
        ]);
        
        $result->assertRedirect();
        $this->assertStringContainsString('Version Code wajib diisi angka valid', session('error'));
    }
}
