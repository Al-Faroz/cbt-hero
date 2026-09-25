<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Branding extends BaseConfig
{
    /*
     * ================================================================
     * CBT-HERO FINAL BRANDING
     * ================================================================
     * Copy berikut dikunci sebagai identitas aplikasi.
     * Jangan mengubahnya tanpa keputusan eksplisit pada dokumen acuan.
     */

    public string $appName = 'CBT-HERO';

    public string $productName = 'Computer Based Test';

    public string $tagline =
        'Ujian digital yang ringan, stabil, dan siap untuk skala besar.';

    public string $description =
        'Satu platform untuk persiapan, pelaksanaan, monitoring, dan hasil ujian.';

    public string $footer =
        'CBT-HERO · Computer Based Test Platform';

    public string $logo =
        'assets/images/branding/cbt-hero-logo.png';

    public string $logoWithText =
        'assets/images/branding/cbt-hero-logo-text.png';
}
