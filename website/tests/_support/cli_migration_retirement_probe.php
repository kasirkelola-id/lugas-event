<?php

// Isolated testing bootstrap selects SQLite :memory:, never application MySQL.
putenv('CI_ENVIRONMENT=testing');
putenv('FCM_MOCK=true');
require __DIR__ . '/testing_bootstrap.php';
$config = config('Database');
if ($config->defaultGroup !== 'tests' || $config->tests['DBDriver'] !== 'SQLite3'
    || $config->tests['database'] !== ':memory:') {
    throw new RuntimeException('Disposable CLI migration database required');
}
command('migrate -n App -g tests');
$db = \Config\Database::connect('tests');
echo json_encode([
    'users' => $db->tableExists('users'),
    'notification_jobs' => $db->tableExists('notification_jobs'),
    'history_rows' => $db->tableExists('migrations') ? $db->table('migrations')->countAllResults() : 0,
]);
