# CBT-HERO — Batch FG-1/FG-2 Fullscreen Guard

Tanggal: 25 November 2026  
Branch: `feat/client-fullscreen-guard-psychology`  
Status: **IMPLEMENTASI SOURCE AWAL — BELUM FUNCTIONAL ACCEPTANCE**

## Ruang lingkup yang sudah ditambahkan

### Server-side
- Endpoint participant `POST /api/attempt/{id}/fullscreen-pause`.
- Endpoint participant `POST /api/attempt/{id}/fullscreen-resume`.
- Ownership, client UUID, client generation, jadwal BUKA, status Attempt ACTIVE, dan Exam Browser required diperiksa sebelum pause.
- Pause dicatat di `attempt.pause_started_at` dan `attempt_pause_event.reason = FULLSCREEN_EXIT`.
- Event `FULLSCREEN_EXIT` dan `FULLSCREEN_RESTORED` dicatat di `attempt_client_event`.
- Operasi memakai `participant_operations` untuk idempotency.
- Deadline dan `paused_seconds` disesuaikan saat resume; jawaban tidak dihapus.
- Resume umum menolak pause dengan reason `FULLSCREEN_EXIT`; pemulihan harus memakai endpoint khusus.
- Resume umum kini selalu memvalidasi kecocokan client UUID, termasuk saat Attempt sedang paused.
- Bootstrap mengirim `pause_reason` agar client membedakan jeda Fullscreen Guard dari jeda pengawas.

### Client-side
- Workspace browser biasa mendeteksi `fullscreenchange`.
- Saat fullscreen keluar, workspace dikunci dan permintaan pause dikirim ke server.
- Tombol pemulihan meminta user gesture untuk masuk fullscreen, kemudian memanggil endpoint resume khusus.
- Tombol pemulihan tidak aktif sebelum server mengonfirmasi pause tersimpan.
- Timer client berhenti ketika runtime paused dan diperbarui dengan deadline baru setelah resume.
- Jika Attempt dijeda pengawas, client tidak menampilkan tombol pemulihan fullscreen.
- Kegiatan dengan Exam Browser required tidak mengaktifkan guard browser biasa.
- Pada kegiatan Exam Browser optional, guard berlaku untuk client yang tidak memakai mode wajib; Exambro yang berjalan fullscreen tetap dapat dipakai.

## Database
Tidak ada tabel baru pada batch ini. Implementasi memakai struktur yang sudah ada:
- `attempt.pause_started_at`
- `attempt.paused_seconds`
- `attempt_pause_event.reason`
- `attempt_client_event.event_type/metadata_json`
- `participant_operations`

## Batasan yang wajib diketahui
1. Fullscreen API merupakan sinyal client. Browser biasa tidak dapat membuktikan ke server bahwa fullscreen benar-benar aktif; pengguna mahir dapat memodifikasi JavaScript atau memanggil API secara manual. Ini bukan pengganti kiosk lockdown Exambro.
2. Saat koneksi terputus tepat ketika fullscreen keluar, server baru dapat memproses pause ketika request diterima. Timer lokal dikunci, tetapi pembekuan deadline server tidak dapat dijamin sebelum server menerima event. Penanganan offline/reconciliation perlu ditinjau pada batch berikutnya.
3. Kontrak proof Exambro pada kode existing masih berupa pemeriksaan keberadaan string pada START/RESUME; validasi kriptografis/attestation Exambro belum menjadi bagian batch ini dan harus diaudit terpisah sebelum klaim "Exambro tervalidasi".
4. Guard diaktifkan pada kegiatan dengan `Exam Browser required = OFF`; jika Exambro optional digunakan, Exambro juga berada di lingkungan fullscreen guard. Ini menghindari bypass guard dengan mengirim string proof client yang tidak tervalidasi.

## Pemeriksaan yang belum dilakukan
- Belum menjalankan PHP lint, PHPUnit, browser test, database integration, maupun simulasi perangkat.
- Belum acceptance fungsional. Tidak ada klaim PASS.
- Monitoring operator untuk jumlah/riwayat event fullscreen belum diintegrasikan; itu masuk FG-3.
- Pengujian terhadap Ujian Psikologi belum dilakukan; shared Attempt core akan dipakai saat Phase 10 diimplementasikan.

## Checklist review sebelum merge
- [ ] PHP lint seluruh service/controller/route yang berubah.
- [ ] Uji idempotency pause/resume, termasuk retry request.
- [ ] Uji jeda operator tidak tertukar dengan FULLSCREEN_EXIT.
- [ ] Uji jawaban tidak dapat sync dan Attempt tidak dapat finalize saat paused.
- [ ] Uji deadline freeze dan penambahan paused_seconds.
- [ ] Uji client mismatch/generation mismatch.
- [ ] Uji browser tanpa Fullscreen API.
- [ ] Uji offline saat fullscreen keluar dan dokumentasikan perilaku waktu.
- [ ] Integrasi FG-3 monitoring operator.
