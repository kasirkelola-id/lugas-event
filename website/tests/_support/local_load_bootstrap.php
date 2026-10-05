<?php

// Only a new harness-owned TEMP directory, disposable MySQL8 and real loopback
// HTTP may use this bootstrap. It never reads the application's .env.
if (getenv('CI_ENVIRONMENT') !== 'testing' || getenv('KARTAR_MYSQL_TEST_ENABLE') !== '1'
    || getenv('KARTAR_LOCAL_LOAD_ENABLE') !== '1') throw new RuntimeException('Local load opt-in required');
$root = realpath(getenv('KARTAR_LOCAL_LOAD_ROOT') ?: '');
if (!$root || realpath(dirname($root)) !== realpath(sys_get_temp_dir())
    || !preg_match('/\Akartar-local-load-[a-f0-9]{16}\z/', basename($root))
    || !is_file($root . '/owned.json')) throw new RuntimeException('Owned local load directory required');
$owned = json_decode(file_get_contents($root . '/owned.json'), true, 512, JSON_THROW_ON_ERROR);
if (($owned['schema'] ?? '') !== getenv('KARTAR_MYSQL_APPLICATION_SCHEMA')) throw new RuntimeException('Schema ownership mismatch');
if (PHP_SAPI !== 'cli' && (PHP_SAPI !== 'cli-server' || ($_SERVER['REMOTE_ADDR'] ?? '') !== '127.0.0.1'
    || ($_SERVER['SERVER_NAME'] ?? '') !== '127.0.0.1')) throw new RuntimeException('Loopback built-in server required');
define('ENVIRONMENT', 'testing');
define('CI_DEBUG', false);
define('FCPATH', $root . '/public/');
define('COMPOSER_PATH', dirname(__DIR__, 2) . '/vendor/autoload.php');
putenv('FCM_MOCK=true');
require dirname(__DIR__, 2) . '/app/Config/Paths.php';
$paths = new \Config\Paths();
$paths->writableDirectory = $root . '/writable';
$paths->envDirectory = $root;
require $paths->systemDirectory . '/Boot.php';

final class LocalLoadBoot extends \CodeIgniter\Boot
{
    protected static function loadDotEnv(\Config\Paths $paths): void {}

    protected static function loadAutoloader(): void
    {
        parent::loadAutoloader();
        require_once __DIR__ . '/DisposableMySQL.php';
        $schema = getenv('KARTAR_MYSQL_APPLICATION_SCHEMA');
        $configuration = \Tests\Support\DisposableMySQL::configuration($schema);
        $config = config(\Config\Database::class);
        $config->tests = $configuration;
        $config->defaultGroup = 'tests';
        // Remove all application/default/failover credentials from this process.
        $config->default = $configuration;
        $db = \Config\Database::connect('tests');
        $runtime = $db->query('SELECT VERSION() AS v, @@bind_address AS host')->getRowArray();
        if (!preg_match('/\A8\./', $runtime['v']) || stripos($runtime['v'], 'MariaDB') !== false
            || $runtime['host'] !== '127.0.0.1') throw new RuntimeException('Loopback MySQL8 required');
        $app = config(\Config\App::class);
        $app->baseURL = getenv('KARTAR_LOCAL_HTTP_URL');
        $app->indexPage = '';
        $app->forceGlobalSecureRequests = false; // This isolated HTTP target only.
        date_default_timezone_set($app->appTimezone);
        // Hard deny unexpected application transport; never enable the network
        // bypass used by some existing FeatureTests.
        $transport = new class extends \CodeIgniter\HTTP\CURLRequest {
            public function __construct() {}
            public function request($method, string $url, array $options = []): \CodeIgniter\HTTP\ResponseInterface
            {
                throw new RuntimeException('Local load application transport denied');
            }
        };
        \Config\Services::injectMock('curlrequest', $transport);
    }
}

if (PHP_SAPI === 'cli-server') exit(LocalLoadBoot::bootWeb($paths));
LocalLoadBoot::bootConsole($paths);
