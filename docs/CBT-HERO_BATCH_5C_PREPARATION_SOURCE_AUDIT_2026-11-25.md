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
