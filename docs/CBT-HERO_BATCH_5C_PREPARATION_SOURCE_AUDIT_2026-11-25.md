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
