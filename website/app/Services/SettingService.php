<?php

namespace App\Services;

use App\Models\SettingModel;

class SettingService
{
    private static $cache = [];

    /**
     * Get a setting for a tenant with fallback to default.
     * 
     * @param int $tenantId
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function getSetting($tenantId, $key, $default = null)
    {
        if (!isset(self::$cache[$tenantId])) {
            self::preloadSettings($tenantId);
        }

        return self::$cache[$tenantId][$key] ?? $default;
    }

    public static function clearCache()
    {
        self::$cache = [];
    }

    /**
     * Preload all settings for a tenant to avoid N+1 queries.
     * 
     * @param int $tenantId
     */
    public static function preloadSettings($tenantId)
    {
        $settingModel = new SettingModel();
        $settings = $settingModel->where('karang_taruna_id', $tenantId)->findAll();
        
        self::$cache[$tenantId] = [];
        foreach ($settings as $setting) {
            self::$cache[$tenantId][$setting['setting_key']] = $setting['setting_value'];
        }
    }
}
