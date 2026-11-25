# CBT-HERO — Batch 5C Preparation Source Audit

Tanggal: 2026-11-25  
Branch: `feat/batch-5c-schedule-susulan-preparation`  
Status: source audit berjalan; acceptance P01–P22 tetap DEFERRED.

## Temuan PREP-FP-01 — fingerprint tidak memasukkan struktur Jadwal

`PreparationService::scheduleFingerprint()` sebelumnya hanya memasukkan jadwal ID, Bank, versi/fingerprint Bank, selection, dan konfigurasi Bank. Perubahan struktural Jadwal sebelum START (waktu, batas mulai, durasi, urutan, akses, visibilitas hasil, atau penggantian konteks) tidak mengubah fingerprint. Akibatnya assignment READY dapat tetap dianggap valid walaupun Jadwal sudah diubah, bertentangan dengan acceptance P10 dan J11.

Perbaikan:
- Fingerprint sekarang memasukkan identitas dan seluruh field struktur/operasional Jadwal yang relevan: Kegiatan, Bank, jenis/parent Jadwal, urutan, Mulai, Batas Mulai, durasi, access_state, dan visibilitas nilai.
- Fingerprint peserta sekarang juga memasukkan `peserta_id` selain `peserta_kegiatan_id`, target mode, dan supersede Attempt.
- Formula yang sama diterapkan konsisten pada build, assignment status, dan pemeriksaan READY sebelum START.

File: `app/Services/PreparationService.php`  
Commit: `e5b6b29b997f39e01002a41d7197d2b7ea7bccd4`

Belum ada PHPUnit/database test; perubahan ini belum dinyatakan PASS.

## Scope audit berikutnya

1. Audit Susulan #1..N, eligibility FIRST_ATTEMPT/REPLACEMENT, pending target, cancellation, idempotency.
2. Audit operational commands: access gate, extend start window, result visibility lock, add time.
3. Audit Preparation: target MAIN/SUSULAN, chunking/resume, selective rebuild, deterministic selection/order, matching aliases, media manifest, preflight, detail authorization.
4. Sinkronkan dengan Master Document, API contract, ERD, dan acceptance.


## Temuan OP-ADD-TIME-01 — Tambah Waktu harus mengunci Attempt yang dipilih

Sebelumnya `JadwalOperationalService::addTime()` membaca Attempt ACTIVE tanpa `FOR UPDATE`, lalu menjalankan UPDATE bersyarat status ACTIVE. Jika Attempt selesai di antara SELECT dan UPDATE, request dapat tetap melaporkan ID tersebut sebagai affected walaupun UPDATE tidak mengubah baris.

Perbaikan:
- Daftar ID pada scope IDS divalidasi ketat; ID invalid tidak lagi dibuang diam-diam.
- Attempt dipilih dengan `SELECT ... FOR UPDATE` dalam transaksi.
- Update tetap mensyaratkan status ACTIVE dan jumlah baris terdampak harus tepat satu; perubahan status di tengah operasi menggagalkan transaksi.
- Audit Log hanya ditulis setelah seluruh update berhasil.

File: `app/Services/JadwalOperationalService.php`  
Commit: `05927a156afb530812afd89ee59467b367727ea9`

Belum diuji dengan konkurensi database; tidak ada klaim PASS.


## Temuan SUSULAN-ELIG-01 — eligibility target harus sesuai tingkat Bank dan bebas Attempt ACTIVE global

Audit menemukan dua celah pada kandidat/validasi Susulan:
- Kandidat hanya difilter berdasarkan Kegiatan dan status aktif, bukan tingkat peserta terhadap tingkat Bank Jadwal MAIN. Target salah tingkat dapat tersimpan, lalu baru ditolak saat START.
- Pemeriksaan Attempt sebelumnya hanya melihat riwayat pada root Jadwal tersebut. Peserta yang sedang mempunyai Attempt ACTIVE pada Jadwal lain masih dapat dipilih sebagai target, padahal aturan Susulan melarang target dengan Attempt aktif.

