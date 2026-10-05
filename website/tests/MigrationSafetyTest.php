<?php

namespace Tests;

final class MigrationSafetyTest extends \CodeIgniter\Test\CIUnitTestCase
{
    public function test_actual_production_runner_blocks_historical_execution_before_parent_call(): void
    {
        $command = [PHP_BINARY, __DIR__ . '/_support/production_migration_probe.php'];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $this->assertIsResource($process);
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        $this->assertSame(0, proc_close($process));
        $this->assertSame('', $errors);
        $this->assertSame(['up_blocked' => true, 'down_blocked' => true,
            'destructive_executions' => 0, 'forward_allowed' => true], json_decode($output, true));
    }
}
