<?php

namespace App\Services;

final class ParticipantBatchService
{
    public static function add(int $tenant, int $event, array $ids): int
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (!$ids) return 0;
        if (count($ids) > 100 || min($ids) < 1 || max($ids) > 4294967295) throw new \InvalidArgumentException('Invalid participant IDs');
        $db = \Config\Database::connect();
        if ($db->transDepth !== 0 || !$db->transStatus() || !$db->transBegin()) throw new \RuntimeException('Participant transaction unavailable');
        try {
            $lock = $db->DBDriver === 'MySQLi' ? ' FOR UPDATE' : '';
            $parent = $db->query('SELECT id FROM events WHERE id = ? AND karang_taruna_id = ?' . $lock, [$event, $tenant])->getRowArray();
            if (!$parent) throw new \RuntimeException('Participant event unavailable');
            $builder = $db->table('organization_members m')->select('m.user_id')->join('users u', 'u.id = m.user_id')
                ->where('m.karang_taruna_id', $tenant)->where('m.role_level', 'anggota')->where('m.status_aktif', 1)
                ->where('m.approval_status', 'approved')->where('u.status_aktif', 1)->whereIn('m.user_id', $ids)->orderBy('m.user_id');
            $eligible = array_column($db->query($builder->getCompiledSelect() . $lock)->getResultArray(), 'user_id');
            $existing = $db->table('event_participants')->select('user_id')->where('event_id', $event)->whereIn('user_id', $ids)->get()->getResultArray();
            $new = array_diff($eligible, array_column($existing, 'user_id'));
            $rows = [];
            foreach ($new as $user) $rows[] = ['karang_taruna_id' => $tenant, 'event_id' => $event, 'user_id' => (int)$user, 'created_at' => date('Y-m-d H:i:s')];
            if ($rows && $db->table('event_participants')->insertBatch($rows) !== count($rows)) throw new \RuntimeException('Participant batch write failed');
            if (!$db->transStatus() || !$db->transCommit()) throw new \RuntimeException('Participant commit failed');
            return count($rows);
        } catch (\Throwable $error) {
            if ($db->transDepth > 0) $db->transRollback();
            throw $error;
        }
    }
}
