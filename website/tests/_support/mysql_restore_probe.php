<?php

// Synthetic backup drill only: creates two NEW schemas, never accepts a schema/host.
if (getenv('CI_ENVIRONMENT') !== 'testing' || getenv('KARTAR_MYSQL_TEST_ENABLE') !== '1') exit(2);
putenv('FCM_MOCK=true');
chdir(dirname(__DIR__, 2));
ob_start(); require 'tests/_support/testing_bootstrap.php'; ob_end_clean();
$source = $target = $sourceDb = $targetDb = null;
$clientFile = null;
$exitCode = 0;
$assert = static function (bool $condition, string $label): void {
    if (!$condition) throw new RuntimeException($label);
};
$execute = static function (array $command, ?string $input = null): array {
    $process = proc_open($command, [0 => $input ? ['file', $input, 'r'] : ['pipe', 'r'],
        1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, ROOTPATH, null, ['bypass_shell' => true]);
    if (!is_resource($process)) throw new RuntimeException('Backup child unavailable');
    if (!$input) fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    return [proc_close($process), $output, $error];
};
$snapshot = static function ($db): array {
    $tables = $db->query('SHOW TABLES')->getResultArray();
    $result = [];
    foreach ($tables as $row) {
        $table = array_values($row)[0];
        if (!preg_match('/\A[a-zA-Z0-9_]+\z/', $table)) throw new RuntimeException('Unexpected fixture table');
        $rows = $db->table($table)->get()->getResultArray();
        $rows = array_map(static fn($row) => json_encode($row, JSON_THROW_ON_ERROR), $rows);
        sort($rows, SORT_STRING);
        $ddl = array_values($db->query('SHOW CREATE TABLE `' . $table . '`')->getRowArray())[1];
        // mysqldump emits an explicit column charset already implied by collation.
        // Independent information_schema column metadata below must still match.
        $ddl = preg_replace('/ CHARACTER SET utf8mb4(?= COLLATE utf8mb4_)/', '', $ddl);
        $indexes = $db->query('SHOW INDEX FROM `' . $table . '`')->getResultArray();
        foreach ($indexes as &$index) unset($index['Cardinality']); // Optimizer estimate, not index definition.
        unset($index);
        $columns = $db->query('SELECT COLUMN_NAME, ORDINAL_POSITION, COLUMN_DEFAULT, IS_NULLABLE, COLUMN_TYPE, COLUMN_KEY, EXTRA, CHARACTER_SET_NAME, COLLATION_NAME, GENERATION_EXPRESSION FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION', [$table])->getResultArray();
        $result[$table] = ['rows' => $rows, 'ddl' => $ddl, 'columns' => $columns, 'indexes' => $indexes];
    }
    ksort($result);
    $triggers = $db->query('SELECT TRIGGER_NAME, EVENT_MANIPULATION, EVENT_OBJECT_TABLE, ACTION_STATEMENT, ACTION_TIMING, SQL_MODE, CHARACTER_SET_CLIENT, COLLATION_CONNECTION, DATABASE_COLLATION FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() ORDER BY TRIGGER_NAME')->getResultArray();
    return ['tables' => $result, 'triggers' => $triggers];
};
try {
    $binaryRoot = realpath(getenv('KARTAR_MYSQL_TEST_BIN') ?: '');
    $assert($binaryRoot !== false && is_file($binaryRoot . '/mysqldump.exe') && is_file($binaryRoot . '/mysql.exe'), 'Explicit MySQL client directory required');
    foreach (['mysqldump.exe', 'mysql.exe'] as $name) {
        [$code, $version] = $execute([$binaryRoot . '/' . $name, '--no-defaults', '--version']);
        $assert($code === 0 && preg_match('/\b(?:Ver |Distrib )?8\.0\./', $version) === 1 && stripos($version, 'MariaDB') === false, 'MySQL8 clients required');
    }
    $source = new \Tests\Support\DisposableMySQL();
    $sourceDb = $source->connect();
    $runtime = $sourceDb->query('SELECT VERSION() AS version, @@bind_address AS bind_address, @@port AS port')->getRowArray();
    $assert($runtime['bind_address'] === '127.0.0.1', 'Non-loopback runtime');
    [$code, $output, $stderr] = $execute([PHP_BINARY, TESTPATH . '_support/mysql_inventory_worker.php', 'fresh', $source->schema]);
    $assert($code === 0 && $stderr === '' && (json_decode($output, true)['ok'] ?? false), 'Fresh fixture migration failed');
    $insert = static function (string $table, array $rows) use ($sourceDb, $assert): void {
        $assert($sourceDb->table($table)->insertBatch($rows) === count($rows), 'Synthetic seed write failed');
    };
    $insert('karang_taruna', [['id' => 101, 'nama_organisasi' => 'Synthetic restore', 'kode_pin' => '999901', 'status_aktif' => 1]]);
    foreach ([1, 2] as $id) {
        $insert('users', [['id' => $id, 'username' => 'synthetic-' . $id, 'nama_lengkap' => 'Synthetic', 'password' => '', 'status_aktif' => 1]]);
        $insert('organization_members', [['user_id' => $id, 'karang_taruna_id' => 101, 'username' => 'synthetic-' . $id,
            'role_level' => $id === 1 ? 'ketua' : 'anggota', 'status_aktif' => 1, 'approval_status' => 'approved']]);
    }
    $relativeUpload = 'uploads/users/profile/' . str_repeat('a', 32) . '.png';
    $assert($sourceDb->table('users')->where('id', 1)->update(['profile_photo' => $relativeUpload]), 'Synthetic upload reference failed');
    $insert('chats', [['karang_taruna_id' => 101, 'sender_id' => 1, 'receiver_id' => 2, 'type' => 'private',
        'message' => 'Synthetic restore', 'client_message_id' => '12345678-1234-4234-8234-123456789abc', 'created_at' => gmdate('Y-m-d H:i:s')]]);
    $insert('inventories', [['id' => 1, 'karang_taruna_id' => 101, 'name' => 'Synthetic', 'total_quantity' => 1, 'available_quantity' => 1]]);
    $insert('inventory_loans', [['inventory_id' => 1, 'user_id' => 2, 'quantity' => 1, 'status' => 'pending', 'created_at' => gmdate('Y-m-d H:i:s')]]);
    $before = $snapshot($sourceDb);
    $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'kartar-restore-' . bin2hex(random_bytes(8));
    $assert(mkdir($directory, 0700), 'Refusing an existing backup directory');
    foreach (['backup/uploads/users/profile', 'second-local-copy/uploads/users/profile', 'restored/uploads/users/profile'] as $path) {
        $assert(mkdir($directory . '/' . $path, 0700, true), 'Owned backup directory creation failed');
    }
    $configuration = \Tests\Support\DisposableMySQL::configuration();
    $quote = static fn(string $value): string => '"' . str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '\\n', '\\r'], $value) . '"';
    $clientFile = $directory . '/private-client.ini';
    $assert(file_put_contents($clientFile, "[client]\nhost=127.0.0.1\nprotocol=tcp\nport=" . $configuration['port'] . "\nuser=" . $quote($configuration['username']) . "\npassword=" . $quote($configuration['password']) . "\n") !== false, 'Private client setup failed');
    chmod($clientFile, 0600); // Not a claim of Windows ACL hardening.
    unset($configuration);
    $started = microtime(true);
    [$code, , $stderr] = $execute([$binaryRoot . '/mysqldump.exe', '--defaults-file=' . $clientFile,
        '--single-transaction', '--no-tablespaces', '--set-gtid-purged=OFF', '--column-statistics=0', '--routines', '--events', '--triggers',
        '--skip-comments', '--result-file=' . $directory . '/backup/database.sql', $source->schema]);
    $assert($code === 0 && $stderr === '', 'Synthetic mysqldump failed');
    $dump = file_get_contents($directory . '/backup/database.sql');
    $assert(!preg_match('/\b(?:CREATE\s+DATABASE|USE\s+`?kartar_batch4_test_)/i', $dump), 'Dump must not select/create a schema');
    $backupSeconds = microtime(true) - $started;
    // A valid generated synthetic PNG; no production upload or configuration is read.
    file_put_contents($directory . '/backup/' . $relativeUpload, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jC1sAAAAASUVORK5CYII='));
    file_put_contents($directory . '/backup/config-template.json', json_encode(['synthetic' => true, 'environment' => 'testing', 'provider_delivery' => false], JSON_THROW_ON_ERROR));
    $manifest = [];
    foreach (['database.sql', $relativeUpload, 'config-template.json'] as $path) {
        $manifest[$path] = hash_file('sha256', $directory . '/backup/' . $path);
        $assert(copy($directory . '/backup/' . $path, $directory . '/second-local-copy/' . $path), 'Local second-copy failed');
        $assert(hash_file('sha256', $directory . '/second-local-copy/' . $path) === $manifest[$path], 'Second-copy checksum mismatch');
    }
    file_put_contents($directory . '/sha256-manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    copy($directory . '/second-local-copy/database.sql', $directory . '/tampered.sql');
    file_put_contents($directory . '/tampered.sql', '\nsynthetic corruption', FILE_APPEND);
    $assert(hash_file('sha256', $directory . '/tampered.sql') !== $manifest['database.sql'], 'Corruption detection failed');
    $target = new \Tests\Support\DisposableMySQL();
    $targetDb = $target->connect();
    $assert($targetDb->query('SHOW TABLES')->getResultArray() === [], 'Restore target must be newly empty');
    $started = microtime(true);
    [$code, , $stderr] = $execute([$binaryRoot . '/mysql.exe', '--defaults-file=' . $clientFile, '--binary-mode', $target->schema], $directory . '/second-local-copy/database.sql');
    $assert($code === 0 && $stderr === '', 'Synthetic restore failed');
    $restored = $snapshot($targetDb);
    if ($before !== $restored) {
        file_put_contents($directory . '/comparison-before.json', json_encode($before, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        file_put_contents($directory . '/comparison-restored.json', json_encode($restored, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        $differences = [];
        foreach ($before['tables'] as $table => $definition) foreach ($definition as $field => $value) {
            if ($value !== ($restored['tables'][$table][$field] ?? null)) $differences[] = $table . ':' . $field;
        }
        if ($before['triggers'] !== $restored['triggers']) $differences[] = 'triggers';
        throw new RuntimeException('Restore mismatch: ' . implode(',', $differences));
    }
    foreach ([$relativeUpload, 'config-template.json'] as $path) {
        $assert(copy($directory . '/second-local-copy/' . $path, $directory . '/restored/' . $path), 'Synthetic file restore failed');
        $assert(hash_file('sha256', $directory . '/restored/' . $path) === $manifest[$path], 'Restored file checksum mismatch');
    }
    $restoreSeconds = microtime(true) - $started;
    $jobs = $targetDb->table('notification_jobs')->countAllResults();
    $assert($targetDb->table('chats')->insert(['karang_taruna_id' => 101, 'sender_id' => 1, 'receiver_id' => 2,
        'type' => 'private', 'message' => 'Synthetic post-restore', 'created_at' => gmdate('Y-m-d H:i:s')]), 'Restored chat write failed');
    $assert($targetDb->table('notification_jobs')->countAllResults() === $jobs + 1, 'Restored outbox trigger failed');
    $fkRejected = false;
    try { $targetDb->table('organization_members')->insert(['user_id' => 999999, 'karang_taruna_id' => 101, 'username' => 'synthetic-invalid']); }
    catch (\CodeIgniter\Database\Exceptions\DatabaseException $error) { $fkRejected = $targetDb->error()['code'] === 1452; }
    $assert($fkRejected, 'Restored FK enforcement failed');
    $summary = ['ok' => true, 'classification' => 'SYNTHETIC LOCAL ONLY; PRODUCTION/OFF-HOST RECOVERY NOT PROVEN',
        'runtime' => $runtime, 'tables' => count($before['tables']), 'triggers' => count($before['triggers']),
        'rows_ddl_indexes_triggers_equal' => true, 'checksums_verified' => count($manifest), 'corruption_detected' => true,
        'outbox_trigger_live' => true, 'foreign_key_rejection' => 1452, 'backup_seconds' => round($backupSeconds, 3),
        'restore_and_validation_seconds' => round($restoreSeconds, 3), 'artifacts' => $directory];
} catch (Throwable $error) {
    $summary = ['ok' => false, 'error_label' => $error instanceof RuntimeException ? $error->getMessage() : 'Synthetic backup probe failed'];
    $exitCode = 1;
} finally {
    if ($clientFile && is_file($clientFile)) unlink($clientFile); // Exact newly owned credential file only.
    $targetDb?->close(); $sourceDb?->close(); $target?->close(); $source?->close();
}
echo json_encode($summary, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), "\n";
exit($exitCode);
