<?php

namespace App\Services;

final class SettingsPolicy
{
    public const TENANT_BOUNDS = [
        'attendance_before_minutes' => [0, 240], 'attendance_after_minutes' => [0, 240],
        'default_geofence_radius' => [1, 5000], 'kas_backdate_limit' => [0, 365],
    ];

    public static function integer($value, int $min, int $max): bool
    {
        if (!(is_int($value) || (is_string($value) && preg_match('/\A[0-9]{1,10}\z/D', $value)))) return false;
        return (int)$value >= $min && (int)$value <= $max;
    }

    public static function text($value, int $max, bool $required = true): bool
    {
        return is_string($value) && mb_check_encoding($value, 'UTF-8')
            && mb_strlen($value) <= $max && (!$required || trim($value) !== '')
            && !preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value);
    }

    public static function trustedDownloadUrl($value): bool
    {
        if (!is_string($value) || strlen($value) > 2048 || !filter_var($value, FILTER_VALIDATE_URL)
            || preg_match('/[\x00-\x20\x7F\\\\]/', $value)) return false;
        $url = parse_url($value);
        if (!$url || ($url['scheme'] ?? '') !== 'https' || isset($url['user']) || isset($url['pass'])
            || isset($url['fragment']) || (isset($url['port']) && $url['port'] !== 443)) return false;
        $host = strtolower($url['host'] ?? '');
        if (!preg_match('/\A[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?\z/D', $host)
            || !str_contains($host, '.') || filter_var($host, FILTER_VALIDATE_IP)) return false;
        return in_array($host, array_map('strtolower', config('UpdatePolicy')->trustedDownloadHosts), true);
    }
}
