<?= $this->extend('manager/layouts/main') ?>
<?= $this->section('content') ?>
<section class="manager-page-header">
    <div>
        <div class="manager-page-kicker">Pelaksanaan Ujian</div>
        <h1 class="manager-page-title">Monitoring Ujian</h1>
        <p class="manager-page-description">Pantau peserta, sinkronisasi, sisa waktu, dan lakukan kontrol operasional tanpa membuka Bank Soal.</p>
    </div>
</section>

<div id="monitoringApp"
    data-api="<?= esc(base_url('manager/api/monitoring'), 'attr') ?>"
    data-control-api="<?= esc(base_url('manager/api/monitoring'), 'attr') ?>">

    <section class="manager-section-card monitoring-filter-card">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-lg-3 col-md-6">
                    <label class="cbt-form-label" for="monitorKegiatan">Kegiatan</label>
                    <select class="form-select" id="monitorKegiatan"><option value="">Pilih Kegiatan</option></select>
                </div>
                <div class="col-lg-4 col-md-6">
                    <label class="cbt-form-label" for="monitorJadwal">Jadwal</label>
                    <select class="form-select" id="monitorJadwal"><option value="">Pilih Jadwal</option></select>
                </div>
                <div class="col-lg-2 col-md-4">
                    <label class="cbt-form-label" for="monitorStatus">Status</label>
                    <select class="form-select" id="monitorStatus">
                        <option value="">Semua</option>
                        <option value="BELUM">Belum</option>
                        <option value="SEDANG">Sedang</option>
                        <option value="SELESAI">Selesai</option>
                    </select>
                </div>
                <div class="col-lg-2 col-md-4">
                    <label class="cbt-form-label" for="monitorRuang">Ruang</label>
                    <select class="form-select" id="monitorRuang"><option value="">Semua Ruang</option></select>
                </div>
                <div class="col-lg-1 col-md-4">
                    <label class="cbt-form-label" for="monitorPageSize">Baris</label>
                    <select class="form-select" id="monitorPageSize"><option value="25">25</option><option value="50" selected>50</option><option value="100">100</option></select>
                </div>
                <div class="col-lg-4 col-md-6">
                    <label class="cbt-form-label" for="monitorSearch">Cari peserta</label>
                    <input class="form-control" id="monitorSearch" type="search" placeholder="Nomor, nama, atau rombel">
                </div>
                <div class="col-lg-2 col-md-3">
                    <label class="cbt-form-label" for="monitorRefresh">Refresh</label>
                    <select class="form-select" id="monitorRefresh">
                        <option value="5">5 detik</option>
                        <option value="10" selected>10 detik</option>
                        <option value="15">15 detik</option>
                        <option value="30">30 detik</option>
                    </select>
                </div>
                <div class="col-lg-2 col-md-3">
                    <button class="btn btn-outline-secondary w-100" id="monitorReload" type="button">Refresh Sekarang</button>
                </div>
            </div>
            <div class="cbt-inline-feedback mt-2" id="monitorFeedback" role="status" aria-live="polite"></div>
        </div>
    </section>

    <div class="monitoring-summary">
        <?php foreach (['Total' => 'monitorTotal', 'Belum' => 'monitorBelum', 'Sedang' => 'monitorSedang', 'Selesai' => 'monitorSelesai', 'Tidak Terdeteksi' => 'monitorUndetected'] as $label => $id): ?>
            <div class="monitoring-summary-item">
                <span><?= esc($label) ?></span>
                <strong id="<?= esc($id) ?>">0</strong>
            </div>
        <?php endforeach; ?>
    </div>

    <section class="manager-section-card monitoring-table-card">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <strong id="monitorSelected">0 dipilih</strong>
                <button class="btn btn-outline-secondary btn-sm" id="monitorBulkReset" type="button" disabled>Reset Akses</button>
                <button class="btn btn-outline-secondary btn-sm" id="monitorBulkTime" type="button" disabled>Tambah Waktu</button>
                <button class="btn btn-outline-danger btn-sm" id="monitorBulkFinish" type="button" disabled>Paksa Selesai</button>
            </div>
            <span class="small text-secondary" id="monitorLastRefresh">-</span>
        </div>

        <div class="table-responsive">
            <table class="table manager-table mb-0">
                <thead>
                    <tr>
                        <th class="text-center"><input class="form-check-input" id="monitorCheckPage" type="checkbox" aria-label="Pilih Attempt aktif pada halaman"></th>
                        <th>No Peserta</th>
                        <th>Nama</th>
                        <th>Rombel</th>
                        <th>Ruang</th>
                        <th>Status</th>
                        <th>Used</th>
                        <th>Remaining</th>
                        <th>Last Sync</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody id="monitorRows"></tbody>
            </table>
        </div>

        <div class="card-body border-top d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span class="small text-secondary" id="monitorCount">0 peserta</span>
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-outline-secondary btn-sm" id="monitorPrev" type="button">Sebelumnya</button>
                <span class="small" id="monitorPageInfo">1 / 1</span>
                <button class="btn btn-outline-secondary btn-sm" id="monitorNext" type="button">Berikutnya</button>
            </div>
        </div>
    </section>
</div>

<div class="modal fade" id="monitorDetailModal" tabindex="-1" aria-labelledby="monitorDetailTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="monitorDetailTitle">Detail Attempt</h2>
                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body" id="monitorDetailBody"></div>
        </div>
    </div>
</div>

<div class="modal fade" id="monitorActionModal" tabindex="-1" aria-labelledby="monitorActionTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="monitorActionTitle">Kontrol Attempt</h2>
                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <form id="monitorActionForm">
                    <input type="hidden" id="monitorActionType">
                    <div id="monitorActionFields"></div>
                    <div class="cbt-inline-feedback mt-2" id="monitorActionFeedback"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Batal</button>
                <button class="btn btn-cbt-primary" type="submit" form="monitorActionForm">Jalankan</button>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('pageScripts') ?>
<script src="<?= base_url('assets/js/manager-monitoring.js') ?>" defer></script>
<?= $this->endSection() ?>
