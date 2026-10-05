<?php

namespace Tests\Api;

use Tests\Support\BaseTest;

final class OperationalHealthTest extends BaseTest
{
    protected $namespace = 'App';

    public function test_report_counts_due_expired_and_terminal_jobs_without_mutation_or_secrets(): void
    {
        $rows = [
            ['status' => 'pending', 'next_attempt_at' => '2000-01-01 00:00:00'],
            ['status' => 'pending', 'next_attempt_at' => '2100-01-01 00:00:00'],
            ['status' => 'processing', 'lease_expires_at' => '2000-01-01 00:00:00'],
            ['status' => 'processing', 'lease_expires_at' => '2100-01-01 00:00:00'],
            ['status' => 'failed'], ['status' => 'cancelled'],
            ['status' => 'completed', 'completed_at' => '2026-01-01 00:00:00'],
        ];
        foreach ($rows as $i => $row) $this->db->table('notification_jobs')->insert($row + [
            'job_key' => 'synthetic-health-' . $i, 'kind' => 'chat', 'entity_id' => 1, 'karang_taruna_id' => 101,
            'created_at' => gmdate('Y-m-d H:i:s'), 'updated_at' => gmdate('Y-m-d H:i:s'),
            'next_attempt_at' => '2000-01-01 00:00:00',
            'last_error' => 'synthetic-private-error', 'lease_token' => 'synthetic-private-lease',
        ]);
        $before = $this->db->table('notification_jobs')->get()->getResultArray();
        $report = (new \App\Services\OperationalHealth())->report();
        $this->assertTrue($report['database_ready']);
        $this->assertSame(['pending' => 2, 'processing' => 2, 'completed' => 1, 'failed' => 1, 'cancelled' => 1], $report['notification_jobs']);
        $this->assertSame(1, $report['due_jobs']); $this->assertSame(1, $report['expired_leases']);
        $this->assertSame('2000-01-01 00:00:00', $report['oldest_due_at']);
        $this->assertSame('2026-01-01 00:00:00', $report['last_completed_job_at']);
        $this->assertNull($report['cleanup_last_success']); $this->assertNull($report['backup_last_success']);
        $this->assertStringNotContainsString('synthetic-private', json_encode($report));
        $this->assertSame($before, $this->db->table('notification_jobs')->get()->getResultArray());
    }
}
