<?php

namespace Tests\Api;

use App\Database\Migrations\AddMeasuredQueryIndexes;
use Tests\Support\AuthTrait;
use Tests\Support\BaseTest;

final class MeasuredIndexMigrationTest extends BaseTest
{
    use AuthTrait;
    protected $namespace = 'App';

    protected function tearDown(): void
    {
        try {
            // Restore only this test's SQLite fixture indexes for migrate-once suites.
            $this->db->query('DROP INDEX IF EXISTS operator_cash_page');
            $this->db->query('DROP INDEX IF EXISTS idx_kas_tenant_page');
            (new AddMeasuredQueryIndexes(\Config\Database::forge($this->db)))->up();
        } finally { parent::tearDown(); }
    }

    public function test_reapplication_preserves_rows_and_reuses_an_equivalent_operator_index(): void
    {
        $user = $this->createTestUser(101, 'ketua');
        $this->db->table('kas')->insert(['karang_taruna_id' => 101, 'jenis' => 'pemasukan', 'nominal' => 10,
            'keterangan' => 'Synthetic', 'tanggal' => '2026-10-06', 'dibuat_oleh' => $user['id']]);
        $before = $this->db->table('kas')->get()->getResultArray();
        $this->db->query('DROP INDEX idx_kas_tenant_page');
        $this->db->query('CREATE INDEX operator_cash_page ON kas (karang_taruna_id ASC, tanggal DESC, created_at DESC, id ASC)');
        $migration = new AddMeasuredQueryIndexes(\Config\Database::forge($this->db));
        $migration->up(); $migration->up();
        $names = array_keys($this->db->getIndexData('kas'));
        $this->assertContains('operator_cash_page', $names);
        $this->assertNotContains('idx_kas_tenant_page', $names);
        $this->assertSame($before, $this->db->table('kas')->get()->getResultArray());
    }

    public function test_conflicting_name_refuses_ddl_before_creating_the_other_index(): void
    {
        $this->db->query('DROP INDEX idx_kas_tenant_page');
        $this->db->query('DROP INDEX idx_notification_expired_lease');
        $this->db->query('CREATE INDEX idx_kas_tenant_page ON kas (id)');
        try {
            (new AddMeasuredQueryIndexes(\Config\Database::forge($this->db)))->up();
            $this->fail('Conflicting schema should be refused');
        } catch (\RuntimeException $error) {
            $this->assertSame('Measured index name conflicts with existing schema', $error->getMessage());
        }
        $this->assertNotContains('idx_notification_expired_lease', array_keys($this->db->getIndexData('notification_jobs')));
    }
}
