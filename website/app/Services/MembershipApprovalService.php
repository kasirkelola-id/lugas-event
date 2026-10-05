<?php

namespace App\Services;

final class MembershipApprovalService
{
    public static function decide(int $tenantId, int $membershipId, string $action, ?int $actorId, string $actorType): bool
    {
        if (!in_array($action, ['approved', 'rejected'], true) || !in_array($actorType, ['user', 'superadmin'], true)) {
            throw new \InvalidArgumentException('Invalid membership decision');
        }
        $db = \Config\Database::connect();
        if ($db->transDepth !== 0 || !$db->transStatus() || !$db->transBegin()) throw new \RuntimeException('Approval transaction unavailable');
        try {
            if (!$db->table('organization_members')->where('id', $membershipId)->where('karang_taruna_id', $tenantId)
                ->where('approval_status', 'pending')->update(['approval_status' => $action, 'updated_at' => date('Y-m-d H:i:s')])) {
                throw new \RuntimeException('Approval update failed');
            }
            if ($db->affectedRows() !== 1) {
                $db->transRollback();
                return false;
            }
            if (!(new \App\Models\MembershipApprovalHistoryModel())->insert([
                'organization_member_id' => $membershipId, 'karang_taruna_id' => $tenantId, 'action' => $action,
                'actor_user_id' => $actorId, 'actor_type' => $actorType, 'note' => null, 'created_at' => date('Y-m-d H:i:s'),
            ]) || !$db->transStatus() || !$db->transCommit()) throw new \RuntimeException('Approval history commit failed');
            return true;
        } catch (\Throwable $error) {
            $db->transRollback();
            throw $error;
        }
    }
}
