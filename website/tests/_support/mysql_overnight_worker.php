<?php

// Explicit disposable test namespace and independently checked MySQL8 only.
if (getenv('CI_ENVIRONMENT') !== 'testing' || getenv('KARTAR_MYSQL_TEST_ENABLE') !== '1') exit(2);
putenv('FCM_MOCK=true');
chdir(dirname(__DIR__, 2));
ob_start(); require 'vendor/codeigniter4/framework/system/Test/bootstrap.php'; ob_end_clean();
// Standalone bootstrap lacks the application's normal timezone initialization.
date_default_timezone_set(config('App')->appTimezone);
try {
    $mode = $argv[1]; $schema = $argv[2];
    $config = config(\Config\Database::class);
    $config->tests = \Tests\Support\DisposableMySQL::configuration($schema); $config->defaultGroup = 'tests';
    $db = \Config\Database::connect('tests');
    $version = (string)$db->query('SELECT VERSION() AS v')->getRow()->v;
    if (!preg_match('/\A8\./', $version) || stripos($version, 'MariaDB') !== false) throw new RuntimeException('MySQL8 required');
    $db->query('SET SESSION innodb_lock_wait_timeout = 5');
    $databaseErrors = [];
    \CodeIgniter\Events\Events::on('DBQuery', static function () use ($db, &$databaseErrors): void {
        $code = (int)($db->error()['code'] ?? 0); if ($code) $databaseErrors[] = $code;
    });
    if (($barrier = $argv[3] ?? '') !== '') {
        if (realpath(dirname($barrier)) !== realpath(sys_get_temp_dir()) || !preg_match('/\Akartar_overnight_race_[a-f0-9]{16}_[01]\z/', basename($barrier))) throw new RuntimeException('Invalid barrier');
        file_put_contents($barrier . '.ready', 'ready'); $deadline = microtime(true) + 15;
        while (!is_file($barrier . '.release')) { if (microtime(true) > $deadline) throw new RuntimeException('Barrier timeout'); usleep(10000); }
    }
    \App\Services\AuthService::setUser(['id' => 1, 'karang_taruna_id' => 101, 'role_level' => 'ketua']);
    if ($mode === 'chat') {
        $result = \App\Services\ChatPersistenceService::persist(['karang_taruna_id' => 101, 'sender_id' => 1, 'receiver_id' => 2,
            'type' => 'private', 'message' => 'Synthetic race', 'client_message_id' => '12345678-1234-4234-8234-123456789abc', 'created_at' => gmdate('Y-m-d H:i:s')]);
        $result = ['code' => 200, 'created' => $result['created'], 'id' => (int)$result['row']['id']];
    } elseif ($mode === 'approval' || $mode === 'rejection') {
        $changed = \App\Services\MembershipApprovalService::decide(101, 2, $mode === 'approval' ? 'approved' : 'rejected', 1, 'user');
        $result = ['code' => $changed ? 200 : 409, 'changed' => $changed];
    } elseif ($mode === 'identity') {
        try {
            $id = \App\Services\IdentityCreationService::create(101, ['nama_lengkap' => 'Synthetic', 'username' => 'synthetic-race', 'password' => '', 'status_aktif' => 1],
                ['username' => 'synthetic-race', 'role_level' => 'anggota', 'status_aktif' => 1, 'approval_status' => 'approved']);
            $result = ['code' => 201, 'id' => $id];
        } catch (DomainException $error) { $result = ['code' => (int)$error->getCode()]; }
    } elseif ($mode === 'participants') {
        $result = ['code' => 200, 'added' => \App\Services\ParticipantBatchService::add(101, 1, [2])];
    } elseif ($mode === 'queue') {
        $result = (new \App\Services\NotificationWorker())->runOne();
    } elseif ($mode === 'spin' || $mode === 'close') {
        $client = new class extends \CodeIgniter\HTTP\CURLRequest {
            public function __construct() {}
            public function request($method, string $url, array $options = []): \CodeIgniter\HTTP\ResponseInterface {
                return (new \CodeIgniter\HTTP\Response(config('App')))->setJSON(['success' => true]);
            }
        };
        \Config\Services::injectMock('curlrequest', $client);
        $controller = new \App\Controllers\Api\WheelController();
        $controller->initController(\Config\Services::request()->setMethod('post'), \Config\Services::response(), \Config\Services::logger());
        $response = $controller->{$mode}(1);
        $result = ['code' => $response->getStatusCode()];
    } else throw new RuntimeException('Unknown worker mode');
    echo json_encode(['ok' => true, 'result' => $result, 'mysql_errors' => $databaseErrors], JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    // No SQL/credentials/payload/exception details leave this synthetic worker.
    echo json_encode(['ok' => false, 'label' => 'worker_failed', 'error_class' => get_class($error), 'mysql_errors' => $databaseErrors ?? []]); exit(1);
}
