<?php

namespace App\Services;

/** Preserve the Superadmin entry point while sharing one password policy. */
final class SuperadminCredentialPolicy
{
    public static function verify(string $password, array $account): bool
    {
        return CredentialPolicy::verify($password, $account);
    }

    public static function hashNewPassword(string $password): string
    {
        return CredentialPolicy::hashNewPassword($password);
    }
}
