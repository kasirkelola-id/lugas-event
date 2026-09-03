<?php

namespace Tests\Api;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use App\Models\UserModel;
use App\Models\OrganizationMemberModel;
use Tests\Support\AuthTrait;

class MembershipApiTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthTrait;

    protected $migrate = true;
    protected $migrateOnce = false;
    protected $namespace = 'App';

    public function testGetMembershipsReturnsOnlyActiveMembershipsForLoggedUser()
    {
        $db = \Config\Database::connect();
        $db->table('karang_taruna')->insert(['id' => 11, 'nama_organisasi' => 'KT 11', 'kode_pin' => '111', 'alamat_lengkap' => '-', 'status_aktif' => 1]);
        $db->table('karang_taruna')->insert(['id' => 12, 'nama_organisasi' => 'KT 12', 'kode_pin' => '222', 'alamat_lengkap' => '-', 'status_aktif' => 1]);
        $db->table('karang_taruna')->insert(['id' => 13, 'nama_organisasi' => 'KT 13', 'kode_pin' => '333', 'alamat_lengkap' => '-', 'status_aktif' => 1]);
        
        $userModel = new UserModel();
        $userId = $userModel->insert([
            'karang_taruna_id' => 11,
            'nama_lengkap' => 'Target User',
            'username' => 'target',
            'password' => 'pass',
            'role_level' => 'anggota',
            'status_aktif' => 1
        ]);
        
        $memberModel = new OrganizationMemberModel();
        // Active in 11 and 12
        $memberModel->insert(['user_id' => $userId, 'karang_taruna_id' => 11, 'role_level' => 'ketua', 'status_aktif' => 1]);
        $memberModel->insert(['user_id' => $userId, 'karang_taruna_id' => 12, 'role_level' => 'anggota', 'status_aktif' => 1]);
        
        // Inactive in 13
        $memberModel->insert(['user_id' => $userId, 'karang_taruna_id' => 13, 'role_level' => 'pengelola', 'status_aktif' => 0]);
        
        // Another user's membership in 11
        $otherUserId = $userModel->insert([
            'karang_taruna_id' => 11,
            'nama_lengkap' => 'Other User',
            'username' => 'other',
            'password' => 'pass',
            'role_level' => 'anggota',
            'status_aktif' => 1
        ]);
        $memberModel->insert(['user_id' => $otherUserId, 'karang_taruna_id' => 11, 'role_level' => 'anggota', 'status_aktif' => 1]);
        
        $token = $this->generateTokenForUser($userModel->find($userId));
        
        $res = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
                    ->get('api/memberships');
                    
        $res->assertStatus(200);
        $json = json_decode($res->getJSON(), true);
        
        $memberships = $json['data'];
        $this->assertCount(2, $memberships, 'Should only return 2 active memberships for the logged in user');
        
        $ids = array_column($memberships, 'karang_taruna_id');
        $this->assertContains(11, $ids);
        $this->assertContains(12, $ids);
        $this->assertNotContains(13, $ids, 'Inactive membership should not be returned');
        
        // Role check
        foreach ($memberships as $m) {
            if ($m['karang_taruna_id'] == 11) {
                $this->assertEquals('ketua', $m['role']);
            }
        }
    }

    public function testGetMembershipsWithoutTenantHeaderForAmbiguousUser()
    {
        $db = \Config\Database::connect();
        $db->table('karang_taruna')->insert(['id' => 21, 'nama_organisasi' => 'KT 21', 'kode_pin' => '111', 'alamat_lengkap' => '-', 'status_aktif' => 1]);
        $db->table('karang_taruna')->insert(['id' => 22, 'nama_organisasi' => 'KT 22', 'kode_pin' => '222', 'alamat_lengkap' => '-', 'status_aktif' => 1]);
        
        $userModel = new UserModel();
        $userId = $userModel->insert([
            'karang_taruna_id' => 21,
            'nama_lengkap' => 'Multi User',
            'username' => 'multi',
            'password' => 'pass',
            'role_level' => 'anggota',
            'status_aktif' => 1
        ]);
        
        $memberModel = new OrganizationMemberModel();
        $memberModel->insert(['user_id' => $userId, 'karang_taruna_id' => 21, 'role_level' => 'anggota', 'status_aktif' => 1]);
        $memberModel->insert(['user_id' => $userId, 'karang_taruna_id' => 22, 'role_level' => 'anggota', 'status_aktif' => 1]);
        
        $token = $this->generateTokenForUser($userModel->find($userId));
        
        // Request without X-Karang-Taruna-ID header. Because it's a global endpoint, it should succeed instead of 400.
        $res = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
                    ->get('api/memberships');
                    
        $res->assertStatus(200);
        $json = json_decode($res->getJSON(), true);
        $this->assertCount(2, $json['data']);
    }

    public function testInvalidBearerTokenRejected()
    {
        $res = $this->withHeaders(['Authorization' => 'Bearer invalid-token-123'])
                    ->get('api/memberships');
                    
        $res->assertStatus(401);
    }
}
