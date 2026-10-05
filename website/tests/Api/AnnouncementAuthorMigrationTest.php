<?php

namespace Tests\Api;

use CodeIgniter\Test\CIUnitTestCase;

final class AnnouncementAuthorMigrationTest extends CIUnitTestCase
{
    private function legacy()
    {
        $db = \Config\Database::connect(['DBDriver' => 'SQLite3', 'database' => ':memory:', 'DBPrefix' => '', 'DBDebug' => true], false);
        $db->query('PRAGMA foreign_keys = ON');
        $db->query('CREATE TABLE users (id INTEGER PRIMARY KEY)');
        $db->query('CREATE TABLE superadmins (id INTEGER PRIMARY KEY)');
        $db->query('CREATE TABLE karang_taruna (id INTEGER PRIMARY KEY)');
        $db->query('CREATE TABLE notification_jobs (id INTEGER PRIMARY KEY AUTOINCREMENT, entity_id INTEGER)');
        $db->query("CREATE TABLE pengumuman (
            id INTEGER PRIMARY KEY AUTOINCREMENT, judul TEXT NOT NULL, isi TEXT NOT NULL,
            dibuat_oleh INTEGER NOT NULL, target_role TEXT NOT NULL DEFAULT 'semua' CHECK(target_role IN ('semua','anggota')),
            status_aktif INTEGER NOT NULL DEFAULT 1, created_at DATETIME, updated_at DATETIME,
            karang_taruna_id INTEGER NOT NULL, dashboard_until DATETIME,
            FOREIGN KEY(dibuat_oleh) REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE,
            FOREIGN KEY(karang_taruna_id) REFERENCES karang_taruna(id) ON UPDATE CASCADE ON DELETE CASCADE)");
        $db->query('CREATE INDEX operator_announcement_order ON pengumuman (karang_taruna_id, created_at DESC, id ASC)');
        $db->query('CREATE TRIGGER enqueue_announcement_notification AFTER INSERT ON pengumuman BEGIN INSERT INTO notification_jobs (entity_id) VALUES (NEW.id); END');
        $db->table('users')->insert(['id' => 1]); $db->table('superadmins')->insert(['id' => 777]);
        $db->table('karang_taruna')->insert(['id' => 101]);
        foreach ([5, 100] as $id) $db->table('pengumuman')->insert(['id' => $id, 'judul' => 'Synthetic legacy', 'isi' => 'Synthetic',
            'dibuat_oleh' => 1, 'karang_taruna_id' => 101, 'created_at' => '2026-01-01 00:00:00']);
        $db->table('pengumuman')->where('id', 100)->delete(); // Higher allocator remains meaningful after restore.
        return $db;
    }

    private function migration($db)
    {
        require_once APPPATH . 'Database/Migrations/2026-10-06-000007_AddSuperadminAnnouncementAuthor.php';
        return new \App\Database\Migrations\AddSuperadminAnnouncementAuthor(\Config\Database::forge($db));
    }

    public function test_legacy_rows_indexes_checks_trigger_and_allocator_survive_forward_rebuild_and_reapply(): void
    {
        $db = $this->legacy();
        try {
            $before = $db->table('pengumuman')->get()->getResultArray();
            $objects = $db->query("SELECT type,name,sql FROM sqlite_master WHERE tbl_name = 'pengumuman' AND type IN ('index','trigger') ORDER BY type,name")->getResultArray();
            $jobs = $db->table('notification_jobs')->countAllResults();
            $migration = $this->migration($db); $migration->up(); $migration->up();
            $after = $db->table('pengumuman')->get()->getResultArray();
            foreach ($after as &$row) { $this->assertNull($row['dibuat_oleh_superadmin']); unset($row['dibuat_oleh_superadmin']); }
            unset($row);
            $this->assertSame($before, $after);
            $this->assertSame($objects, $db->query("SELECT type,name,sql FROM sqlite_master WHERE tbl_name = 'pengumuman' AND type IN ('index','trigger') ORDER BY type,name")->getResultArray());
            $this->assertSame($jobs, $db->table('notification_jobs')->countAllResults()); // Copy must not re-enqueue.
            $this->assertSame('1', (string)$db->query('PRAGMA foreign_keys')->getRowArray()['foreign_keys']);
            $db->table('pengumuman')->insert(['judul' => 'Synthetic browser', 'isi' => 'Synthetic', 'dibuat_oleh' => null,
                'dibuat_oleh_superadmin' => 777, 'karang_taruna_id' => 101]);
            $this->assertSame(101, $db->insertID());
            $this->assertSame($jobs + 1, $db->table('notification_jobs')->countAllResults());
            try {
                $db->table('pengumuman')->insert(['judul' => 'Invalid', 'isi' => 'Synthetic', 'dibuat_oleh_superadmin' => 999999, 'karang_taruna_id' => 101]);
                $this->fail('Invalid superadmin FK accepted');
            } catch (\CodeIgniter\Database\Exceptions\DatabaseException $error) { $this->assertStringContainsString('FOREIGN KEY', $error->getMessage()); }
            $db->resetTransStatus();
            try {
                $db->table('pengumuman')->insert(['judul' => 'Invalid', 'isi' => 'Synthetic', 'dibuat_oleh' => 1, 'target_role' => 'unsupported', 'karang_taruna_id' => 101]);
                $this->fail('Legacy CHECK constraint lost');
            } catch (\CodeIgniter\Database\Exceptions\DatabaseException $error) { $this->assertStringContainsString('CHECK', $error->getMessage()); }
            try { $migration->down(); $this->fail('Lossy author rollback accepted'); }
            catch (\RuntimeException $error) { $this->assertStringContainsString('losslessly', $error->getMessage()); }
        } finally { $db->close(); }
    }

    public function test_existing_rebuild_target_is_not_adopted_and_failure_preserves_original_schema_rows_and_trigger(): void
    {
        $db = $this->legacy();
        try {
            $db->query('CREATE TABLE pengumuman_author_forward_new (sentinel TEXT)');
            $db->table('pengumuman_author_forward_new')->insert(['sentinel' => 'Synthetic preserve']);
            $schema = $db->query("SELECT type,name,sql FROM sqlite_master WHERE tbl_name = 'pengumuman' ORDER BY type,name")->getResultArray();
            $before = $db->table('pengumuman')->get()->getResultArray();
            try { $this->migration($db)->up(); $this->fail('Existing staging table was adopted'); }
            catch (\RuntimeException $error) { $this->assertSame('Announcement author schema write failed', $error->getMessage()); }
            $this->assertSame($before, $db->table('pengumuman')->get()->getResultArray());
            $this->assertSame($schema, $db->query("SELECT type,name,sql FROM sqlite_master WHERE tbl_name = 'pengumuman' ORDER BY type,name")->getResultArray());
            $this->assertSame('Synthetic preserve', $db->table('pengumuman_author_forward_new')->get()->getRowArray()['sentinel']);
            $this->assertSame(0, $db->transDepth);
        } finally { $db->close(); }
    }
}
