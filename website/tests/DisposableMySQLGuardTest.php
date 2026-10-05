<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\DisposableMySQL;

/** Guard checks never open a connection. */
class DisposableMySQLGuardTest extends CIUnitTestCase
{
    private array $previous = [];

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['KARTAR_MYSQL_TEST_ENABLE', 'KARTAR_MYSQL_TEST_PORT', 'KARTAR_MYSQL_TEST_USER', 'KARTAR_MYSQL_TEST_PASSWORD'] as $key) {
            $this->previous[$key] = getenv($key);
        }
        putenv('KARTAR_MYSQL_TEST_ENABLE=1');
        putenv('KARTAR_MYSQL_TEST_PORT=3307');
        putenv('KARTAR_MYSQL_TEST_USER=synthetic');
        putenv('KARTAR_MYSQL_TEST_PASSWORD=');
    }

    protected function tearDown(): void
    {
        foreach ($this->previous as $key => $value) {
            putenv($value === false ? $key : $key . '=' . $value);
        }
        parent::tearDown();
    }

    public function testMissingOptInCannotUseAnExistingDatabase(): void
    {
        putenv('KARTAR_MYSQL_TEST_ENABLE=0');
        $this->expectException(\RuntimeException::class);
        DisposableMySQL::configuration();
    }

    public function testProductionSchemaNameIsRejectedBeforeConnection(): void
    {
        $this->expectException(\RuntimeException::class);
        DisposableMySQL::configuration('kartar');
    }

    public function testPortMustBeExplicitAndValid(): void
    {
        putenv('KARTAR_MYSQL_TEST_PORT=3307suffix');
        $this->expectException(\RuntimeException::class);
        DisposableMySQL::configuration();
    }

    public function testConfigurationIsLoopbackStrictMysqlWithNoAppEnvironmentFallback(): void
    {
        $config = DisposableMySQL::configuration('kartar_batch4_test_0123456789abcdef');
        $this->assertSame('127.0.0.1', $config['hostname']);
        $this->assertSame('MySQLi', $config['DBDriver']);
        $this->assertTrue($config['strictOn']);
        $this->assertSame('synthetic', $config['username']);
        $this->assertSame('', $config['password']);
        $this->assertSame(3307, $config['port']);
    }
}
