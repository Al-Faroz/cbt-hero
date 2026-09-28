<?= $this->extend('manager/layouts/main') ?>
<?= $this->section('content') ?>
<section class="manager-page-header">
    <div>
        <div class="manager-page-kicker">Master Ujian</div>
        <h1 class="manager-page-title">Jadwal Ujian</h1>
        <p class="manager-page-description">Atur waktu ujian, durasi, akses, dan jumlah soal yang diambil dari Bank READY.</p>
    </div>
    <button class="btn btn-cbt-primary" type="button" id="jadwalAdd">Tambah Jadwal</button>
</section>

<div id="jadwalApp"
    data-api="<?= esc(base_url('manager/api/jadwal'), 'attr') ?>"
    data-ui-base="<?= esc(base_url('manager/master-ujian/jadwal'), 'attr') ?>"
    data-timezone="<?= esc($appTimezone, 'attr') ?>">
    <section class="manager-section-card">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="cbt-form-label" for="jadwalSearch">Cari Kegiatan / Bank / Mapel</label>
                    <input class="form-control" id="jadwalSearch" autocomplete="off" placeholder="Ketik kata pencarian">
                </div>
                <div class="col-md-3">
                    <label class="cbt-form-label" for="jadwalKegiatanFilter">Kegiatan</label>
                    <select class="form-select" id="jadwalKegiatanFilter"><option value="">Semua Kegiatan</option></select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="cbt-form-label" for="jadwalAccessFilter">Akses</label>
                    <select class="form-select" id="jadwalAccessFilter"><option value="">Semua</option><option value="BUKA">Buka</option><option value="TAHAN">Tahan</option></select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="cbt-form-label" for="jadwalPageSize">Per halaman</label>
                    <select class="form-select" id="jadwalPageSize"><option value="25">25</option><option value="50">50</option><option value="100">100</option></select>
                </div>
                <div class="col-md-1"><button class="btn btn-outline-secondary w-100" type="button" id="jadwalReset">Reset</button></div>
            </div>
            <div class="cbt-inline-feedback mt-2" id="jadwalFeedback" role="status" aria-live="polite"></div>
        </div>

        <div class="table-responsive">
            <table class="table manager-table mb-0">
                <thead>
                    <tr>
                        <th>Kegiatan</th>
                        <th>Bank / Mapel</th>
                        <th>Mulai</th>
                        <th>Batas Mulai</th>
                        <th>Durasi</th>
                        <th>Soal Diambil</th>
                        <th>Nilai</th>
                        <th>Akses</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody id="jadwalRows"></tbody>
            </table>
        </div>

        <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-2 border-top">
            <span class="small text-secondary" id="jadwalCount"></span>
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-outline-secondary btn-sm" type="button" id="jadwalPrevious">Sebelumnya</button>
                <span class="small" id="jadwalPageInfo"></span>
                <button class="btn btn-outline-secondary btn-sm" type="button" id="jadwalNext">Berikutnya</button>
            </div>
        </div>
    </section>
</div>

<div class="modal fade" id="jadwalModal" tabindex="-1" aria-labelledby="jadwalModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <div class="manager-page-kicker mb-1">Jadwal Ujian Utama</div>
                    <h2 class="modal-title fs-5" id="jadwalModalTitle">Tambah Jadwal</h2>
                </div>
                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <form id="jadwalForm">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="cbt-form-label" for="jadwalKegiatan">Kegiatan Akademik</label>
                            <select class="form-select" id="jadwalKegiatan" required></select>
                        </div>
                        <div class="col-md-6">
                            <label class="cbt-form-label" for="jadwalBank">Bank Soal READY</label>
                            <select class="form-select" id="jadwalBank" required></select>
                            <div class="form-text" id="jadwalBankHint"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="cbt-form-label" for="jadwalMulai">Mulai</label>
                            <input class="form-control" id="jadwalMulai" type="datetime-local" required>
                        </div>
                        <div class="col-md-6">
                            <label class="cbt-form-label" for="jadwalBatasMulai">Batas Mulai</label>
                            <input class="form-control" id="jadwalBatasMulai" type="datetime-local" required>
                            <div class="form-text">Batas Mulai adalah batas START baru, bukan waktu peserta harus selesai.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="cbt-form-label" for="jadwalDurasi">Durasi (menit)</label>
                            <input class="form-control" id="jadwalDurasi" type="number" min="1" step="1" value="90" required>
                        </div>
                        <div class="col-md-4">
                            <label class="cbt-form-label" for="jadwalAccess">Akses</label>
                            <select class="form-select" id="jadwalAccess" required><option value="BUKA">Buka</option><option value="TAHAN">Tahan</option></select>
                            <div class="form-text">TAHAN menahan peserta masuk, tetapi tidak menghentikan timer peserta yang sudah berada di ujian.</div>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="jadwalShowScore">
                                <label class="form-check-label" for="jadwalShowScore">Tampilkan nilai saat selesai</label>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                        <div>
                            <h3 class="fs-6 mb-1">Jumlah Soal yang Diambil</h3>
                            <p class="small text-secondary mb-0">Bank boleh berisi lebih banyak soal. Tentukan berapa soal setiap tipe yang diberikan kepada masing-masing peserta.</p>
                        </div>
                    </div>
                    <div id="jadwalSelection" class="row g-2"></div>
                    <div class="small text-secondary mt-2" id="jadwalSelectionHint">Pilih Bank Soal untuk menampilkan komposisi.</div>

                    <div class="alert alert-light border small mt-4 mb-0">
                        Waktu mengikuti zona aplikasi <strong><?= esc($appTimezone) ?></strong>. Pengacakan dan Prepared Assignment dikerjakan pada tahap Preparation, bukan saat peserta menekan START.
                    </div>
                    <div class="cbt-inline-feedback mt-3" id="jadwalFormFeedback" role="alert"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Batal</button>
                <button class="btn btn-cbt-primary" type="submit" form="jadwalForm" id="jadwalSubmit">Simpan Jadwal</button>
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
<?= $this->endSection() ?>

<?= $this->section('pageScripts') ?>
<script src="<?= base_url('assets/js/manager-jadwal.js') ?>" defer></script>
<?= $this->endSection() ?>
