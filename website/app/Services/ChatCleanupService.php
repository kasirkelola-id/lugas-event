<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/** Deletes only expired chat rows in short, independently committed batches. */
class ChatCleanupService
{
    public const BATCH_SIZE = 1000;

    public function __construct(private ?BaseConnection $db = null)
    {
        $this->db ??= \Config\Database::connect();
    }

    /**
     * @return array{deleted: int, batches: int, bounded_stop: bool, busy: bool}
     */
    public function deleteExpired(?string $cutoff = null, int $batchSize = self::BATCH_SIZE): array
    {
        $cutoff ??= (new \App\Models\ChatModel())->getRetentionCutoff();
        $batchSize = max(1, min($batchSize, self::BATCH_SIZE));
        $deleted = 0;
        $batches = 0;
        if ($this->db->transDepth !== 0 || !$this->db->transStatus()) throw new \RuntimeException('Cleanup requires a clean connection');
        $lock = MaintenanceLock::acquire('chat:' . $this->db->DBDriver . ':' . $this->db->getDatabase());
        if (!$lock) return ['deleted' => 0, 'batches' => 0, 'bounded_stop' => false, 'busy' => true];
        $deadline = microtime(true) + 30;
        $previous = null;
        $noProgress = false;
        try {
            if ($this->db->DBDriver === 'MySQLi') {
                $previous = $this->db->query('SELECT @@session.innodb_lock_wait_timeout AS lock_wait, @@session.max_execution_time AS execution_time')->getRowArray();
                $this->db->query('SET SESSION innodb_lock_wait_timeout = 5');
                $this->db->query('SET SESSION max_execution_time = 5000');
            }

            while ($batches < 10 && microtime(true) < $deadline) {
                // This indexed selector keeps each delete small; there is deliberately no
                // outer transaction spanning batches.
                $ids = $this->db->table('chats')
                    ->select('id')
                    ->where('created_at <', $cutoff)
                    ->orderBy('created_at', 'ASC')
                    ->orderBy('id', 'ASC')
                    ->limit($batchSize)
                    ->get()
                    ->getResultArray();

                if ($ids === []) {
                    break;
                }

                // Recheck expiry at the write, rather than deleting a captured ID that
                // another writer may have moved into the retained window.
                if (!$this->db->table('chats')->whereIn('id', array_column($ids, 'id'))->where('created_at <', $cutoff)->delete()) {
                    throw new \RuntimeException('Cleanup batch write failed');
                }
                $affected = $this->db->affectedRows();
                $deleted += $affected;
                $batches++;
                if ($affected === 0) { $noProgress = true; break; }
            }

            return ['deleted' => $deleted, 'batches' => $batches,
                'bounded_stop' => $noProgress || $batches >= 10 || microtime(true) >= $deadline, 'busy' => false];
        } finally {
            try {
                if ($previous !== null) {
                    $this->db->query('SET SESSION innodb_lock_wait_timeout = ' . (int)$previous['lock_wait']);
                    $this->db->query('SET SESSION max_execution_time = ' . (int)$previous['execution_time']);
                }
            } finally { $lock->release(); }
        }
    }
}
