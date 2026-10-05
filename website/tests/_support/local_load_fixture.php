<?php

// Parent owns CREATE/DROP. The Node child receives only this explicit namespace.
if (getenv('CI_ENVIRONMENT') !== 'testing' || getenv('KARTAR_MYSQL_TEST_ENABLE') !== '1'
    || getenv('KARTAR_LOCAL_LOAD_ENABLE') !== '1') exit(2);
chdir(dirname(__DIR__, 2));
ob_start(); require 'tests/_support/testing_bootstrap.php'; ob_end_clean();
$fixture = null; $db = null; $exit = 1;
try {
    $fixture = new \Tests\Support\DisposableMySQL();
    $root = sys_get_temp_dir() . '/kartar-local-load-' . bin2hex(random_bytes(8));
    if (!mkdir($root, 0700)) throw new RuntimeException('New local load directory required');
    foreach (['public', 'writable', 'writable/cache', 'writable/logs', 'writable/session', 'writable/debugbar'] as $dir) {
        if (!mkdir($root . '/' . $dir, 0700)) throw new RuntimeException('Local load directory unavailable');
    }
    file_put_contents($root . '/owned.json', json_encode(['schema' => $fixture->schema], JSON_THROW_ON_ERROR));
    $process = proc_open([PHP_BINARY, TESTPATH . '_support/mysql_inventory_worker.php', 'fresh', $fixture->schema],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, ROOTPATH, null, ['bypass_shell' => true]);
    if (!is_resource($process)) throw new RuntimeException('Migration process unavailable');
    fclose($pipes[0]); $fresh = stream_get_contents($pipes[1]); $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    if (proc_close($process) !== 0 || $stderr !== '' || !(json_decode($fresh, true)['ok'] ?? false)) throw new RuntimeException('Fresh migrations failed');
    $db = $fixture->connect();
    foreach ([101, 102] as $tenant) $db->table('karang_taruna')->insert(['id' => $tenant, 'nama_organisasi' => 'Synthetic local load', 'kode_pin' => (string)(900000 + $tenant), 'status_aktif' => 1]);
    $tokens = [];
    for ($id = 1; $id <= 1000; $id++) {
        $token = bin2hex(random_bytes(32)); $tokens[] = $token;
        $db->table('users')->insert(['id' => $id, 'username' => 'synthetic-load-' . $id, 'nama_lengkap' => 'Synthetic', 'password' => '', 'status_aktif' => 1, 'password_must_change' => 0]);
        $db->table('organization_members')->insert(['user_id' => $id, 'karang_taruna_id' => 101, 'username' => 'synthetic-load-' . $id,
            'role_level' => 'ketua', 'status_aktif' => 1, 'approval_status' => 'approved']);
        $db->table('user_tokens')->insert(['user_id' => $id, 'karang_taruna_id' => 101, 'token_hash' => hash('sha256', $token), 'expires_at' => gmdate('Y-m-d H:i:s', time() + 1800)]);
    }
    $db->table('inventories')->insert(['id' => 1, 'karang_taruna_id' => 101, 'name' => 'Synthetic stock', 'total_quantity' => 1, 'available_quantity' => 1]);
    foreach ([1, 2] as $id) $db->table('inventory_loans')->insert(['id' => $id, 'inventory_id' => 1, 'user_id' => $id, 'quantity' => 1, 'status' => 'pending']);
    // Private synthetic bearer material exists only in TEMP for this child.
    file_put_contents($root . '/synthetic-tokens.json', json_encode($tokens, JSON_THROW_ON_ERROR));
    putenv('KARTAR_MYSQL_APPLICATION_SCHEMA=' . $fixture->schema);
    putenv('KARTAR_LOCAL_LOAD_ROOT=' . $root);
    putenv('KARTAR_LOCAL_PHP_BINARY=' . PHP_BINARY);
    $node = getenv('KARTAR_LOCAL_NODE_BINARY');
    if (!$node || !is_file($node)) throw new RuntimeException('Explicit Node binary required');
    $process = proc_open([$node, ROOTPATH . '../chat-server/tests/local-load-runner.js'],
        [0 => ['pipe', 'r'], 1 => ['file', $root . '/runner.log', 'w'], 2 => ['file', $root . '/runner.log', 'a']], $pipes, $root, null, ['bypass_shell' => true]);
    if (!is_resource($process)) throw new RuntimeException('Node runner unavailable');
    fclose($pipes[0]); $runnerExit = proc_close($process);
    $result = json_decode(file_get_contents($root . '/result.json'), true, 512, JSON_THROW_ON_ERROR);
    $chats = $db->table('chats')->countAllResults(); $jobs = $db->table('notification_jobs')->where('kind', 'chat')->countAllResults();
    $duplicates = $db->query('SELECT COUNT(*) AS n FROM (SELECT sender_id, client_message_id FROM chats GROUP BY karang_taruna_id,sender_id,client_message_id HAVING COUNT(*) > 1) d')->getRowArray();
    $inventory = $db->table('inventories')->where('id', 1)->get()->getRowArray();
    $approved = $db->table('inventory_loans')->where('status', 'approved')->countAllResults();
    $result['invariants'] = ['chat_rows' => $chats, 'chat_jobs' => $jobs, 'acknowledged_unique_messages' => $result['acknowledged_unique_messages'] ?? 0,
        'duplicate_persistence' => (int)$duplicates['n'], 'stock' => (int)$inventory['available_quantity'], 'approved_loans' => $approved,
        'ok' => $chats === $jobs && $chats === ($result['acknowledged_unique_messages'] ?? 0) && (int)$duplicates['n'] === 0 && (int)$inventory['available_quantity'] + $approved === 1];
    $result['evidence_directory'] = $root;
    $exit = $runnerExit === 0 && $result['invariants']['ok'] ? 0 : 1;
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    echo json_encode(['ok' => false, 'error_class' => get_class($error), 'label' => 'local_load_fixture_failed']);
} finally {
    if (isset($root) && is_file($root . '/synthetic-tokens.json')) unlink($root . '/synthetic-tokens.json');
    $db?->close(); $fixture?->close();
}
exit($exit);
