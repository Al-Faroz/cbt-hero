<div class="modal fade" id="preparationModal" tabindex="-1" aria-labelledby="preparationModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <div class="manager-page-kicker mb-1">Preparation</div>
                    <h2 class="modal-title fs-5" id="preparationModalTitle">Prepared Assignment</h2>
                </div>
                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="row g-2 mb-3">
                    <div class="col-6 col-md">
                        <div class="border rounded p-3 h-100">
                            <div class="small text-secondary">Target</div>
                            <div class="fs-5 fw-semibold" id="prepTotal">0</div>
                        </div>
                    </div>
                    <div class="col-6 col-md">
                        <div class="border rounded p-3 h-100">
                            <div class="small text-secondary">Siap</div>
                            <div class="fs-5 fw-semibold" id="prepReady">0</div>
                        </div>
                    </div>
                    <div class="col-6 col-md">
                        <div class="border rounded p-3 h-100">
                            <div class="small text-secondary">Perlu Dibuat</div>
                            <div class="fs-5 fw-semibold" id="prepMissing">0</div>
                        </div>
                    </div>
                    <div class="col-6 col-md">
                        <div class="border rounded p-3 h-100">
                            <div class="small text-secondary">Perlu Dibangun Ulang</div>
                            <div class="fs-5 fw-semibold" id="prepStale">0</div>
                        </div>
                    </div>
                </div>

                <div class="progress mb-2" role="progressbar" aria-label="Progress Preparation">
                    <div class="progress-bar" id="prepProgressBar" style="width:0%">0%</div>
                </div>
                <p class="small text-secondary mb-3" id="prepStatusText">Memuat status...</p>

                <div class="border rounded p-3 mb-3">
                    <div class="fw-semibold mb-1">Pemeriksaan Peserta</div>
                    <div class="small" id="prepPreflightText">-</div>
                </div>

                <div class="alert alert-light border small mb-3">
                    Preparation memilih soal, menetapkan urutan soal/pilihan, mengunci revisi soal, dan menyiapkan daftar media sebelum peserta START. Proses dilakukan bertahap agar aman untuk jumlah peserta besar.
                </div>

                <div class="cbt-inline-feedback" id="prepFeedback" role="status" aria-live="polite"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Tutup</button>
                <button class="btn btn-outline-primary" type="button" id="prepRebuild">Bangun Ulang Terdampak</button>
                <button class="btn btn-cbt-primary" type="button" id="prepRun">Siapkan Semua</button>
            </div>
        </div>
    </div>
</div>
