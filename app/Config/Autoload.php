<?php

namespace Config;

use CodeIgniter\Config\AutoloadConfig;

class Autoload extends AutoloadConfig
{
    /**
     * PSR-4 namespaces.
     *
     * @var array<string, list<string>|string>
     */
    public $psr4 = [
        APP_NAMESPACE => APPPATH,
    ];

    /**
     * Explicit class map.
     *
     * @var array<string, string>
     */
    public $classmap = [];

    /**
     * Non-class files yang dimuat otomatis.
     *
     * @var list<string>
     */
    public $files = [];

    /**
     * Global helpers.
     *
     * Sengaja kosong.
     * Helper dimuat sesuai kebutuhan modul.
     *
     * @var list<string>
     */
    public $helpers = [];
}
