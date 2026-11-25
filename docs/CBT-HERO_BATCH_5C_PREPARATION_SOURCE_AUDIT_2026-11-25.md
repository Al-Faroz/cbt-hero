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
