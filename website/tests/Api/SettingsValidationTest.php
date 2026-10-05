<?php

namespace Tests\Api;

use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\AuthTrait;
use Tests\Support\BaseTest;

final class SettingsValidationTest extends BaseTest
{
    use FeatureTestTrait;
    use AuthTrait;
    protected $namespace = 'App';

    protected function setUp(): void
    {
        parent::setUp();
        $this->db->resetTransStatus();
    }

    private function manager(): array
    {
        return $this->getAuthHeaders($this->generateTokenForUser($this->createTestUser(101, 'ketua')));
    }

    private function browser(): self
    {
        $hash = password_hash('synthetic-private-admin', PASSWORD_BCRYPT);
        $this->db->table('superadmins')->insert(['id' => 1, 'username' => 'synthetic', 'nama_lengkap' => 'Synthetic', 'password' => $hash]);
        \Config\Services::resetSingle('security');
        return $this->withSession(['is_superadmin_logged_in' => true, 'superadmin_id' => 1,
            'superadmin_credential_version' => hash('sha256', $hash), 'csrf_test_name' => str_repeat('e', 64)])
            ->withHeaders(['X-CSRF-TOKEN' => str_repeat('e', 64)]);
    }

    public function test_arbitrary_tenant_setting_rejected_without_rows(): void
    {
        $before = $this->db->table('settings')->get()->getResultArray();
        $this->withHeaders($this->manager())->withBodyFormat('json')->post('api/settings', ['unknown_key' => 'value'])->assertStatus(422);
        $this->assertSame($before, $this->db->table('settings')->get()->getResultArray());
    }

    public function test_browser_update_rejects_cleartext_url_without_rows(): void
    {
        $before = $this->db->table('settings')->get()->getResultArray();
        $result = $this->browser()->post('superadmin/settings', ['android_update_enabled' => 'true',
            'android_version_name' => '1.0', 'android_version_code' => '2', 'android_download_url' => 'http://attacker.invalid/app.apk']);
        $result->assertRedirect();
        $this->assertSame($before, $this->db->table('settings')->get()->getResultArray());
    }

    public static function invalidSettings(): array
    {
        return array_map(static fn($value) => [['attendance_before_minutes' => $value]],
            [null, [], true, false, 1.5, '1.5', '1e2', '-1', ' 1', '1suffix', '9999999999999', 241])
            + [12 => [[ 'attendance_before_minutes' => 1, 'attendance_after_minutes' => 1,
                'default_geofence_radius' => 10, 'kas_backdate_limit' => 30, 'extra' => 1]]];
    }

    /** @dataProvider invalidSettings */
    public function test_invalid_settings_leave_exact_rows(array $input): void
    {
        $before = $this->db->table('settings')->get()->getResultArray();
        $this->withHeaders($this->manager())->withBodyFormat('json')->post('api/settings', $input)->assertStatus(422);
        $this->assertSame($before, $this->db->table('settings')->get()->getResultArray());
    }

    public function test_valid_settings_are_tenant_scoped_and_invalidate_cache(): void
    {
        $headers = $this->manager();
        $this->db->table('settings')->insert(['karang_taruna_id' => 102, 'setting_key' => 'kas_backdate_limit', 'setting_value' => '3']);
        \App\Services\SettingService::clearCache();
        $this->assertSame('initial', \App\Services\SettingService::getSetting(101, 'kas_backdate_limit', 'initial'));
        foreach ([0, 240] as $number) {
            $this->withHeaders($headers)->withBodyFormat('json')->post('api/settings', [
                'attendance_before_minutes' => $number, 'attendance_after_minutes' => (string)$number,
                'default_geofence_radius' => 5000, 'kas_backdate_limit' => 365,
            ])->assertStatus(200);
        }
        $this->assertSame('365', \App\Services\SettingService::getSetting(101, 'kas_backdate_limit'));
        $this->assertSame('3', \App\Services\SettingService::getSetting(102, 'kas_backdate_limit'));
        $this->assertSame(4, $this->db->table('settings')->where('karang_taruna_id', 101)->countAllResults());
    }

    public function test_settings_failed_second_write_rolls_back_first(): void
    {
        $headers = $this->manager();
        $before = $this->db->table('settings')->get()->getResultArray();
        $this->db->query("CREATE TRIGGER settings_failure BEFORE INSERT ON settings WHEN NEW.setting_key = 'kas_backdate_limit' BEGIN SELECT RAISE(ABORT, 'synthetic-private-detail'); END");
        try {
            $this->withHeaders($headers)->withBodyFormat('json')->post('api/settings', [
                'attendance_before_minutes' => 10, 'kas_backdate_limit' => 30,
            ])->assertStatus(503);
            $this->assertSame($before, $this->db->table('settings')->get()->getResultArray());
        } finally {
            $this->db->query('DROP TRIGGER settings_failure');
            $this->db->resetTransStatus();
        }
    }