Perbaikan:
- Daftar kandidat dibatasi ke tingkat Bank MAIN.
- Validasi server mengulang pemeriksaan tingkat melalui relasi Rombel, sehingga request manual tidak dapat melewati filter UI.
- Kandidat dan validasi target memeriksa Attempt ACTIVE peserta secara global.
- Preparation Susulan juga hanya menghasilkan assignment untuk target aktif yang masih sesuai tingkat Bank.

File:
- `app/Services/SusulanService.php`
- `app/Services/PreparationService.php`

Commit:
- `7aa3832ebd1558a8061e12aef76c35e98172b63d`
- `c46bce1e7dabcf778fc94011126677e259e53668`

Catatan: perubahan tingkat peserta setelah target dibuat akan membuat peserta tidak masuk target Preparation; operator perlu memperbaiki target sebelum persiapan. Concurrency antara START MAIN dan pembuatan target Susulan masih diaudit.


## Temuan SUSULAN-OPS-01 — tombol BUKA/TAHAN Susulan mengarah ke handler MAIN

UI `manager-susulan.js` sudah menampilkan tombol Buka/Tahan dan mengirim PATCH ke `/manager/api/jadwal/{id}/access`, tetapi controller selalu memanggil `JadwalService::changeAccess()`, yang hanya menerima jenis MAIN. Akibatnya aksi akses pada Susulan ditolak 404 meskipun tombol tersedia.

Perbaikan:
- `SusulanService::changeAccess()` mengunci row Susulan, memvalidasi jenis dan parent, menolak perubahan setelah hasil final, memperbarui gate akses, dan mencatat audit.
- `JadwalController::access()` sekarang mengarahkan ID Susulan ke handler Susulan, sedangkan MAIN tetap memakai `JadwalService`.
- Toggle akses tidak diblok hanya karena Preparation/Attempt sudah ada; ia adalah gate operasional dan tidak mengubah timer Attempt aktif.

File:
- `app/Services/SusulanService.php`
- `app/Controllers/Api/Manager/Schedule/JadwalController.php`

Commit:
- `1529b1063e6ed0500d53185c5021a0f20c944584`
- `f8deb51c3330cc094c855668e474319b462d9ab3`

Belum diuji melalui browser/API.


## Temuan ORDER-01 — Attempt Susulan FIRST_ATTEMPT tidak dihitung pada gate urutan

`JadwalOrderService::blockerForStart()` sebelumnya mencari Attempt pada `jadwal_id` MAIN secara langsung. Peserta yang belum pernah START MAIN tetapi menyelesaikan Susulan FIRST_ATTEMPT tetap terlihat belum menyelesaikan jadwal urutan lebih kecil, sehingga urutan berikutnya dapat terus terblokir.

Perbaikan: lookup Attempt menggunakan `root_jadwal_id` MAIN, sehingga histori MAIN dan seluruh Susulan pada root yang sama menjadi satu rangkaian untuk gate urutan. Status latest Attempt tetap harus FINISHED/SUPERSEDED agar gate lolos.

File: `app/Services/JadwalOrderService.php`  
Commit: `73567e37c9fa2e632a0bf5c506b71f57ed4adb9b`

Belum diuji melalui acceptance J17–J20.


## Temuan PREP-INPUT-01 — scope SELECTED tidak boleh membuang ID invalid diam-diam

Sebelumnya ID peserta pada scope SELECTED dikonversi dengan cast integer lalu nilai 0 dibuang. Payload campuran seperti `[123, "abc"]` dapat diterima sebagai peserta 123 tanpa memberi tahu operator bahwa sebagian ID request salah. Flag `force_rebuild` juga dikonversi ke boolean, sehingga string non-kosong dapat menjadi TRUE.

Perbaikan:
- Setiap ID peserta wajib lolos validasi integer positif; satu ID invalid menolak seluruh request.
- Duplikasi ID tetap dinormalisasi satu kali.
- `force_rebuild` wajib JSON boolean, bukan string/angka.
- Scope dan opsi diperiksa sebelum transaksi dimulai.

