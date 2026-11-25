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
                            <div class="small text-secondary">Target Valid</div>
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
                    <div class="small text-warning mt-2" id="prepInvalidTargets" hidden></div>
                </div>

                <div class="alert alert-light border small mb-3">
                    Preparation memilih soal, menetapkan urutan soal/pilihan, mengunci revisi soal, dan menyiapkan daftar media sebelum peserta START. Proses dilakukan bertahap agar aman untuk jumlah peserta besar.
                </div>

                <div class="border rounded p-3 mt-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <div>
                            <div class="fw-semibold">Prepared Assignment Peserta</div>
                            <div class="small text-secondary">Lihat versi assignment, revisi soal, dan manifest media untuk pemeriksaan operator.</div>
                        </div>
                        <div class="d-flex gap-2">
                            <input class="form-control form-control-sm" id="prepAssignmentSearch" type="search" placeholder="Cari nomor/nama/rombel" aria-label="Cari assignment peserta">
                            <button class="btn btn-outline-secondary btn-sm" type="button" id="prepAssignmentSearchButton">Cari</button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm manager-table mb-2">
                            <thead><tr><th>Peserta</th><th>Versi</th><th>Status</th><th>Soal</th><th>Dipakai</th><th>Aksi</th></tr></thead>
                            <tbody id="prepAssignmentRows"><tr><td colspan="6" class="text-center text-secondary">Memuat assignment...</td></tr></tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-between align-items-center gap-2">
                        <span class="small text-secondary" id="prepAssignmentInfo">-</span>
                        <div class="d-flex align-items-center gap-2">
                            <button class="btn btn-outline-secondary btn-sm" type="button" id="prepAssignmentPrev">Sebelumnya</button>
                            <span class="small" id="prepAssignmentPageInfo">1 / 1</span>
                            <button class="btn btn-outline-secondary btn-sm" type="button" id="prepAssignmentNext">Berikutnya</button>
                        </div>
                    </div>
                    <div id="prepAssignmentDetail" class="border rounded p-3 mt-3" hidden>
                        <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                            <strong id="prepAssignmentDetailTitle">Detail Assignment</strong>
                            <button class="btn btn-outline-secondary btn-sm" type="button" id="prepAssignmentDetailClose">Tutup Detail</button>
                        </div>
                        <div class="small text-secondary mb-2" id="prepAssignmentDetailMeta"></div>
                        <div class="table-responsive">
                            <table class="table table-sm manager-table mb-2">
                                <thead><tr><th>No.</th><th>Tipe</th><th>ID Soal</th><th>Revisi</th><th>Poin</th><th>Konten</th></tr></thead>
                                <tbody id="prepAssignmentDetailItems"></tbody>
                            </table>
                        </div>
                        <div class="fw-semibold small mb-2">Manifest Media</div>
                        <div id="prepAssignmentDetailMedia" class="d-flex flex-wrap gap-2"></div>
                    </div>
                </div>
                <div class="cbt-inline-feedback mt-2" id="prepFeedback" role="status" aria-live="polite"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Tutup</button>
                <button class="btn btn-outline-primary" type="button" id="prepRebuild">Bangun Ulang Terdampak</button>
                <button class="btn btn-cbt-primary" type="button" id="prepRun">Siapkan Semua</button>
            </div>
        </div>
    </div>
</div>
