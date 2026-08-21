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
}