File: `app/Services/PreparationService.php`  
Commit: `75b1721425e11618e8d35dd1868b5f58d61962d6`


## Temuan PREP-REBUILD-01 — force_rebuild tidak memaksa rebuild dan dapat menyentuh assignment terpakai

Sebelumnya `prepare()` memasukkan semua target ke queue saat `force_rebuild=true`, tetapi `buildAssignment()` tetap return untuk assignment READY dengan fingerprint sama. Jadi flag force tidak bekerja. Sebaliknya, assignment yang sudah dipakai tidak boleh diganti karena Attempt aktif/selesai harus tetap menunjuk assignment yang digunakan.

Perbaikan:
- Assignment READY dengan `used_at` tidak pernah dibangun ulang dan tetap dihitung READY untuk histori peserta.
- Assignment READY yang belum dipakai dapat dibangun ulang saat `force_rebuild=true`, sekalipun fingerprint masih sama.
- Batas 100 berlaku pada jumlah assignment belum terpakai yang benar-benar masuk queue, bukan jumlah seluruh target.
- Force rebuild tetap tidak mengubah assignment yang sudah digunakan.

File: `app/Services/PreparationService.php`  
Commit: `2098e5c4f831f89c90f4b342dc04362267a63a55`

Belum diuji dengan DB fixture/Attempt aktual.


## Temuan PREP-DETAIL-01 — API detail ada, tetapi operator belum mempunyai UI inspeksi assignment

Sebelumnya API `prepared-assignments` dan `prepared-assignments/{id}` tersedia, tetapi modal Preparation hanya menampilkan ringkasan progress. Acceptance P21 meminta operator dapat memeriksa item, revisi, dan media.

Perbaikan:
- Daftar Prepared Assignment kini mendukung pagination 25/50/100 dan pencarian nomor peserta/nama/rombel.
- Detail assignment menyertakan item, question/revision ID, konten soal/stimulus, metadata, mapping Menjodohkan, serta manifest media dengan URL preview manager.
- UI modal Preparation menampilkan daftar assignment dan panel detail yang merender konten/media; answer key tidak ditambahkan ke payload detail.
- Shared Question Renderer dan KaTeX dimuat pada halaman Jadwal untuk preview konten.

File:
- `app/Services/PreparationService.php`
- `app/Controllers/Api/Manager/Schedule/PreparationController.php`
- `app/Views/manager/schedule/_preparation_modal.php`
- `app/Views/manager/schedule/jadwal.php`
- `assets/js/manager-preparation.js`

Commit:
- `3e58dadbff381049893376ba3f90cd6c2f5c881f`
- `2977a9c98a1059ad95c3bf74ba2c82fffbc5b3f0`
- `3529f84ac286cc82286a6856f4a1d079579897a1`
- `0ee6757c770d094e5a6fadff3aec02561c450b83`
- `a3769a3046bb815834b6a7444f2481edd969e85e`
- `83a92f71456f7494f9a6c0c4db4720482dd2e928`

Belum diuji melalui browser; X/P acceptance tetap DEFERRED.


## Temuan SUSULAN-CONCURRENCY-01 — target FIRST_ATTEMPT dapat race dengan START MAIN

Pemeriksaan eligibility sebelumnya membaca riwayat Attempt tanpa mengunci row peserta. START Akademik mengunci row `peserta` lebih dahulu, lalu Jadwal dan membership. Tanpa lock yang searah, Susulan FIRST_ATTEMPT dapat dibuat bersamaan dengan START MAIN dan menyimpan target berdasarkan snapshot riwayat yang sudah kedaluwarsa.

Perbaikan:
- Create/Update Susulan membaca konteks awal, lalu mengunci row peserta target secara berurutan sebelum mengunci Jadwal.
- Setelah Jadwal terkunci, membership `peserta_kegiatan` target juga dikunci sebelum validasi eligibility.
- Urutan lock mengikuti pola START (peserta → Jadwal → membership), sehingga validasi riwayat/Attempt aktif terserialisasi dengan proses START.
- Validasi target tetap dilakukan ulang di server setelah seluruh lock diperoleh.

