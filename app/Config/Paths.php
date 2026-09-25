<?php

namespace Config;

class Paths
{
    /**
     * CodeIgniter framework system directory.
     */
    public string $systemDirectory =
        __DIR__ . '/../../vendor/codeigniter4/framework/system';

    /**
     * Application directory.
     */
    public string $appDirectory =
        __DIR__ . '/..';

    /**
     * Writable directory.
     */
    public string $writableDirectory =
        __DIR__ . '/../../writable';

    /**
     * Tests directory.
     */
    public string $testsDirectory =
        __DIR__ . '/../../tests';

    /**
     * View directory.
     */
    public string $viewDirectory =
        __DIR__ . '/../Views';

    /**
     * Directory tempat .env berada.
     */
    public string $envDirectory =
        __DIR__ . '/../../';
}
