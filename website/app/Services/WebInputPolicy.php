<?php

namespace App\Services;

final class WebInputPolicy
{
    public static function organization(array $input, bool $creating): bool
    {
        if (!SettingsPolicy::text($input['nama_organisasi'] ?? null, 150)
            || !SettingsPolicy::text($input['nama_ketua'] ?? '', 100, false)
            || !SettingsPolicy::text($input['alamat_lengkap'] ?? '', 2000, false)
            || !SettingsPolicy::integer($input['status_aktif'] ?? ($creating ? 1 : null), 0, 1)
            || !SettingsPolicy::integer($input['kelurahan_id'] ?? null, 1, 2147483647)) return false;
        if ($creating && (!is_string($input['kode_pin'] ?? null) || !preg_match('/\A[0-9]{6}\z/D', $input['kode_pin']))) return false;
        return \Config\Database::connect()->table('kelurahan')->where('id', (int)$input['kelurahan_id'])->countAllResults() === 1;
    }
}
