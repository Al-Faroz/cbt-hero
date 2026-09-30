<?= $this->extend('participant/layouts/main') ?>

<?= $this->section('pageStyles') ?>
<link rel="stylesheet" href="<?= base_url('assets/vendor/katex/katex.min.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/participant-exam-workspace.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div id="examWorkspace"
    class="exam-workspace"
    data-attempt-id="<?= (int) $attempt['id'] ?>"
    data-api-base="<?= esc(base_url('api/attempt/' . (int) $attempt['id']), 'attr') ?>"
    data-exam-list-url="<?= esc(base_url('ujian'), 'attr') ?>">

    <section class="exam-workspace-bar">
        <div class="exam-workspace-title">
            <span class="small text-secondary">Ujian</span>
            <strong id="examName"><?= esc($attempt['nama_mapel'] ?: $attempt['nama_bank']) ?></strong>
        </div>

        <div class="exam-runtime-badges">
            <div class="exam-text-controls" role="group" aria-label="Ukuran teks soal">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-exam-font="small" aria-label="Perkecil teks">A−</button>
                <button type="button" class="btn btn-outline-secondary btn-sm is-active" data-exam-font="normal" aria-label="Ukuran teks normal">A</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-exam-font="large" aria-label="Perbesar teks">A+</button>
            </div>
            <span class="exam-sync-state" id="examSyncState">Memuat...</span>
            <span class="exam-timer" id="examTimer">--:--:--</span>
        </div>
    </section>

    <section class="exam-progress-strip">
        <span id="examQuestionPosition">Soal - / -</span>
        <span class="exam-progress-counts">
            <span id="examAnsweredCount">0 dijawab</span>
            <span aria-hidden="true">·</span>
            <span id="examFlaggedCount">0 ditandai</span>
        </span>
        <button class="btn btn-outline-secondary btn-sm" type="button" id="examPaletteToggle">
            Daftar Soal
        </button>
    </section>

    <div class="exam-layout">
        <main class="exam-question-panel">
            <div class="exam-question-card" id="examQuestionCard">
                <div class="participant-skeleton"></div>
            </div>

            <div class="exam-answer-status">
                <label class="form-check">
                    <input class="form-check-input" type="checkbox" id="examFlagged">
                    <span class="form-check-label">Tandai soal ini</span>
                </label>
                <span class="small text-secondary" id="examLocalStatus">Belum ada jawaban</span>
            </div>

            <div class="exam-navigation">
                <button class="btn btn-outline-secondary" type="button" id="examPrev">Sebelumnya</button>
                <button class="btn btn-cbt-primary" type="button" id="examNext">Berikutnya</button>
            </div>
        </main>

        <aside class="exam-palette" id="examPalette" aria-label="Daftar soal">
            <div class="exam-palette-header">
                <strong>Daftar Soal</strong>
                <button class="btn-close d-lg-none" type="button" id="examPaletteClose" aria-label="Tutup"></button>
            </div>
            <div class="exam-palette-grid" id="examPaletteGrid"></div>
            <div class="exam-palette-legend">
                <span><i class="is-answered"></i> Dijawab</span>
                <span><i class="is-flagged"></i> Ditandai</span>
            </div>
            <button class="btn btn-danger w-100 mt-3" type="button" id="examSubmit">
                Selesai Ujian
            </button>
        </aside>
        <button class="exam-palette-backdrop" id="examPaletteBackdrop" type="button" aria-label="Tutup daftar soal" hidden></button>
    </div>

    <div class="exam-offline-banner" id="examOfflineBanner" hidden>
        Tidak ada koneksi. Jawaban tetap disimpan di perangkat dan akan disinkronkan saat koneksi kembali.
    </div>

    <div class="exam-lock-overlay" id="examLockOverlay" hidden>
        <div class="exam-lock-card">
            <h2 id="examLockTitle">Ujian dikunci</h2>
            <p id="examLockMessage">Silakan ikuti petunjuk pengawas.</p>
            <a class="btn btn-outline-secondary" href="<?= base_url('ujian') ?>">Kembali ke Daftar Ujian</a>
        </div>
    </div>
