<?php

namespace Tests\Api;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use App\Models\UserModel;
use Tests\Support\AuthTrait;

class ProfileTest extends CIUnitTestCase
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
        // Clear databases
        $db = \Config\Database::connect();
        $db->table('users')->emptyTable();
        $db->table('karang_taruna')->emptyTable();
        $db->table('organization_members')->emptyTable();
    }

    public function testUpdateProfileTenantIsolation()
    {
        $tenantA = 1;
        $tenantB = 2;
        $db = \Config\Database::connect();
        
        $db->table('karang_taruna')->insertBatch([
            ['id' => $tenantA, 'nama_organisasi' => 'KT A', 'kode_pin' => '111111', 'status_aktif' => 1, 'created_at' => date('Y-m-d H:i:s')],
            ['id' => $tenantB, 'nama_organisasi' => 'KT B', 'kode_pin' => '222222', 'status_aktif' => 1, 'created_at' => date('Y-m-d H:i:s')]
        ]);

        $userId1 = 100; // member in A and B
        $userId2 = 200; // member in B only

        $db->table('users')->insertBatch([
            ['id' => $userId1, 'username' => 'global_user1', 'password' => password_hash('123', PASSWORD_BCRYPT), 'nama_lengkap' => 'User 1', 'role_level' => 'anggota', 'karang_taruna_id' => $tenantA],
            ['id' => $userId2, 'username' => 'global_user2', 'password' => password_hash('123', PASSWORD_BCRYPT), 'nama_lengkap' => 'User 2', 'role_level' => 'anggota', 'karang_taruna_id' => $tenantB],
        ]);

        $db->table('organization_members')->insertBatch([
            ['user_id' => $userId1, 'karang_taruna_id' => $tenantA, 'role_level' => 'anggota', 'status_aktif' => 1, 'username' => 'user_1_a'],
            ['user_id' => $userId1, 'karang_taruna_id' => $tenantB, 'role_level' => 'anggota', 'status_aktif' => 1, 'username' => 'user_1_b'],
            ['user_id' => $userId2, 'karang_taruna_id' => $tenantB, 'role_level' => 'anggota', 'status_aktif' => 1, 'username' => 'user_2_b'],
        ]);

        $userModel = new UserModel();
        $member1 = $userModel->find($userId1);
        
        $tokenA = $this->generateTokenForUser($member1);
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $tokenA, 'X-Tenant-ID' => $tenantA])
                       ->withBodyFormat('json')
                       ->put("api/profile", [
                           'nama_lengkap' => 'User 1 Changed',
                           'username' => 'user_1_a_changed'
                       ]);
        
        $result->assertStatus(200);

        // Check global users table (nama_lengkap should change, username should NOT change)
        $updatedUser1 = $userModel->find($userId1);
        $this->assertEquals('User 1 Changed', $updatedUser1['nama_lengkap']);
        $this->assertEquals('global_user1', $updatedUser1['username']); // Global username should remain intact

        // Check organization_members for Tenant A (should change)
        $memberA = $db->table('organization_members')->where('user_id', $userId1)->where('karang_taruna_id', $tenantA)->get()->getRowArray();
        $this->assertEquals('user_1_a_changed', $memberA['username']);

        // Check organization_members for Tenant B (should NOT change)
        $memberB = $db->table('organization_members')->where('user_id', $userId1)->where('karang_taruna_id', $tenantB)->get()->getRowArray();
        $this->assertEquals('user_1_b', $memberB['username']);
    }

    public function testDuplicateUsernameInsideTenant()
    {
        $tenantA = 1;
        $db = \Config\Database::connect();
        $db->table('karang_taruna')->insert(['id' => $tenantA, 'nama_organisasi' => 'KT A', 'kode_pin' => '111111', 'status_aktif' => 1]);

        $userId1 = 100;
        $userId2 = 200;

        $db->table('users')->insertBatch([
            ['id' => $userId1, 'username' => 'user1', 'password' => password_hash('123', PASSWORD_BCRYPT), 'nama_lengkap' => 'User 1'],
            ['id' => $userId2, 'username' => 'user2', 'password' => password_hash('123', PASSWORD_BCRYPT), 'nama_lengkap' => 'User 2'],
        ]);

        $db->table('organization_members')->insertBatch([
            ['user_id' => $userId1, 'karang_taruna_id' => $tenantA, 'status_aktif' => 1, 'username' => 'user_1'],
            ['user_id' => $userId2, 'karang_taruna_id' => $tenantA, 'status_aktif' => 1, 'username' => 'user_2'],
        ]);

        $userModel = new UserModel();
        $member1 = $userModel->find($userId1);
        
        $token = $this->generateTokenForUser($member1);
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token, 'X-Tenant-ID' => $tenantA])
                       ->withBodyFormat('json')
                       ->put("api/profile", [
                           'username' => 'user_2' // try to use user 2's username
                       ]);
        if ($result->getStatus() !== 409) {
            echo "\nDEBUG:\n" . $result->getJSON() . "\n";
        }
        $result->assertStatus(409); // Conflict
    }

    public function testDuplicateUsernameDifferentTenant()
    {
        $tenantA = 1;
        $tenantB = 2;
        $db = \Config\Database::connect();
        
        $db->table('karang_taruna')->insertBatch([
            ['id' => $tenantA, 'nama_organisasi' => 'KT A', 'kode_pin' => '111111', 'status_aktif' => 1],
            ['id' => $tenantB, 'nama_organisasi' => 'KT B', 'kode_pin' => '222222', 'status_aktif' => 1]
        ]);

        $userId1 = 100;
        $userId2 = 200;

        $db->table('users')->insertBatch([
            ['id' => $userId1, 'username' => 'user1', 'password' => password_hash('123', PASSWORD_BCRYPT), 'nama_lengkap' => 'User 1'],
            ['id' => $userId2, 'username' => 'user2', 'password' => password_hash('123', PASSWORD_BCRYPT), 'nama_lengkap' => 'User 2'],
        ]);

        $db->table('organization_members')->insertBatch([
            ['user_id' => $userId1, 'karang_taruna_id' => $tenantA, 'status_aktif' => 1, 'username' => 'user_1_a'],
            ['user_id' => $userId2, 'karang_taruna_id' => $tenantB, 'status_aktif' => 1, 'username' => 'user_same'],
        ]);

        $userModel = new UserModel();
        $member1 = $userModel->find($userId1);
        
        $token = $this->generateTokenForUser($member1);
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token, 'X-Tenant-ID' => $tenantA])
                       ->withBodyFormat('json')
                       ->put("api/profile", [
                           'username' => 'user_same' // try to use user 2's username from Tenant B
                       ]);
        if ($result->getStatus() !== 200) {
            echo "\nDEBUG:\n" . $result->getJSON() . "\n";
        }
        $result->assertStatus(200); // Should succeed, different tenant namespace
    }
}
