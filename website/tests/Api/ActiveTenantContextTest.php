<?php

namespace Tests\Api;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use App\Models\UserModel;
use App\Models\OrganizationMemberModel;
use Tests\Support\AuthTrait;

class ActiveTenantContextTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthTrait;

    protected $migrate = true;
    protected $migrateOnce = false;
    protected $namespace = 'App';
    
    // We will use existing endpoints to verify auth behaviors
    // e.g. GET /api/me (requires auth, returns current context)
    // or GET /api/events (requires certain role)

    public function testSingleMembershipAutoSelected()
    {
        // 1. Single membership
        // AuthTrait creates a user with tenant ID 1 by default (and single membership via backfill if needed,
        // but AuthTrait uses direct UserModel insert, which doesn't trigger backfill automatically after migration.
        // Wait, AuthTrait creates a user AFTER migration, so we MUST manually add the membership if we want one.
        
        $db = \Config\Database::connect();
        $db->table('karang_taruna')->insert(['id' => 10, 'nama_organisasi' => 'KT Single', 'kode_pin' => '123123', 'alamat_lengkap' => '-', 'status_aktif' => 1]);
        
        $userModel = new UserModel();
        $userId = $userModel->insert([
            'karang_taruna_id' => 10,
            'nama_lengkap' => 'Single User',
            'username' => 'singleuser',
            'password' => 'pass',
            'role_level' => 'anggota',
            'status_aktif' => 1
        ]);
        
        $memberModel = new OrganizationMemberModel();
        $memberModel->insert([
            'user_id' => $userId,
            'karang_taruna_id' => 10,
            'role_level' => 'bendahara',
            'status_aktif' => 1
        ]);
        
        $token = $this->generateTokenForUser($userModel->find($userId));
        
        $res = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
                    ->get('api/me');
                    
        $res->assertStatus(200);
        $json = json_decode($res->getJSON(), true);
        
        // Assert auto-selected context
        $this->assertEquals(10, $json['data']['karang_taruna']['id']);
        $this->assertEquals('bendahara', $json['data']['role_level']);
    }

    public function testMultipleMembershipWithHeaderA()
    {
        $db = \Config\Database::connect();
        $db->table('karang_taruna')->insert(['id' => 21, 'nama_organisasi' => 'KT A', 'kode_pin' => '111', 'alamat_lengkap' => '-', 'status_aktif' => 1]);
        $db->table('karang_taruna')->insert(['id' => 22, 'nama_organisasi' => 'KT B', 'kode_pin' => '222', 'alamat_lengkap' => '-', 'status_aktif' => 1]);
        
        $userModel = new UserModel();
        $userId = $userModel->insert([
            'karang_taruna_id' => 21,
            'nama_lengkap' => 'Multi User',
            'username' => 'multiuser',
            'password' => 'pass',
            'role_level' => 'anggota',
            'status_aktif' => 1
        ]);
        
        $memberModel = new OrganizationMemberModel();
        $memberModel->insert(['user_id' => $userId, 'karang_taruna_id' => 21, 'role_level' => 'ketua', 'status_aktif' => 1]);
        $memberModel->insert(['user_id' => $userId, 'karang_taruna_id' => 22, 'role_level' => 'anggota', 'status_aktif' => 1]);
        
        $token = $this->generateTokenForUser($userModel->find($userId)); // The token tenant id doesn't matter much anymore
        
        // Use Header A (21)
        $res = $this->withHeaders([
                        'Authorization' => 'Bearer ' . $token,
                        'X-Karang-Taruna-ID' => '21'
                    ])
                    ->get('api/me');
                    
        $res->assertStatus(200);
        $json = json_decode($res->getJSON(), true);
        $this->assertEquals(21, $json['data']['karang_taruna']['id']);
        $this->assertEquals('ketua', $json['data']['role_level']);
    }

    public function testMultipleMembershipWithHeaderB()
    {
        // Same setup as above, but request Tenant B
        $db = \Config\Database::connect();
        $db->table('karang_taruna')->insert(['id' => 31, 'nama_organisasi' => 'KT A', 'kode_pin' => '111', 'alamat_lengkap' => '-', 'status_aktif' => 1]);
        $db->table('karang_taruna')->insert(['id' => 32, 'nama_organisasi' => 'KT B', 'kode_pin' => '222', 'alamat_lengkap' => '-', 'status_aktif' => 1]);
        
        $userModel = new UserModel();
        $userId = $userModel->insert([
            'karang_taruna_id' => 31,
            'nama_lengkap' => 'Multi User B',
            'username' => 'multiuserb',
            'password' => 'pass',
            'role_level' => 'anggota',
            'status_aktif' => 1
        ]);
        
        $memberModel = new OrganizationMemberModel();
        $memberModel->insert(['user_id' => $userId, 'karang_taruna_id' => 31, 'role_level' => 'ketua', 'status_aktif' => 1]);
        $memberModel->insert(['user_id' => $userId, 'karang_taruna_id' => 32, 'role_level' => 'pengelola', 'status_aktif' => 1]);
        
        $token = $this->generateTokenForUser($userModel->find($userId));
        
        // Use Header B (32)
        $res = $this->withHeaders([
                        'Authorization' => 'Bearer ' . $token,
                        'X-Karang-Taruna-ID' => '32'
                    ])
                    ->get('api/me');
                    
        $res->assertStatus(200);
        $json = json_decode($res->getJSON(), true);
        $this->assertEquals(32, $json['data']['karang_taruna']['id']);
        $this->assertEquals('pengelola', $json['data']['role_level']);
    }

    public function testMultipleMembershipNoHeaderAmbiguous()
    {
        $db = \Config\Database::connect();
        $db->table('karang_taruna')->insert(['id' => 41, 'nama_organisasi' => 'KT A', 'kode_pin' => '111', 'alamat_lengkap' => '-', 'status_aktif' => 1]);
        $db->table('karang_taruna')->insert(['id' => 42, 'nama_organisasi' => 'KT B', 'kode_pin' => '222', 'alamat_lengkap' => '-', 'status_aktif' => 1]);
        
        $userModel = new UserModel();
        $userId = $userModel->insert([
            'karang_taruna_id' => 41,
            'nama_lengkap' => 'Multi User C',
            'username' => 'multiuserc',
            'password' => 'pass',
            'role_level' => 'anggota',
            'status_aktif' => 1
        ]);
        
        $memberModel = new OrganizationMemberModel();
        $memberModel->insert(['user_id' => $userId, 'karang_taruna_id' => 41, 'role_level' => 'ketua', 'status_aktif' => 1]);
        $memberModel->insert(['user_id' => $userId, 'karang_taruna_id' => 42, 'role_level' => 'pengelola', 'status_aktif' => 1]);
        
        $token = $this->generateTokenForUser($userModel->find($userId));
        
        // No explicit header, but multiple active memberships!
        // We use /api/events since /api/me is now a global endpoint and doesn't require a tenant header.
        $res = $this->withHeaders([
                        'Authorization' => 'Bearer ' . $token,
                    ])
                    ->get('api/events');
                    
        // Should return 400 Bad Request
        $res->assertStatus(400);
        $res->assertJSONExact(['status' => false, 'message' => 'Active organization required. Please provide X-Karang-Taruna-ID header']);
    }

    public function testNonMemberTenantRejected()
    {
        $db = \Config\Database::connect();
        $db->table('karang_taruna')->insert(['id' => 51, 'nama_organisasi' => 'KT Mine', 'kode_pin' => '111', 'alamat_lengkap' => '-', 'status_aktif' => 1]);
        $db->table('karang_taruna')->insert(['id' => 52, 'nama_organisasi' => 'KT Other', 'kode_pin' => '222', 'alamat_lengkap' => '-', 'status_aktif' => 1]);
        
        $userModel = new UserModel();
        $userId = $userModel->insert([
            'karang_taruna_id' => 51,
            'nama_lengkap' => 'User D',
            'username' => 'userd',
            'password' => 'pass',
            'role_level' => 'anggota',
            'status_aktif' => 1
        ]);
        
        $memberModel = new OrganizationMemberModel();
        $memberModel->insert(['user_id' => $userId, 'karang_taruna_id' => 51, 'role_level' => 'anggota', 'status_aktif' => 1]);
        
        $token = $this->generateTokenForUser($userModel->find($userId));
        
        // Explicit header to a tenant they DO NOT belong to (52)
        $res = $this->withHeaders([
                        'Authorization' => 'Bearer ' . $token,
                        'X-Karang-Taruna-ID' => '52'
                    ])
                    ->get('api/me');
                    
        // Should return 403 Forbidden
        $res->assertStatus(403);
    }

    public function testInactiveMembershipRejected()
    {
        $db = \Config\Database::connect();
        $db->table('karang_taruna')->insert(['id' => 61, 'nama_organisasi' => 'KT Inactive', 'kode_pin' => '111', 'alamat_lengkap' => '-', 'status_aktif' => 1]);
        
        $userModel = new UserModel();
        $userId = $userModel->insert([
            'karang_taruna_id' => 61,
            'nama_lengkap' => 'User E',
            'username' => 'usere',
            'password' => 'pass',
            'role_level' => 'anggota',
            'status_aktif' => 1
        ]);
        
        $memberModel = new OrganizationMemberModel();
        // Inactive membership
        $memberModel->insert(['user_id' => $userId, 'karang_taruna_id' => 61, 'role_level' => 'anggota', 'status_aktif' => 0]);
        
        $token = $this->generateTokenForUser($userModel->find($userId));
        
        $res = $this->withHeaders([
                        'Authorization' => 'Bearer ' . $token,
                        'X-Karang-Taruna-ID' => '61'
                    ])
                    ->get('api/me');
                    
        // Should return 403 Forbidden
        $res->assertStatus(403);
    }

    public function testRoleCompatibilityAndDataSwitching()
    {
        $db = \Config\Database::connect();
        $db->table('karang_taruna')->insert(['id' => 71, 'nama_organisasi' => 'KT 71', 'kode_pin' => '111', 'alamat_lengkap' => '-', 'status_aktif' => 1]);
        $db->table('karang_taruna')->insert(['id' => 72, 'nama_organisasi' => 'KT 72', 'kode_pin' => '222', 'alamat_lengkap' => '-', 'status_aktif' => 1]);
        
        $userModel = new UserModel();
        // Global legacy role = ketua
        $userId = $userModel->insert([
            'karang_taruna_id' => 71,
            'nama_lengkap' => 'User F',
            'username' => 'userf',
            'password' => 'pass',
            'role_level' => 'ketua', 
            'status_aktif' => 1
        ]);
        
        $memberModel = new OrganizationMemberModel();
        // Tenant 71 = ketua
        $memberModel->insert(['user_id' => $userId, 'karang_taruna_id' => 71, 'role_level' => 'ketua', 'status_aktif' => 1]);
        // Tenant 72 = anggota
        $memberModel->insert(['user_id' => $userId, 'karang_taruna_id' => 72, 'role_level' => 'anggota', 'status_aktif' => 1]);
        
        // Insert events specific to tenant 71 and 72
        $db->table('events')->insert(['karang_taruna_id' => 71, 'nama_acara' => 'Event 71', 'tanggal_acara' => '2027-01-01', 'kode_qr' => 'qr71', 'status_aktif' => 'aktif', 'dibuat_oleh' => $userId]);
        $eventId71 = $db->insertID();
        $db->table('events')->insert(['karang_taruna_id' => 72, 'nama_acara' => 'Event 72', 'tanggal_acara' => '2027-01-01', 'kode_qr' => 'qr72', 'status_aktif' => 'aktif', 'dibuat_oleh' => $userId]);
        $eventId72 = $db->insertID();
        
        $token = $this->generateTokenForUser($userModel->find($userId));
        
        // Test Event list as Tenant 71 (Role: Ketua -> allowed to list, should see Event 71)
        // Event endpoint requires 'admin, pengelola, ketua' to view list
        $res = $this->withHeaders([
                        'Authorization' => 'Bearer ' . $token,
                        'X-Karang-Taruna-ID' => '71'
                    ])
                    ->get('api/events/' . $eventId71);
                    
        $res->assertStatus(200);
        $json = json_decode($res->getJSON(), true);
        $this->assertEquals('Event 71', $json['data']['nama_acara']);

        // Test Event list as Tenant 72 (Role: Anggota -> should be DENIED)
        // Because Event endpoint requires 'pengelola, admin, ketua'
        $res2 = $this->withHeaders([
                        'Authorization' => 'Bearer ' . $token,
                        'X-Karang-Taruna-ID' => '72'
                    ])
                    ->get('api/events/' . $eventId72);
                    
        $res2->assertStatus(403);
        $res2->assertJSONExact(['status' => false, 'message' => 'Forbidden']);
    }
}
