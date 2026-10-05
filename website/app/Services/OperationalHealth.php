<?php

namespace App\Services;

/** CLI-only aggregate report; no mutation, provider call or entity/secret data. */
final class OperationalHealth
{
    public function report(): array
    {
        $db = \Config\Database::connect();
        $db->query('SELECT 1 AS healthy');
        $now = gmdate('Y-m-d H:i:s');
        $counts = array_fill_keys(['pending', 'processing', 'completed', 'failed', 'cancelled'], 0);
        foreach ($db->table('notification_jobs')->select('status, COUNT(*) AS amount')->groupBy('status')->get()->getResultArray() as $row) {
            if (isset($counts[$row['status']])) $counts[$row['status']] = (int)$row['amount'];
        }
        $due = $db->table('notification_jobs')->select('COUNT(*) AS amount, MIN(next_attempt_at) AS oldest')
            ->where('status', 'pending')->where('next_attempt_at <=', $now)->get()->getRowArray();
        $expired = $db->table('notification_jobs')->where('status', 'processing')->where('lease_expires_at <', $now)->countAllResults();
        $last = $db->table('notification_jobs')->selectMax('completed_at')->where('status', 'completed')->get()->getRowArray()['completed_at'] ?? null;
        return ['database_ready' => true, 'observed_at' => $now, 'notification_jobs' => $counts,
            'due_jobs' => (int)$due['amount'], 'oldest_due_at' => $due['oldest'], 'expired_leases' => $expired,
            'last_completed_job_at' => $last, 'cleanup_last_success' => null, 'backup_last_success' => null,
            'scheduler_evidence' => 'Operator scheduler/backup exit records required; job completion does not prove scheduler health'];
    }
}
