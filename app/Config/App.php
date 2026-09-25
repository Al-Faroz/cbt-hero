<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class App extends BaseConfig
{
    /**
     * Base URL CBT-HERO.
     * Nilai ini dapat dioverride melalui .env.
     */
    public string $baseURL = 'http://localhost/cbt-hero/';

    /**
     * Host tambahan yang diperbolehkan.
     */
    public array $allowedHostnames = [];

    /**
     * index.php disembunyikan melalui mod_rewrite.
     */
    public string $indexPage = '';

    /**
     * URI protocol.
     */
    public string $uriProtocol = 'REQUEST_URI';

    /**
     * Karakter URI yang diperbolehkan.
     */
    public string $permittedURIChars = 'a-z 0-9~%.:_\-';

    /**
     * Bahasa utama CBT-HERO.
     */
    public string $defaultLocale = 'id';

    /**
     * Tidak melakukan auto-negotiation browser.
     */
    public bool $negotiateLocale = false;

    /**
     * Locale yang tersedia.
     *
     * @var list<string>
     */
    public array $supportedLocales = [
        'id',
        'en',
    ];

    /**
     * Zona waktu operasional.
     */
    public string $appTimezone = 'Asia/Jakarta';

    /**
     * Character set.
     */
    public string $charset = 'UTF-8';

    /**
     * Development localhost masih HTTP.
     * Production nanti menggunakan HTTPS.
     */
    public bool $forceGlobalSecureRequests = false;

    /**
     * Reverse proxy.
     *
     * @var array<string, string>
     */
    public array $proxyIPs = [];

    /**
     * CSP akan kita konfigurasi pada fase security/frontend.
     */
    public bool $CSPEnabled = false;
}
