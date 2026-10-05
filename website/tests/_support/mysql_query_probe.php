<?php

// Explicit local-only synthetic scale probe; never adopts an existing database.
if (getenv('CI_ENVIRONMENT') !== 'testing' || getenv('KARTAR_MYSQL_TEST_ENABLE') !== '1') exit(2);
$tenants = filter_var($argv[1] ?? '', FILTER_VALIDATE_INT);
if (!in_array($tenants, [10, 100], true)) exit(2);
putenv('FCM_MOCK=true');
chdir(dirname(__DIR__, 2));
ob_start(); require 'tests/_support/testing_bootstrap.php'; ob_end_clean();
date_default_timezone_set(config('App')->appTimezone);
$fixture = null;
$db = null;
$exitCode = 0;
try {
    $fixture = new \Tests\Support\DisposableMySQL();
    $process = proc_open([PHP_BINARY, TESTPATH . '_support/mysql_inventory_worker.php', 'fresh', $fixture->schema],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, ROOTPATH, null, ['bypass_shell' => true]);
    if (!is_resource($process)) throw new RuntimeException('Synthetic migration child unavailable');
    fclose($pipes[0]); $fresh = stream_get_contents($pipes[1]); $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    if (proc_close($process) !== 0 || $stderr !== '' || !(json_decode($fresh, true)['ok'] ?? false)) throw new RuntimeException('Synthetic migration failed');
    $config = config(\Config\Database::class);
    $config->tests = \Tests\Support\DisposableMySQL::configuration($fixture->schema); $config->defaultGroup = 'tests';
    $db = \Config\Database::connect('tests');
    $runtime = $db->query('SELECT VERSION() AS version, @@bind_address AS bind_address, @@port AS port')->getRowArray();
    if ($runtime['bind_address'] !== '127.0.0.1') throw new RuntimeException('Non-loopback runtime');
    $db->query('SET SESSION innodb_lock_wait_timeout = 5');
    $started = microtime(true);
    $insert = static function (string $table, array $rows) use ($db): void {
        if ($db->table($table)->insertBatch($rows, null, 1000) !== count($rows)) throw new RuntimeException('Synthetic batch write incomplete');
    };
    for ($tenant = 1; $tenant <= $tenants; $tenant++) {
        $firstUser = ($tenant - 1) * 100 + 1;
        $insert('karang_taruna', [['id' => $tenant, 'nama_organisasi' => 'Synthetic ' . $tenant, 'kode_pin' => (string)(800000 + $tenant), 'status_aktif' => 1]]);
        $users = $members = $events = $attendance = $cash = $chats = [];
        for ($user = $firstUser; $user < $firstUser + 100; $user++) {
            $users[] = ['id' => $user, 'username' => 'synthetic-' . $user, 'nama_lengkap' => 'Synthetic', 'password' => '', 'status_aktif' => 1, 'password_must_change' => 0];
            $members[] = ['user_id' => $user, 'karang_taruna_id' => $tenant, 'username' => 'synthetic-' . $user,
                'role_level' => $user === $firstUser ? 'ketua' : 'anggota', 'status_aktif' => 1, 'approval_status' => 'approved'];
        }
        $insert('users', $users); $insert('organization_members', $members);
        for ($event = $firstUser; $event < $firstUser + 100; $event++) {
            $events[] = ['id' => $event, 'karang_taruna_id' => $tenant, 'nama_acara' => 'Synthetic', 'kode_qr' => 'synthetic-' . $event,
                'tanggal_acara' => gmdate('Y-m-d', time() - ($event % 30) * 86400), 'dibuat_oleh' => $firstUser, 'status_aktif' => 'selesai'];
        }
        $insert('events', $events);
        for ($row = 0; $row < 1000; $row++) {
            $stamp = gmdate('Y-m-d H:i:s', time() - ($row % 20) * 86400 - $row);
            $attendance[] = ['karang_taruna_id' => $tenant, 'event_id' => $firstUser + intdiv($row, 100), 'user_id' => $firstUser + ($row % 100), 'waktu_absen' => $stamp];
            $cash[] = ['karang_taruna_id' => $tenant, 'jenis' => $row % 2 ? 'pengeluaran' : 'pemasukan', 'nominal' => 100,
                'keterangan' => 'Synthetic', 'tanggal' => substr($stamp, 0, 10), 'created_at' => $stamp, 'dibuat_oleh' => $firstUser];
            $chats[] = ['karang_taruna_id' => $tenant, 'sender_id' => $firstUser, 'receiver_id' => $firstUser + 1 + ($row % 99),
                'type' => 'private', 'message' => 'Synthetic', 'created_at' => $stamp];
        }
        $insert('absensi', $attendance); $insert('kas', $cash); $insert('chats', $chats);
        $inventories = $loans = [];
        for ($offset = 0; $offset < 10; $offset++) $inventories[] = ['id' => ($tenant - 1) * 10 + $offset + 1,
            'karang_taruna_id' => $tenant, 'name' => 'Synthetic', 'total_quantity' => 50, 'available_quantity' => 50];
        $insert('inventories', $inventories);
        foreach ($inventories as $inventory) for ($offset = 0; $offset < 50; $offset++) $loans[] = ['inventory_id' => $inventory['id'],
            'user_id' => $firstUser + $offset, 'quantity' => 1, 'status' => 'pending', 'created_at' => gmdate('Y-m-d H:i:s', time() - $offset)];
        $insert('inventory_loans', $loans);
        $insert('wheel_sessions', [['id' => $tenant, 'karang_taruna_id' => $tenant, 'created_by_user_id' => $firstUser,
            'title' => 'Synthetic', 'source_type' => 'custom', 'status' => 'closed', 'spin_duration_seconds' => 10]]);
        $items = $results = [];
        for ($offset = 0; $offset < 20; $offset++) $items[] = ['id' => ($tenant - 1) * 20 + $offset + 1,
            'session_id' => $tenant, 'label_snapshot' => 'Synthetic', 'is_active' => 1];
        $insert('wheel_items', $items);
        for ($offset = 1; $offset <= 1000; $offset++) $results[] = ['session_id' => $tenant, 'wheel_item_id' => $items[$offset % 20]['id'],
            'result_label_snapshot' => 'Synthetic', 'spin_sequence' => $offset, 'started_at' => gmdate('Y-m-d H:i:s', time() - 1000 + $offset), 'duration_seconds' => 10];
        $insert('wheel_results', $results);
        $insert('votings', [['id' => $tenant, 'karang_taruna_id' => $tenant, 'title' => 'Synthetic', 'created_by' => $firstUser,
            'status' => 'closed', 'waktu_mulai' => '2000-01-01 00:00:00', 'waktu_selesai' => '2000-01-02 00:00:00']]);
        $options = $votes = [];
        for ($offset = 0; $offset < 10; $offset++) $options[] = ['id' => ($tenant - 1) * 10 + $offset + 1, 'voting_id' => $tenant, 'option_name' => 'Synthetic'];
        $insert('voting_options', $options);
        for ($offset = 0; $offset < 100; $offset++) $votes[] = ['voting_id' => $tenant, 'option_id' => $options[$offset % 10]['id'], 'user_id' => $firstUser + $offset];
        $insert('voting_votes', $votes);
        // Only fixture data is written; no notification worker/provider is invoked.
    }
    $counts = [];
    foreach (['karang_taruna', 'users', 'organization_members', 'events', 'absensi', 'kas', 'chats', 'notification_jobs',
        'inventories', 'inventory_loans', 'wheel_sessions', 'wheel_items', 'wheel_results', 'votings', 'voting_options', 'voting_votes'] as $table) {
        $counts[$table] = $db->table($table)->countAllResults();
        $db->query('ANALYZE TABLE `' . $table . '`');
    }
    if ($counts['users'] !== $tenants * 100 || $counts['absensi'] !== $tenants * 1000
        || $counts['kas'] !== $tenants * 1000 || $counts['chats'] !== $tenants * 1000 || $counts['notification_jobs'] !== $counts['chats']) throw new RuntimeException('Synthetic dataset invariant');
    $seedSeconds = microtime(true) - $started;
    \App\Services\AuthService::setUser(['id' => 1, 'karang_taruna_id' => 1, 'role_level' => 'ketua']);
    $cases = [
        'events' => [\App\Controllers\Api\EventController::class, 'index', [], []],
        'attendance' => [\App\Controllers\Api\AbsensiController::class, 'myHistory', [], []],
        'cash' => [\App\Controllers\Api\KasController::class, 'index', [], []],
        'cash_month' => [\App\Controllers\Api\KasController::class, 'index', [], ['month' => gmdate('Y-m')]],
        'private_chat' => [\App\Controllers\Api\ChatController::class, 'getPrivateChats', [2], []],
        'loans' => [\App\Controllers\Api\InventoryController::class, 'getLoans', [], []],
        'wheel' => [\App\Controllers\Api\WheelController::class, 'index', [], []],
        'wheel_results' => [\App\Controllers\Api\WheelController::class, 'show', [1], []],
        'voting' => [\App\Controllers\Api\VotingController::class, 'index', [], []],
        'voting_detail' => [\App\Controllers\Api\VotingController::class, 'show', [1], []],
    ];
    $measure = static function () use ($db, $cases): array {
        $out = [];
        foreach ($cases as $label => [$class, $method, $args, $parameters]) {
            $queries = [];
            $listener = static function ($query) use (&$queries): void {
                $sql = (string)$query->getQuery();
                if (preg_match('/\A\s*SELECT\b/i', $sql)) $queries[] = $sql;
            };
            \CodeIgniter\Events\Events::on('DBQuery', $listener);
            try {
                $request = new \CodeIgniter\HTTP\IncomingRequest(config('App'), new \CodeIgniter\HTTP\URI(), null, new \CodeIgniter\HTTP\UserAgent());
                $request->setMethod('get')->setGlobal('get', ['page' => '1', 'limit' => '50'] + $parameters)
                    ->setGlobal('request', ['page' => '1', 'limit' => '50'] + $parameters);
                $controller = new $class();
                $controller->initController($request, new \CodeIgniter\HTTP\Response(config('App')), \Config\Services::logger());
                $start = microtime(true); $response = $controller->{$method}(...$args); $ms = (microtime(true) - $start) * 1000;
                $body = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
                if ($response->getStatusCode() !== 200) throw new RuntimeException('Synthetic collection rejected');
                $rows = match ($label) {
                    'cash', 'cash_month' => $body['data']['transaksi'],
                    'wheel_results' => $body['data']['results'],
                    'voting_detail' => $body['data']['options'],
                    default => $body['data'],
                };
                if (count($rows) > 50) throw new RuntimeException('Collection bound exceeded');
                foreach ($rows as $row) {
                    if (isset($row['karang_taruna_id']) && (int)$row['karang_taruna_id'] !== 1) throw new RuntimeException('Tenant isolation invariant');
                    if (isset($row['event_id']) && (int)$row['event_id'] > 100) throw new RuntimeException('Attendance isolation invariant');
                    if ($label === 'private_chat' && ((int)$row['sender_id'] !== 1 || (int)$row['receiver_id'] !== 2)) throw new RuntimeException('Chat isolation invariant');
                    if ($label === 'loans' && (int)$row['inventory_id'] > 10) throw new RuntimeException('Loan isolation invariant');
                    if ($label === 'wheel_results' && (int)$row['session_id'] !== 1) throw new RuntimeException('Wheel isolation invariant');
                    if ($label === 'voting_detail' && (int)$row['voting_id'] !== 1) throw new RuntimeException('Voting isolation invariant');
                    if ($label === 'events' && (int)$row['jumlah_hadir'] !== ((int)$row['id'] <= 10 ? 100 : 0)) throw new RuntimeException('Attendance count invariant');
                    if ($label === 'wheel' && (int)$row['item_count'] !== 20) throw new RuntimeException('Wheel item count invariant');
                    if ($label === 'voting' && ((int)$row['total_votes'] !== 100 || $row['has_voted'] !== true)) throw new RuntimeException('Voting list count invariant');
                }
                if ($label === 'cash' && (int)$body['data']['saldo'] !== 0) throw new RuntimeException('Balance invariant');
                if ($label === 'wheel_results' && ((int)$rows[0]['spin_sequence'] !== 951 || (int)$rows[49]['spin_sequence'] !== 1000)) throw new RuntimeException('Wheel history bound invariant');
                if ($label === 'voting_detail' && ((int)$body['data']['total_votes'] !== 100 || (float)array_sum(array_column($rows, 'percentage')) !== 100.0)) throw new RuntimeException('Voting count invariant');
            } finally { \CodeIgniter\Events\Events::removeListener('DBQuery', $listener); }
            $plans = [];
            foreach ($queries as $sql) {
                $plans[] = ['sql' => $sql, 'explain' => $db->query('EXPLAIN ' . $sql)->getResultArray(),
                    'json' => json_decode($db->query('EXPLAIN FORMAT=JSON ' . $sql)->getRowArray()['EXPLAIN'], true, 512, JSON_THROW_ON_ERROR)];
            }
            $out[$label] = ['controller_ms' => round($ms, 3), 'rows' => count($rows), 'pagination' => $body['pagination'] ?? null, 'queries' => $plans];
        }
        $claims = [];
        $listener = static function ($query) use (&$claims): void {
            $sql = (string)$query->getQuery();
            if (preg_match('/\ASELECT (?:id|\*) FROM notification_jobs /i', $sql)) $claims[] = $sql;
        };
        \CodeIgniter\Events\Events::on('DBQuery', $listener);
        try {
            $dispatch = (new \App\Services\NotificationWorker())->runOne();
            if ($dispatch['claimed'] !== 1 || $dispatch['errors'] !== 0 || $dispatch['sent'] !== 0) throw new RuntimeException('Synthetic claim invariant');
        } finally { \CodeIgniter\Events\Events::removeListener('DBQuery', $listener); }
        foreach ($claims as $claim) $out['queue_claim']['queries'][] = ['sql' => $claim, 'explain' => $db->query('EXPLAIN ' . $claim)->getResultArray(),
            'json' => json_decode($db->query('EXPLAIN FORMAT=JSON ' . $claim)->getRowArray()['EXPLAIN'], true, 512, JSON_THROW_ON_ERROR)];
        return $out;
    };
    $result = ['classification' => 'LOCAL SYNTHETIC DATABASE/CONTROLLER PROBE; NOT AUTHENTICATED HTTP LOAD OR PRODUCTION CAPACITY',
        'runtime' => $runtime, 'counts' => $counts, 'seed_seconds' => round($seedSeconds, 3), 'plans' => $measure(), 'network_providers_invoked' => false];
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    echo json_encode(['ok' => false, 'label' => 'synthetic_query_probe_failed', 'error_class' => get_class($error), 'mysql_error' => (int)($db?->error()['code'] ?? 0)]);
    $exitCode = 1;
} finally {
    $db?->close(); $fixture?->close();
}
exit($exitCode);
