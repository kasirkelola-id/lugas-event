<?php

namespace Tests\Api;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use App\Models\UserModel;
use App\Models\OrganizationMemberModel;
use Tests\Support\AuthTrait;
use App\Services\AuthService;

class MemberLifecycleTest extends CIUnitTestCase
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
            'username' => $username,
            'password' => password_hash('pass', PASSWORD_BCRYPT),
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

    public function testLastKetuaProtection()
    {
        $ketua = $this->setupTenantUser('ketua', 200);
        $token = $this->generateTokenForUser($ketua);
        $headers = ['Authorization' => 'Bearer ' . $token, 'X-Karang-Taruna-ID' => '200'];

        // Attempt to demote self
        $resDemote = $this->withBodyFormat('json')->withHeaders($headers)->patch("api/users/{$ketua['id']}/role", ['role_level' => 'anggota']);
        $resDemote->assertStatus(400);

        // Attempt to deactivate self
        $resDeactivate = $this->withHeaders($headers)->patch("api/users/{$ketua['id']}/status");
        $resDeactivate->assertStatus(400);
    }

    public function testMultiMembershipDeactivation()
    {
        $db = \Config\Database::connect();
        $db->table('karang_taruna')->ignore(true)->insert(['id' => 300, 'nama_organisasi' => "Tenant 300", 'kode_pin' => '123', 'status_aktif' => 1]);
        $db->table('karang_taruna')->ignore(true)->insert(['id' => 301, 'nama_organisasi' => "Tenant 301", 'kode_pin' => '123', 'status_aktif' => 1]);

        $userModel = new UserModel();
        $userId = $userModel->insert([
            'karang_taruna_id' => 300,
            'nama_lengkap' => "Multi Member",
            'username' => "multi",
            'password' => password_hash('pass', PASSWORD_BCRYPT),
            'role_level' => 'anggota',
            'status_aktif' => 1
        ]);

        $memberModel = new OrganizationMemberModel();
        $memberModel->insert(['user_id' => $userId, 'karang_taruna_id' => 300, 'username' => 'multi_300', 'role_level' => 'anggota', 'status_aktif' => 1]);
        $memberModel->insert(['user_id' => $userId, 'karang_taruna_id' => 301, 'username' => 'multi_301', 'role_level' => 'bendahara', 'status_aktif' => 1]);

        $ketua300 = $this->setupTenantUser('ketua', 300);
        $token300 = $this->generateTokenForUser($ketua300);
        
        $ketua301 = $this->setupTenantUser('ketua', 301);
        $token301 = $this->generateTokenForUser($ketua301);

        // Deactivate membership in Tenant 300
        $resDeactivate = $this->withHeaders(['Authorization' => 'Bearer ' . $token300, 'X-Karang-Taruna-ID' => '300'])->patch("api/users/$userId/status");
        $resDeactivate->assertStatus(200);

        // Verify Tenant 300 membership is inactive
        $m300 = $memberModel->where('user_id', $userId)->where('karang_taruna_id', 300)->first();
        $this->assertEquals(0, $m300['status_aktif']);

        // Verify Tenant 301 membership is still active and bendahara
        $m301 = $memberModel->where('user_id', $userId)->where('karang_taruna_id', 301)->first();
        $this->assertEquals(1, $m301['status_aktif']);
        $this->assertEquals('bendahara', $m301['role_level']);

        // Verify Global User still exists and is active
        $u = $userModel->find($userId);
        $this->assertNotNull($u);
        $this->assertEquals(1, $u['status_aktif']);

        // Session Revocation: Token with Tenant 300 should fail
        $user = $userModel->find($userId);
        $userToken = $this->generateTokenForUser($user);
        
        $resAccess300 = $this->withHeaders(['Authorization' => 'Bearer ' . $userToken, 'X-Karang-Taruna-ID' => '300'])->get("api/events");
        $resAccess300->assertStatus(403); // Status when user is deactivated is 401 or 403, actual is 403.

        // Access with Tenant 301 should succeed
        $resAccess301 = $this->withHeaders(['Authorization' => 'Bearer ' . $userToken, 'X-Karang-Taruna-ID' => '301'])->get("api/events");
        $resAccess301->assertStatus(200);

        // Reactivation
        $resReactivate = $this->withHeaders(['Authorization' => 'Bearer ' . $token300, 'X-Karang-Taruna-ID' => '300'])->patch("api/users/$userId/status");
        $resReactivate->assertStatus(200);
        $m300_after = $memberModel->where('user_id', $userId)->where('karang_taruna_id', 300)->first();
        $this->assertEquals(1, $m300_after['status_aktif']);
        $this->assertEquals($m300['id'], $m300_after['id']); // same ID reused
    }

    public function testTenantUsernameChange()
    {
        $ketua = $this->setupTenantUser('ketua', 400);
        $token = $this->generateTokenForUser($ketua);
        $headers = ['Authorization' => 'Bearer ' . $token, 'X-Karang-Taruna-ID' => '400'];

        $userModel = new UserModel();
        $userId = $userModel->insert([
            'karang_taruna_id' => 400,
            'nama_lengkap' => "Andi",
            'username' => "andi",
            'password' => password_hash('pass', PASSWORD_BCRYPT),
            'role_level' => 'anggota',
            'status_aktif' => 1
        ]);
        
        $memberModel = new OrganizationMemberModel();
        $memberModel->insert(['user_id' => $userId, 'karang_taruna_id' => 400, 'username' => 'andi', 'role_level' => 'anggota', 'status_aktif' => 1]);
        $memberModel->insert(['user_id' => $userId, 'karang_taruna_id' => 401, 'username' => 'andi_b', 'role_level' => 'anggota', 'status_aktif' => 1]);

        // Rename in Tenant 400
        $resRename = $this->withBodyFormat('json')->withHeaders($headers)->put("api/users/$userId", ['username' => 'andi_baru']);
        $resRename->assertStatus(200);

        $m400 = $memberModel->where('user_id', $userId)->where('karang_taruna_id', 400)->first();
        $this->assertEquals('andi_baru', $m400['username']);

        $m401 = $memberModel->where('user_id', $userId)->where('karang_taruna_id', 401)->first();
        $this->assertEquals('andi_b', $m401['username']);
    }
    
    public function testUsernameCollision()
    {
        $ketua = $this->setupTenantUser('ketua', 500);
        $token = $this->generateTokenForUser($ketua);
        $headers = ['Authorization' => 'Bearer ' . $token, 'X-Karang-Taruna-ID' => '500'];

        $budiId = $this->setupTenantUser('anggota', 500, 'budi')['id'];
        $jokoId = $this->setupTenantUser('anggota', 500, 'joko')['id'];

        // Try to rename joko to budi
        $resRename = $this->withBodyFormat('json')->withHeaders($headers)->put("api/users/$jokoId", ['username' => 'budi']);
        $resRename->assertStatus(409);
    }
    
    public function testCrossTenantWriteBlocked()
    {
        $ketuaA = $this->setupTenantUser('ketua', 600);
        $tokenA = $this->generateTokenForUser($ketuaA);
        $headersA = ['Authorization' => 'Bearer ' . $tokenA, 'X-Karang-Taruna-ID' => '600'];

        $memberB = $this->setupTenantUser('anggota', 601);
        $memberBId = $memberB['id'];

        // Tenant A tries to edit Tenant B's member
        $resUpdate = $this->withBodyFormat('json')->withHeaders($headersA)->put("api/users/$memberBId", ['username' => 'hacked']);
        $resUpdate->assertStatus(403);

        $resRole = $this->withBodyFormat('json')->withHeaders($headersA)->patch("api/users/$memberBId/role", ['role_level' => 'pengelola']);
        $resRole->assertStatus(404); // 404 because not found in tenant's membership list

        $resStatus = $this->withHeaders($headersA)->patch("api/users/$memberBId/status");
        $resStatus->assertStatus(404);
    }
}
