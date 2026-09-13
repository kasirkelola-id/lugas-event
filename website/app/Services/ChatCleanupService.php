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
     * @return array{deleted: int, batches: int}
     */
    public function deleteExpired(?string $cutoff = null, int $batchSize = self::BATCH_SIZE): array
    {
        $cutoff ??= (new \App\Models\ChatModel())->getRetentionCutoff();
        $batchSize = max(1, min($batchSize, self::BATCH_SIZE));
        $deleted = 0;
        $batches = 0;

        while (true) {
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

            $this->db->table('chats')->whereIn('id', array_column($ids, 'id'))->delete();
            $deleted += $this->db->affectedRows();
            $batches++;
        }

        return ['deleted' => $deleted, 'batches' => $batches];
    }
}
