<?php
$nama = (string) ($auth['nama'] ?? 'Manager');
$role = (string) ($auth['role'] ?? '');
$initial = mb_strtoupper(mb_substr(trim($nama) !== '' ? trim($nama) : 'M', 0, 1));
?>
<header class="manager-topbar">
    <button
        type="button"
        class="manager-menu-button"
        data-bs-toggle="offcanvas"
        data-bs-target="#managerSidebar"
        aria-controls="managerSidebar"
        aria-label="Buka menu"
    >
        <span class="manager-menu-icon"></span>
    </button>

    <div class="manager-topbar-title">
        <strong><?= esc($pageTitle) ?></strong>
        <span><?= esc($pageSubtitle) ?></span>
    </div>

    <div class="manager-context" title="Context Kegiatan akan aktif setelah modul Kegiatan tersedia">
        <div>
            <div class="manager-context-label">Kegiatan</div>
            <div class="manager-context-value">Belum ada context aktif</div>
        </div>
    </div>

    <div class="dropdown manager-account">
        <button
            class="manager-account-button dropdown-toggle"
            type="button"
            data-bs-toggle="dropdown"
            aria-expanded="false"
        >
            <span class="manager-avatar"><?= esc($initial) ?></span>

            <span class="manager-account-copy">
                <strong><?= esc($nama) ?></strong>
                <span><?= esc($role) ?></span>
            </span>
        </button>

        <ul class="dropdown-menu dropdown-menu-end">
            <li class="px-2 py-2">
                <div class="small fw-semibold text-dark"><?= esc($nama) ?></div>
                <div class="small text-secondary"><?= esc($auth['username'] ?? '') ?> · <?= esc($role) ?></div>
            </li>

            <li><hr class="dropdown-divider"></li>

            <li>
                <button
                    class="dropdown-item text-danger"
                    type="button"
                    data-manager-logout
                    data-logout-url="<?= base_url('manager/api/auth/logout') ?>"
                >
                    Logout
                </button>
            </li>
        </ul>
    </div>
</header>