File: `app/Services/SusulanService.php`  
Commit: `e050414177a8e066960ba634642831bbee04df7d`

Belum diuji dengan dua transaksi START/CREATE paralel; concurrency acceptance tetap DEFERRED.


Renderer/KaTeX juga dimuat pada halaman Susulan, karena modal Preparation yang sama digunakan dari Jadwal MAIN maupun Susulan.

Commit: `ed56499ab211e00340a2ed99be8c98db4ff3a3cd`


### PREP-TARGET-02 — verifikasi ulang Kegiatan pada target Susulan

Query target Susulan pada Preparation sekarang memastikan membership peserta masih terhubung ke Kegiatan sumber MAIN, selain status aktif dan tingkat Bank. Ini merupakan defensive check terhadap perubahan relasi membership setelah target dibuat.

Commit: `d8bb41113c0b6c31d9ac0a82bd83aa703642b070`


## Pemeriksaan keamanan PREP-DETAIL-02 — detail manager terpisah dari payload peserta

Audit silang terhadap `QuestionRuntimeItemService`:
- API peserta menerima alias acak untuk sisi Matching, bukan `mapping_json` asli atau pasangan jawaban.
- `QuestionRuntimeItemService::build()` hanya mengirim key alias dan konten sisi kiri/kanan.
- `PreparationService::detail()` adalah endpoint manager; ia dapat menampilkan mapping internal untuk troubleshooting, tetapi tidak menambahkan `is_correct`, correct_key, accepted answers, atau rubric answer key ke payload peserta.
- Route detail berada di grup manager API dengan filter role manager.

Tidak ada perubahan runtime peserta pada temuan ini. Ini merupakan hasil source audit, belum diuji melalui HTTP response fixture.


### OP-ADD-TIME-02 — Attempt ACTIVE tanpa deadline tidak boleh menerima sukses semu

Sebelum UPDATE, Tambah Waktu sekarang menolak Attempt ACTIVE yang `deadline_at`-nya kosong. Ini mencegah `added_seconds` berubah sementara `DATE_ADD(NULL,...)` tetap NULL dan API melaporkan sukses tanpa memperpanjang deadline.

Commit: `37af008c5b39ee6d0804c5b1ae237115686c298a`


## Temuan PREP-SEED-01 — fingerprint staleness tidak boleh mengubah pilihan soal untuk perubahan non-konten

Fingerprint lengkap kini memang membuat assignment STALE saat struktur Jadwal berubah (P10), tetapi jika fingerprint yang sama dipakai sebagai seed shuffle, perubahan jam/akses/visibilitas juga mengubah selection/urutan soal ketika dibangun ulang. Itu bukan sumber konten soal.

Perbaikan:
- Fingerprint assignment tetap mencakup struktur Jadwal lengkap untuk deteksi STALE.
- Seed deterministik selection/order dipisahkan dan hanya mencakup Jadwal/root, Bank version/fingerprint, selection/config, identitas peserta, serta mode/Attempt replacement.
- Perubahan waktu, access_state, dan visibilitas hasil tetap mewajibkan rebuild, tetapi tidak mengubah selection soal jika sumber kontennya sama.

File: `app/Services/PreparationService.php`  
Commit:
- `e86ab74d0d09a8f98f7a357461e3db6954517aab`
- `42e513d96bc8b5804c6350700e375d0d92319d25`

Belum diuji dengan fixture deterministic selection.


### PREP-SEED-02 — urutan konfigurasi harus canonical sebelum hashing

Seed selection sekarang mengurutkan key array `config` dan `selection` sebelum JSON encoding. Ini menghindari seed berubah hanya karena urutan baris query database berbeda, bukan karena isi konfigurasi berubah.

Commit: `62143150aa5032a9ca12deeeb99892c33a6ed809`


## Temuan PREP-MEDIA-ORDER-01 — urutan media harus stabil ketika sort_order sama

Media manifest Preparation dan media per item runtime kini memakai `id` relasi sebagai tie-breaker setelah `sort_order`. Ini menghindari urutan media yang berubah-ubah jika beberapa relasi memiliki sort_order sama.

