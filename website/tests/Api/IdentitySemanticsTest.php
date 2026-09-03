<?php

namespace Tests\Api;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use App\Models\UserModel;
use App\Models\OrganizationMemberModel;
use Tests\Support\AuthTrait;
use App\Services\AuthService;

class IdentitySemanticsTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthTrait;

    protected $migrate = true;
    protected $migrateOnce = false;
    protected $namespace = 'App';

    protected function setUp(): void
    {
        parent::setUp();
        AuthService::setUser(null);
    }

    private function setupTenantUser($role, $tenantId = 1, $username = null)
    {
        $db = \Config\Database::connect();
        $db->table('karang_taruna')->ignore(true)->insert(['id' => $tenantId, 'nama_organisasi' => "Tenant $tenantId", 'kode_pin' => '123123', 'status_aktif' => 1]);

        $username = $username ?? "user_{$role}_" . uniqid();
        $userModel = new UserModel();
        $userId = $userModel->insert([
            'karang_taruna_id' => $tenantId,
            'nama_lengkap' => "User $role",
            'username' => $username, // legacy fallback
            'password' => password_hash('pass' . $tenantId, PASSWORD_BCRYPT),
            'role_level' => 'anggota',
            'status_aktif' => 1
        ]);
        
        $memberModel = new OrganizationMemberModel();
        $memberModel->insert([
            'user_id' => $userId,
            'karang_taruna_id' => $tenantId,
            'username' => $username,
            'role_level' => $role,
            'status_aktif' => 1
        ]);

        return $userModel->find($userId);
    }

    public function testTwoAndiUsers()
    {
        $andiA = $this->setupTenantUser('anggota', 700, 'andi');
        $andiB = $this->setupTenantUser('anggota', 701, 'andi');

        $this->assertNotEquals($andiA['id'], $andiB['id'], "Andi A and Andi B should have different global user IDs");

        // Test Cross Tenant Login Fail
        $resA = $this->withBodyFormat('json')->post('api/login', [
            'karang_taruna_id' => 700,
            'username' => 'andi',
            'password' => 'pass700'
        ]);
        $resA->assertStatus(200);

        $resBFail = $this->withBodyFormat('json')->post('api/login', [
            'karang_taruna_id' => 700,
            'username' => 'andi',
            'password' => 'pass701' // Andi B password in Tenant A
        ]);
        $resBFail->assertStatus(401);
        
        $resB = $this->withBodyFormat('json')->post('api/login', [
            'karang_taruna_id' => 701,
            'username' => 'andi',
            'password' => 'pass701'
        ]);
        $resB->assertStatus(200);
    }

    public function testResetAndiAOnly()
    {
        // Setup Superadmin temporary password
        $settingModel = new \App\Models\SettingModel();
        $settingModel->insert([
            'karang_taruna_id' => 0,
            'setting_key' => 'temporary_reset_password',
            'setting_value' => 'kartarjosjis',
            'description' => 'Temp pass'
        ]);

        $ketuaA = $this->setupTenantUser('ketua', 700);
        $tokenA = $this->generateTokenForUser($ketuaA);
        $headersA = ['Authorization' => 'Bearer ' . $tokenA, 'X-Karang-Taruna-ID' => '700'];

        $andiA = $this->setupTenantUser('anggota', 700, 'andi2');
        $andiB = $this->setupTenantUser('anggota', 701, 'andi2'); // Different tenant, same username

        // Admin A resets Andi A
        $resReset = $this->withHeaders($headersA)->post("api/users/{$andiA['id']}/reset-password");
        $resReset->assertStatus(200);
        // The data is inside 'data' key
        $json = json_decode($resReset->getJSON(), true);
        $this->assertEquals('kartarjosjis', $json['data']['temporary_password']);

        // Verify A was reset
        $userModel = new UserModel();
        $dbAndiA = $userModel->find($andiA['id']);
        $this->assertEquals(1, $dbAndiA['password_must_change']);
        $this->assertTrue(password_verify('kartarjosjis', $dbAndiA['password']));

        // Verify B was untouched
        $dbAndiB = $userModel->find($andiB['id']);
        $this->assertEquals(0, $dbAndiB['password_must_change']);
        $this->assertFalse(password_verify('kartarjosjis', $dbAndiB['password']));
    }

    public function testCrossTenantReset()
    {
        $settingModel = new \App\Models\SettingModel();
        $settingModel->ignore(true)->insert([
            'karang_taruna_id' => 0,
            'setting_key' => 'temporary_reset_password',
            'setting_value' => 'kartarjosjis',
            'description' => 'Temp pass'
        ]);

        $ketuaA = $this->setupTenantUser('ketua', 800);
        $tokenA = $this->generateTokenForUser($ketuaA);
        $headersA = ['Authorization' => 'Bearer ' . $tokenA, 'X-Karang-Taruna-ID' => '800'];

        $userB = $this->setupTenantUser('anggota', 801);

        // Admin A resets User B
        $resReset = $this->withHeaders($headersA)->post("api/users/{$userB['id']}/reset-password");
        $resReset->assertStatus(404); // 404 because user is isolated to tenant B
    }

    public function testUnauthorizedReset()
    {
        $settingModel = new \App\Models\SettingModel();
        $settingModel->ignore(true)->insert([
            'karang_taruna_id' => 0,
            'setting_key' => 'temporary_reset_password',
            'setting_value' => 'kartarjosjis',
            'description' => 'Temp pass'
        ]);

        $anggotaA = $this->setupTenantUser('anggota', 900);
        $tokenA = $this->generateTokenForUser($anggotaA);
        $headersA = ['Authorization' => 'Bearer ' . $tokenA, 'X-Karang-Taruna-ID' => '900'];

        $target = $this->setupTenantUser('anggota', 900);

        // Anggota A resets Target
        $resReset = $this->withHeaders($headersA)->post("api/users/{$target['id']}/reset-password");
        $resReset->assertStatus(403);
    }

    public function testInactiveResetFail()
    {
        $settingModel = new \App\Models\SettingModel();
        $settingModel->ignore(true)->insert([
            'karang_taruna_id' => 0,
            'setting_key' => 'temporary_reset_password',
            'setting_value' => 'kartarjosjis',
            'description' => 'Temp pass'
        ]);

        $ketuaA = $this->setupTenantUser('ketua', 950);
        $tokenA = $this->generateTokenForUser($ketuaA);
        $headersA = ['Authorization' => 'Bearer ' . $tokenA, 'X-Karang-Taruna-ID' => '950'];

        $target = $this->setupTenantUser('anggota', 950);
        // Make target inactive
        $memberModel = new OrganizationMemberModel();
        $memberModel->where('user_id', $target['id'])->where('karang_taruna_id', 950)->set(['status_aktif' => 0])->update();

        // Ketua A resets Inactive Target
        $resReset = $this->withHeaders($headersA)->post("api/users/{$target['id']}/reset-password");
        $resReset->assertStatus(403); // Pengguna tidak aktif
    }
}
