<?php

namespace Tests\Api;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\AuthTrait;
use App\Models\KasModel;

class TenantIsolationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthTrait;

    protected $migrate = true;
    protected $migrateOnce = true;
    protected $namespace = 'App';

    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        $this->cleanUpAuth();
        parent::tearDown();
    }

    public function testKasIsolationAndSummary()
    {
        $tenantA = 101;
        $tenantB = 102;

        $adminA = $this->createTestUser($tenantA, 'ketua');
        $tokenA = $this->generateTokenForUser($adminA);

        $adminB = $this->createTestUser($tenantB, 'ketua');
        $tokenB = $this->generateTokenForUser($adminB);

        // Seed Kas A
        $kasModel = new KasModel();
        $kasModel->insert([
            'karang_taruna_id' => $tenantA,
            'jenis' => 'pemasukan',
            'nominal' => 50000,
            'keterangan' => 'Kas masuk A',
            'dibuat_oleh' => $adminA['id'],
            'tanggal' => date('Y-m-d')
        ]);
        
        $kasModel->insert([
            'karang_taruna_id' => $tenantA,
            'jenis' => 'pengeluaran',
            'nominal' => 10000,
            'keterangan' => 'Kas keluar A',
            'dibuat_oleh' => $adminA['id'],
            'tanggal' => date('Y-m-d')
        ]);

        // Seed Kas B
        $kasBId = $kasModel->insert([
            'karang_taruna_id' => $tenantB,
            'jenis' => 'pemasukan',
            'nominal' => 100000,
            'keterangan' => 'Kas masuk B',
            'dibuat_oleh' => $adminB['id'],
            'tanggal' => date('Y-m-d')
        ]);

        // Test 1: Admin A requests kas list, should only see Kas A
        $resultA = $this->withHeaders($this->getAuthHeaders($tokenA))->get('api/kas');
        $resultA->assertStatus(200);
        $resultA->assertJSONExactString('Kas masuk A');
        // Make sure B is absent
        $json = json_decode($resultA->getJSON(), true);
        $foundB = false;
        foreach ($json['data']['transaksi'] ?? [] as $item) {
            if ($item['nominal'] == 100000) $foundB = true;
        }
        $this->assertFalse($foundB, "Tenant A should not see Tenant B's Kas");

        // Test 2: Admin A Kas Summary
        $summaryA = $this->withHeaders($this->getAuthHeaders($tokenA))->get('api/kas/summary');
        $summaryA->assertStatus(200);
        $summaryData = json_decode($summaryA->getJSON(), true)['data'] ?? [];
        $this->assertEquals(40000, $summaryData['saldo_akhir'] ?? 0);

        // Test 3: Admin A attempts to delete Kas B (IDOR)
        $deleteReq = $this->withHeaders($this->getAuthHeaders($tokenA))->delete('api/kas/' . $kasBId);
        $deleteReq->assertStatus(404);

        // Verify Kas B still exists
        $this->assertNotNull($kasModel->find($kasBId));

        // Test 4: Kas Create Tenant Override
        $createReq = $this->withBodyFormat('json')->withHeaders($this->getAuthHeaders($tokenA))->post('api/kas', [
            'karang_taruna_id' => $tenantB, // Attempt override
            'jenis' => 'pemasukan',
            'nominal' => 15000,
            'keterangan' => 'Hacked Kas',
            'tanggal' => date('Y-m-d')
        ]);
        $createReq->assertStatus(201);
        
        $insertedKas = $kasModel->where('keterangan', 'Hacked Kas')->first();
        $this->assertNotNull($insertedKas);
        // Important: Should be saved as Tenant A despite the payload
        $this->assertEquals($tenantA, $insertedKas['karang_taruna_id']);
    }

    public function testUserManagementIsolation()
    {
        $tenantA = 101;
        $tenantB = 102;

        $ketuaA = $this->createTestUser($tenantA, 'ketua', 'ketuaA');
        $tokenA = $this->generateTokenForUser($ketuaA);

        $memberB = $this->createTestUser($tenantB, 'anggota', 'memberB');

        // Admin A attempts to disable User B
        $toggleReq = $this->withHeaders($this->getAuthHeaders($tokenA))->patch('api/users/' . $memberB['id'] . '/status');
        $toggleReq->assertStatus(404);

        // Verify User B still active
        $userModel = new \App\Models\UserModel();
        $dbMemberB = $userModel->find($memberB['id']);
        $this->assertEquals(1, $dbMemberB['status_aktif']);

    }
}
