<?php

namespace Tests\MySQL;

use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\DisposableMySQL;

/** Real MySQL 8 ONLY. Explicit opt-in; otherwise six cases are NOT RUN/skipped. */
class InventorySchemaMySQLTest extends CIUnitTestCase
{
    private ?DisposableMySQL $fixture = null;
    private $mysqlDb;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('KARTAR_MYSQL_TEST_ENABLE') !== '1') {
            $this->markTestSkipped('NOT PROVEN: local disposable MySQL 8 fixture not explicitly enabled');
        }
        $this->fixture = new DisposableMySQL();
        $this->mysqlDb = $this->fixture->connect();
    }

    protected function tearDown(): void
    {
        if ($this->fixture !== null) {
            $this->mysqlDb?->close();
            $this->fixture->close();
        }
        parent::tearDown();
    }

    private function startWorker(string $mode, array $arguments = []): array
    {
        $pipes = [];
        $process = proc_open(array_merge([
            PHP_BINARY, TESTPATH . '_support/mysql_inventory_worker.php', $mode, $this->fixture->schema,
        ], $arguments), [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, ROOTPATH, null, ['bypass_shell' => true]);
        $this->assertIsResource($process);
        fclose($pipes[0]);
        return [$process, $pipes];
    }

    private function finishWorker(array $worker): array
    {
        [$process, $pipes] = $worker;
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        $this->assertSame(0, $exit, $error);
        $data = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($data['ok']);
        return $data['result'];
    }

    private function migrate(string $mode = 'fresh'): void
    {
        $this->finishWorker($this->startWorker($mode));
    }

    private function seedLoans(string $state, int $stock, int $count = 1): array
    {
        $this->migrate();
        foreach (['inventories', 'inventory_loans'] as $table) {
            $engine = $this->mysqlDb->query('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
                [$this->fixture->schema, $table])->getRow()->ENGINE;
            $this->assertSame('InnoDB', $engine);
        }
        $this->mysqlDb->table('karang_taruna')->insert(['id' => 101, 'nama_organisasi' => 'Synthetic', 'kode_pin' => 'B4TEST']);
        $this->mysqlDb->table('users')->insert(['nama_lengkap' => 'Synthetic', 'username' => 'synthetic', 'password' => 'unused']);
        $user = $this->mysqlDb->insertID();
        $this->mysqlDb->table('inventories')->insert(['karang_taruna_id' => 101, 'name' => 'Synthetic', 'total_quantity' => 1, 'available_quantity' => $stock]);
        $inventory = $this->mysqlDb->insertID();
        $loans = [];
        for ($i = 0; $i < $count; $i++) {
            $this->mysqlDb->table('inventory_loans')->insert(['inventory_id' => $inventory, 'user_id' => $user, 'quantity' => 1, 'status' => $state]);
            $loans[] = $this->mysqlDb->insertID();
        }
        return $loans;
    }

    private function race(array $loans, array $statuses): array
    {
        $base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'kartar_batch4_barrier_' . bin2hex(random_bytes(8));
        $workers = [];
        try {
            for ($i = 0; $i < 2; $i++) {
                $workers[] = $this->startWorker('transition', [(string) $loans[$i], $statuses[$i], $base . '_' . $i]);
            }
            $deadline = microtime(true) + 10;
            while (!is_file($base . '_0.ready') || !is_file($base . '_1.ready')) {
                if (microtime(true) > $deadline) {
                    $this->fail('Concurrent MySQL processes did not reach the read barrier');
                }
                usleep(10000);
            }
            foreach ([0, 1] as $i) {
                file_put_contents($base . '_' . $i . '.release', 'release');
            }
            $results = array_map(fn ($worker) => $this->finishWorker($worker), $workers);
            $snapshot = [
                'mysql' => $this->fixture->version, 'responses' => $results,
                'loans' => $this->mysqlDb->table('inventory_loans')->get()->getResultArray(),
                'stock' => (int) $this->mysqlDb->table('inventories')->get()->getRow()->available_quantity,
            ];
            file_put_contents($base . '.json', json_encode($snapshot, JSON_PRETTY_PRINT));
            return $snapshot;
        } finally {
            foreach ($workers as [$process]) {
                if (is_resource($process)) {
                    proc_terminate($process);
                    proc_close($process);
                }
            }
            foreach ([0, 1] as $i) {
                foreach (['ready', 'release'] as $suffix) {
                    $path = $base . '_' . $i . '.' . $suffix;
                    if (is_file($path)) {
                        unlink($path);
                    }
                }
            }
        }
    }

    public function testTwoApprovalsOnLastStock(): void
    {
        $loans = $this->seedLoans('pending', 1, 2);
        $result = $this->race($loans, ['approved', 'approved']);
        $codes = array_column($result['responses'], 'code');
        sort($codes);
        $this->assertSame([200, 409], $codes);
        $this->assertSame(1, count(array_filter($result['loans'], fn ($loan) => $loan['status'] === 'approved')));
        $this->assertSame(0, $result['stock']);
    }

    public function testTwoReturnsRestoreExactlyOnce(): void
    {
        $loan = $this->seedLoans('approved', 0)[0];
        $result = $this->race([$loan, $loan], ['returned', 'returned']);
        $this->assertSame([200, 200], array_column($result['responses'], 'code'));
        $this->assertSame(1, count(array_filter($result['responses'], fn ($response) => $response['changed'])));
        $this->assertSame('returned', $result['loans'][0]['status']);
        $this->assertSame(1, $result['stock']);
    }

    public function testApproveVersusRejectMakesOneDecision(): void
    {
        $loan = $this->seedLoans('pending', 1)[0];
        $result = $this->race([$loan, $loan], ['approved', 'rejected']);
        $codes = array_column($result['responses'], 'code');
        sort($codes);
        $this->assertSame([200, 409], $codes);
        $state = $result['loans'][0]['status'];
        $this->assertContains($state, ['approved', 'rejected']);
        $this->assertSame($state === 'approved' ? 0 : 1, $result['stock']);
    }

    public function testSecondWriteFailureRollsBackBoth(): void
    {
        $loan = $this->seedLoans('pending', 1)[0];
        $this->mysqlDb->query("CREATE TRIGGER batch4_fail_loan BEFORE UPDATE ON inventory_loans FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'forced second write failure'");
        $result = $this->finishWorker($this->startWorker('controller-transition', [(string) $loan, 'approved']));
        file_put_contents(sys_get_temp_dir() . DIRECTORY_SEPARATOR . $this->fixture->schema . '_rollback.json', json_encode($result, JSON_PRETTY_PRINT));
        $this->assertSame(500, $result['code']);
        $this->assertSame('pending', $result['loan']['status']);
        $this->assertSame(1, $result['stock']);
        $this->assertSame([], $result['notifications']);
        $this->assertContains(1644, $result['database_error_codes']);
    }

    private function schemaEvidence($db = null): array
    {
        $db ??= $this->mysqlDb;
        $evidence = [];
        foreach (['users', 'organization_members', 'event_participants', 'settings'] as $table) {
            $ddl = array_values($db->query('SHOW CREATE TABLE `' . $table . '`')->getRowArray())[1];
            $evidence[$table] = [
                'ddl' => preg_replace('/ AUTO_INCREMENT=\d+/', '', $ddl),
                'indexes' => $db->query('SHOW INDEX FROM `' . $table . '`')->getResultArray(),
            ];
        }
        file_put_contents(sys_get_temp_dir() . DIRECTORY_SEPARATOR . $this->fixture->schema . '_ddl.json', json_encode($evidence, JSON_PRETTY_PRINT));
        return $evidence;
    }

    private function comparableDdl(string $ddl): string
    {
        $ddl = preg_replace('/ AUTO_INCREMENT=\d+/', '', $ddl);
        return preg_replace('/(CONSTRAINT|KEY) `[^`]+`/', '$1', $ddl);
    }

    private function applicationSchemaProof(): void
    {
        foreach ([0, 101, 102] as $tenant) {
            $this->assertTrue($this->mysqlDb->table('settings')->insert([
                'setting_key' => 'batch41_shared_key', 'setting_value' => (string) $tenant, 'karang_taruna_id' => $tenant,
            ]));
            $this->assertGreaterThan(0, $this->mysqlDb->insertID());
        }
        $this->assertSame(3, $this->mysqlDb->table('settings')->where('setting_key', 'batch41_shared_key')->countAllResults());
        $application = $this->finishWorker($this->startWorker('application'));
        $engines = $this->mysqlDb->query('SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME IN (?, ?)',
            [$this->fixture->schema, 'inventories', 'inventory_loans'])->getResultArray();
        foreach ($engines as $engine) {
            $this->assertSame('InnoDB', $engine['ENGINE']);
        }
        file_put_contents(sys_get_temp_dir() . DIRECTORY_SEPARATOR . $this->fixture->schema . '_fresh.json', json_encode([
            'mysql' => $this->fixture->version, 'settings' => $this->mysqlDb->table('settings')->where('setting_key', 'batch41_shared_key')->get()->getResultArray(),
            'engines' => $engines, 'application' => $application,
        ], JSON_PRETTY_PRINT));
    }

    public function testEntireFreshMigrationChainAndCurrentWriteSchema(): void
    {
        $this->migrate();
        $evidence = $this->schemaEvidence();
        $this->assertMatchesRegularExpression('/`karang_taruna_id` int(?:\(\d+\))? unsigned DEFAULT NULL/', $evidence['users']['ddl']);
        $this->assertStringContainsString('PRIMARY KEY (`id`)', $evidence['settings']['ddl']);
        $this->assertStringContainsString('UNIQUE KEY `unique_setting_tenant` (`setting_key`,`karang_taruna_id`)', $evidence['settings']['ddl']);
        $this->mysqlDb->table('users')->insert(['nama_lengkap' => 'Global', 'username' => 'global', 'password' => 'unused']);
        $this->assertNull($this->mysqlDb->table('users')->where('username', 'global')->get()->getRow()->karang_taruna_id);
        $this->assertMatchesRegularExpression('/`karang_taruna_id` int(?:\(\d+\))? unsigned DEFAULT NULL/', $evidence['event_participants']['ddl']);
        // The historical ADD COLUMN is nullable on real MySQL. The application
        // must nevertheless populate the selected tenant on every participant.
        $this->applicationSchemaProof();
    }

    public function testUpgradeOnlyNewMigrationPreservesRowsAndMatchesFreshDdl(): void
    {
        $this->migrate('baseline');
        $legacyTenant = $this->mysqlDb->query("SHOW COLUMNS FROM users LIKE 'karang_taruna_id'")->getRowArray();
        $this->assertSame('NO', $legacyTenant['Null']);
        $this->mysqlDb->table('karang_taruna')->insert(['id' => 101, 'nama_organisasi' => 'Kept', 'kode_pin' => 'B4KEEP']);
        $this->mysqlDb->table('users')->insert(['karang_taruna_id' => 101, 'nama_lengkap' => 'Kept', 'username' => 'kept', 'password' => 'unused']);
        $user = $this->mysqlDb->insertID();
        $this->mysqlDb->table('organization_members')->insert(['user_id' => $user, 'karang_taruna_id' => 101, 'username' => 'kept']);
        $this->mysqlDb->table('events')->insert(['karang_taruna_id' => 101, 'nama_acara' => 'Kept', 'tanggal_acara' => date('Y-m-d'), 'dibuat_oleh' => $user, 'kode_qr' => 'KEPT']);
        $event = $this->mysqlDb->insertID();
        $this->mysqlDb->table('event_participants')->insert(['event_id' => $event, 'user_id' => $user, 'karang_taruna_id' => 101]);
        $this->mysqlDb->table('settings')->insert(['karang_taruna_id' => 101, 'setting_key' => 'kept', 'setting_value' => 'kept']);
        $before = [];
        foreach (['users', 'organization_members', 'event_participants', 'settings'] as $table) {
            $before[$table] = $this->mysqlDb->table($table)->get()->getResultArray();
        }
        $beforeDdl = $this->schemaEvidence();
        $this->migrate('upgrade');
        $tenant = $this->mysqlDb->query("SHOW COLUMNS FROM users LIKE 'karang_taruna_id'")->getRowArray();
        $this->assertSame('YES', $tenant['Null']);
        $this->assertNull($tenant['Default']);
        foreach ($before as $table => $rows) {
            $this->assertSame($rows, $this->mysqlDb->table($table)->get()->getResultArray());
        }
        $upgraded = $this->schemaEvidence();
        foreach (['organization_members', 'event_participants', 'settings'] as $table) {
            $this->assertSame($beforeDdl[$table]['ddl'], $upgraded[$table]['ddl']);
        }
        $fresh = new DisposableMySQL();
        try {
            $original = $this->fixture;
            $this->fixture = $fresh;
            $this->migrate();
            $freshDb = $fresh->connect();
            $freshEvidence = $this->schemaEvidence($freshDb);
            foreach (array_keys($upgraded) as $table) {
                $this->assertSame($this->comparableDdl($upgraded[$table]['ddl']), $this->comparableDdl($freshEvidence[$table]['ddl']));
            }
            file_put_contents(sys_get_temp_dir() . DIRECTORY_SEPARATOR . $original->schema . '_upgrade.json', json_encode([
                'mysql' => $original->version, 'before' => $before, 'before_ddl' => $beforeDdl,
                'after' => array_map(fn ($table) => $this->mysqlDb->table($table)->get()->getResultArray(), array_keys($before)),
                'legacy_tenant' => $legacyTenant, 'upgraded_tenant' => $tenant, 'schema_equivalent' => true,
                'rows_preserved' => true, 'upgraded' => $upgraded, 'fresh' => $freshEvidence,
            ], JSON_PRETTY_PRINT));
            $freshDb->close();
        } finally {
            $this->fixture = $original;
            $fresh->close();
        }
    }
}
