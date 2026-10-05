<?php

namespace App\Services;

final class IdentityCreationService
{
    public static function create(int $tenantId, array $user, array $membership, bool $checkPhone = false): int
    {
        $db = \Config\Database::connect();
        if ($db->transDepth !== 0 || !$db->transStatus() || !$db->transBegin()) throw new \RuntimeException('Identity transaction unavailable');
        try {
            $lock = $db->DBDriver === 'SQLite3' ? '' : ' FOR UPDATE';
            $tenant = $db->query('SELECT id FROM karang_taruna WHERE id = ? AND status_aktif = 1' . $lock, [$tenantId])->getRowArray();
            if (!$tenant) throw new \DomainException('Organisasi tidak tersedia', 422);
            if ($db->table('organization_members')->where('karang_taruna_id', $tenantId)->where('username', $membership['username'])->countAllResults()) {
                throw new \DomainException('Username sudah terdaftar', 409);
            }
            $role = $membership['role_level'];
            if (in_array($role, ['ketua', 'wakil_ketua', 'sekretaris', 'wakil_sekretaris', 'bendahara', 'wakil_bendahara'], true)
                && $db->table('organization_members')->where('karang_taruna_id', $tenantId)->where('role_level', $role)->where('status_aktif', 1)->countAllResults()) {
                throw new \DomainException('Jabatan sudah diisi oleh pengguna aktif lain', 400);
            }
            if ($checkPhone && $db->table('users')->where('no_whatsapp', $user['no_whatsapp'])->countAllResults()) {
                throw new \DomainException('Nomor WhatsApp sudah terdaftar', 422);
            }
            $users = new \App\Models\UserModel();
            $members = new \App\Models\OrganizationMemberModel();
            $userId = $users->insert($user);
            if (!$userId || !$members->insert(array_replace($membership, ['user_id' => $userId, 'karang_taruna_id' => $tenantId]))) {
                throw new \RuntimeException('Identity write failed');
            }
            if (!$db->transStatus() || !$db->transCommit()) throw new \RuntimeException('Identity commit failed');
            return (int)$userId;
        } catch (\Throwable $error) {
            $db->transRollback();
            throw $error;
        }
    }
}
