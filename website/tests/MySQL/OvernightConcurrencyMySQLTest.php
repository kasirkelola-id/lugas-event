<?php

namespace Tests\MySQL;

use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\DisposableMySQL;

final class OvernightConcurrencyMySQLTest extends CIUnitTestCase
{
    private ?DisposableMySQL $fixture = null;
    private $mysqlDb;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('KARTAR_MYSQL_TEST_ENABLE') !== '1') $this->markTestSkipped('Explicit disposable MySQL8 opt-in required');
        $this->fixture = new DisposableMySQL(); $this->mysqlDb = $this->fixture->connect();
        $info = $this->mysqlDb->query('SELECT VERSION() AS version, @@bind_address AS bind_address, @@port AS port')->getRowArray();
        $this->assertSame('127.0.0.1', $info['bind_address']);
        $this->finish($this->start(TESTPATH . '_support/mysql_inventory_worker.php', 'fresh'));
        $db = $this->mysqlDb;
        $db->table('karang_taruna')->insert(['id' => 101, 'nama_organisasi' => 'Synthetic', 'kode_pin' => '999901', 'status_aktif' => 1]);
        foreach ([1, 2] as $id) {
            $db->table('users')->insert(['id' => $id, 'username' => 'synthetic-' . $id, 'nama_lengkap' => 'Synthetic', 'password' => '', 'status_aktif' => 1, 'password_must_change' => 0]);
            $db->table('organization_members')->insert(['id' => $id, 'user_id' => $id, 'karang_taruna_id' => 101, 'username' => 'synthetic-' . $id,
                'role_level' => $id === 1 ? 'ketua' : 'anggota', 'status_aktif' => 1, 'approval_status' => 'approved']);
        }
    }

    protected function tearDown(): void
    {
        if ($this->fixture) { $this->mysqlDb?->close(); $this->fixture->close(); }
        parent::tearDown();
    }

    private function start(string $script, string $mode, string $barrier = ''): array
    {
        $process = proc_open([PHP_BINARY, $script, $mode, $this->fixture->schema, $barrier],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, ROOTPATH, null, ['bypass_shell' => true]);
        $this->assertIsResource($process); fclose($pipes[0]); return [$process, $pipes];
    }

    private function finish(array $worker): array
    {
        [$process, $pipes] = $worker;
        $output = stream_get_contents($pipes[1]); $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]); $exit = proc_close($process);
        file_put_contents(sys_get_temp_dir() . DIRECTORY_SEPARATOR . $this->fixture->schema . '_overnight_child_' . bin2hex(random_bytes(4)) . '.log', $output . "\n" . $stderr);
        $this->assertSame(0, $exit, 'Synthetic child failed; no raw stderr included');
        $this->assertSame('', $stderr, 'Unexpected child stderr; inspect only safe error labels');
        $data = json_decode($output, true, 512, JSON_THROW_ON_ERROR); $this->assertTrue($data['ok']); return $data;
    }

    private function race(string $first, ?string $second = null): array
    {
        $base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'kartar_overnight_race_' . bin2hex(random_bytes(8));
        $workers = [];
        try {
            foreach ([$first, $second ?? $first] as $i => $mode) $workers[] = $this->start(TESTPATH . '_support/mysql_overnight_worker.php', $mode, $base . '_' . $i);
            $deadline = microtime(true) + 15;
            while (!is_file($base . '_0.ready') || !is_file($base . '_1.ready')) {
                if (microtime(true) > $deadline) throw new \RuntimeException('Real workers did not reach barrier'); usleep(10000);
            }
            foreach ([0, 1] as $i) file_put_contents($base . '_' . $i . '.release', 'release');
            $results = array_map(fn($worker) => $this->finish($worker), $workers);
            $this->saveEvidence($first, $results);
            return array_column($results, 'result');
        } finally {
            // Exact harness-created files only; never recursive cleanup.
            foreach ([0, 1] as $i) foreach (['ready', 'release'] as $suffix) if (is_file($base . '_' . $i . '.' . $suffix)) unlink($base . '_' . $i . '.' . $suffix);
        }
    }

    private function saveEvidence(string $label, array $data): void
    {
        file_put_contents(sys_get_temp_dir() . DIRECTORY_SEPARATOR . $this->fixture->schema . '_overnight_' . $label . '.json',
            json_encode(['mysql' => $this->fixture->version, 'schema' => $this->fixture->schema, 'evidence' => $data], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }

    private function event(): void
    {
        $this->mysqlDb->table('events')->insert(['id' => 1, 'karang_taruna_id' => 101, 'nama_acara' => 'Synthetic', 'tanggal_acara' => gmdate('Y-m-d'),
            'kode_qr' => 'synthetic-event', 'dibuat_oleh' => 1, 'status_aktif' => 'selesai']);
    }

    public function test_two_chat_writers_persist_one_row_and_one_atomic_job(): void
    {
        $results = $this->race('chat');
        $this->assertSame([200, 200], array_column($results, 'code'));
        $this->assertSame(1, count(array_filter(array_column($results, 'created'))));
        $this->assertSame($results[0]['id'], $results[1]['id']);
        $this->assertSame(1, $this->mysqlDb->table('chats')->countAllResults());
        $this->assertSame(1, $this->mysqlDb->table('notification_jobs')->countAllResults());
        $this->assertSame($results[0]['id'], (int)$this->mysqlDb->table('notification_jobs')->get()->getRowArray()['entity_id']);
    }

    public function test_competing_membership_decisions_write_one_history(): void
    {
        $this->mysqlDb->table('organization_members')->where('id', 2)->update(['approval_status' => 'pending']);
        $results = $this->race('approval', 'rejection'); $codes = array_column($results, 'code'); sort($codes);
        $this->assertSame([200, 409], $codes);
        $this->assertSame(1, $this->mysqlDb->table('membership_approval_history')->countAllResults());
        $this->assertSame($this->mysqlDb->table('organization_members')->where('id', 2)->get()->getRowArray()['approval_status'],
            $this->mysqlDb->table('membership_approval_history')->get()->getRowArray()['action']);
    }

    public function test_competing_identity_creation_leaves_one_user_and_membership(): void
    {
        $results = $this->race('identity'); $codes = array_column($results, 'code'); sort($codes);
        $this->assertSame([201, 409], $codes);
        $this->assertSame(1, $this->mysqlDb->table('users')->where('username', 'synthetic-race')->countAllResults());
        $this->assertSame(1, $this->mysqlDb->table('organization_members')->where('username', 'synthetic-race')->countAllResults());
        $this->assertSame(3, $this->mysqlDb->table('users')->countAllResults());
    }

    public function test_concurrent_participant_batches_are_idempotent(): void
    {
        $this->event(); $results = $this->race('participants'); $added = array_column($results, 'added'); sort($added);
        $this->assertSame([0, 1], $added);
        $this->assertSame([200, 200], array_column($results, 'code'));
        $this->assertSame(1, $this->mysqlDb->table('event_participants')->where('karang_taruna_id', 101)->countAllResults());
    }

    public function test_simultaneous_queue_claims_dispatch_one_receipt(): void
    {
        $db = $this->mysqlDb;
        $db->table('user_tokens')->insert(['user_id' => 2, 'token_hash' => hash('sha256', 'synthetic'), 'expires_at' => gmdate('Y-m-d H:i:s', time() + 3600)]);
        $db->table('user_devices')->insert(['user_id' => 2, 'user_token_id' => $db->insertID(), 'fcm_token' => 'synthetic-device']);
        $db->table('chats')->insert(['karang_taruna_id' => 101, 'sender_id' => 1, 'receiver_id' => 2, 'type' => 'private', 'message' => 'Synthetic', 'created_at' => gmdate('Y-m-d H:i:s')]);
        $results = $this->race('queue');
        $this->assertSame(1, array_sum(array_column($results, 'claimed')));
        $this->assertSame(1, array_sum(array_column($results, 'sent')));
        $this->assertSame(0, array_sum(array_column($results, 'errors')));
        $this->assertSame('completed', $db->table('notification_jobs')->get()->getRowArray()['status']);
        $receipt = $db->table('notification_deliveries')->get()->getRowArray();
        $this->assertSame('sent', $receipt['status']); $this->assertSame(1, (int)$receipt['attempts']);
    }

    public function test_spin_and_close_serialize_on_the_real_session(): void
    {
        $db = $this->mysqlDb;
        $db->table('wheel_sessions')->insert(['id' => 1, 'karang_taruna_id' => 101, 'created_by_user_id' => 1, 'title' => 'Synthetic',
            'source_type' => 'custom', 'status' => 'active', 'spin_duration_seconds' => 10]);
        foreach (['A', 'B'] as $label) $db->table('wheel_items')->insert(['session_id' => 1, 'label_snapshot' => $label, 'is_active' => 1]);
        $results = $this->race('spin', 'close');
        $this->assertSame(200, $results[1]['code']);
        $this->assertContains($results[0]['code'], [200, 400]);
        $this->assertSame('closed', $db->table('wheel_sessions')->where('id', 1)->get()->getRowArray()['status']);
        $count = $db->table('wheel_results')->countAllResults();
        $this->assertSame($results[0]['code'] === 200 ? 1 : 0, $count);
        $late = $this->finish($this->start(TESTPATH . '_support/mysql_overnight_worker.php', 'spin'));
        $this->assertSame(400, $late['result']['code']);
        $this->assertSame($count, $db->table('wheel_results')->countAllResults());
    }

    public function test_trigger_failure_rolls_back_real_chat_and_job_write(): void
    {
        $db = $this->mysqlDb;
        $db->query("CREATE TRIGGER synthetic_queue_failure BEFORE INSERT ON notification_jobs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'synthetic'");
        try {
            try { $db->table('chats')->insert(['karang_taruna_id' => 101, 'sender_id' => 1, 'receiver_id' => 2, 'type' => 'private', 'message' => 'Synthetic']); $this->fail('Trigger did not reject'); }
            catch (\CodeIgniter\Database\Exceptions\DatabaseException $error) { $this->assertSame(1644, (int)$db->error()['code']); }
            $this->assertSame(0, $db->table('chats')->countAllResults());
            $this->assertSame(0, $db->table('notification_jobs')->countAllResults());
        } finally { $db->query('DROP TRIGGER synthetic_queue_failure'); $db->resetTransStatus(); }
    }

    public function test_measured_index_reapplication_preserves_rows_and_directions(): void
    {
        require_once APPPATH . 'Database/Migrations/2026-10-06-000006_AddMeasuredQueryIndexes.php';
        $db = $this->mysqlDb;
        $db->table('kas')->insert(['karang_taruna_id' => 101, 'jenis' => 'pemasukan', 'nominal' => 10,
            'keterangan' => 'Synthetic', 'tanggal' => '2026-10-06', 'dibuat_oleh' => 1]);
        $before = $db->table('kas')->get()->getResultArray();
        $beforeIndexes = $db->query('SHOW INDEX FROM kas')->getResultArray();
        $migration = new \App\Database\Migrations\AddMeasuredQueryIndexes(\Config\Database::forge($db));
        $migration->up(); $migration->up();
        $this->assertSame($before, $db->table('kas')->get()->getResultArray());
        $this->assertSame($beforeIndexes, $db->query('SHOW INDEX FROM kas')->getResultArray());
        $columns = array_values(array_filter($beforeIndexes, static fn($row) => $row['Key_name'] === 'idx_kas_tenant_page'));
        $this->assertSame(['karang_taruna_id', 'tanggal', 'created_at', 'id'], array_column($columns, 'Column_name'));
        $this->assertSame(['A', 'D', 'D', 'A'], array_column($columns, 'Collation'));
    }

    public function test_bounded_maintenance_preserves_current_rows_and_restores_session_settings(): void
    {
        $db = $this->mysqlDb;
        $old = gmdate('Y-m-d H:i:s', time() - 40 * 86400);
        $future = gmdate('Y-m-d H:i:s', time() + 86400);
        foreach ([$old, $future] as $i => $expiry) {
            $db->table('user_tokens')->insert(['user_id' => 2, 'token_hash' => hash('sha256', 'synthetic-' . $i), 'expires_at' => $expiry]);
            $db->table('user_devices')->insert(['user_id' => 2, 'user_token_id' => $db->insertID(), 'fcm_token' => 'synthetic-' . $i, 'updated_at' => $old]);
        }
        foreach ([$old, gmdate('Y-m-d H:i:s')] as $stamp) $db->table('chats')->insert(['karang_taruna_id' => 101,
            'sender_id' => 1, 'receiver_id' => 2, 'type' => 'private', 'message' => 'Synthetic', 'created_at' => $stamp]);
        $result = $this->finish($this->start(TESTPATH . '_support/mysql_overnight_worker.php', 'maintenance'))['result'];
        $this->assertTrue($result['session_settings_restored']);
        $this->assertSame(0, $result['dry']['tokens_deleted']);
        $this->assertSame(1, $result['sessions']['tokens_deleted']);
        $this->assertSame(1, $result['sessions']['devices_deleted']);
        $this->assertSame(1, $result['chat']['deleted']);
        $this->assertSame(1, $db->table('user_tokens')->countAllResults());
        $this->assertSame('synthetic-1', $db->table('user_devices')->get()->getRowArray()['fcm_token']);
        $this->assertSame(1, $db->table('chats')->countAllResults());
        $this->saveEvidence('maintenance', $result);
    }

    public function test_announcement_author_schema_preserves_user_rows_and_outbox_on_reapplication(): void
    {
        $db = $this->mysqlDb;
        $db->table('superadmins')->insert(['id' => 777, 'username' => 'synthetic-restore-author', 'nama_lengkap' => 'Synthetic Browser Author', 'password' => '']);
        $this->assertTrue($db->table('pengumuman')->insert(['dibuat_oleh' => 1, 'karang_taruna_id' => 101, 'judul' => 'Synthetic legacy', 'isi' => 'Synthetic']));
        // Synthesize the old author definition only inside this freshly owned
        // fixture, retaining the seeded user author and existing outbox trigger.
        $db->query('ALTER TABLE pengumuman DROP FOREIGN KEY pengumuman_superadmin_author_fk,
            DROP COLUMN dibuat_oleh_superadmin, MODIFY dibuat_oleh INT UNSIGNED NOT NULL');
        $db->resetDataCache();
        $legacy = $db->table('pengumuman')->orderBy('id')->get()->getResultArray();
        require_once APPPATH . 'Database/Migrations/2026-10-06-000007_AddSuperadminAnnouncementAuthor.php';
        $migration = new \App\Database\Migrations\AddSuperadminAnnouncementAuthor(\Config\Database::forge($db));
        $migration->up();
        $expected = array_map(static fn($row) => $row + ['dibuat_oleh_superadmin' => null], $legacy);
        $this->assertSame($expected, $db->table('pengumuman')->orderBy('id')->get()->getResultArray());
        $this->assertSame(1, $db->table('notification_jobs')->where('kind', 'announcement')->countAllResults());
        $this->assertTrue($db->table('pengumuman')->insert(['dibuat_oleh' => null, 'dibuat_oleh_superadmin' => 777,
            'karang_taruna_id' => 101, 'judul' => 'Synthetic browser', 'isi' => 'Synthetic']));
        $before = $db->table('pengumuman')->orderBy('id')->get()->getResultArray();
        $ddl = $db->query('SHOW CREATE TABLE pengumuman')->getRowArray();
        $migration->up();
        $this->assertSame($before, $db->table('pengumuman')->orderBy('id')->get()->getResultArray());
        $this->assertSame($ddl, $db->query('SHOW CREATE TABLE pengumuman')->getRowArray());
        $this->assertSame(2, $db->table('notification_jobs')->where('kind', 'announcement')->countAllResults());
        $fields = array_column($db->query('SHOW FULL COLUMNS FROM pengumuman')->getResultArray(), null, 'Field');
        $this->assertSame('YES', $fields['dibuat_oleh']['Null']); $this->assertSame('YES', $fields['dibuat_oleh_superadmin']['Null']);
        $code = null;
        try { $db->table('pengumuman')->insert(['karang_taruna_id' => 101, 'judul' => 'Invalid', 'isi' => 'Synthetic', 'dibuat_oleh_superadmin' => 999999]); }
        catch (\CodeIgniter\Database\Exceptions\DatabaseException $error) { $code = $db->error()['code']; }
        $this->assertSame(1452, $code);
        $this->assertSame(2, $db->table('pengumuman')->countAllResults());
        $this->assertSame(2, $db->table('notification_jobs')->where('kind', 'announcement')->countAllResults());
        $this->saveEvidence('announcement-authors', ['rows_preserved' => true, 'ddl' => $ddl, 'fields' => $fields, 'foreign_key_error' => $code]);
    }
}
