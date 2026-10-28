<?php

namespace Tests;

use App\Services\CredentialPolicy;
use App\Services\SettingService;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\AuthTrait;

final class DefaultPasswordSettingsTest extends \Tests\Support\PasswordFixtureTest
{
    use FeatureTestTrait;
    use AuthTrait;

    private array $logs = [];

    protected function setUp(): void
    {
        parent::setUp();
        SettingService::clearCache();
        $this->db->table('settings')->insertBatch([
            ['karang_taruna_id' => 0, 'setting_key' => 'temporary_reset_password', 'setting_value' => 'synthetic-old-default'],
            ['karang_taruna_id' => 101, 'setting_key' => 'temporary_reset_password', 'setting_value' => 'synthetic-tenant-decoy'],
        ]);
        $this->db->table('superadmins')->insert(['id' => 1, 'username' => 'synthetic_owner',
            'nama_lengkap' => 'Synthetic Owner', 'password' => CredentialPolicy::hashNewPassword('synthetic-owner')]);
        $logger = $this->getMockBuilder(\CodeIgniter\Log\Logger::class)
            ->disableOriginalConstructor()->onlyMethods(['log'])->getMock();
        $logger->method('log')->willReturnCallback(function ($level, $message, $context): void {
            $this->logs[] = [$level, (string) $message, $context];
        });
        \Config\Services::injectMock('logger', $logger);
    }

    private function browser(): self
    {
        $admin = $this->db->table('superadmins')->where('id', 1)->get()->getRowArray();
        \Config\Services::resetSingle('security');
        return $this->withSession(['is_superadmin_logged_in' => true, 'superadmin_id' => 1,
            'superadmin_credential_version' => hash('sha256', $admin['password']),
            'csrf_test_name' => str_repeat('e', 64)])->withHeaders([]);
    }

    private function save(array $input)
    {
        return $this->browser()->post('superadmin/settings', $input + ['csrf_test_name' => str_repeat('e', 64)]);
    }

    private function globalRow(): array
    {
        return $this->db->table('settings')->where('karang_taruna_id', 0)
            ->where('setting_key', 'temporary_reset_password')->get()->getRowArray() ?? [];
    }

    public function testOriginalAccountFormDisplaysExistingValueWithoutWriting(): void
    {
        $before = $this->db->table('settings')->get()->getResultArray();
        $result = $this->browser()->get('superadmin/settings');
        $result->assertStatus(200);
        $html = $result->getBody();
        $this->assertTrue(str_contains($html, 'name="temporary_reset_password"'));
        $this->assertTrue(str_contains($html, 'type="password" id="temp_password"'));
        $this->assertTrue(str_contains($html, 'value="synthetic-old-default"'));
        $this->assertFalse(str_contains($html, 'synthetic-tenant-decoy'));
        $this->assertTrue(str_contains($html, 'id="togglePassword"'));
        $this->assertTrue(str_contains($html, 'Simpan Pengaturan Akun'));
        $this->assertTrue(str_contains($html, 'csrf_test_name'));
        $this->assertFalse(str_contains($html, 'minlength='));
        $this->assertStringContainsString('no-store', $result->response()->getHeaderLine('Cache-Control'));
        $this->assertSame($before, $this->db->table('settings')->get()->getResultArray());
        $this->assertFalse(str_contains(json_encode($this->logs), 'synthetic-old-default'));
    }

    public function testSavingDefaultOnlyKeepsAndroidAndTenantSettingsAndClearsCache(): void
    {
        $this->db->table('settings')->insertBatch([
            ['karang_taruna_id' => 0, 'setting_key' => 'android_update_enabled', 'setting_value' => 'true'],
            ['karang_taruna_id' => 0, 'setting_key' => 'android_version_name', 'setting_value' => 'Synthetic 9'],
        ]);
        $old = $this->globalRow();
        $others = $this->db->table('settings')->where('id !=', $old['id'])->get()->getResultArray();
        $this->assertTrue(SettingService::getSetting(0, 'temporary_reset_password') === 'synthetic-old-default');
        $result = $this->save(['temporary_reset_password' => 'abcd']);
        $result->assertRedirect();
        $new = $this->globalRow();
        $this->assertSame($old['id'], $new['id']);
        $this->assertTrue($new['setting_value'] === 'abcd');
        $this->assertTrue(SettingService::getSetting(0, 'temporary_reset_password') === 'abcd');
        $this->assertSame($others, $this->db->table('settings')->where('id !=', $old['id'])->get()->getResultArray());
        $this->assertFalse(str_contains(json_encode($this->logs), 'abcd'));
        $this->assertFalse(str_contains(json_encode($_SESSION), 'abcd'));
        $this->assertFalse(str_contains($result->getBody(), 'abcd'));
    }

    public function testMissingSettingIsCreatedOnlyOnExplicitSave(): void
    {
        $this->db->table('settings')->where('karang_taruna_id', 0)->where('setting_key', 'temporary_reset_password')->delete();
        $this->browser()->get('superadmin/settings')->assertStatus(200);
        $this->assertSame([], $this->globalRow());
        $this->save(['temporary_reset_password' => 'xy'])->assertRedirect();
        $this->assertTrue($this->globalRow()['setting_value'] === 'xy');
        $this->assertSame(1, $this->db->table('settings')->where('karang_taruna_id', 0)
            ->where('setting_key', 'temporary_reset_password')->countAllResults());
    }

    public function testBlankFieldPreservesExistingValueExactly(): void
    {
        $before = $this->db->table('settings')->get()->getResultArray();
        $this->save(['temporary_reset_password' => '   '])->assertRedirect();
        $this->assertSame($before, $this->db->table('settings')->get()->getResultArray());
    }

