# CBT-HERO — Keputusan Implementasi Fullscreen Guard & Target Penyelesaian Psikologi

Tanggal: 2026-11-25  
Status: **REQUIREMENT DISETUJUI — IMPLEMENTASI BERTAHAP**

Dokumen ini mencatat revisi eksplisit pemilik proyek atas Dokumen Acuan Utama v1.4 dan keputusan bahwa target implementasi CBT-HERO mencakup Ujian Akademik sampai Ujian Psikologi. Dokumen Acuan Utama diperbarui menjadi v1.5; dokumen ini menjadi catatan alasan/perincian implementasi.

## 1. Target penyelesaian

- Seluruh implementasi Akademik diselesaikan.
- Seluruh implementasi Psikologi V1 diselesaikan.
- Modul system (Settings, Backup/Restore, Log, Pengosongan Data) dan performance hardening diselesaikan sesuai dependency roadmap.
- Pre-Test Readiness Review dilakukan secara statis.
- **Ujicoba Menyeluruh baru dimulai setelah implementasi Akademik dan Psikologi selesai.**
- Functional acceptance yang sebelumnya DEFERRED tidak dijalankan di tengah batch implementasi. Pemeriksaan source, schema, dependency, lint, dan konsistensi dokumentasi tetap boleh dilakukan.

## 2. Kebijakan client

Keputusan pemilik proyek: **Exambro adalah APK CBT-HERO yang dikembangkan sendiri dan khusus Android.** Exambro bukan aplikasi vendor pihak ketiga. Desktop/laptop tidak memakai APK; desktop tetap menggunakan browser dan Fullscreen Guard.

| Exam Browser pada Kegiatan | Client | Kebijakan |
|---|---|---|
| ON | APK Exambro Android yang lolos verifikasi server | Wajib; START/RESUME ditolak jika device proof/challenge tidak valid |
| ON | Browser desktop biasa | Ditolak |
| OFF | APK Exambro Android | Diizinkan sesuai kebijakan kegiatan |
| OFF | Browser desktop biasa | Diizinkan dengan Fullscreen Guard |

Kedua klien memakai shared Attempt core dan backend yang sama. APK Android menggunakan device key Android Keystore + server challenge-response sebagai baseline; attestation Android dan Lock Task Mode merupakan tingkat penguatan tambahan, bukan klaim default. Desktop Fullscreen API adalah kontrol operasional, bukan kiosk/attestation. Proof string non-empty tidak dianggap bukti Exambro yang valid. Detail normatif ada di `CBT-HERO_EXAMBRO_ANDROID_ARCHITECTURE_2026-11-25.md`.

## 3. Fullscreen Guard — browser biasa

1. Browser biasa harus berhasil masuk fullscreen sebelum START.
2. Saat event fullscreenchange menunjukkan fullscreen telah keluar selama Attempt ACTIVE, client segera mengunci interaksi workspace dan mengirim perintah pause ke server.
3. Server memverifikasi ownership, client UUID/generation, jadwal, status ACTIVE, dan kebijakan kegiatan sebelum melakukan pause.
4. Pause harus atomik/idempotent. pause_started_at dan attempt_pause_event digunakan atau diperluas secara kompatibel; jawaban yang telah tersimpan tidak dihapus.
5. Deadline dibekukan dengan menambahkan durasi pause ketika RESUME berhasil. Waktu pause tidak dihitung sebagai waktu pengerjaan.
6. RESUME untuk sumber pause FULLSCREEN hanya dapat dilakukan setelah client kembali fullscreen; server tetap memvalidasi state/ownership/generation.
7. Setiap keluar dan kembali fullscreen dicatat sebagai event bermakna. Frekuensi kejadian ditampilkan kepada operator melalui monitoring/log.
8. Pelanggaran berulang tidak otomatis menggugurkan peserta. Tindakan lanjutan merupakan keputusan operator sesuai kebijakan penyelenggara.
9. Tidak ada polling anti-cheat berat. Browser fullscreen tidak dapat mencegah pindah aplikasi, screenshot, perangkat kedua, atau manipulasi OS.

## 4. Batas implementasi

- Jangan mengubah semantics Reset Akses operator.
- Jangan menaikkan client_generation untuk pause fullscreen.
- Jangan menjadikan status browser sebagai authority untuk scoring, waktu, atau ownership.
- Jangan membuat Attempt engine kedua untuk psikologi.
- Fullscreen Guard harus menjadi capability pada shared Attempt core, sehingga berlaku untuk jadwal Akademik dan Psikologi.
- Exambro yang tervalidasi tidak dipaksa mengikuti alur browser biasa kecuali kontrak Exambro sendiri memerlukan fullscreen.

## 5. Urutan pengerjaan

1. Sinkronisasi dokumen dan kontrak API.
2. Audit schema/event pause, Attempt lifecycle, START/RESUME, bootstrap, workspace dan monitoring.
3. Implementasi server-side pause/resume/event untuk Fullscreen Guard.
4. Implementasi client-side fullscreen gate, hard lock, recovery, dan event reporting.
5. Integrasi konfigurasi Exam Browser dan monitoring operator.
6. Lanjutkan penyelesaian batch Akademik yang tersisa.
7. Implementasi lengkap Phase 10 Psikologi: instrumen, import/editor, item engine, scoring, norms/interpretation, hasil, laporan PDF/Excel, dan integrasi shared Attempt core.
8. Settings/Backup/Restore/Log/Pengosongan Data dan performance hardening sesuai roadmap.
9. Pre-Test Readiness Review.
10. Ujicoba Menyeluruh Akademik + Psikologi, perbaikan, retest, final acceptance.

**Status saat dokumen dibuat:** requirement dan urutan kerja disinkronkan; Fullscreen Guard dan implementasi Psikologi belum dinyatakan selesai. Tidak ada klaim functional PASS.