<?php

// Child PHPUnit process only: reuse the schema owned by the parent fixture.
if (getenv('CI_ENVIRONMENT') !== 'testing' || getenv('KARTAR_MYSQL_TEST_ENABLE') !== '1') {
    throw new RuntimeException('MySQL application proof requires testing opt-in');
}
$schema = getenv('KARTAR_MYSQL_APPLICATION_SCHEMA');
if (!$schema || !preg_match('/\Akartar_batch4_test_[a-f0-9]{16}\z/', $schema)) {
    throw new RuntimeException('A parent disposable schema is required');
}
putenv('FCM_MOCK=true');
require __DIR__ . '/testing_bootstrap.php';
$configuration = \Tests\Support\DisposableMySQL::configuration($schema);
$config = config(\Config\Database::class);
$config->tests = $configuration;
$config->defaultGroup = 'tests';
$version = (string) \Config\Database::connect('tests')->query('SELECT VERSION() AS v')->getRow()->v;
if (!preg_match('/\A8\./', $version) || stripos($version, 'MariaDB') !== false) {
    throw new RuntimeException('MySQL 8 required');
}
