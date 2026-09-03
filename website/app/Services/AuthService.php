<?php

namespace App\Services;

class AuthService
{
    private static $user = null;
    private static $token = null;

    public static function setUser($user)
    {
        self::$user = $user;
    }

    public static function getUser()
    {
        return self::$user;
    }

    public static function setToken($tokenData)
    {
        self::$token = $tokenData;
    }

    public static function getToken()
    {
        return self::$token;
    }

    public static function getTenantId()
    {
        return self::$user['karang_taruna_id'] ?? null;
    }

    public static function getRole()
    {
        return self::$user['role_level'] ?? null;
    }

    public static function getGlobalUserId()
    {
        return self::$user['id'] ?? null;
    }

    /**
     * Checks if the currently authenticated user's active membership role has the given permission.
     * Deny by default.
     */
    public static function can(string $permission): bool
    {
        $role = self::getRole();
        if (!$role) {
            return false;
        }

        $config = config('Rbac');
        $rolePermissions = $config->permissions[$role] ?? [];
        
        return in_array($permission, $rolePermissions, true);
    }

    /**
     * Requires a specific permission, throws exception or halts if denied.
     */
    public static function requirePermission(string $permission): void
    {
        if (!self::can($permission)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Forbidden: ' . $permission);
        }
    }
}
