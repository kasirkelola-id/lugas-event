<?php

namespace Tests\Api;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use App\Models\UserModel;
use App\Models\OrganizationMemberModel;
use Tests\Support\AuthTrait;
use App\Services\AuthService;

class RbacPolicyTest extends CIUnitTestCase
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

    // --- 1. META TEST & CONFIG VALIDATION ---

    public function testConfigValidation()
    {
        $config = config('Rbac');
        $this->assertIsArray($config->permissions);
        
        foreach ($config->permissions as $role => $perms) {
            $this->assertIsString($role);
            $this->assertIsArray($perms);
            
            foreach ($perms as $p) {
                $this->assertIsString($p);
                $this->assertNotEmpty($p);
            }
            
            // Check for duplicates
            $this->assertEquals(count($perms), count(array_unique($perms)), "Role $role has duplicate permissions in config");
        }
    }

    public function testCanonicalCatalogAgainstControllers()
    {
        $config = config('Rbac');
        $allPermissions = [];
        foreach ($config->permissions as $perms) {
            $allPermissions = array_merge($allPermissions, $perms);
        }
        $allPermissions = array_unique($allPermissions);

        // Scan controllers
        $controllerPath = APPPATH . 'Controllers/Api';
        $files = glob($controllerPath . '/*.php');
        
        $usedPermissions = [];
        
        foreach ($files as $file) {
            $content = file_get_contents($file);
            // Match AuthService::can('...') or AuthService::requirePermission('...')
            if (preg_match_all('/AuthService::(?:can|requirePermission)\(\'([a-z_.]+)\'\)/', $content, $matches)) {
                foreach ($matches[1] as $match) {
                    $usedPermissions[] = $match;
                }
            }
        }
        
        $usedPermissions = array_unique($usedPermissions);

        foreach ($usedPermissions as $perm) {
            $this->assertContains($perm, $allPermissions, "Permission '$perm' used in controller but not defined in Rbac.php");
        }
    }

    // --- 2. ENDPOINT POLICY TESTS ---

    private function setupTenantUser($role, $tenantId = 1)
    {
        $db = \Config\Database::connect();
        $db->table('karang_taruna')->ignore(true)->insert(['id' => $tenantId, 'nama_organisasi' => "Tenant $tenantId", 'kode_pin' => '123123', 'status_aktif' => 1]);

        $userModel = new UserModel();
        $userId = $userModel->insert([
            'karang_taruna_id' => $tenantId, // Original global tenant
            'nama_lengkap' => "User $role",
            'username' => "user_$role" . uniqid(),
            'password' => 'pass',
            'role_level' => 'anggota', // Global role usually anggota
            'status_aktif' => 1
        ]);
        
        $memberModel = new OrganizationMemberModel();
        $memberModel->insert([
            'user_id' => $userId,
            'karang_taruna_id' => $tenantId,
            'role_level' => $role,
            'status_aktif' => 1
        ]);

        return $userModel->find($userId);
    }

    public function testNegativeAndPositiveTestsAnggota()
    {
        $user = $this->setupTenantUser('anggota', 10);
        $token = $this->generateTokenForUser($user);
        $headers = ['Authorization' => 'Bearer ' . $token, 'X-Karang-Taruna-ID' => '10'];

        // Positive Tests (Should be 200 or 404/not 403 because allowed to reach logic)
        $resViewCash = $this->withHeaders($headers)->get('api/kas');
        $resViewCash->assertStatus(200);

        // Negative Tests
        $resCreateCash = $this->withHeaders($headers)->post('api/kas', []);
        $resCreateCash->assertStatus(403);

        $resEventManage = $this->withHeaders($headers)->post('api/events', []);
        $resEventManage->assertStatus(403);

        // Inventory Status (approve/reject/return)
        $resInventoryApprove = $this->withHeaders($headers)->patch('api/inventories/loans/1/status', ['status' => 'disetujui']);
        $resInventoryApprove->assertStatus(403);
    }

    public function testBendaharaEndpoints()
    {
        $user = $this->setupTenantUser('bendahara', 11);
        $token = $this->generateTokenForUser($user);
        $headers = ['Authorization' => 'Bearer ' . $token, 'X-Karang-Taruna-ID' => '11'];

        // Can create cash
        $resCreateCash = $this->withHeaders($headers)->post('api/kas', [
            'type' => 'in',
            'amount' => 1000,
            'description' => 'test'
        ]);
        // 422 if validation fails, 201 if success, but NOT 403
        $this->assertNotEquals(403, $resCreateCash->response()->getStatusCode());

        // Cannot manage events
        $resEventManage = $this->withHeaders($headers)->post('api/events', []);
        $resEventManage->assertStatus(403);
    }

    public function testSekretarisEndpoints()
    {
        $user = $this->setupTenantUser('sekretaris', 12);
        $token = $this->generateTokenForUser($user);
        $headers = ['Authorization' => 'Bearer ' . $token, 'X-Karang-Taruna-ID' => '12'];

        // Can manage announcements
        $resAnnounce = $this->withHeaders($headers)->post('api/announcements', []);
        $this->assertNotEquals(403, $resAnnounce->response()->getStatusCode());

        // Cannot approve inventory
        $resInvApprove = $this->withHeaders($headers)->patch('api/inventories/loans/1/status', ['status' => 'disetujui']);
        $resInvApprove->assertStatus(403);
    }

    public function testPengelolaEndpoints()
    {
        $user = $this->setupTenantUser('pengelola', 13);
        $token = $this->generateTokenForUser($user);
        $headers = ['Authorization' => 'Bearer ' . $token, 'X-Karang-Taruna-ID' => '13'];

        // Can manage events
        $resEventManage = $this->withHeaders($headers)->post('api/events', []);
        $this->assertNotEquals(403, $resEventManage->response()->getStatusCode());

        // Cannot create cash
        $resCreateCash = $this->withHeaders($headers)->post('api/kas', []);
        $resCreateCash->assertStatus(403);
    }

    public function testKetuaEndpoints()
    {
        $user = $this->setupTenantUser('ketua', 14);
        $token = $this->generateTokenForUser($user);
        $headers = ['Authorization' => 'Bearer ' . $token, 'X-Karang-Taruna-ID' => '14'];

        // Can approve inventory
        $resInvApprove = $this->withHeaders($headers)->patch('api/inventories/loans/1/status', ['status' => 'disetujui']);
        $this->assertNotEquals(403, $resInvApprove->response()->getStatusCode()); // maybe 404 because loan doesn't exist, but not 403

        // Can view report
        $resReport = $this->withHeaders($headers)->get('api/reports/summary');
        $this->assertNotEquals(403, $resReport->response()->getStatusCode());
    }

    // --- 3. ADVANCED SECURITY SCENARIOS ---

    public function testSameUserDifferentRole()
    {
        $db = \Config\Database::connect();
        $db->table('karang_taruna')->ignore(true)->insert(['id' => 20, 'nama_organisasi' => "Tenant 20", 'kode_pin' => '123', 'status_aktif' => 1]);
        $db->table('karang_taruna')->ignore(true)->insert(['id' => 21, 'nama_organisasi' => "Tenant 21", 'kode_pin' => '456', 'status_aktif' => 1]);

        $userModel = new UserModel();
        $userId = $userModel->insert([
            'karang_taruna_id' => 20,
            'nama_lengkap' => "Multi Role",
            'username' => "multi",
            'password' => 'pass',
            'role_level' => 'anggota',
            'status_aktif' => 1
        ]);
        
        $memberModel = new OrganizationMemberModel();
        // Ketua in 20
        $memberModel->insert(['user_id' => $userId, 'karang_taruna_id' => 20, 'role_level' => 'ketua', 'status_aktif' => 1]);
        // Anggota in 21
        $memberModel->insert(['user_id' => $userId, 'karang_taruna_id' => 21, 'role_level' => 'anggota', 'status_aktif' => 1]);

        $user = $userModel->find($userId);
        $token = $this->generateTokenForUser($user);

        // Access as Ketua (Tenant 20) -> Success
        $resA = $this->withHeaders(['Authorization' => 'Bearer ' . $token, 'X-Karang-Taruna-ID' => '20'])
                     ->post('api/kas', []);
        $this->assertNotEquals(403, $resA->response()->getStatusCode());

        // Access as Anggota (Tenant 21) -> Denied
        $resB = $this->withHeaders(['Authorization' => 'Bearer ' . $token, 'X-Karang-Taruna-ID' => '21'])
                     ->post('api/kas', []);
        $resB->assertStatus(403);
    }

    public function testTenantIsolationRbacComposition()
    {
        // User is Ketua in Tenant A, attempts to read resource of Tenant B using Tenant A header
        $user = $this->setupTenantUser('ketua', 30);
        $token = $this->generateTokenForUser($user);
        $headers = ['Authorization' => 'Bearer ' . $token, 'X-Karang-Taruna-ID' => '30'];

        $db = \Config\Database::connect();
        $db->table('karang_taruna')->ignore(true)->insert(['id' => 31, 'nama_organisasi' => "Tenant B", 'kode_pin' => '123', 'status_aktif' => 1]);
        $db->table('events')->insert(['karang_taruna_id' => 31, 'nama_acara' => 'Secret', 'tanggal_acara' => '2026-09-01', 'kode_qr' => 'qr123', 'status_aktif' => 'aktif', 'dibuat_oleh' => $user['id']]);
        $eventId = $db->insertID();

        // Should return 404 because event is not found in Tenant A
        $res = $this->withHeaders($headers)->get('api/events/' . $eventId);
        $res->assertStatus(404);
    }

    public function testHeaderTampering()
    {
        // User belongs to Tenant 40, tries to access Tenant 41
        $user = $this->setupTenantUser('anggota', 40);
        $token = $this->generateTokenForUser($user);
        
        $db = \Config\Database::connect();
        $db->table('karang_taruna')->ignore(true)->insert(['id' => 41, 'nama_organisasi' => "Tenant 41", 'kode_pin' => '123', 'status_aktif' => 1]);

        $headers = ['Authorization' => 'Bearer ' . $token, 'X-Karang-Taruna-ID' => '41'];

        // Access denied entirely before reaching RBAC (membership resolution fails)
        $res = $this->withHeaders($headers)->get('api/events');
        $res->assertStatus(403); // Our auth filter rejects if membership not found for header
    }

    // testPasswordResetHardening removed because reset password feature was removed
    
    public function testSuperadminHardening()
    {
        // Superadmin uses global identity level, but if they access tenant endpoint they must have membership
        // Wait, does Superadmin have auto-membership to everything?
        // No, current logic says Membership resolution determines role. If Superadmin doesn't have membership, they can't access tenant data unless specifically bypassed.
        // Let's create a Superadmin with NO membership in Tenant 60.
        
        $db = \Config\Database::connect();
        $db->table('karang_taruna')->ignore(true)->insert(['id' => 60, 'nama_organisasi' => "Tenant 60", 'kode_pin' => '123', 'status_aktif' => 1]);
        
        $userModel = new UserModel();
        $adminId = $userModel->insert([
            'karang_taruna_id' => 1,
            'nama_lengkap' => "Superadmin",
            'username' => "super",
            'password' => 'pass',
            'role_level' => 'superadmin',
            'status_aktif' => 1
        ]);
        
        $adminUser = $userModel->find($adminId);
        $token = $this->generateTokenForUser($adminUser);
        
        // No membership added!
        $headers = ['Authorization' => 'Bearer ' . $token, 'X-Karang-Taruna-ID' => '60'];
        
        $res = $this->withHeaders($headers)->get('api/events');
        $res->assertStatus(403);
    }
    
    public function testUnknownRoleDeny()
    {
        // Create user with unknown role in membership
        $user = $this->setupTenantUser('alien_role', 70);
        $token = $this->generateTokenForUser($user);
        $headers = ['Authorization' => 'Bearer ' . $token, 'X-Karang-Taruna-ID' => '70'];
        
        $res = $this->withHeaders($headers)->get('api/events');
        $statusCode = $res->response()->getStatusCode();
        if ($statusCode !== 403) {
            echo "\ntestUnknownRoleDeny output: " . $res->getJSON() . "\n";
        }
        $res->assertStatus(403);
    }
}