File:
- `app/Services/PreparationService.php`
- `app/Services/QuestionRuntimeItemService.php`

Commit:
- `3054f6428db50f84dc871274b8dd3fabb1c0e16e`
- `400e43d5b2ccfcbd99fdbbb69d464f5da34ca0da`
- `c234040784876067c068bcae271628b0fa83b545`


## Pemetaan source terhadap acceptance P01–P22

Pemetaan berikut adalah hasil audit source, bukan hasil menjalankan acceptance.

| ID | Area source |
|---|---|
| P01 | `PreparationService::targets()` + `status()`; MAIN dan SUSULAN dibatasi ke membership aktif/tingkat Bank. |
| P02 | `CHUNK_SIZE=100`, queue dan `has_more`, loop UI berurutan dengan Idempotency-Key unik per chunk. |
| P03–P04 | `buildPlan()` membaca `jadwal_type_selection`; `buildAssignment()` memilih tepat selection per tipe. |
| P05–P06 | sort_order Bank atau hash seed deterministik per peserta; tidak memakai `ORDER BY RAND()`. |
| P07 | `option_order_json` menyimpan urutan key opsi. |
| P08 | `mapping_json` menyimpan order dan alias acak; payload participant hanya menerima alias. |
| P09 | lifecycle Bank/Jadwal mengunci edit Bank setelah dependency Jadwal. |
| P10 | `scheduleFingerprint()` mencakup field struktur Jadwal, Bank, selection, config; assignment lama terbaca STALE. |
| P11 | rebuild-only queue hanya STALE/FAILED; force rebuild hanya assignment belum terpakai. |
| P12 | `prepared_assignment_item.soal_revision_id` menyimpan baseline revision. |
| P13 | `prepared_assignment_media` dedupe asset, critical image, prefetch order; runtime memakai manifest. |
| P14–P15 | `jadwal_operations` + payload hash + unique Idempotency-Key. |
| P16 | queue menghitung ulang state assignment; READY yang masih valid dilewati pada run berikutnya. |
| P17–P18 | `participantPreflight()` memeriksa Nomor Peserta, username, password hash, credential_status. |
| P19–P20 | target SUSULAN TARGETED; fingerprint participant mencakup target_mode dan supersede Attempt. |
| P21 | API dan UI detail Prepared Assignment; runtime participant tetap hanya menerima alias Matching, bukan mapping asli. |
| P22 | AuditService mencatat PREPARE/REBUILD per chunk dan jumlah assignment. |

## Gap yang masih perlu ditutup sebelum Batch 5C dinyatakan selesai

1. Jika peserta yang sudah ditargetkan sebagai FIRST_ATTEMPT kemudian memulai Jadwal MAIN sebelum Preparation Susulan, assignment Susulan dapat tetap dibangun dari target lama. START Susulan tetap menolak melalui `FIRST_ATTEMPT_ALREADY_USED`, tetapi operator perlu membatalkan target secara manual. Perlu keputusan apakah target otomatis dibatalkan saat START MAIN atau Preparation menandainya sebagai invalid.
2. Concurrency lock Create/Update Susulan sudah diselaraskan dengan urutan START (peserta → Jadwal → membership), tetapi harus diuji dengan transaksi paralel pada tahap acceptance.
3. Seluruh J01–Jxx, S01–Sxx, P01–P22 tetap DEFERRED; PHP lint, PHPUnit, API/DB concurrency, dan browser belum dijalankan.


## Pemetaan source terhadap acceptance J01–J21 dan S01–S24