    public static function unsafeUrls(): array
    {
        return array_map(static fn($url) => [$url], [
            'http://kartar.kelolakasir.id/app.apk', 'https://attacker.invalid/app.apk',
            'https://kartar.kelolakasir.id.attacker.invalid/app.apk', 'https://user@kartar.kelolakasir.id/app.apk',
            'https://kartar.kelolakasir.id:444/app.apk', 'https://kartar.kelolakasir.id./app.apk',
            'https://127.0.0.1/app.apk', 'https://localhost/app.apk', 'javascript:alert(1)',
            'https://kartar.kelolakasir.id/app.apk#fragment', "https://kartar.kelolakasir.id/\napp.apk", [],
        ]);
    }

    /** @dataProvider unsafeUrls */
    public function test_update_url_policy_rejects_untrusted_ambiguity($url): void
    {
        $this->assertFalse(\App\Services\SettingsPolicy::trustedDownloadUrl($url));
    }

    public function test_trusted_https_url_and_historical_public_fail_closed(): void
    {
        $this->assertTrue(\App\Services\SettingsPolicy::trustedDownloadUrl('https://kartar.kelolakasir.id/app.apk'));
        $this->assertTrue(\App\Services\SettingsPolicy::trustedDownloadUrl('https://kartar.kelolakasir.id:443/app.apk'));
        $this->db->table('settings')->insertBatch([
            ['karang_taruna_id' => 0, 'setting_key' => 'android_update_enabled', 'setting_value' => 'true'],
            ['karang_taruna_id' => 0, 'setting_key' => 'android_download_url', 'setting_value' => 'http://attacker.invalid/app.apk'],
        ]);
        $response = $this->get('api/app-version');
        $response->assertStatus(200);
        $data = json_decode($response->getJSON(), true)['data'];
        $this->assertFalse($data['update_enabled']);
        $this->assertSame('', $data['download_url']);
    }

    public function test_invalid_web_fields_do_not_change_business_rows(): void
    {
        $user = $this->createTestUser(101);
        $this->db->table('kelurahan')->insert(['nama' => 'Synthetic']);
        $kel = $this->db->insertID();
        $before = [];
        foreach (['users', 'organization_members', 'karang_taruna', 'kelurahan', 'pengumuman'] as $table) {
            $before[$table] = $this->db->table($table)->get()->getResultArray();
        }
        $this->browser()->post('superadmin/karang_taruna/create', ['nama_organisasi' => '', 'kode_pin' => 'abc', 'kelurahan_id' => $kel])->assertRedirect();
        // Fresh CSRF state with the same authenticated synthetic identity.
        foreach ([['superadmin/kelurahan/store', ['nama' => ['array']]],
            ['superadmin/karang_taruna/update/101', ['nama_organisasi' => str_repeat('x', 151)]],
            ["superadmin/manage/101/users/{$user['id']}/role", ['role_level' => 'invented']],
            ['superadmin/manage/101/pengumuman/create', ['judul' => '', 'isi' => ['array']]]] as [$path, $input]) {
            \Config\Services::resetSingle('security');
            $admin = $this->db->table('superadmins')->where('id', 1)->get()->getRowArray();
            $this->withSession(['is_superadmin_logged_in' => true, 'superadmin_id' => 1,
                'superadmin_credential_version' => hash('sha256', $admin['password']), 'csrf_test_name' => str_repeat('e', 64)])
                ->withHeaders(['X-CSRF-TOKEN' => str_repeat('e', 64)])->post($path, $input)->assertRedirect();
        }
        foreach ($before as $table => $rows) $this->assertSame($rows, $this->db->table($table)->get()->getResultArray());
    }

    public function test_browser_settings_write_failure_is_atomic(): void
    {
        $this->browser();
        $before = $this->db->table('settings')->get()->getResultArray();
        $this->db->query("CREATE TRIGGER global_settings_failure BEFORE INSERT ON settings WHEN NEW.setting_key = 'android_update_enabled' BEGIN SELECT RAISE(ABORT, 'synthetic-private-detail'); END");
        try {
            $this->post('superadmin/settings', ['android_version_name' => 'Synthetic 9'])->assertRedirect();
            $this->assertSame($before, $this->db->table('settings')->get()->getResultArray());
            $this->assertSame('Pengaturan tidak dapat disimpan.', session('error'));
        } finally {
            $this->db->query('DROP TRIGGER global_settings_failure');
            $this->db->resetTransStatus();
        }
    }
}
