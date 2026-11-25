# CBT-HERO — FG-4 / FG-5 Trust Boundary & Offline Policy

Tanggal: 25 November 2026  
Branch: `feat/client-fullscreen-guard-psychology`  
Status: **AUDIT + SOURCE HARDENING — BELUM FUNCTIONAL ACCEPTANCE**

> Keputusan platform terbaru: Exambro adalah APK Android resmi yang akan dikembangkan sendiri sebagai bagian dari CBT-HERO. Desktop/laptop tetap menggunakan browser dan Fullscreen Guard. Lihat [Arsitektur Resmi Exambro Android & Desktop Fullscreen Guard](CBT-HERO_EXAMBRO_ANDROID_ARCHITECTURE_2026-11-25.md).

## FG-4 — Audit trust boundary Exambro

### Temuan source saat audit
- Alur `AttemptStartService` dan `AttemptResumeService` yang diaudit sebelumnya hanya memastikan `exam_browser_proof` berupa string tidak kosong untuk mode required.
- Belum ada bukti bahwa proof tersebut diverifikasi melalui signature, issuer, audience, expiry, nonce, atau public-key attestation.
- Karena itu, string proof tidak membuktikan bahwa request berasal dari APK Exambro resmi. Jangan menganggap pemeriksaan string sebagai autentikasi aplikasi.

### Keputusan produk dan keamanan
- Exambro akan menjadi aplikasi Android first-party, bukan integrasi vendor pihak ketiga.
- Desktop tidak menggunakan Exambro; desktop memakai browser Fullscreen API dan Fullscreen Guard.
- Android menggunakan device key dari Android Keystore dan server-issued challenge-response sebagai baseline A1.
- Server memverifikasi signature menggunakan public key perangkat yang telah didaftarkan, nonce sekali pakai, expiry, audience, serta binding peserta/jadwal/Attempt/client UUID/generation/device.
- Android Key Attestation (A2) dan managed-device Lock Task Mode (A3) merupakan penguatan terpisah. Jangan mengklaim keduanya aktif sebelum server benar-benar memvalidasi bukti dan perangkat diprovisikan.
- Private key atau shared secret tidak boleh ditanam bersama di APK maupun JavaScript. Package name atau signing certificate fingerprint yang dikirim sebagai teks bukan attestation yang memadai.
- Proof string lama tidak boleh menjadi dasar trust. Integrasi penggantinya harus menjaga kompatibilitas lifecycle dan menolak browser biasa pada kegiatan yang mewajibkan Exambro.

### Kontrak implementasi
Endpoint dan entitas data masih rancangan, bukan API yang sudah tersedia. Audit source dan migration harus mendahului implementasi. Detail alur, tingkat pengamanan, API rancangan, kandidat skema data, serta acceptance criteria tercantum dalam dokumen arsitektur Exambro.

## FG-5 — Offline exit dan rekonsiliasi waktu

### Prinsip keamanan
- Server timestamp adalah sumber waktu otoritatif.
- Timestamp fullscreen/lifecycle dari JavaScript atau APK adalah informasi event client; tidak boleh langsung digunakan untuk menggeser deadline.
- Desktop: ketika fullscreen keluar, client mengunci workspace seketika dan mengirim permintaan pause. Pause server baru sah setelah request diterima dan disimpan.
- Android: APK mengunci UI sesuai kemampuan OS dan mengirim lifecycle/security event. Server menerapkan status pause/lock sesuai kontrak Exambro yang telah diverifikasi.
- Jika offline, client tetap mengunci interaksi lokal dan melakukan retry saat jaringan pulih. Waktu sebelum server menerima event tidak otomatis dikreditkan sebagai waktu jeda.
- Setelah server mengonfirmasi pause, server membekukan sisa waktu. Resume yang sah menghitung durasi berdasarkan waktu server.
- Tidak ada endpoint yang boleh menerima tambahan waktu berdasarkan jam client yang tidak tepercaya.

### Kebijakan operasional
1. **Strict server-time:** durasi jeda yang dihitung adalah durasi yang tercatat server.
2. **Koreksi oleh pengawas:** pengawas dapat menggunakan fitur penyesuaian waktu resmi setelah memeriksa log; perubahan harus memiliki alasan, aktor, nilai sebelum/sesudah, dan audit trail.
3. **Trusted signed event:** event bertanda tangan dari device key dapat dipakai untuk validasi integritas/konteks event, tetapi timestamp perangkat tetap tidak otomatis menjadi waktu server yang otoritatif.

## FG-4/5 — Hardening source yang sudah dilakukan

- Endpoint fullscreen pause menolak pause bila deadline server telah tercapai.
- Request pause berulang ketika Attempt sudah memiliki pause `FULLSCREEN_EXIT` terbuka tidak membuat event pause tambahan.
- Pause yang dibuat pengawas/proses lain tidak dapat ditimpa oleh Fullscreen Guard.
- Sinkronisasi jawaban dan finalisasi tetap menolak Attempt ketika `pause_started_at` terisi.
- Resume fullscreen menggunakan endpoint khusus dan memperbarui deadline berdasarkan durasi yang dihitung server.
- Desktop browser Fullscreen Guard dipertahankan terpisah dari jalur Exambro Android.

## Checklist sebelum merge / acceptance

### Desktop Fullscreen Guard
- [ ] PHP lint dan PHPUnit pada service/controller/route yang berubah.
- [ ] Uji pause/resume idempotent, retry, dan deadline.
- [ ] Uji jeda pengawas tidak tertukar dengan `FULLSCREEN_EXIT`.
- [ ] Uji answer sync dan finalize ditolak saat Attempt paused.
- [ ] Uji client UUID/generation mismatch.
- [ ] Uji browser tanpa Fullscreen API dan gangguan offline.
- [ ] Pastikan monitoring menampilkan alasan jeda yang benar.

### Android Exambro (implementasi berikutnya)
- [ ] Audit dan finalisasi kontrak enrollment/device key/challenge.
- [ ] Validasi signature, nonce sekali pakai, expiry, audience, binding, dan revocation.
- [ ] Uji replay, proof kedaluwarsa, signature salah, nonce salah, serta context mismatch.
- [ ] Pastikan string proof dari browser biasa tidak dapat memenuhi mode wajib.
- [ ] Uji lifecycle, offline, reconnect, dan perpindahan client/generation.
- [ ] Uji Android Keystore dan matriks versi/perangkat.
- [ ] Uji A2 attestation hanya pada perangkat yang mendukung.
- [ ] Uji Lock Task Mode hanya pada perangkat yang benar-benar dikelola.
- [ ] Pastikan monitoring membedakan desktop D1 dan Android A1/A2/A3.

## Status

Audit dan hardening source FG-4/5 bukan functional PASS. Dokumen arsitektur first-party Android telah ditambahkan, tetapi device enrollment, challenge-response, attestation, APK, dan kiosk belum diimplementasikan. Tidak ada ujicoba fungsional yang diklaim pada batch ini.