    public function testAndroidFormNeverReplacesExistingDefault(): void
    {
        $before = $this->globalRow();
        $this->save(['android_version_name' => 'Synthetic 2', 'android_version_code' => '2',
            'android_update_enabled' => 'true', 'android_download_url' => 'https://kartar.kelolakasir.id/app.apk'])->assertRedirect();
        $this->assertSame($before, $this->globalRow());
        $this->assertTrue($this->db->table('settings')->where('karang_taruna_id', 0)
            ->where('setting_key', 'android_update_enabled')->get()->getRowArray()['setting_value'] === 'true');
    }

    public static function invalidDefaults(): array
    {
        return [[['array']], [str_repeat('x', 73)], ["ab\0cd"], ["\0abcd"], [str_repeat('界', 25)]];
    }

    /** @dataProvider invalidDefaults */
    public function testInvalidInputPreservesEverySetting($value): void
    {
        $before = $this->db->table('settings')->get()->getResultArray();
        $this->save(['temporary_reset_password' => $value])->assertRedirect();
        $this->assertNotEmpty(session('error'));
        $this->assertSame($before, $this->db->table('settings')->get()->getResultArray());
    }

    public function testMixedFormFailureRollsBackDefaultAndAndroidTogether(): void
    {
        $before = $this->db->table('settings')->get()->getResultArray();
        $this->db->query("CREATE TRIGGER settings_failure BEFORE INSERT ON settings WHEN NEW.setting_key = 'android_update_enabled' BEGIN SELECT RAISE(ABORT, 'synthetic failure'); END");
        $this->save(['temporary_reset_password' => 'abcd', 'android_version_name' => 'Synthetic 2'])->assertRedirect();
        $this->assertSame($before, $this->db->table('settings')->get()->getResultArray());
        $this->assertSame('Pengaturan tidak dapat disimpan.', session('error'));
        $this->assertFalse(str_contains(json_encode($this->logs), 'abcd'));
    }

    public function testSettingsRequireSuperadminSessionAndCsrf(): void
    {
        $before = $this->db->table('settings')->get()->getResultArray();
        $unauthorized = $this->withSession([])->get('superadmin/settings');
        $unauthorized->assertRedirectTo('/superadmin/login');
        $this->assertFalse(str_contains($unauthorized->getBody(), 'synthetic-old-default'));
        $this->browser();
        try {
            $this->post('superadmin/settings', ['temporary_reset_password' => 'abcd']);
            $this->fail('Settings accepted POST without CSRF');
        } catch (\CodeIgniter\Security\Exceptions\SecurityException $e) {
            $this->assertSame(403, $e->getCode());
        }
        $this->assertSame($before, $this->db->table('settings')->get()->getResultArray());
    }

    public function testSavedDefaultIsUsedByApiAndWebResetAndCanLogin(): void
    {
        $this->save(['temporary_reset_password' => 'abcd'])->assertRedirect();
        $admin = $this->createTestUser(101, 'ketua');
        $one = $this->createTestUser(101, 'anggota', 'synthetic_api_target');
        $two = $this->createTestUser(101, 'anggota', 'synthetic_web_target');
        $token = $this->generateTokenForUser($admin);
        $result = $this->withHeaders($this->getAuthHeaders($token))->post('api/users/' . $one['id'] . '/reset-password');
        $result->assertStatus(200);
        $this->assertFalse(str_contains($result->getJSON(), 'abcd'));
        $this->browser()->post('superadmin/manage/101/users/' . $two['id'] . '/reset-password',
            ['csrf_test_name' => str_repeat('e', 64)])->assertRedirect();
        foreach ([$one, $two] as $target) {
            $row = $this->db->table('users')->where('id', $target['id'])->get()->getRowArray();
            $this->assertTrue(password_verify('abcd', $row['password']));
            $this->assertFalse(password_verify('password123', $row['password']));
            $this->withHeaders([])->withBodyFormat('json')->post('api/login', [
                'karang_taruna_id' => 101, 'username' => $target['username'], 'password' => 'abcd'])->assertStatus(200);
        }
        $this->assertTrue($admin['password'] === $this->db->table('users')->where('id', $admin['id'])->get()->getRowArray()['password']);
        $this->assertFalse(str_contains(json_encode($this->logs), 'abcd'));
    }

    public function testPublicAndTenantApisNeverPublishGlobalDefault(): void
    {
        $result = $this->withHeaders([])->get('api/app-version');
        $result->assertStatus(200);
        $this->assertFalse(str_contains($result->getJSON(), 'synthetic-old-default'));
        $this->assertFalse(str_contains($result->getJSON(), 'temporary_reset_password'));
        $user = $this->createTestUser(101, 'anggota');
        $result = $this->withHeaders($this->getAuthHeaders($this->generateTokenForUser($user)))->get('api/settings');
        $result->assertStatus(200);
        $this->assertFalse(str_contains($result->getJSON(), 'synthetic-old-default'));
    }

    public function testExistingValueIsHtmlEscapedInAuthorizedForm(): void
    {
        $this->db->table('settings')->where('karang_taruna_id', 0)->where('setting_key', 'temporary_reset_password')
            ->update(['setting_value' => 'synthetic"><script>invalid</script>']);
        $html = $this->browser()->get('superadmin/settings')->getBody();
        $this->assertFalse(str_contains($html, 'value="synthetic"><script>'));
        $this->assertTrue(str_contains($html, '&lt;script&gt;'));
    }
}
