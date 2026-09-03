<?php

namespace Tests\Api;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\AuthTrait;
use App\Models\InventoryModel;
use App\Models\InventoryLoanModel;

class InventoryIntegrityTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthTrait;

    protected $migrate = true;
    protected $migrateOnce = true;
    protected $namespace = 'App';

    protected function tearDown(): void
    {
        $this->cleanUpAuth();
        parent::tearDown();
    }

    public function testInventoryApproveOnce()
    {
        $tenantId = 201;
        $ketua = $this->createTestUser($tenantId, 'ketua');
        $token = $this->generateTokenForUser($ketua);

        $inventoryModel = new InventoryModel();
        $loanModel = new InventoryLoanModel();

        // Seed Inventory
        $invId = $inventoryModel->insert([
            'karang_taruna_id' => $tenantId,
            'name' => 'Tenda',
            'total_quantity' => 10,
            'available_quantity' => 10,
        ]);

        // Seed Loan
        $loanId = $loanModel->insert([
            'inventory_id' => $invId,
            'user_id' => $ketua['id'],
            'quantity' => 4,
            'status' => 'pending',
            'borrow_date' => date('Y-m-d'),
            'return_date' => date('Y-m-d')
        ]);

        // Approve
        $res = $this->withBodyFormat('json')->withHeaders($this->getAuthHeaders($token))->patch('api/inventories/loans/' . $loanId . '/status', [
            'status' => 'approved'
        ]);

        $res->assertStatus(200);

        $dbInv = $inventoryModel->find($invId);
        $this->assertEquals(6, $dbInv['available_quantity'], 'Stock should be 6 after 4 approved.');
    }

    public function testInventoryDoubleApproveIdempotent()
    {
        $tenantId = 201;
        $ketua = $this->createTestUser($tenantId, 'ketua');
        $token = $this->generateTokenForUser($ketua);

        $inventoryModel = new InventoryModel();
        $loanModel = new InventoryLoanModel();

        $invId = $inventoryModel->insert([
            'karang_taruna_id' => $tenantId,
            'name' => 'Tenda',
            'total_quantity' => 10,
            'available_quantity' => 6, // manually simulate already reduced
        ]);

        $loanId = $loanModel->insert([
            'inventory_id' => $invId,
            'user_id' => $ketua['id'],
            'quantity' => 4,
            'status' => 'approved', // already approved
            'borrow_date' => date('Y-m-d'),
            'return_date' => date('Y-m-d')
        ]);

        // Approve again
        $res = $this->withBodyFormat('json')->withHeaders($this->getAuthHeaders($token))->patch('api/inventories/loans/' . $loanId . '/status', [
            'status' => 'approved'
        ]);

        $res->assertStatus(200); // Idempotent success
        $res->assertJSONFragment(['message' => 'Status peminjaman berhasil diproses']);

        $dbInv = $inventoryModel->find($invId);
        $this->assertEquals(6, $dbInv['available_quantity'], 'Stock should remain 6. Must not deduct again.');
    }

    public function testInventoryDoubleReturnIdempotent()
    {
        $tenantId = 201;
        $ketua = $this->createTestUser($tenantId, 'ketua');
        $token = $this->generateTokenForUser($ketua);

        $inventoryModel = new InventoryModel();
        $loanModel = new InventoryLoanModel();

        $invId = $inventoryModel->insert([
            'karang_taruna_id' => $tenantId,
            'name' => 'Tenda',
            'total_quantity' => 10,
            'available_quantity' => 10, // already returned
        ]);

        $loanId = $loanModel->insert([
            'inventory_id' => $invId,
            'user_id' => $ketua['id'],
            'quantity' => 4,
            'status' => 'returned', // already returned
            'borrow_date' => date('Y-m-d'),
            'return_date' => date('Y-m-d')
        ]);

        // Return again
        $res = $this->withBodyFormat('json')->withHeaders($this->getAuthHeaders($token))->patch('api/inventories/loans/' . $loanId . '/status', [
            'status' => 'returned'
        ]);

        $res->assertStatus(200);

        $dbInv = $inventoryModel->find($invId);
        $this->assertEquals(10, $dbInv['available_quantity'], 'Stock should remain 10. Must not increase again.');
    }

    public function testInventoryInsufficientStock()
    {
        $tenantId = 201;
        $ketua = $this->createTestUser($tenantId, 'ketua');
        $token = $this->generateTokenForUser($ketua);

        $inventoryModel = new InventoryModel();
        $loanModel = new InventoryLoanModel();

        $invId = $inventoryModel->insert([
            'karang_taruna_id' => $tenantId,
            'name' => 'Tenda',
            'total_quantity' => 10,
            'available_quantity' => 3, // only 3 left
        ]);

        $loanId = $loanModel->insert([
            'inventory_id' => $invId,
            'user_id' => $ketua['id'],
            'quantity' => 4,
            'status' => 'pending',
            'borrow_date' => date('Y-m-d'),
            'return_date' => date('Y-m-d')
        ]);

        // Approve
        $res = $this->withBodyFormat('json')->withHeaders($this->getAuthHeaders($token))->patch('api/inventories/loans/' . $loanId . '/status', [
            'status' => 'approved'
        ]);

        $res->assertStatus(409); // Conflict

        $dbInv = $inventoryModel->find($invId);
        $this->assertEquals(3, $dbInv['available_quantity'], 'Stock should remain 3.');

        $dbLoan = $loanModel->find($loanId);
        $this->assertEquals('pending', $dbLoan['status'], 'Loan should remain pending.');
    }

    public function testInventoryInvalidTransition()
    {
        $tenantId = 201;
        $ketua = $this->createTestUser($tenantId, 'ketua');
        $token = $this->generateTokenForUser($ketua);

        $inventoryModel = new InventoryModel();
        $loanModel = new InventoryLoanModel();

        $invId = $inventoryModel->insert([
            'karang_taruna_id' => $tenantId,
            'name' => 'Tenda',
            'total_quantity' => 10,
            'available_quantity' => 10,
        ]);

        $loanId = $loanModel->insert([
            'inventory_id' => $invId,
            'user_id' => $ketua['id'],
            'quantity' => 4,
            'status' => 'returned', // currently returned
            'borrow_date' => date('Y-m-d'),
            'return_date' => date('Y-m-d')
        ]);

        // Try to approve a returned loan
        $res = $this->withBodyFormat('json')->withHeaders($this->getAuthHeaders($token))->patch('api/inventories/loans/' . $loanId . '/status', [
            'status' => 'approved'
        ]);

        $res->assertStatus(409); // Conflict

        $dbInv = $inventoryModel->find($invId);
        $this->assertEquals(10, $dbInv['available_quantity']);
    }

    public function testStrictQuantityValidation()
    {
        $tenantId = 201;
        $ketua = $this->createTestUser($tenantId, 'ketua');
        $token = $this->generateTokenForUser($ketua);

        $inventoryModel = new InventoryModel();
        
        $invId = $inventoryModel->insert([
            'karang_taruna_id' => $tenantId,
            'name' => 'Tenda',
            'total_quantity' => 10,
            'available_quantity' => 10,
        ]);

        $invalidQuantities = ["4abc", "3.8", 0, -1, ""];

        foreach ($invalidQuantities as $qty) {
            $res = $this->withBodyFormat('json')->withHeaders($this->getAuthHeaders($token))->post('api/inventories/loans', [
                'inventory_id' => $invId,
                'quantity' => $qty,
                'borrow_date' => date('Y-m-d'),
                'return_date' => date('Y-m-d')
            ]);

            $res->assertStatus(400);
            $json = json_decode($res->getJSON(), true);
            $this->assertArrayHasKey('quantity', $json['messages']);
        }
    }
}
