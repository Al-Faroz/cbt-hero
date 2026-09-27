<?= $this->extend('manager/layouts/main') ?>
<?= $this->section('content') ?>
<section class="manager-page-header">
    <div>
        <div class="manager-page-kicker">Master Ujian / Kegiatan</div>
        <h1 class="manager-page-title">Peserta Ujian</h1>
        <p class="manager-page-description" id="pesertaUjianContext">Memuat Kegiatan...</p>
    </div>
    <a class="btn btn-outline-secondary" href="<?= base_url('manager/master-ujian/kegiatan') ?>">Kembali ke Kegiatan</a>
</section>
<div id="pesertaUjianApp" data-api="<?= esc(base_url('manager/api/kegiatan/' . $kegiatanId . '/peserta'), 'attr') ?>" data-rombel-api="<?= esc(base_url('manager/api/peserta/rombel-options'), 'attr') ?>" data-ruang-api="<?= esc(base_url('manager/api/ruang/options'), 'attr') ?>" data-nomor-api="<?= esc(base_url('manager/api/kegiatan/' . $kegiatanId . '/nomor-peserta/generate'), 'attr') ?>">
    <section class="manager-section-card mb-3" id="pesertaUjianAssignCard" hidden>
        <div class="card-header"><div class="fw-bold">Tambahkan Peserta</div></div>
        <div class="card-body">
            <p class="text-secondary">Pilih cakupan dari Peserta aktif. Anggota yang sudah ada tetap tercatat sekali.</p>
            <div class="row g-2 align-items-end">
                <div class="col-md-3"><label class="cbt-form-label" for="pesertaUjianSelector">Cakupan</label><select class="form-select" id="pesertaUjianSelector"><option value="ALL">Semua Peserta</option><option value="TINGKAT">Tingkat</option><option value="ROMBEL">Rombel</option><option value="IDS">Pilih Individu</option></select></div>
                <div class="col-md-3" id="pesertaUjianTingkatWrap" hidden><label class="cbt-form-label" for="pesertaUjianTingkat">Tingkat</label><select class="form-select" id="pesertaUjianTingkat"><option value="7">7</option><option value="8">8</option><option value="9">9</option></select></div>
                <div class="col-md-3" id="pesertaUjianRombelWrap" hidden><label class="cbt-form-label" for="pesertaUjianRombel">Rombel</label><select class="form-select" id="pesertaUjianRombel"></select></div>
                <div class="col-md-3"><button class="btn btn-cbt-primary" type="button" id="pesertaUjianAssign">Tambahkan</button></div>
            </div>
            <div id="pesertaUjianAssignFeedback" class="cbt-inline-feedback mt-2" role="status" aria-live="polite"></div>
        </div>
        <div id="pesertaUjianCandidatePanel" hidden>
            <div class="card-body border-top">
                <div class="row g-2 align-items-end">
                    <div class="col-md-5"><label class="cbt-form-label" for="pesertaUjianCandidateSearch">Cari NISN / Nama</label><input class="form-control" id="pesertaUjianCandidateSearch"></div>
                    <div class="col-md-4"><label class="cbt-form-label" for="pesertaUjianCandidateRombel">Filter Rombel</label><select class="form-select" id="pesertaUjianCandidateRombel"><option value="">Semua Rombel aktif</option></select></div>
                    <div class="col-md-3"><label class="cbt-form-label" for="pesertaUjianCandidateSize">Per halaman</label><select class="form-select" id="pesertaUjianCandidateSize"><option>25</option><option>50</option><option>100</option></select></div>
                </div>
                <div id="pesertaUjianCandidateFeedback" class="cbt-inline-feedback mt-2" role="status"></div>
            </div>
            <div class="table-responsive"><table class="table manager-table mb-0"><thead><tr><th><input class="form-check-input" type="checkbox" id="pesertaUjianSelectAll" aria-label="Pilih semua Peserta pada halaman ini"></th><th>NISN</th><th>Nama</th><th>JK</th><th>Rombel</th></tr></thead><tbody id="pesertaUjianCandidateRows"></tbody></table></div>
            <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2"><span id="pesertaUjianCandidateCount" class="small text-secondary"></span><div class="d-flex align-items-center gap-2"><button class="btn btn-outline-secondary btn-sm" type="button" id="pesertaUjianCandidatePrevious">Sebelumnya</button><span id="pesertaUjianCandidatePageInfo" class="small"></span><button class="btn btn-outline-secondary btn-sm" type="button" id="pesertaUjianCandidateNext">Berikutnya</button></div></div>
        </div>
    </section>
    <section class="manager-section-card mb-3">
        <div class="card-header"><div class="fw-bold">Ringkasan Anggota Test</div></div>
        <div class="card-body">
            <div class="fw-semibold mb-2" id="pesertaUjianSummaryTotal">Total: 0 peserta</div>
            <div id="pesertaUjianSummary" class="row g-2"></div>
            <p class="small text-secondary mb-0 mt-3">Asal penugasan dicatat saat Peserta pertama kali masuk. Menjalankan cakupan lain tidak mengubah asal anggota yang sudah ada.</p>
        </div>
    </section>
    <section class="manager-section-card mb-3" id="pesertaUjianRuangCard" hidden>
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div><div class="fw-bold">Penempatan Ruang</div><span class="small text-secondary" id="pesertaUjianRuangCount"></span></div>
            <a class="btn btn-outline-secondary btn-sm" href="<?= base_url('manager/master-ujian/ruang') ?>">Kelola Ruang</a>
        </div>
        <div class="card-body">
            <p class="small text-secondary">Pilih cakupan anggota lalu Ruang tujuan. Cakupan Semua berlaku untuk seluruh anggota Kegiatan, bukan hanya halaman/filter yang terlihat.</p>
            <div class="row g-2 align-items-end">
                <div class="col-md-3"><label class="cbt-form-label" for="pesertaUjianRuangScope">Cakupan</label><select class="form-select" id="pesertaUjianRuangScope"><option value="IDS">Anggota terpilih di halaman ini</option><option value="ROMBEL">Rombel</option><option value="TINGKAT">Tingkat</option><option value="ALL">Semua anggota Kegiatan</option></select></div>
                <div class="col-md-2" id="pesertaUjianRuangRombelWrap" hidden><label class="cbt-form-label" for="pesertaUjianRuangRombel">Rombel</label><select class="form-select" id="pesertaUjianRuangRombel"></select></div>
                <div class="col-md-2" id="pesertaUjianRuangTingkatWrap" hidden><label class="cbt-form-label" for="pesertaUjianRuangTingkat">Tingkat</label><select class="form-select" id="pesertaUjianRuangTingkat"><option value="7">7</option><option value="8">8</option><option value="9">9</option></select></div>
                <div class="col-md-3"><label class="cbt-form-label" for="pesertaUjianRuangTarget">Ruang tujuan</label><select class="form-select" id="pesertaUjianRuangTarget"><option value="">Tanpa Ruang</option></select></div>
                <div class="col-md-2"><button class="btn btn-cbt-primary" type="button" id="pesertaUjianRuangAssign">Terapkan Ruang</button></div>
            </div>
            <div id="pesertaUjianRuangFeedback" class="cbt-inline-feedback mt-2" role="status" aria-live="polite"></div>
        </div>
    </section>
    <section class="manager-section-card mb-3" id="pesertaUjianNomorCard" hidden>
        <div class="card-header"><div class="fw-bold">Nomor Peserta</div><span class="small text-secondary" id="pesertaUjianNomorCount"></span></div>
        <div class="card-body">
            <p class="small text-secondary">Nomor berlaku untuk Kegiatan ini, bukan username login. Urutan tetap berdasarkan rombel, nama, lalu ID anggota. Contoh: <strong>UJIAN26-0001</strong>.</p>
            <div class="row g-2 align-items-end">
                <div class="col-sm-6 col-lg-2"><label class="cbt-form-label" for="pesertaUjianNomorPrefix">Prefix</label><input class="form-control text-uppercase" id="pesertaUjianNomorPrefix" maxlength="20" pattern="[A-Za-z0-9]([A-Za-z0-9-]{0,18}[A-Za-z0-9])?" placeholder="UJIAN26" autocomplete="off"></div>
                <div class="col-6 col-lg-2"><label class="cbt-form-label" for="pesertaUjianNomorStart">Nomor awal</label><input class="form-control" type="number" id="pesertaUjianNomorStart" min="1" max="99999999" value="1"></div>
                <div class="col-6 col-lg-2"><label class="cbt-form-label" for="pesertaUjianNomorMode">Tindakan</label><select class="form-select" id="pesertaUjianNomorMode"><option value="FILL_EMPTY">Isi yang kosong</option><option value="REGENERATE">Regenerate cakupan</option></select></div>
                <div class="col-sm-6 col-lg-2"><label class="cbt-form-label" for="pesertaUjianNomorScope">Cakupan</label><select class="form-select" id="pesertaUjianNomorScope"><option value="ALL">Semua anggota</option><option value="ROMBEL">Rombel</option><option value="TINGKAT">Tingkat</option><option value="IDS">Anggota terpilih</option></select></div>
                <div class="col-6 col-lg-2" id="pesertaUjianNomorRombelWrap" hidden><label class="cbt-form-label" for="pesertaUjianNomorRombel">Rombel</label><select class="form-select" id="pesertaUjianNomorRombel"></select></div>
                <div class="col-6 col-lg-2" id="pesertaUjianNomorTingkatWrap" hidden><label class="cbt-form-label" for="pesertaUjianNomorTingkat">Tingkat</label><select class="form-select" id="pesertaUjianNomorTingkat"><option>7</option><option>8</option><option>9</option></select></div>
                <div class="col-12 col-lg-2"><button class="btn btn-cbt-primary w-100" type="button" id="pesertaUjianNomorGenerate">Jalankan</button></div>
            </div>
            <div id="pesertaUjianNomorFeedback" class="cbt-inline-feedback mt-2" role="status" aria-live="polite"></div>
        </div>
    </section>
    <section class="manager-section-card">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div class="fw-bold">Anggota Kegiatan</div>
            <button class="btn btn-outline-danger btn-sm" type="button" id="pesertaUjianBulkRemove" disabled hidden>Hapus Terpilih (0)</button>
        </div>
        <div class="card-body"><div class="row g-2 align-items-end"><div class="col-md-6"><label class="cbt-form-label" for="pesertaUjianSearch">Cari NISN / Nama / Rombel</label><input class="form-control" id="pesertaUjianSearch"></div><div class="col-md-3"><label class="cbt-form-label" for="pesertaUjianPageSize">Per halaman</label><select class="form-select" id="pesertaUjianPageSize"><option>25</option><option>50</option><option>100</option></select></div></div><div id="pesertaUjianFeedback" class="cbt-inline-feedback mt-2" role="status" aria-live="polite"></div></div>
        <div class="table-responsive"><table class="table manager-table mb-0"><thead><tr><th><input class="form-check-input" type="checkbox" id="pesertaUjianMemberSelectAll" aria-label="Pilih semua anggota pada halaman ini"></th><th>NISN</th><th>Nama</th><th>JK</th><th>Rombel saat Penugasan</th><th>Asal</th><th>Nomor Peserta</th><th>Ruang</th><th>Aksi</th></tr></thead><tbody id="pesertaUjianRows"></tbody></table></div>
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2"><span id="pesertaUjianCount" class="small text-secondary"></span><div class="d-flex align-items-center gap-2"><button class="btn btn-outline-secondary btn-sm" type="button" id="pesertaUjianPrevious">Sebelumnya</button><span id="pesertaUjianPageInfo" class="small"></span><button class="btn btn-outline-secondary btn-sm" type="button" id="pesertaUjianNext">Berikutnya</button></div></div>
    </section>
</div>
<?= $this->endSection() ?>
<?= $this->section('pageScripts') ?>
<script src="<?= base_url('assets/js/manager-peserta-ujian.js') ?>" defer></script>
<?= $this->endSection() ?>
