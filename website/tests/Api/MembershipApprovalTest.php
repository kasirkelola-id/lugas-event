<?php

namespace Tests\Api;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use App\Models\UserModel;
use App\Models\KarangTarunaModel;
use App\Models\OrganizationMemberModel;
use Tests\Support\AuthTrait;

class MembershipApprovalTest extends \Tests\Support\BaseTest
{
    protected $migrateOnce = true;
    protected $refresh = false;
    use FeatureTestTrait;
    use AuthTrait;

    protected $migrate = true;
    
    protected $namespace = 'App';

    protected function setUp(): void
    {
        parent::setUp();
        helper('text');
    }

    private function createTenantAndUsers()
    {
        $tenantModel = new KarangTarunaModel();
        $tenantId = $tenantModel->insert([
            'nama_organisasi' => 'KT Maju Bersama',
            'deskripsi' => 'Test',
            'provinsi' => 'Test',
            'kabupaten_kota' => 'Test',
            'kecamatan' => 'Test',
            'kelurahan_id' => 1,
            'kode_pin' => '123456',
            'alamat_lengkap' => 'Test Alamat'
        ]);

        $userModel = new UserModel();
        $memberModel = new OrganizationMemberModel();

        $roles = ['ketua', 'bendahara', 'sekretaris', 'pengelola', 'admin', 'anggota', 'superadmin'];
        $users = [];

        foreach ($roles as $role) {
            if ($role === 'superadmin') {
                $users[$role] = ['id' => null, 'karang_taruna_id' => $tenantId, 'role_level' => 'superadmin'];
                continue;
            }

            $id = $userModel->insert([
                'username' => "{$role}user",
                'email' => "{$role}@test.com",
                'password' => password_hash('password123', PASSWORD_BCRYPT),
                'nama_lengkap' => ucfirst($role),
                'no_whatsapp' => '0812345678',
                'role' => 'user'
            ]);
            $users[$role] = $userModel->find($id);

            $memberModel->insert([
                'user_id' => $id,
                'karang_taruna_id' => $tenantId,
                'role_level' => $role,
                'status' => 'active',
                'approval_status' => 'approved',
                'username' => "{$role}user"
            ]);
        }

        // Pending user
        $pendingId = $userModel->insert([
            'username' => "pendinguser",
            'email' => "pending@test.com",
            'password' => password_hash('password123', PASSWORD_BCRYPT),
            'nama_lengkap' => 'Pending User',
            'no_whatsapp' => '0812345678',
            'role' => 'user'
        ]);
        $memberModel->insert([
            'user_id' => $pendingId,
            'karang_taruna_id' => $tenantId,
            'role_level' => 'anggota',
            'status' => 'active',
            'approval_status' => 'pending',
            'username' => "pendinguser"
        ]);
        $pendingMember = $memberModel->where('user_id', $pendingId)->first();

        return [$tenantId, $users, $pendingMember];
    }

    public function testApprovalRbac()
    {
        [$tenantId, $users, $pendingMember] = $this->createTenantAndUsers();

        $allowedRoles = ['ketua', 'bendahara', 'sekretaris', 'superadmin'];
        $deniedRoles = ['admin', 'pengelola', 'anggota'];

        // Test Allowed
        foreach ($allowedRoles as $role) {
            $user = $users[$role];
            $token = $this->generateTokenForUser($user);
            $res = $this->withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'X-Karang-Taruna-ID' => $tenantId
            ])->post("api/memberships/{$pendingMember['id']}/approve");

            $res->assertStatus(200);
            
            // Verify history was written
            $db = \Config\Database::connect();
            $history = $db->table('membership_approval_history')
                          ->where('organization_member_id', $pendingMember['id'])
                          ->where('action', 'approved')
                          ->orderBy('id', 'DESC')
                          ->get()
                          ->getRowArray();
                          
            $this->assertNotNull($history, "History for {$role} should be written");
            $this->assertEquals($tenantId, $history['karang_taruna_id']);
            $this->assertEquals($user['id'], $history['actor_user_id']);
            
            // reset pending status for next test
            $db->table('organization_members')->where('id', $pendingMember['id'])->update(['approval_status' => 'pending']);
        }
        
        // Test Denied
        foreach ($deniedRoles as $role) {
            $user = $users[$role];
            $token = $this->generateTokenForUser($user);
            $result = $this->withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'X-Karang-Taruna-ID' => $tenantId
            ])->post("api/memberships/{$pendingMember['id']}/approve");

            $result->assertStatus(403);
        }
    }
}