| Rentang | Area source yang diaudit |
|---|---|
| J01–J10 | `JadwalService::list/options/save/normalizeSelection`, API controller, dan UI Jadwal. |
| J11 | `JadwalService::save()` melakukan update jadwal + selection dalam satu transaksi; fingerprint Preparation mencakup field struktur Jadwal. |
| J12 | `JadwalService::changeAccess()` dan AuditService. |
| J13–J16 | `JadwalService::delete()`, dependency check, lock first START/finalisasi, serta lifecycle Bank. |
| J17–J20 | `JadwalOrderService::blockerForStart()` memakai slot yang sama, tingkat peserta, target Susulan, dan histori Attempt root. |
| J21 | UI Jadwal + CSS manager responsive/table wrapper dan ringkasan selection. |
| S01–S06 | `SusulanService::list/candidates/create/validateTargets`; child langsung MAIN, FIRST_ATTEMPT/REPLACEMENT, copy selection. |
| S07–S08 | Kandidat/server menolak Attempt ACTIVE global dan pending target; create/update mengunci peserta dan Jadwal. |
| S09–S11 | Selection disalin dari MAIN; operasi create memakai payload hash + Idempotency-Key. |
| S12–S14 | Update struktural hanya sebelum Preparation/START; cancel target mengubah status CANCELED dan menolak target yang sudah START. |
| S15 | Handler BUKA/TAHAN Susulan terpisah dari handler MAIN; timer Attempt tidak diubah. |
| S16–S17 | Extend Start Window hanya menerima batas baru yang lebih akhir. |
| S18–S19 | Result visibility hanya sebelum first START. |
| S20–S21 | Add Time mengunci Attempt ACTIVE, memperbarui added_seconds/deadline, menolak tanpa Attempt aktif. |
| S22 | AuditService untuk create/update/cancel/access/extend/result visibility/add time. |
| S23–S24 | Wrapper tabel modal dengan overflow-x lokal, touch-action, dan tabindex/role region. |

Pemetaan ini adalah audit source, bukan hasil acceptance. Seluruh J01–J21, S01–S24, dan P01–P22 tetap DEFERRED.


## Temuan PREP-SUSULAN-INVALID-01 — target Susulan dapat kedaluwarsa setelah dibuat

Gap yang sebelumnya tercatat: peserta yang dibuat sebagai FIRST_ATTEMPT dapat kemudian START MAIN sebelum Preparation Susulan. Target tetap berstatus TARGETED, sehingga operator bisa melihat target itu sebagai bagian dari daftar persiapan meskipun START Susulan nantinya ditolak oleh AttemptStartService.

Keputusan implementasi:
- Tidak mengubah status target secara otomatis. Pembatalan target harus tetap menjadi tindakan eksplisit operator dan tercatat dalam audit.
- Preparation kini menghitung target Susulan TARGETED yang sudah tidak valid: membership/Kegiatan/rombel tidak lagi sesuai, peserta tidak aktif, peserta mempunyai Attempt ACTIVE, FIRST_ATTEMPT sudah mempunyai histori Attempt root, atau REPLACEMENT tidak lagi menunjuk Attempt FINISHED terbaru.
- Target invalid tidak dibuatkan Prepared Assignment dan tidak dapat membuat status Jadwal `can_start=true`.
- Status Preparation menampilkan jumlah target invalid dan instruksi untuk membatalkan/memperbaiki target dari halaman Susulan.
- Tombol Siapkan Semua hanya aktif ketika masih ada assignment MISSING/STALE/FAILED; target invalid tidak menyebabkan request Preparation kosong berulang.

File:
- `app/Services/PreparationService.php`
- `assets/js/manager-preparation.js`

Commits:
- `afbf81472b6347d4906e5979dfbf20ef1136e4a2`
- `1625c8c8a855d1f58a4fb99f851d9d4834e53607`
- `252359e4687b61a53b8d41ae8084fbcd4d3e9aea`

Belum diuji dengan fixture Attempt MAIN/Susulan atau UI browser. Acceptance P19–P20 dan skenario target invalid tetap DEFERRED.


### Penyempurnaan PREP-SUSULAN-INVALID-01 — invalid target tidak ikut antrean

Penyempurnaan setelah audit implementasi:
- Daftar target valid untuk Preparation dipisahkan dari target invalid.
- Summary Preparation menghitung assignment hanya terhadap target valid; jumlah invalid ditampilkan terpisah.
- `prepare(scope=ALL)` tidak membuat assignment untuk target invalid.
- `prepare(scope=SELECTED)` menolak request jika pilihan mencakup target invalid.
- Jika semua target invalid, API mengembalikan `INVALID_SUSULAN_TARGET` dengan instruksi pembatalan/perbaikan; tidak ada assignment baru yang dibuat.
- START tetap mempunyai validasi finalnya sendiri, jadi Preparation bukan pengganti pemeriksaan AttemptStartService.

