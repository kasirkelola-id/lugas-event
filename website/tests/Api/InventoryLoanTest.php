<?php

namespace Tests\Api;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use App\Models\UserModel;
use Tests\Support\AuthTrait;

class InventoryLoanTest extends CIUnitTestCase
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
        $db->table('inventory_loans')->emptyTable();
        $db->table('inventories')->emptyTable();
        $db->table('users')->emptyTable();
        $db->table('karang_taruna')->emptyTable();
        $db->table('organization_members')->emptyTable();
    }

    public function testInsufficientStockAndIdempotency()
    {
        $tenantId = 1;
        $db = \Config\Database::connect();
        $db->table('karang_taruna')->insert([
            'id' => $tenantId,
            'nama_organisasi' => 'KT Test',
            'kode_pin' => '123123',
            'status_aktif' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        $adminId = 100;
        $db->table('users')->insert([
            'id' => $adminId,
            'username' => 'admin_test',
            'password' => password_hash('123456', PASSWORD_BCRYPT),
            'nama_lengkap' => 'Admin Test',
            'role_level' => 'admin',
            'karang_taruna_id' => $tenantId,
        ]);
        $db->table('organization_members')->insert([
            'user_id' => $adminId,
            'karang_taruna_id' => $tenantId,
            'role_level' => 'admin',
            'status_aktif' => 1,
            'username' => 'admin_test'
        ]);

        $memberId = 101;
        $db->table('users')->insert([
            'id' => $memberId,
            'username' => 'member_test',
            'password' => password_hash('123456', PASSWORD_BCRYPT),
            'nama_lengkap' => 'Member Test',
            'role_level' => 'anggota',
            'karang_taruna_id' => $tenantId,
        ]);
        $db->table('organization_members')->insert([
            'user_id' => $memberId,
            'karang_taruna_id' => $tenantId,
            'role_level' => 'anggota',
            'status_aktif' => 1,
            'username' => 'member_test'
        ]);

        $inventoryId = 50;
        $db->table('inventories')->insert([
            'id' => $inventoryId,
            'karang_taruna_id' => $tenantId,
            'name' => 'Kursi',
            'total_quantity' => 10,
            'available_quantity' => 2, // only 2 left
            'condition' => 'Baik',
            'created_at' => date('Y-m-d H:i:s')
        ]);

        $loanId = 500;
        $db->table('inventory_loans')->insert([
            'id' => $loanId,
            'inventory_id' => $inventoryId,
            'user_id' => $memberId,
            'quantity' => 5, // requesting 5
            'borrow_date' => date('Y-m-d'),
            'return_date' => date('Y-m-d'),
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s')
        ]);

        $adminUser = (new UserModel())->find($adminId);
        $token = $this->generateTokenForUser($adminUser);

        // 1. Try to approve, should fail due to insufficient stock
        $result = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant-ID' => $tenantId
        ])->withBodyFormat('json')->patch("api/inventories/loans/$loanId/status", [
            'status' => 'approved'
        ]);
        
        $result->assertStatus(409);
        $responseBody = json_decode($result->getJSON(), true);
        $errorMessage = $responseBody['messages']['error'] ?? ($responseBody['message'] ?? '');
        $this->assertStringContainsString('Stok tidak mencukupi', $errorMessage);

        // Fix the loan quantity to 2
        $db->table('inventory_loans')->where('id', $loanId)->update(['quantity' => 2]);

        // 2. Try to approve again, should succeed
        $result2 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant-ID' => $tenantId
        ])->withBodyFormat('json')->patch("api/inventories/loans/$loanId/status", [
            'status' => 'approved'
        ]);
        $result2->assertStatus(200);

        // Verify stock is now 0
        $inv = $db->table('inventories')->where('id', $inventoryId)->get()->getRowArray();
        $this->assertEquals(0, $inv['available_quantity']);

        // 3. Try to approve again (Idempotency), should succeed but stock remains 0
        $result3 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant-ID' => $tenantId
        ])->withBodyFormat('json')->patch("api/inventories/loans/$loanId/status", [
            'status' => 'approved'
        ]);
        $result3->assertStatus(200);
        $inv2 = $db->table('inventories')->where('id', $inventoryId)->get()->getRowArray();
        $this->assertEquals(0, $inv2['available_quantity']);
        
        // 4. Test Member cannot approve
        $memberUser = (new UserModel())->find($memberId);
        $memberToken = $this->generateTokenForUser($memberUser);
        $result4 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $memberToken,
            'X-Tenant-ID' => $tenantId
        ])->withBodyFormat('json')->patch("api/inventories/loans/$loanId/status", [
            'status' => 'returned'
        ]);
        $result4->assertStatus(403);
    }
}
