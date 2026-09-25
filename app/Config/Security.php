<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Security extends BaseConfig
{
    /**
     * CBT-HERO memakai session-based CSRF agar konsisten dengan auth berbasis
     * session dan aman untuk request Fetch/AJAX.
     */
    public string $csrfProtection = 'session';

    /**
     * Token tidak diacak per request.
     */
    public bool $tokenRandomize = false;

    public string $tokenName = 'csrf_token';

    public string $headerName = 'X-CSRF-TOKEN';

    public string $cookieName = 'csrf_cookie';

    public int $expires = 7200;

    /**
     * CBT-HERO membutuhkan token stabil, terutama untuk runtime ujian yang
     * nantinya memiliki beberapa request paralel/asinkron.
     */
    public bool $regenerate = false;

    /**
     * API tidak boleh diubah menjadi redirect HTML saat CSRF gagal.
     */
    public bool $redirect = false;
}
