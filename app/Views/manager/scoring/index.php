<?= $this->extend('manager/layouts/main') ?>
<?= $this->section('content') ?>
<section class="manager-page-header">
    <div>
        <div class="manager-page-kicker">Pelaksanaan Ujian</div>
        <h1 class="manager-page-title">Penilaian Akademik</h1>
        <p class="manager-page-description">Periksa hasil per soal, beri nilai manual bila diperlukan, hitung ulang hasil, lalu kunci hasil setelah seluruh penilaian selesai.</p>
    </div>
</section>

<div id="scoringApp"
     data-options-api="<?= esc(base_url('manager/api/monitoring/options'), 'attr') ?>"
     data-scoring-api="<?= esc(base_url('manager/api/scoring'), 'attr') ?>"
     data-results-api="<?= esc(base_url('manager/api/results'), 'attr') ?>">

    <section class="manager-section-card mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-lg-4 col-md-6">
                    <label class="cbt-form-label" for="scoringKegiatan">Kegiatan</label>
                    <select class="form-select" id="scoringKegiatan">
                        <option value="">Pilih Kegiatan</option>
                    </select>
                </div>
                <div class="col-lg-5 col-md-6">
                    <label class="cbt-form-label" for="scoringJadwal">Jadwal</label>
                    <select class="form-select" id="scoringJadwal">
                        <option value="">Pilih Jadwal</option>
                    </select>
                </div>
                <div class="col-lg-3 col-md-6">
                    <label class="cbt-form-label" for="scoringType">Tipe Soal</label>
                    <select class="form-select" id="scoringType">
                        <option value="">Semua Tipe</option>
                        <option value="PG">Pilihan Ganda</option>
                        <option value="PG_KOMPLEKS">PG Kompleks</option>
                        <option value="PG_BERTINGKAT">PG Bertingkat</option>
                        <option value="MATCHING">Menjodohkan</option>
                        <option value="ISIAN_SINGKAT">Isian Singkat</option>
                        <option value="URAIAN">Uraian</option>
                    </select>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-center mt-3">
                <button class="btn btn-outline-primary btn-sm" id="scoringReload" type="button" disabled>
                    <i class="bi bi-arrow-clockwise me-1"></i>Muat Ulang
                </button>
                <button class="btn btn-outline-secondary btn-sm" id="scoringRescore" type="button" disabled>
                    <i class="bi bi-calculator me-1"></i>Hitung Ulang Nilai
                </button>
                <button class="btn btn-cbt-primary btn-sm" id="scoringFinalize" type="button" disabled>
                    <i class="bi bi-lock me-1"></i>Finalisasi Hasil
                </button>
                <span class="small text-secondary ms-lg-auto" id="scoringContext">Pilih Jadwal untuk membuka penilaian.</span>
            </div>
            <div class="cbt-inline-feedback mt-2" id="scoringFeedback" role="status" aria-live="polite"></div>
        </div>
    </section>

    <section class="manager-section-card mb-3">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <div class="manager-page-kicker mb-1">Daftar Soal</div>
                <strong>Koreksi per Soal</strong>
            </div>
            <span class="small text-secondary" id="scoringQuestionCount">0 soal</span>
        </div>
        <div class="table-responsive">
            <table class="table manager-table align-middle mb-0">
                <thead>
                <tr>
                    <th>Soal</th>
                    <th>Tipe</th>
                    <th>Respons</th>
                    <th>Perlu Diperiksa</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
                </thead>
                <tbody id="scoringQuestionRows">
                <tr><td colspan="6" class="text-center text-secondary py-4">Pilih Jadwal terlebih dahulu.</td></tr>
                </tbody>
            </table>
        </div>
    </section>

    <section class="manager-section-card" id="scoringResponsesPanel" hidden>
        <div class="card-header d-flex flex-wrap justify-content-between align-items-start gap-2">
            <div>
                <div class="manager-page-kicker mb-1">Jawaban Peserta</div>
                <strong id="scoringQuestionTitle">Soal</strong>
                <div class="small text-secondary mt-1" id="scoringQuestionText"></div>
            </div>
            <button class="btn btn-outline-secondary btn-sm" id="scoringCloseResponses" type="button">Tutup</button>
        </div>
        <div class="table-responsive">
            <table class="table manager-table align-middle mb-0">
                <thead>
                <tr>
                    <th>No Peserta</th>
                    <th>Nama</th>
                    <th>Rombel</th>
                    <th>Jawaban</th>
                    <th>Auto</th>
                    <th>Manual</th>
                    <th>Efektif</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
                </thead>
                <tbody id="scoringResponseRows"></tbody>
            </table>
        </div>
        <div class="card-body border-top">
            <div class="small text-secondary" id="scoringResponseCount">0 respons</div>
        </div>
    </section>
</div>

<div class="modal fade" id="manualScoreModal" tabindex="-1" aria-labelledby="manualScoreTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <div class="manager-page-kicker mb-1">Koreksi Nilai</div>
                    <h2 class="modal-title fs-5" id="manualScoreTitle">Nilai Manual</h2>
                </div>
                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <form id="manualScoreForm">
                <div class="modal-body">
                    <input type="hidden" id="manualResponseId">
                    <div class="mb-3">
                        <label class="cbt-form-label" for="manualScoreValue">Nilai</label>
                        <input class="form-control" id="manualScoreValue" type="text" inputmode="decimal" pattern="[0-9.,]+" required>
                        <div class="form-text" id="manualScoreLimit"></div>
                    </div>
                    <div>
                        <label class="cbt-form-label" for="manualScoreReason">Alasan Koreksi</label>
                        <textarea class="form-control" id="manualScoreReason" rows="3" maxlength="500" required placeholder="Contoh: Koreksi uraian berdasarkan pedoman penilaian."></textarea>
                    </div>
                    <div class="cbt-inline-feedback mt-2" id="manualScoreFeedback" role="alert"></div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Batal</button>
                    <button class="btn btn-cbt-primary" type="submit">Simpan Nilai</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="scoringOperationModal" tabindex="-1" aria-labelledby="scoringOperationTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="scoringOperationTitle">Konfirmasi</h2>
                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0" id="scoringOperationMessage"></p>
                <div class="cbt-inline-feedback mt-2" id="scoringOperationFeedback"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Batal</button>
                <button class="btn btn-cbt-primary" id="scoringOperationConfirm" type="button">Lanjutkan</button>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('pageScripts') ?>
<script src="<?= base_url('assets/js/manager-scoring.js') ?>" defer></script>
<?= $this->endSection() ?>
