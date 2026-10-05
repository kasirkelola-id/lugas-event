<?php

namespace App\Services;

/** Credential replacement and bearer revocation commit or roll back together. */
final class CredentialSessionService
{
    public static function replace(int $userId, string $expectedHash, string $newHash, bool $mustChange, ?int $keepTokenId = null): bool
    {
        $db = \Config\Database::connect();
        if (!$db->transBegin()) return false;
        try {
            // A stale reset/change cannot overwrite a newer credential.
            if (!$db->table('users')->where('id', $userId)->where('password', $expectedHash)
                ->update(['password' => $newHash, 'password_must_change' => (int)$mustChange]) || $db->affectedRows() !== 1) {
                throw new \RuntimeException('Credential replacement failed');
            }
            $tokens = $db->table('user_tokens')->where('user_id', $userId)->where('revoked_at', null);
            if ($keepTokenId !== null) $tokens->where('id !=', $keepTokenId);
            if (!$tokens->update(['revoked_at' => date('Y-m-d H:i:s')]) || !$db->transStatus() || !$db->transCommit()) {
                throw new \RuntimeException('Credential revocation failed');
            }
            return true;
        } catch (\Throwable $exception) {
            $db->transRollback();
            log_message('error', 'Credential replacement transaction failed');
            return false;
        }
    }
}
