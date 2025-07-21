<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Locale extends BaseConfig
{
    public string $defaultLocale = 'en';
    public array $supportedLocales = ['en'];
    public bool $negotiateLocale = false;
    public string $localeCookieName = 'locale';
    public int $localeCookieExpire = 31536000; // 1 year
    public bool $enableLocaleCookie = false;
    public string $defaultTimezone = 'UTC';

    // ✅ Required by CI 4.6+
    public function getDefault(): string
    {
        return $this->defaultLocale;
    }
}
