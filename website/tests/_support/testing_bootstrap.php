<?php

// Test uploads must never resolve to a drive root when public/ is absent.
if (getenv('CI_ENVIRONMENT') !== 'testing' || (defined('ENVIRONMENT') && ENVIRONMENT !== 'testing')) {
    throw new RuntimeException('Isolated test bootstrap requires testing');
}
if (defined('FCPATH') || defined('PUBLICPATH')) throw new RuntimeException('Test document root must be established before framework bootstrap');
$fixtureRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'kartar-phpunit-' . bin2hex(random_bytes(8));
if (!mkdir($fixtureRoot, 0700)) throw new RuntimeException('Refusing an existing test document root');
// Two parent levels remain inside the owned root for existing traversal fixtures.
$publicRoot = $fixtureRoot . DIRECTORY_SEPARATOR . 'sandbox' . DIRECTORY_SEPARATOR . 'public';
if (!mkdir($publicRoot, 0700, true) || !($resolvedPublic = realpath($publicRoot))) throw new RuntimeException('Isolated test document root unavailable');
define('TEST_FIXTURE_ROOT', $fixtureRoot . DIRECTORY_SEPARATOR);
define('PUBLICPATH', $resolvedPublic . DIRECTORY_SEPARATOR);
define('FCPATH', PUBLICPATH);
unset($fixtureRoot, $publicRoot, $resolvedPublic);
require dirname(__DIR__, 2) . '/vendor/codeigniter4/framework/system/Test/bootstrap.php';
// Retain the owned TEMP directory; exact fixture-file cleanup stays with each test.
