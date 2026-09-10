<?php

namespace Tests\Api;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use App\Models\UserModel;
use Tests\Support\AuthTrait;

class WheelFeatureTest extends \Tests\Support\BaseTest
{
    protected $migrateOnce = true;
    protected $refresh = false;
    use FeatureTestTrait;
    use AuthTrait;

    protected $namespace = 'App';

    protected function setUp(): void
    {
        parent::setUp();
        // Clear databases
        $db = \Config\Database::connect();
        $db->disableForeignKeyChecks();
        $db->table('wheel_sessions')->emptyTable();
        $db->table('wheel_items')->emptyTable();
        $db->table('wheel_results')->emptyTable();
        $db->table('users')->emptyTable();
        $db->table('karang_taruna')->emptyTable();
        $db->table('organization_members')->emptyTable();
        $db->enableForeignKeyChecks();
    }

    public function testWheelDuplicateAndAutoFinish()
    {
        $tenantA = 1;
        $db = \Config\Database::connect();
        $db->disableForeignKeyChecks();
        
        $db->table('karang_taruna')->insert([
            'id' => $tenantA, 'nama_organisasi' => 'KT A', 'kode_pin' => '111111', 'status_aktif' => 1, 'created_at' => date('Y-m-d H:i:s')
        ]);

        $adminIdA = 100;
        $adminIdB = 101; // another admin in same tenant
        $db->table('users')->insertBatch([
            ['id' => $adminIdA, 'username' => 'admin_a', 'password' => password_hash('123', PASSWORD_BCRYPT), 'nama_lengkap' => 'Admin A', 'role_level' => 'admin', 'karang_taruna_id' => $tenantA],
            ['id' => $adminIdB, 'username' => 'admin_b', 'password' => password_hash('123', PASSWORD_BCRYPT), 'nama_lengkap' => 'Admin B', 'role_level' => 'admin', 'karang_taruna_id' => $tenantA],
        ]);
        $db->table('organization_members')->insertBatch([
            ['user_id' => $adminIdA, 'karang_taruna_id' => $tenantA, 'role_level' => 'admin', 'status_aktif' => 1, 'username' => 'admin_a'],
            ['user_id' => $adminIdB, 'karang_taruna_id' => $tenantA, 'role_level' => 'admin', 'status_aktif' => 1, 'username' => 'admin_b'],
        ]);

        $adminUserA = (new UserModel())->find($adminIdA);
        $adminTokenA = $this->generateTokenForUser($adminUserA);

        $adminUserB = (new UserModel())->find($adminIdB);
        $adminTokenB = $this->generateTokenForUser($adminUserB);

        // 1. Create session A
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $adminTokenA, 'X-Tenant-ID' => $tenantA])->post("api/wheels", [
            'title' => 'Test Undian',
            'source_type' => 'custom',
            'spin_duration_seconds' => 10,
            'remove_winner_after_spin' => 1,
            'items' => ['Kandidat 1', 'Kandidat 2']
        ]);
        $result->assertStatus(201);
        $body = json_decode($result->getJSON(), true);
        $sessionId = $body['session_id'];

        // 2. Spin it once
        $spin1 = $this->withHeaders(['Authorization' => 'Bearer ' . $adminTokenA, 'X-Tenant-ID' => $tenantA])->post("api/wheels/{$sessionId}/spin");
        $spin1->assertStatus(200);

        // Check if session is closed because 1 item left
        $show1 = $this->withHeaders(['Authorization' => 'Bearer ' . $adminTokenA, 'X-Tenant-ID' => $tenantA])->get("api/wheels/{$sessionId}");
        $show1->assertStatus(200);
        $showBody1 = json_decode($show1->getJSON(), true);
        $this->assertEquals('closed', $showBody1['data']['session']['status']);
        
        $activeItemsCount = 0;
        foreach ($showBody1['data']['items'] as $it) {
            if ($it['is_active'] == 1) $activeItemsCount++;
        }
        $this->assertEquals(1, $activeItemsCount);

        // 3. Duplicate session by Admin B
        $dup = $this->withHeaders(['Authorization' => 'Bearer ' . $adminTokenB, 'X-Tenant-ID' => $tenantA])->post("api/wheels/{$sessionId}/duplicate");
        $dup->assertStatus(201);
        $dupBody = json_decode($dup->getJSON(), true);
        $newSessionId = $dupBody['session_id'];

        // 4. Verify new session
        $show2 = $this->withHeaders(['Authorization' => 'Bearer ' . $adminTokenB, 'X-Tenant-ID' => $tenantA])->get("api/wheels/{$newSessionId}");
        $show2->assertStatus(200);
        $showBody2 = json_decode($show2->getJSON(), true);
        
        $this->assertEquals('active', $showBody2['data']['session']['status']);
        $this->assertEquals('Test Undian (Copy)', $showBody2['data']['session']['title']);
        $this->assertEquals($adminIdB, $showBody2['data']['session']['creator_id']);
        $this->assertCount(2, $showBody2['data']['items']);
        
        $activeItemsCountNew = 0;
        foreach ($showBody2['data']['items'] as $it) {
            if ($it['is_active'] == 1) $activeItemsCountNew++;
        }
        $this->assertEquals(2, $activeItemsCountNew); // Both items should be active again
    }
}
