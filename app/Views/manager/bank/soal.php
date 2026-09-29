<?= $this->extend('manager/layouts/main') ?>
<?= $this->section('content') ?>
<section class="manager-page-header">
    <div>
        <div class="manager-page-kicker">Master Ujian / Bank Soal</div>
        <h1 class="manager-page-title">Daftar Soal</h1>
        <p class="manager-page-description" id="questionContext">Memuat Bank Soal...</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-outline-primary" href="<?= base_url('manager/master-ujian/bank-soal/' . $bankId . '/komposisi') ?>">Komposisi</a>
        <a class="btn btn-outline-primary" id="questionImportLink" href="<?= base_url('manager/master-ujian/bank-soal/' . $bankId . '/impor') ?>">Impor Soal</a>
        <a class="btn btn-outline-secondary" href="<?= base_url('manager/master-ujian/bank-soal') ?>">Kembali ke Bank</a>
    </div>
</section>

<div id="questionListApp"
     data-api="<?= esc(base_url('manager/api/bank-soal/' . $bankId . '/soal'), 'attr') ?>"
     data-config="<?= esc(base_url('manager/api/bank-soal/' . $bankId . '/type-config'), 'attr') ?>"
     data-preflight="<?= esc(base_url('manager/api/bank-soal/' . $bankId . '/preflight'), 'attr') ?>"
     data-media-api="<?= esc(base_url('manager/api/question-media'), 'attr') ?>">

    <section class="manager-section-card mb-3">
        <div class="card-body pb-0">
            <div class="question-type-tabs-wrap">
                <ul class="nav nav-tabs question-type-tabs" id="questionTypeTabs" role="tablist"></ul>
            </div>
        </div>
        <div class="card-body pt-3">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <button class="btn btn-outline-info btn-sm" id="questionInfo" type="button">
                        <i class="bi bi-info-circle me-1"></i>Informasi
                    </button>
                    <button class="btn btn-cbt-primary btn-sm" id="questionAdd" type="button" disabled>
                        <i class="bi bi-plus-lg me-1"></i>Tambah Soal
                    </button>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="small text-secondary" id="questionSelectedCount">0 soal dipilih</span>
                    <button class="btn btn-outline-danger btn-sm" id="questionBulkDelete" type="button" disabled>
                        Hapus Terpilih
                    </button>
                </div>
            </div>
            <div class="row g-2 align-items-end mb-3">
                <div class="col-md-6 col-lg-5">
                    <label class="cbt-form-label" for="questionSearch">Cari soal</label>
                    <input class="form-control form-control-sm" id="questionSearch" type="search" maxlength="120" placeholder="Cari isi pertanyaan...">
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <label class="cbt-form-label" for="questionPageSize">Per halaman</label>
                    <select class="form-select form-select-sm" id="questionPageSize">
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>
            </div>
            <div class="cbt-inline-feedback mb-2" id="questionFeedback" role="status" aria-live="polite"></div>
        </div>
        <div class="table-responsive">
            <table class="table manager-table align-middle mb-0" id="questionTable">
                <thead>
                <tr>
                    <th class="text-center question-check-col"><input class="form-check-input" type="checkbox" id="questionCheckAll" aria-label="Pilih semua pada halaman ini"></th>
                    <th>No</th>
                    <th>Pertanyaan</th>
                    <th>Poin</th>
                    <th>Revisi</th>
                    <th>Aksi</th>
                </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2 border-top">
            <span class="small text-secondary" id="questionTableInfo">Belum ada soal</span>
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-outline-secondary btn-sm" id="questionPrevious" type="button">Sebelumnya</button>
                <span class="small text-secondary" id="questionPageInfo">1 / 1</span>
                <button class="btn btn-outline-secondary btn-sm" id="questionNext" type="button">Berikutnya</button>
            </div>
        </div>
    </section>

    <section class="manager-section-card mb-3" id="questionEditor" hidden>
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <div class="manager-page-kicker mb-1" id="questionEditorKicker">Daftar Soal</div>
                <strong id="questionEditorTitle">Tambah Soal</strong>
            </div>
            <button class="btn btn-outline-secondary btn-sm" id="questionEditorClose" type="button">Tutup</button>
        </div>
        <div class="card-body">
            <form id="questionForm">
                <div class="row g-3 mb-3">
                    <div class="col-md-8">
                        <label class="cbt-form-label" for="questionTypeLabel">Tipe Soal</label>
                        <input class="form-control" id="questionTypeLabel" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="cbt-form-label" for="questionPoint">Poin Maksimum</label>
                        <input class="form-control" id="questionPoint" type="number" min="0.0001" max="1000" step="0.0001" value="1" required>
                    </div>
                </div>

                <div class="mb-3" id="questionStimulusGroup">
                    <label class="cbt-form-label" for="questionStimulus">Stimulus (opsional)</label>
                    <textarea class="form-control" id="questionStimulus" rows="3" maxlength="20000"></textarea>
                </div>

                <div class="mb-3">
                    <label class="cbt-form-label" for="questionText">Pertanyaan</label>
                    <textarea class="form-control" id="questionText" rows="5" maxlength="10000" required></textarea>
                </div>

                <div id="questionSpecific"></div>

                <div class="border rounded p-3 mt-3" id="questionLivePanel" hidden>
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                        <div>
                            <div class="manager-page-kicker mb-1">Live Edit</div>
                            <strong>Catatan Revisi Saat Ujian Berjalan</strong>
                            <div class="small text-secondary mt-1">Revisi lama tetap tersimpan. Peserta aktif menerima perubahan pada checkpoint berikutnya.</div>
                        </div>
                        <span class="badge text-bg-warning">Bank READY</span>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="cbt-form-label" for="questionLiveKind">Jenis Perubahan</label>
                            <select class="form-select" id="questionLiveKind">
                                <option value="CONTENT">Perbaikan Isi/Tampilan</option>
                                <option value="KEY_WEIGHT">Kunci / Poin</option>
                                <option value="STRUCTURAL">Struktur Jawaban</option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="cbt-form-label" for="questionLiveNote">Catatan Perubahan</label>
                            <input class="form-control" id="questionLiveNote" maxlength="500" placeholder="Jelaskan perubahan yang dilakukan">
                        </div>
                        <div class="col-md-5" id="questionLivePolicyWrap" hidden>
                            <label class="cbt-form-label" for="questionLivePolicy">Jawaban Peserta Aktif</label>
                            <select class="form-select" id="questionLivePolicy">
                                <option value="PRESERVE">Pertahankan Jawaban</option>
                                <option value="REANSWER">Minta Menjawab Ulang</option>
                            </select>
                            <div class="form-text">Menjawab ulang hanya berlaku untuk Attempt yang masih aktif. Jawaban lama tetap masuk audit.</div>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <button class="btn btn-outline-warning btn-sm" id="questionVoid" type="button">
                            <i class="bi bi-slash-circle me-1"></i>Batalkan / VOID Soal
                        </button>
                        <span class="small text-secondary align-self-center">VOID tidak menghapus riwayat jawaban dan soal tidak dihitung dalam denominator nilai.</span>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2 mt-3">
                    <button class="btn btn-cbt-primary" id="questionSave" type="submit">Simpan Soal</button>
                    <button class="btn btn-outline-secondary" id="questionCancel" type="button">Batal</button>
                </div>
                <div class="cbt-inline-feedback mt-2" id="questionFormFeedback" role="alert"></div>
            </form>

            <div class="border-top mt-4 pt-3">
                <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                    <strong>Pratinjau Soal</strong>
                    <span class="small text-secondary">Renderer sama dengan Bank/Exam Client</span>
                </div>
                <div id="questionPreview" class="cbt-question-preview border rounded p-3"></div>
            </div>
        </div>
    </section>
