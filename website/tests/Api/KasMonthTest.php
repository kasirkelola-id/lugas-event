<?php

namespace Tests\Api;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use App\Models\UserModel;
use Tests\Support\AuthTrait;

class KasMonthTest extends \Tests\Support\BaseTest
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
        $db->table('kas')->emptyTable();
        $db->table('users')->emptyTable();
        $db->table('karang_taruna')->emptyTable();
        $db->table('organization_members')->emptyTable();
        $db->enableForeignKeyChecks();
    }

    public function testKasMonthFilterAndTenantIsolation()
    {
        $tenantA = 1;
        $tenantB = 2;
        $db = \Config\Database::connect();
        $db->disableForeignKeyChecks();
        
        $db->table('karang_taruna')->insertBatch([
            ['id' => $tenantA, 'nama_organisasi' => 'KT A', 'kode_pin' => '111111', 'status_aktif' => 1, 'created_at' => date('Y-m-d H:i:s')],
            ['id' => $tenantB, 'nama_organisasi' => 'KT B', 'kode_pin' => '222222', 'status_aktif' => 1, 'created_at' => date('Y-m-d H:i:s')]
        ]);

        $adminIdA = 100;
        $adminIdB = 200;

        $db->table('users')->insertBatch([
            ['id' => $adminIdA, 'username' => 'admin_a', 'password' => password_hash('123', PASSWORD_BCRYPT), 'nama_lengkap' => 'Admin A', 'role_level' => 'admin', 'karang_taruna_id' => $tenantA],
            ['id' => $adminIdB, 'username' => 'admin_b', 'password' => password_hash('123', PASSWORD_BCRYPT), 'nama_lengkap' => 'Admin B', 'role_level' => 'admin', 'karang_taruna_id' => $tenantB],
        ]);

        $db->table('organization_members')->insertBatch([
            ['user_id' => $adminIdA, 'karang_taruna_id' => $tenantA, 'role_level' => 'admin', 'status_aktif' => 1, 'username' => 'admin_a'],
            ['user_id' => $adminIdB, 'karang_taruna_id' => $tenantB, 'role_level' => 'admin', 'status_aktif' => 1, 'username' => 'admin_b'],
        ]);

        // Kas entries for Tenant A
        // Month A (2026-09)
        $db->table('kas')->insertBatch([
            ['karang_taruna_id' => $tenantA, 'keterangan' => 'Masuk Sep 1', 'jenis' => 'pemasukan', 'nominal' => 100000, 'tanggal' => '2026-09-01', 'dibuat_oleh' => $adminIdA],
            ['karang_taruna_id' => $tenantA, 'keterangan' => 'Keluar Sep 1', 'jenis' => 'pengeluaran', 'nominal' => 20000, 'tanggal' => '2026-09-15', 'dibuat_oleh' => $adminIdA],
            // Month B (2026-08)
            ['karang_taruna_id' => $tenantA, 'keterangan' => 'Masuk Aug 1', 'jenis' => 'pemasukan', 'nominal' => 50000, 'tanggal' => '2026-08-01', 'dibuat_oleh' => $adminIdA],
        ]);

        // Kas entries for Tenant B
        // Month A (2026-09)
        $db->table('kas')->insertBatch([
            ['karang_taruna_id' => $tenantB, 'keterangan' => 'Masuk Sep Tenant B', 'jenis' => 'pemasukan', 'nominal' => 300000, 'tanggal' => '2026-09-05', 'dibuat_oleh' => $adminIdB],
        ]);

        $adminUserA = (new UserModel())->find($adminIdA);
        $adminTokenA = $this->generateTokenForUser($adminUserA);

        // Test without month filter => should return all for Tenant A
        $resultAll = $this->withHeaders(['Authorization' => 'Bearer ' . $adminTokenA, 'X-Tenant-ID' => $tenantA])->get("api/kas");
        $resultAll->assertStatus(200);
        $bodyAll = json_decode($resultAll->getJSON(), true);
        $this->assertCount(3, $bodyAll['data']['transaksi']);
        $this->assertEquals(130000, $bodyAll['data']['saldo']);

        // Test with month filter 2026-09 for Tenant A
        $resultMonth = $this->withHeaders(['Authorization' => 'Bearer ' . $adminTokenA, 'X-Tenant-ID' => $tenantA])->get("api/kas?month=2026-09");
        $resultMonth->assertStatus(200);
        $bodyMonth = json_decode($resultMonth->getJSON(), true);

        // Should return 2 entries (Masuk Sep 1, Keluar Sep 1)
        $this->assertCount(2, $bodyMonth['data']['transaksi']);
        $this->assertEquals(130000, $bodyMonth['data']['saldo']); // Saldo for Kas is ALWAYS lifetime total
    }
}
