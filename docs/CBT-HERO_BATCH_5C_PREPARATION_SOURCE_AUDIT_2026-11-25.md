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
