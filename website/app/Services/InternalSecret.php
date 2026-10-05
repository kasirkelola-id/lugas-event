<?php

namespace App\Services;

final class InternalSecret
{
    public static function resolve(?string $configured, string $environment): ?string
    {
        if ($configured !== null && trim($configured) !== '') {
            if (!in_array($environment, ['testing', 'development'], true)
                && $configured === 'default_internal_secret_for_dev') {
                return null;
            }
            return $configured;
        }
        return in_array($environment, ['testing', 'development'], true)
            ? 'default_internal_secret_for_dev' : null;
    }

    public static function configured(): ?string
    {
        $value = getenv('INTERNAL_API_SECRET');
        return self::resolve($value === false ? null : $value, ENVIRONMENT);
    }

    public static function accepts(string $provided): bool
    {
        $secret = self::configured();
        return $secret !== null && $provided !== '' && hash_equals($secret, $provided);
    }
}
