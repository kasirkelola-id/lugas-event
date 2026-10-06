<?php

namespace Tests;

final class LegacyCredentialRetirementTest extends \CodeIgniter\Test\CIUnitTestCase
{
    public function test_obsolete_login_diagnostic_is_absent(): void
    {
        $this->assertFalse(is_file(dirname(__DIR__, 2) . '/test_auth.php'), 'Retired credential-bearing diagnostic must remain absent');
    }

    public function test_legacy_http_migration_controller_is_absent_and_unroutable(): void
    {
        $this->assertFalse(is_file(APPPATH . 'Controllers/Api/MigrateController.php'), 'Retired migration controller must remain absent');
        $this->assertFalse(class_exists('App\\Controllers\\Api\\MigrateController', false));
        $routes = service('routes');
        $routes->loadRoutes();
        $this->assertFalse($routes->shouldAutoRoute());
        foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'] as $method) {
            foreach ($routes->getRoutes($method) as $path => $handler) {
                $this->assertFalse(str_contains($path, 'system/migrate'), 'No legacy HTTP migration route');
                $this->assertFalse(is_string($handler) && str_contains($handler, 'MigrateController'), 'No route to retired controller');
            }
        }
    }

    public function test_cli_migration_infrastructure_remains_available(): void
    {
        $this->assertTrue(class_exists(\CodeIgniter\Commands\Database\Migrate::class));
        $this->assertTrue(class_exists(\App\Database\SafeMigrationRunner::class));
        $this->assertInstanceOf(\App\Database\TestMigrationRunner::class, service('migrations'));
        $process = proc_open([PHP_BINARY, __DIR__ . '/_support/cli_migration_retirement_probe.php'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $this->assertIsResource($process);
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $this->assertSame(0, proc_close($process));
        $this->assertSame('', $errors);
        $this->assertStringContainsString('Migrations complete.', $output);
        $result = json_decode(substr($output, strrpos($output, '{"users"')), true);
        $this->assertTrue($result['users']);
        $this->assertTrue($result['notification_jobs']);
        $this->assertGreaterThan(0, $result['history_rows']);
    }
}
