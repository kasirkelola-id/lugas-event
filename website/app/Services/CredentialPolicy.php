<?php

namespace App\Services;

final class CredentialPolicy
{
    // 144 bits of entropy; 24 URL-safe characters, independent of user/tenant data.
    public static function temporaryPassword(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
    }

    public static function validNewPassword(string $password): bool
    {
        // Bcrypt consumes at most 72 bytes. No composition requirements.
        return mb_strlen($password) >= 12 && strlen($password) <= 72
            && !in_array($password, ['superadmin123', 'lugasjosjis', 'kartarjosjis'], true);
    }

    public static function verify(string $password, array $account, string $environment = ENVIRONMENT): bool
    {
        // Forward-safe login mitigation: historical seeds stay immutable. Known
        // default credentials and username passwords cannot mint production sessions.
        // Operators must reset these accounts to a private credential before rollout.
        if ($environment !== 'testing' && ($password === ($account['username'] ?? null)
            || in_array($password, ['superadmin123', 'lugasjosjis', 'kartarjosjis'], true))) {
            return false;
        }
        return password_verify($password, (string) ($account['password'] ?? ''));
    }

    public static function requireTestingSeeds(string $environment = ENVIRONMENT): void
    {
        if ($environment !== 'testing') {
            throw new \RuntimeException('Demo credentials may only be seeded in testing.');
        }
    }
}
