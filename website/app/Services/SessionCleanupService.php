<?php

namespace App\Services;

/** Bounded cleanup with dry-run default; device registration remains globally owned. */
final class SessionCleanupService
{
    public function run(bool $apply = false, int $limit = 100): array
    {
        $db = \Config\Database::connect();
        if ($db->transDepth !== 0 || !$db->transStatus()) throw new \RuntimeException('Session cleanup requires a clean connection');
        $limit = max(1, min($limit, 500));
        $lock = MaintenanceLock::acquire('sessions:' . $db->DBDriver . ':' . $db->getDatabase());
        $result = ['apply' => $apply, 'busy' => !$lock, 'bounded_stop' => false, 'tokens_selected' => 0, 'devices_selected' => 0, 'tokens_deleted' => 0, 'devices_deleted' => 0];
        if (!$lock) return $result;
        $now = gmdate('Y-m-d H:i:s');
        $grace = gmdate('Y-m-d H:i:s', time() - 30 * 86400);
        $previous = null;
        $deadline = microtime(true) + 30;
        try {
            if ($db->DBDriver === 'MySQLi') {
                $previous = $db->query('SELECT @@session.innodb_lock_wait_timeout AS lock_wait, @@session.max_execution_time AS execution_time')->getRowArray();
                $db->query('SET SESSION innodb_lock_wait_timeout = 5');
                $db->query('SET SESSION max_execution_time = 5000');
            }
            $invalidBinding = 'NOT EXISTS (SELECT 1 FROM user_tokens t WHERE t.id = user_devices.user_token_id AND t.user_id = user_devices.user_id AND t.expires_at > ' . $db->escape($now) . ' AND t.revoked_at IS NULL)';
            $devices = $db->table('user_devices')->select('id, user_token_id, updated_at')
                ->where('user_token_id IS NOT NULL', null, false)->where('updated_at <', $grace)
                ->where($invalidBinding, null, false)->orderBy('id')->limit($limit)->get()->getResultArray();
            $tokens = $db->table('user_tokens')->select('id')->groupStart()->where('expires_at <', $grace)
                ->orWhere('revoked_at <', $grace)->groupEnd()->orderBy('id')->limit($limit)->get()->getResultArray();
            $result['devices_selected'] = count($devices); $result['tokens_selected'] = count($tokens);
            $result['bounded_stop'] = count($devices) === $limit || count($tokens) === $limit;
            if ($apply) {
                foreach ($devices as $device) {
                    if (microtime(true) >= $deadline) { $result['bounded_stop'] = true; break; }
                    // Rebinding to a current session must survive a stale candidate read.
                    if (!$db->table('user_devices')->where('id', $device['id'])->where('user_token_id', $device['user_token_id'])
                        ->where('updated_at', $device['updated_at'])->where($invalidBinding, null, false)->delete()) throw new \RuntimeException('Device cleanup write failed');
                    $result['devices_deleted'] += $db->affectedRows();
                }
                if (microtime(true) >= $deadline) $result['bounded_stop'] = true;
                elseif ($tokens) {
                    if (!$db->table('user_tokens')->whereIn('id', array_column($tokens, 'id'))->groupStart()
                        ->where('expires_at <', $grace)->orWhere('revoked_at <', $grace)->groupEnd()->delete()) throw new \RuntimeException('Token cleanup write failed');
                    $result['tokens_deleted'] = $db->affectedRows();
                }
            }
            return $result;
        } finally {
            try {
                if ($previous !== null) {
                    $db->query('SET SESSION innodb_lock_wait_timeout = ' . (int)$previous['lock_wait']);
                    $db->query('SET SESSION max_execution_time = ' . (int)$previous['execution_time']);
                }
            } finally { $lock->release(); }
        }
    }
}