Commit: `4f0cb3f29c0cece62a84dc8be584bb249cba1c9c`

Masih source-level; belum diuji dengan database fixture.


Progress bar Preparation sekarang menggunakan denominator seluruh target aktif (target valid + invalid), sedangkan kartu jumlah target diberi label "Target Valid". Dengan demikian progress tidak menampilkan 100% ketika masih ada target Susulan invalid yang wajib ditangani operator.

Commits:
- `2bff78504b24024f25266056ba892bfa991369c4`
- `7d11e6220f098544d47622384c01864dca303720`


## Sinkronisasi Master Dokumen

Keputusan validasi ulang target Susulan sebelum Preparation telah disinkronkan ke:
- `docs/CBT-HERO_DOKUMEN_ACUAN_UTAMA.md`, bagian 17.4.
- `docs/CBT-HERO_PHASE_5C_PREPARATION_ACCEPTANCE.md`, acceptance tambahan P23–P27.

Commit:
- Master document: `9bf44a97f74b24125cb38f4f3c6705e49610a153`
- Acceptance: `076b564bbb708d94fb9aed99cf57f027e1052fb9`

Seluruh acceptance tetap DEFERRED sampai tahap uji integrasi Master Ujian Akademis.


## Temuan OP-ADD-TIME-IDEMPOTENCY-01 — retry Tambah Waktu dapat menggandakan tambahan

Sebelumnya endpoint Tambah Waktu tidak memakai Idempotency-Key. Jika server sudah commit tetapi respons hilang, Operator mengulang submit dan deadline Attempt dapat bertambah dua kali.

Perbaikan:
- Endpoint mewajibkan Idempotency-Key dengan format yang tervalidasi.
- Payload hash mengikat Jadwal, detik tambahan, scope, dan daftar Attempt yang dinormalisasi.
- Operasi memakai `jadwal_operations` dengan action `ADD_TIME_JADWAL`, root Jadwal, result Jadwal, dan status COMPLETED dalam transaksi yang sama dengan perubahan Attempt.
- Retry dengan key/payload sama mengembalikan replay tanpa menambah deadline lagi; key sama dengan payload berbeda ditolak IDEMPOTENCY_CONFLICT.
- UI menyimpan key selama submit yang sama dan menggantinya bila nilai input berubah.

File:
- `app/Services/JadwalOperationalService.php`
- `app/Controllers/Api/Manager/Schedule/JadwalController.php`
- `assets/js/manager-jadwal.js`

Commits:
- `10b10b1d28cc9e240cccf112a656fc3fc4640424`
- `7d45cbf17bfbb911373d7b7049cca2d4ae17d163`
- `01dce93f4372419cc2869d0a5af70f77924b54e4`
- `66e2c74772e4b1590e6552790488feee20be2482`

Acceptance S25–S26 ditambahkan. Belum diuji dengan retry API/DB; tetap DEFERRED.


### OP-ADD-TIME-IDEMPOTENCY-02 — halaman Susulan juga harus mengirim key

Setelah endpoint Tambah Waktu diwajibkan memakai Idempotency-Key, audit menemukan halaman Susulan masih mengirim request tanpa header tersebut. UI Jadwal MAIN sudah diperbaiki, dan halaman Susulan kini juga:
- membuat key baru saat modal Tambah Waktu dibuka;
- mempertahankan key selama retry request yang sama;
- mengganti key jika nilai tambahan berubah;
- menampilkan pesan khusus jika server mengembalikan replay.

File: `assets/js/manager-susulan.js`  
Commit: `140a575e7daae625791c7e776644a0d1fbc79e82`

Acceptance S25 berlaku untuk Tambah Waktu MAIN maupun Susulan. Belum diuji melalui browser/API.
