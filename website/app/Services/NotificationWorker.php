<?php

namespace App\Services;

/** Finite worker: no provider call runs inside a database transaction. */
final class NotificationWorker
{
    public function __construct(private ?PushTransport $transport = null, private ?ChatFanout $fanout = null)
    {
        $this->transport ??= \Config\Services::pushTransport();
        $this->fanout ??= new ChatFanout();
    }

    public function runOne(int $deliveryLimit = 5): array
    {
        $started = microtime(true);
        $db = \Config\Database::connect();
        $job = $this->claim($db);
        $result = ['claimed' => $job ? 1 : 0, 'sent' => 0, 'retry' => 0, 'terminal' => 0, 'errors' => 0];
        if (!$job) return $result;
        try {
            $envelope = DomainNotification::envelope($job);
            if (!$envelope) {
                $this->finish($db, $job, ['status' => 'cancelled', 'completed_at' => gmdate('Y-m-d H:i:s')]);
                return $result;
            }
            if (!(int)$job['expanded']) $this->expand($db, $job, $envelope);
            if (!(int)$job['fanout_done'] && (int)$job['fanout_attempts'] < 5
                && (!$job['fanout_next_attempt_at'] || $job['fanout_next_attempt_at'] <= gmdate('Y-m-d H:i:s'))) {
                $this->heartbeat($db, $job);
                $job['fanout_attempts']++;
                if (!$db->table('notification_jobs')->where('id', $job['id'])->where('lease_token', $job['lease_token'])
                    ->update(['fanout_attempts' => $job['fanout_attempts'], 'fanout_next_attempt_at' => gmdate('Y-m-d H:i:s', time() + 60)]) || $db->affectedRows() !== 1) throw new \RuntimeException('Fanout attempt write failed');
                $job['fanout_done'] = $this->fanout->send((int)$job['entity_id']) ? 1 : 0;
                $job['fanout_next_attempt_at'] = gmdate('Y-m-d H:i:s', time() + 60);
            }
            $deliveries = $db->table('notification_deliveries')->where('job_id', $job['id'])->where('status', 'pending')
                ->where('next_attempt_at <=', gmdate('Y-m-d H:i:s'))->orderBy('id')->limit(max(1, min($deliveryLimit, 5)))->get()->getResultArray();
            foreach ($deliveries as $delivery) {
                if (microtime(true) - $started >= 30) break;
                // Recheck domain visibility, ownership and recipient session immediately before send.
                $current = DomainNotification::envelope($job);
                $devices = $current ? DomainNotification::devices($current, 0, (int)$delivery['device_id']) : [];
                $device = $devices[0] ?? null;
                $now = gmdate('Y-m-d H:i:s');
                $attempts = (int)$delivery['attempts'] + 1;
                $status = 'suppressed'; $error = null;
                $this->heartbeat($db, $job);
                if ($attempts > 5) { $attempts = 5; $status = 'failed'; $error = 'attempts_exhausted'; }
                elseif ($device && hash_equals($delivery['registration_hash'], hash('sha256', $device['fcm_token']))) {
                    // Record the attempt before I/O so a crashed worker cannot reset its retry budget.
                    if (!$db->table('notification_deliveries')->where('id', $delivery['id'])->where('status', 'pending')
                        ->where('attempts', $delivery['attempts'])->update(['attempts' => $attempts, 'updated_at' => $now,
                            'next_attempt_at' => gmdate('Y-m-d H:i:s', time() + 60)]) || $db->affectedRows() !== 1) throw new \RuntimeException('Delivery attempt ownership lost');
                    try { $sent = $this->transport->send([$device['fcm_token']], $current['title'], $current['body'], $current['data']); }
                    catch (\Throwable $transportError) { $sent = false; }
                    if ($sent) { $status = 'sent'; $result['sent']++; }
                    elseif (!$db->table('user_devices')->where('id', $device['id'])->where('fcm_token', $device['fcm_token'])->countAllResults()) $status = 'invalid';
                    else { $status = $attempts >= 5 ? 'failed' : 'pending'; $error = 'provider_retry'; $result['retry']++; }
                }
                $this->heartbeat($db, $job);
                if ($status !== 'pending') $result['terminal']++;
                if (!$db->table('notification_deliveries')->where('id', $delivery['id'])->where('status', 'pending')->update([
                    'status' => $status, 'attempts' => $attempts, 'updated_at' => $now,
                    'completed_at' => $status === 'pending' ? null : $now, 'last_error' => $error,
                    'next_attempt_at' => gmdate('Y-m-d H:i:s', time() + min(3600, 60 * (2 ** min($attempts - 1, 6)))),
                ]) || $db->affectedRows() !== 1) throw new \RuntimeException('Notification delivery write failed');
            }
            $pending = $db->table('notification_deliveries')->selectMin('next_attempt_at')->where('job_id', $job['id'])->where('status', 'pending')->get()->getRowArray()['next_attempt_at'] ?? null;
            $fanoutPending = !(int)$job['fanout_done'] && (int)$job['fanout_attempts'] < 5;
            $complete = (int)$job['expanded'] && !$pending && !$fanoutPending;
            $failed = (!(int)$job['fanout_done'] && (int)$job['fanout_attempts'] >= 5)
                || $db->table('notification_deliveries')->where('job_id', $job['id'])->where('status', 'failed')->countAllResults() > 0;
            $next = !(int)$job['expanded'] ? gmdate('Y-m-d H:i:s') : ($pending ?? $job['fanout_next_attempt_at'] ?? gmdate('Y-m-d H:i:s'));
            if ($fanoutPending && $job['fanout_next_attempt_at'] < $next) $next = $job['fanout_next_attempt_at'];
            $this->finish($db, $job, ['status' => $complete ? ($failed ? 'failed' : 'completed') : 'pending',
                'next_attempt_at' => $next, 'completed_at' => $complete ? gmdate('Y-m-d H:i:s') : null,
                'fanout_done' => $job['fanout_done'], 'fanout_attempts' => $job['fanout_attempts'], 'fanout_next_attempt_at' => $job['fanout_next_attempt_at'],
                'last_error' => $failed ? 'delivery_failed' : null]);
        } catch (\Throwable $error) {
            $result['errors']++;
            if ($db->transDepth > 0) $db->transRollback();
            $this->finish($db, $job, ['status' => (int)$job['attempts'] >= 5 ? 'failed' : 'pending', 'last_error' => 'worker_failed',
                'next_attempt_at' => gmdate('Y-m-d H:i:s', time() + 60), 'completed_at' => (int)$job['attempts'] >= 5 ? gmdate('Y-m-d H:i:s') : null]);
        }
        return $result;
    }