</div>


<div class="modal fade" id="examImageModal" tabindex="-1" aria-labelledby="examImageModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content exam-image-modal-content">
            <div class="modal-header py-2">
                <h2 class="modal-title fs-6" id="examImageModalTitle">Perbesar Gambar</h2>
                <div class="d-flex align-items-center gap-1 ms-auto me-2">
                    <button class="btn btn-outline-secondary btn-sm" type="button" id="examImageZoomOut" aria-label="Perkecil gambar"><i class="bi bi-dash-lg"></i></button>
                    <button class="btn btn-outline-secondary btn-sm" type="button" id="examImageZoomReset" aria-label="Kembalikan ukuran gambar">100%</button>
                    <button class="btn btn-outline-secondary btn-sm" type="button" id="examImageZoomIn" aria-label="Perbesar gambar"><i class="bi bi-plus-lg"></i></button>
                </div>
                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body exam-image-stage" id="examImageStage">
                <img id="examImageZoomTarget" alt="Gambar soal diperbesar">
            </div>
            <div class="modal-footer py-2">
                <span class="small text-secondary me-auto">Geser gambar saat diperbesar. Gunakan tombol +/− atau roda mouse.</span>
                <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="examSubmitModal" tabindex="-1" aria-labelledby="examSubmitModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <div class="participant-kicker mb-1">Konfirmasi Selesai</div>
                    <h2 class="modal-title fs-5" id="examSubmitModalTitle">Selesaikan ujian?</h2>
                </div>
                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3">Pastikan semua jawaban yang ingin Anda kerjakan sudah terisi. Setelah ujian diselesaikan, jawaban tidak dapat diubah.</p>
                <div class="exam-submit-summary">
                    <div><span>Total Soal</span><strong id="examSubmitTotal">0</strong></div>
                    <div><span>Sudah Dijawab</span><strong id="examSubmitAnswered">0</strong></div>
                    <div><span>Belum Dijawab</span><strong id="examSubmitUnanswered">0</strong></div>
                    <div><span>Ditandai</span><strong id="examSubmitFlagged">0</strong></div>
                </div>
                <div class="alert alert-warning small mt-3 mb-0" id="examSubmitWarning" hidden>
                    Masih ada soal yang belum dijawab. Anda tetap dapat menyelesaikan ujian jika memang ingin mengakhiri pengerjaan.
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Kembali Mengerjakan</button>
                <button class="btn btn-danger" type="button" id="examSubmitConfirm">Ya, Selesaikan Ujian</button>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('pageScripts') ?>
<script src="<?= base_url('assets/vendor/dexie/dexie.min.js') ?>"></script>
<script src="<?= base_url('assets/vendor/katex/katex.min.js') ?>"></script>
<script src="<?= base_url('assets/vendor/panzoom/panzoom.min.js') ?>"></script>
<script src="<?= base_url('assets/js/question-renderer.js') ?>"></script>
<script src="<?= base_url('assets/js/exam-display.js') ?>"></script>
<script src="<?= base_url('assets/js/exam-db.js') ?>"></script>
<script src="<?= base_url('assets/js/exam-runtime-state.js') ?>"></script>
<script src="<?= base_url('assets/js/exam-tab-lock.js') ?>"></script>
<script src="<?= base_url('assets/js/exam-renderer.js') ?>"></script>
<script src="<?= base_url('assets/js/exam-answer-store.js') ?>"></script>
<script src="<?= base_url('assets/js/exam-sync.js') ?>"></script>
<script src="<?= base_url('assets/js/exam-timer.js') ?>"></script>
<script src="<?= base_url('assets/js/exam-navigation.js') ?>"></script>
<script src="<?= base_url('assets/js/exam-media.js') ?>"></script>
<script src="<?= base_url('assets/js/exam-submit.js') ?>"></script>
<script src="<?= base_url('assets/js/exam-bootstrap.js') ?>"></script>
<?= $this->endSection() ?>
