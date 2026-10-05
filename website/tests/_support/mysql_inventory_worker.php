<?php

// Test fixture only. No HTTP listener, application credentials, FCM or real users.
if (getenv('KARTAR_MYSQL_TEST_ENABLE') !== '1' || getenv('CI_ENVIRONMENT') !== 'testing') {
    fwrite(STDERR, "MySQL worker requires explicit testing opt-in\n");
    exit(1);
}
putenv('FCM_MOCK=true');
chdir(dirname(__DIR__, 2));
ob_start();
require 'vendor/codeigniter4/framework/system/Test/bootstrap.php';
ob_end_clean();

use App\Services\InventoryLoanTransitionService;
use App\Services\InventoryTransitionException;
use CodeIgniter\Events\Events;
use Config\Database;
use Tests\Support\DisposableMySQL;

final class MySQLInventoryNotificationProbe extends \App\Controllers\Api\InventoryController
{
    public array $notifications = [];

    protected function notifyLoanTransition(array $loan, array $inventory, int $tenantId, string $status): void
    {
        $this->notifications[] = $status;
    }
}

try {
    [$script, $mode, $schema] = $argv;
    $configuration = DisposableMySQL::configuration($schema);
    // Replace the tests group before any application/default DB is opened.
    // Some historical migrations explicitly call Database::connect().
    $config = config(Database::class);
    $config->tests = $configuration;
    $config->defaultGroup = 'tests';
    $db = Database::connect('tests');
    $version = (string) $db->query('SELECT VERSION() AS v')->getRow()->v;
    if (!preg_match('/\A8\./', $version) || stripos($version, 'MariaDB') !== false) {
        throw new RuntimeException('MySQL 8 required');
    }
    if ($mode === 'fresh' || $mode === 'baseline') {
        if ($db->listTables() !== []) {
            throw new RuntimeException('Historical migrations require a newly empty disposable schema');
        }
        $migrationConfig = new \Config\Migrations();
        $migrationConfig->enabled = true;
        $runner = new \CodeIgniter\Database\MigrationRunner($migrationConfig, $db);
        $runner->setNamespace('App');
        if ($mode === 'fresh') {
            if (!$runner->latest('tests')) {
                throw new RuntimeException('Fresh migrations failed');
            }
        } else {
            // Build the old state only on this brand-new empty schema.
            // Upgrade mode below never replays these historical migrations.
            $files = glob(APPPATH . 'Database/Migrations/*.php');
            sort($files);
            foreach ($files as $file) {
                if (str_contains($file, '2026-10-05-000001_')) {
                    continue;
                }
                if (!$runner->force($file, 'App', 'tests')) {
                    throw new RuntimeException('Baseline migration failed');
                }
            }
            // Model the stricter expected legacy deployment, even though current
            // Forge ADD COLUMN defaults to nullable when NULL is unspecified.
            $db->query('ALTER TABLE users MODIFY karang_taruna_id INT UNSIGNED NOT NULL');
        }
        $result = ['mode' => $mode, 'tables' => count($db->listTables())];
    } elseif ($mode === 'upgrade') {
        require_once APPPATH . 'Database/Migrations/2026-10-05-000001_AlignGlobalIdentityAndSettingsSchema.php';
        (new \App\Database\Migrations\AlignGlobalIdentityAndSettingsSchema(Database::forge($db)))->up();
        $result = ['mode' => $mode];
    } elseif ($mode === 'application') {
        putenv('KARTAR_MYSQL_APPLICATION_SCHEMA=' . $schema);
        $log = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $schema . '_application.log';
        $xml = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $schema . '_application.xml';
        $process = proc_open([
            PHP_BINARY, ROOTPATH . 'vendor/bin/phpunit', '--colors=never', '--testdox',
            '--bootstrap', TESTPATH . '_support/mysql_application_bootstrap.php',
            '--filter', 'testCreateAndRegisterOmitLegacyTenantWhileMembershipOwnsTenant|testParticipantInsertDerivesTenantAndGetRouteKeepsPhoneContract',
            '--log-junit', $xml, TESTPATH . 'Api/SchemaParityRegressionTest.php',
        ], [0 => ['pipe', 'r'], 1 => ['file', $log, 'w'], 2 => ['file', $log, 'a']], $pipes, ROOTPATH, null, ['bypass_shell' => true]);
        if (!is_resource($process)) {
            throw new RuntimeException('Application proof process failed to start');
        }
        fclose($pipes[0]);
        $exit = proc_close($process);
        $report = is_file($xml) ? simplexml_load_file($xml) : false;
        $suite = $report ? $report->testsuite : null;
        if ($exit !== 0 || !$suite || (int) $suite['tests'] !== 2
            || (int) $suite['skipped'] !== 0 || (int) $suite['errors'] !== 0 || (int) $suite['failures'] !== 0) {
            throw new RuntimeException('MySQL application proof failed; inspect disposable application log');
        }
        $result = ['mode' => $mode, 'mysql' => $version, 'tests' => 2, 'log' => $log];
    } elseif ($mode === 'transition' || $mode === 'controller-transition') {
        $loan = (int) $argv[3];
        $status = $argv[4];
        $barrier = $argv[5] ?? '';
        if ($barrier !== '') {
            $root = realpath(sys_get_temp_dir());
            if (realpath(dirname($barrier)) !== $root
                || !preg_match('/\Akartar_batch4_barrier_[a-f0-9]{16}_[01]\z/', basename($barrier))) {
                throw new RuntimeException('Unsafe test barrier path');
            }
            // Test synchronization only: both real processes read pending/approved
            // before either locks. No sleep/retry appears in application code.
            Events::on('DBQuery', static function ($query) use ($barrier): void {
                if (!str_starts_with($query->getQuery(), 'SELECT l.status FROM inventory_loans')) {
                    return;
                }
                file_put_contents($barrier . '.ready', 'ready');
                $deadline = microtime(true) + 10;
                while (!is_file($barrier . '.release')) {
                    if (microtime(true) >= $deadline) {
                        throw new RuntimeException('Test barrier timeout');
                    }
                    usleep(10000);
                }
            });
        }
        $databaseErrors = [];
        Events::on('DBQuery', static function ($query) use (&$databaseErrors, $db): void {
            // MySQLi exposes failed transaction-query codes on the connection;
            // CI's Query object does not always receive setError() in that path.
            $error = $db->error();
            if ((int) $error['code'] !== 0) {
                $databaseErrors[] = (int) $error['code'];
            }
        });
        try {
            if ($mode === 'controller-transition') {
                \App\Services\AuthService::setUser(['id' => 1, 'karang_taruna_id' => 101, 'role_level' => 'ketua']);
                $controller = new MySQLInventoryNotificationProbe();
                $request = \Config\Services::request()->setMethod('patch')->setBody(json_encode(['status' => $status]));
                $controller->initController($request, \Config\Services::response(), \Config\Services::logger());
                $response = $controller->changeLoanStatus($loan);
                $result = ['code' => $response->getStatusCode(), 'notifications' => $controller->notifications];
            } else {
                $change = (new InventoryLoanTransitionService($db))->change(101, $loan, $status);
                $result = ['code' => 200, 'changed' => $change['changed']];
            }
        } catch (InventoryTransitionException $error) {
            $result = ['code' => $error->getCode(), 'changed' => false];
        } catch (Throwable $error) {
            $result = ['code' => 500, 'changed' => false, 'error_class' => get_class($error), 'error_code' => $error->getCode()];
        }
        $result['database_error_codes'] = $databaseErrors;
        $result['loan'] = $db->table('inventory_loans')->where('id', $loan)->get()->getRowArray();
        $result['stock'] = (int) $db->table('inventories')->where('id', $result['loan']['inventory_id'])->get()->getRow()->available_quantity;
    } else {
        throw new RuntimeException('Unknown fixture operation');
    }
    echo json_encode(['ok' => true, 'result' => $result], JSON_THROW_ON_ERROR);
    $db->close();
} catch (Throwable $error) {
    // Fixture schema/SQL contains synthetic data only; never print credentials.
    fwrite(STDERR, 'MySQL fixture failed: ' . get_class($error) . "\n");
    exit(1);
}