    private function claim($db): ?array
    {
        if ($db->transDepth !== 0 || !$db->transStatus() || !$db->transBegin()) throw new \RuntimeException('Notification claim unavailable');
        try {
            $now = gmdate('Y-m-d H:i:s');
            $lock = $db->DBDriver === 'MySQLi' ? ' FOR UPDATE' : '';
            // Recover a crashed owner first. Separate ordered ranges avoid an OR scan
            // and keep recovery from being starved by a continuously growing backlog.
            // Candidate reads do not lock an empty secondary-index range. Such gap
            // locks can deadlock concurrent owners while they change pending->processing.
            $candidate = $db->query("SELECT id FROM notification_jobs WHERE status = 'processing' AND lease_expires_at <= ? ORDER BY lease_expires_at, id LIMIT 1", [$now])->getRowArray();
            if (!$candidate) $candidate = $db->query("SELECT id FROM notification_jobs WHERE status = 'pending' AND next_attempt_at <= ? ORDER BY next_attempt_at, id LIMIT 1", [$now])->getRowArray();
            if (!$candidate) { $db->transRollback(); return null; }
            $job = $db->query('SELECT * FROM notification_jobs WHERE id = ?' . $lock, [$candidate['id']])->getRowArray();
            // Locking reads see the current committed row, unlike the earlier candidate
            // snapshot. A competing winner causes a clean no-work result, not a retry.
            if (!$job || !(($job['status'] === 'pending' && $job['next_attempt_at'] <= $now)
                || ($job['status'] === 'processing' && $job['lease_expires_at'] !== null && $job['lease_expires_at'] <= $now))) {
                $db->transRollback(); return null;
            }
            $oldLease = $job['lease_token'];
            $job['lease_token'] = bin2hex(random_bytes(16)); $job['attempts']++;
            if (!$db->table('notification_jobs')->where('id', $job['id'])->where('lease_token', $oldLease)->update([
                'status' => 'processing', 'lease_token' => $job['lease_token'], 'attempts' => $job['attempts'],
                'lease_expires_at' => gmdate('Y-m-d H:i:s', time() + 120), 'updated_at' => $now,
            ]) || $db->affectedRows() !== 1 || !$db->transStatus() || !$db->transCommit()) throw new \RuntimeException('Notification claim failed');
            return $job;
        } catch (\Throwable $error) {
            if ($db->transDepth > 0) $db->transRollback();
            throw $error;
        }
    }

    private function expand($db, array &$job, array $envelope): void
    {
        if (!$db->transStatus() || !$db->transBegin()) throw new \RuntimeException('Notification expansion unavailable');
        $now = gmdate('Y-m-d H:i:s');
        $devices = DomainNotification::devices($envelope, (int)$job['cursor_device_id']);
        $rows = [];
        foreach ($devices as $device) $rows[] = ['job_id' => $job['id'], 'device_id' => $device['id'],
            'registration_hash' => hash('sha256', $device['fcm_token']), 'next_attempt_at' => $now, 'created_at' => $now, 'updated_at' => $now];
        if ($rows && !$db->table('notification_deliveries')->insertBatch($rows)) throw new \RuntimeException('Notification expansion write failed');
        if ($devices) $job['cursor_device_id'] = (int)end($devices)['id'];
        $job['expanded'] = count($devices) < 100 ? 1 : 0;
        if (!$db->table('notification_jobs')->where('id', $job['id'])->where('lease_token', $job['lease_token'])->update([
            'cursor_device_id' => $job['cursor_device_id'], 'expanded' => $job['expanded'], 'updated_at' => $now,
        ]) || $db->affectedRows() !== 1 || !$db->transStatus() || !$db->transCommit()) throw new \RuntimeException('Notification expansion commit failed');
    }

    private function finish($db, array $job, array $fields): void
    {
        if (!$db->table('notification_jobs')->where('id', $job['id'])->where('lease_token', $job['lease_token'])->update($fields + [
            'lease_token' => null, 'lease_expires_at' => null, 'updated_at' => gmdate('Y-m-d H:i:s'),
        ]) || $db->affectedRows() !== 1) throw new \RuntimeException('Notification lease ownership lost');
    }

    private function heartbeat($db, array $job): void
    {
        if (!$db->table('notification_jobs')->where('id', $job['id'])->where('lease_token', $job['lease_token'])
            ->update(['lease_expires_at' => gmdate('Y-m-d H:i:s', time() + 120)])
            || !$db->table('notification_jobs')->where('id', $job['id'])->where('lease_token', $job['lease_token'])
                ->where('lease_expires_at >', gmdate('Y-m-d H:i:s'))->countAllResults()) throw new \RuntimeException('Notification lease ownership lost');
    }
}