</div>

<div class="modal fade" id="questionInfoModal" tabindex="-1" aria-labelledby="questionInfoTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <div class="manager-page-kicker mb-1">Panduan Pembuatan Soal</div>
                    <h2 class="modal-title fs-5" id="questionInfoTitle">Informasi</h2>
                </div>
                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body" id="questionInfoBody"></div>
            <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Tutup</button></div>
        </div>
    </div>
</div>

<div class="modal fade" id="questionVoidModal" tabindex="-1" aria-labelledby="questionVoidTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="questionVoidTitle">Batalkan / VOID Soal</h2>
                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">Soal akan ditandai VOID pada revisi baru dan tidak lagi dihitung dalam nilai.</p>
                <div class="alert alert-warning mb-0">Riwayat jawaban tidak dihapus. Tindakan ini memengaruhi denominator penilaian dan perlu diikuti Hitung Ulang Nilai sebelum Finalisasi Hasil.</div>
                <div class="cbt-inline-feedback mt-2" id="questionVoidFeedback"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Batal</button>
                <button class="btn btn-warning" type="button" id="questionVoidConfirm">Ya, VOID Soal</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="questionDeleteModal" tabindex="-1" aria-labelledby="questionDeleteTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="questionDeleteTitle">Hapus Soal</h2>
                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2" id="questionDeleteMessage"></p>
                <div class="alert alert-warning mb-0">Penghapusan bersifat permanen dan menghapus seluruh revisi soal. Aksi hanya tersedia saat Bank dan Kegiatan masih DRAFT serta Bank belum dipakai Jadwal.</div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Batal</button>
                <button class="btn btn-danger" type="button" id="questionDeleteConfirm">Hapus Permanen</button>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('pageScripts') ?>
<link rel="stylesheet" href="<?= base_url('assets/vendor/katex/katex.min.css') ?>">
<script src="<?= base_url('assets/vendor/katex/katex.min.js') ?>" defer></script>
<script src="<?= base_url('assets/js/question-renderer.js') ?>" defer></script>
<script src="<?= base_url('assets/js/question-rich-editor.js') ?>" defer></script>
<script src="<?= base_url('assets/js/manager-question-list.js') ?>" defer></script>
<?= $this->endSection() ?>
