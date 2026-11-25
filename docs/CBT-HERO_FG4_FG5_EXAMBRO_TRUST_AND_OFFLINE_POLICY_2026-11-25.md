# CBT-HERO — FG-4 / FG-5 Trust Boundary & Offline Policy

Tanggal: 25 November 2026  
Branch: `feat/client-fullscreen-guard-psychology`  
Status: **AUDIT + HARDENING SOURCE — BELUM FUNCTIONAL ACCEPTANCE**

## FG-4 — Audit Exam Browser

### Temuan source
- `AttemptStartService` dan `AttemptResumeService` membaca `exam_browser_proof` sebagai string.
- Untuk kegiatan dengan `exam_browser_required = 1`, kode hanya memastikan string tidak kosong.
- Belum ditemukan verifikasi signature, issuer, audience, expiry, nonce, atau public-key attestation.
- Karena itu, nilai proof yang dikirim browser biasa dapat dipalsukan. Keberadaan string tidak membuktikan aplikasi Exam Browser resmi.

### Keputusan teknis
Tidak menambahkan HMAC secret statis ke JavaScript atau membuat token buatan yang diklaim sebagai bukti Exambro. Secret di client dapat diekstrak dan token client tanpa attestation tidak menambah keamanan.

### Kontrak integrasi yang dibutuhkan sebelum menyebut Exambro tervalidasi
1. Dokumentasi resmi/protokol dari vendor Exam Browser yang dipilih.
2. Cara memperoleh bukti yang tidak dapat dibuat oleh browser biasa (misalnya signed assertion dari aplikasi/SDK dengan private key yang dilindungi).
3. Identitas issuer/audience dan public key atau endpoint verifikasi resmi.
4. Masa berlaku, nonce anti-replay, binding ke attempt/peserta, serta versi aplikasi.
5. Kebijakan ketika layanan verifikasi tidak tersedia (fail closed untuk kegiatan wajib).

Sampai kontrak tersebut tersedia, label yang benar adalah **Exam Browser required — pemeriksaan proof dasar, belum cryptographically attested**. Jangan mengklaim kiosk lockdown atau attestation tervalidasi.

## FG-5 — Offline exit dan rekonsiliasi waktu

### Prinsip keamanan
- Server timestamp adalah sumber waktu otoritatif.
- Timestamp fullscreen exit dari JavaScript hanya informasi diagnostik; tidak boleh dipakai untuk menggeser deadline.
- Saat browser mendeteksi keluar fullscreen, input dan timer lokal dikunci seketika.
- Pause server baru sah pada saat endpoint menerima dan menyimpan event.
- Bila offline, client tetap terkunci dan melakukan retry. Waktu sebelum server menerima event tidak otomatis dikreditkan sebagai waktu jeda.
- Setelah server mengonfirmasi pause, server membekukan sisa waktu. Setelah fullscreen aktif dan endpoint resume berhasil, deadline ditambah durasi pause server yang tercatat.

### Dampak yang harus dipahami
Jika jaringan putus bersamaan dengan keluar fullscreen, peserta dapat kehilangan sebagian waktu ujian karena server belum menerima pause. Ini lebih aman daripada menerima waktu client yang bisa dimanipulasi, tetapi berpotensi tidak adil pada gangguan jaringan.

### Pilihan kebijakan untuk keputusan produk
A. **Strict server-time (rekomendasi keamanan):** hanya durasi sejak server menerima pause yang dibekukan. Tidak ada klaim kompensasi waktu offline.
B. **Pengawas melakukan koreksi waktu:** pengawas memeriksa log dan memberi tambahan waktu melalui fitur penyesuaian waktu resmi. Semua perubahan tercatat sebagai `attempt_time_adjustment`.
C. **Trusted Exambro event:** hanya dapat dipertimbangkan jika vendor menyediakan signed event dengan timestamp/nonce yang dapat diverifikasi server. Browser biasa tidak memenuhi syarat ini.

Tidak mengimplementasikan kompensasi otomatis berdasarkan jam client.

## FG-4/5 — Hardening source yang sudah dilakukan
- Endpoint fullscreen pause menolak pause bila deadline server telah tercapai, sehingga pause tidak dapat dimulai setelah waktu ujian habis.
- Request pause berulang ketika Attempt sudah memiliki pause FULLSCREEN_EXIT terbuka tidak membuat event pause tambahan.
- Pause yang dibuat pengawas/proses lain tidak dapat ditimpa oleh Fullscreen Guard.
- Sinkronisasi jawaban dan finalisasi tetap menolak Attempt ketika `pause_started_at` terisi.
- Pemulihan fullscreen menggunakan endpoint khusus dan memperbarui deadline dari durasi yang dihitung server.

## Checklist sebelum merge / acceptance
- [ ] Dapatkan protokol resmi vendor Exam Browser.
- [ ] Implementasikan signature/attestation verification sesuai protokol vendor.
- [ ] Uji replay, proof kedaluwarsa, nonce salah, issuer/audience salah, proof dari browser biasa.
- [ ] Putus jaringan sebelum, selama, dan setelah fullscreen exit.
- [ ] Pastikan timer lokal langsung berhenti saat fullscreen keluar.
- [ ] Pastikan server tidak menerima jawaban/finalisasi saat Attempt paused.
- [ ] Pastikan pengawas dapat memantau dan melakukan koreksi waktu dengan audit trail.
- [ ] Tetapkan kebijakan batas durasi/jumlah pause fullscreen dan eskalasi pengawas.
- [ ] Jalankan PHP lint, PHPUnit, integrasi database, serta browser/device test pada fase Ujicoba Menyeluruh.

## Status
Audit dan hardening source FG-4/5 belum berarti functional PASS. Integrasi attestation vendor dan keputusan kebijakan offline masih terbuka. Tidak ada ujicoba fungsional yang dilakukan pada batch ini.
