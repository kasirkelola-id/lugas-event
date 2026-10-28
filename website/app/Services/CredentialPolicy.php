<?php

namespace App\Services;

final class CredentialPolicy
{
    // Only input requirements imposed by bcrypt; no product complexity rules.
    public static function validNewPassword(string $password): bool
    {
        return $password !== '' && strlen($password) <= 72 && !str_contains($password, "\0");
    }

    public static function hashNewPassword(string $password): string
    {
        if (!self::validNewPassword($password)) {
            throw new \InvalidArgumentException('Password wajib diisi, maksimal 72 byte, tanpa byte NUL.');
        }
        return password_hash($password, PASSWORD_BCRYPT);
    }

    public static function verify(string $password, array $account, string $environment = ENVIRONMENT): bool
    {
        // Login verifies existing hashes independently of password creation rules.
        return password_verify($password, (string) ($account['password'] ?? ''));
    }

    public static function requireTestingSeeds(string $environment = ENVIRONMENT): void
    {
        if ($environment !== 'testing') {
            throw new \RuntimeException('Demo credentials may only be seeded in testing.');
        }
    }
}
