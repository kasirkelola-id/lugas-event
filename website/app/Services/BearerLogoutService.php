<?php

namespace App\Services;

final class BearerLogoutService
{
    public static function revoke(array $token): bool
    {
        $db = \Config\Database::connect();
        if (!$db->transBegin()) return false;
        try {
            if (!$db->table('user_tokens')->where('id', $token['id'])->update(['revoked_at' => date('Y-m-d H:i:s')])
                || !$db->table('user_devices')->where('user_token_id', $token['id'])->delete()
                || !$db->transStatus() || !$db->transCommit()) {
                throw new \RuntimeException('Logout transaction failed');
            }
            return true;
        } catch (\Throwable $error) {
            $db->transRollback();
            log_message('error', 'Bearer logout failed');
            return false;
        }
    }
}
