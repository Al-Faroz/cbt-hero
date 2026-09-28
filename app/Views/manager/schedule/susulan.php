<?= $this->extend('manager/layouts/main') ?>
<?= $this->section('content') ?>
<section class="manager-page-header">
    <div>
        <div class="manager-page-kicker">Master Ujian / Jadwal Ujian</div>
        <h1 class="manager-page-title">Jadwal Susulan</h1>
        <p class="manager-page-description">Buat Susulan berkali-kali untuk peserta tertentu tanpa menggandakan Bank Soal.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-outline-secondary" href="<?= base_url('manager/master-ujian/jadwal') ?>">Kembali</a>
        <button class="btn btn-cbt-primary" type="button" id="susulanAdd">Tambah Susulan</button>
    </div>
</section>

<div id="susulanApp"
    data-main-id="<?= (int) $mainJadwalId ?>"
    data-api="<?= esc(base_url('manager/api/jadwal/' . $mainJadwalId . '/susulan'), 'attr') ?>"
    data-jadwal-api="<?= esc(base_url('manager/api/jadwal'), 'attr') ?>"
    data-preparation-api-base="<?= esc(base_url('manager/api/jadwal'), 'attr') ?>"
    data-timezone="<?= esc($appTimezone, 'attr') ?>">
    <section class="manager-section-card mb-3">
        <div class="card-body">
            <div class="row g-3" id="susulanMainContext">
                <div class="col-md-4"><div class="small text-secondary">Jadwal Utama</div><div class="fw-semibold" id="susulanMainBank">Memuat...</div></div>
                <div class="col-md-3"><div class="small text-secondary">Mapel / Tingkat</div><div class="fw-semibold" id="susulanMainMapel">-</div></div>
                <div class="col-md-5"><div class="small text-secondary">Pengambilan Soal</div><div class="fw-semibold" id="susulanMainSelection">-</div></div>
            </div>
        </div>
    </section>

    <section class="manager-section-card">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h2 class="fs-6 mb-1">Daftar Susulan</h2>
                <p class="small text-secondary mb-0">Susulan #1, #2, dan seterusnya tetap menjadi child langsung dari Jadwal utama.</p>
            </div>
            <div class="cbt-inline-feedback" id="susulanFeedback" role="status" aria-live="polite"></div>
        </div>
        <div class="table-responsive">
            <table class="table manager-table mb-0">
                <thead>
                    <tr>
                        <th>Susulan</th>
                        <th>Mulai</th>
                        <th>Batas Mulai</th>
                        <th>Durasi</th>
                        <th>Target</th>
                        <th>Akses</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody id="susulanRows"></tbody>
            </table>
        </div>
    </section>
</div>

<div class="modal fade" id="susulanModal" tabindex="-1" aria-labelledby="susulanModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <div class="manager-page-kicker mb-1">Jadwal Susulan</div>
                    <h2 class="modal-title fs-5" id="susulanModalTitle">Tambah Susulan</h2>
                </div>
                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <form id="susulanForm">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="cbt-form-label" for="susulanMulai">Mulai</label>
                            <input class="form-control" id="susulanMulai" type="datetime-local" required>
                        </div>
                        <div class="col-md-4">
                            <label class="cbt-form-label" for="susulanBatasMulai">Batas Mulai</label>
                            <input class="form-control" id="susulanBatasMulai" type="datetime-local" required>
                        </div>
                        <div class="col-md-2">
                            <label class="cbt-form-label" for="susulanDurasi">Durasi (menit)</label>
                            <input class="form-control" id="susulanDurasi" type="number" min="1" step="1" value="90" required>
                        </div>
                        <div class="col-md-2">
                            <label class="cbt-form-label" for="susulanAccess">Akses</label>
                            <select class="form-select" id="susulanAccess"><option value="TAHAN">Tahan</option><option value="BUKA">Buka</option></select>
                        </div>
                    </div>

                    <hr class="my-4">
                    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
                        <div>
                            <h3 class="fs-6 mb-1">Pilih Peserta</h3>
                            <p class="small text-secondary mb-0">Sistem otomatis membedakan peserta yang belum pernah START dan peserta yang membutuhkan replacement.</p>
                        </div>
                        <div class="d-flex gap-2 flex-wrap">
                            <input class="form-control form-control-sm" id="susulanCandidateSearch" type="search" placeholder="Cari nama / rombel / nomor">
                            <select class="form-select form-select-sm" id="susulanCandidatePageSize"><option value="25">25</option><option value="50">50</option><option value="100">100</option></select>
                        </div>
                    </div>

                    <div class="table-responsive border rounded">
                        <table class="table manager-table mb-0">
                            <thead><tr><th class="text-center"><input class="form-check-input" id="susulanCheckPage" type="checkbox" aria-label="Pilih peserta pada halaman ini"></th><th>No Peserta</th><th>Nama</th><th>Rombel</th><th>Status Susulan</th></tr></thead>
                            <tbody id="susulanCandidateRows"></tbody>
                        </table>
                    </div>
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-2">
                        <span class="small text-secondary" id="susulanCandidateInfo"></span>
                        <div class="d-flex align-items-center gap-2">
                            <button class="btn btn-outline-secondary btn-sm" id="susulanCandidatePrev" type="button">Sebelumnya</button>
                            <span class="small" id="susulanCandidatePageInfo">1 / 1</span>
                            <button class="btn btn-outline-secondary btn-sm" id="susulanCandidateNext" type="button">Berikutnya</button>
                        </div>
                    </div>
                    <div class="small mt-2"><strong id="susulanSelectedCount">0 peserta dipilih</strong></div>
                    <div class="cbt-inline-feedback mt-3" id="susulanFormFeedback" role="alert"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Batal</button>
                <button class="btn btn-cbt-primary" type="submit" form="susulanForm" id="susulanSubmit">Simpan Susulan</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="susulanTargetsModal" tabindex="-1" aria-labelledby="susulanTargetsTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="susulanTargetsTitle">Target Susulan</h2>
                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table manager-table mb-0">
                        <thead><tr><th>No Peserta</th><th>Nama</th><th>Rombel</th><th>Mode</th><th>Status</th><th>Aksi</th></tr></thead>
                        <tbody id="susulanTargetRows"></tbody>
                    </table>
                </div>
                <div class="cbt-inline-feedback mt-2" id="susulanTargetFeedback"></div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="jadwalOperationModal" tabindex="-1" aria-labelledby="jadwalOperationTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="jadwalOperationTitle">Kontrol Jadwal</h2>
                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <form id="jadwalOperationForm">
                    <input type="hidden" id="jadwalOperationId">
                    <input type="hidden" id="jadwalOperationType">
                    <div id="jadwalOperationFields"></div>
                    <div class="cbt-inline-feedback mt-2" id="jadwalOperationFeedback"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Batal</button>
                <button class="btn btn-cbt-primary" type="submit" form="jadwalOperationForm">Simpan</button>
            </div>
        </div>
    </div>
</div>
<?= view('manager/schedule/_preparation_modal') ?>
<?= $this->endSection() ?>

<?= $this->section('pageScripts') ?>
<script src="<?= base_url('assets/js/manager-susulan.js') ?>" defer></script>
<script src="<?= base_url('assets/js/manager-preparation.js') ?>" defer></script>
<?= $this->endSection() ?>
