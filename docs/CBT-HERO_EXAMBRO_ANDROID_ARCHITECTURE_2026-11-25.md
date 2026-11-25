# CBT-HERO — Arsitektur Resmi Exambro Android & Desktop Fullscreen Guard

Tanggal keputusan: 25 November 2026  
Branch: `feat/client-fullscreen-guard-psychology`  
Status: **KEPUTUSAN ARSITEKTUR DISETUJUI — KONTRAK IMPLEMENTASI SERVER MASIH DALAM AUDIT**

## 1. Keputusan platform (normatif)

CBT-HERO menggunakan dua jalur klien peserta yang berbeda:

| Platform | Klien | Mekanisme |
|---|---|---|
| Android | APK Exambro CBT-HERO buatan sendiri | Android WebView, immersive/fullscreen, device identity, challenge-response, dan opsi kiosk pada perangkat terkelola |
| Desktop/laptop | Browser | Browser Fullscreen API + Fullscreen Guard yang mengunci workspace dan meminta pause/resume terverifikasi server |

Exambro adalah aplikasi resmi yang dikembangkan sebagai bagian dari CBT-HERO dan **khusus Android**. Desktop tidak memerlukan dan tidak menggunakan APK Exambro. Desktop tetap menggunakan browser dan mekanisme auto-fullscreen/Fullscreen Guard yang telah dibuat.

Kedua klien memakai backend dan domain ujian yang sama: autentikasi peserta, jadwal, Attempt, token, renderer soal, penyimpanan jawaban, timer server, sinkronisasi, scoring, dan finalisasi. Perbedaan platform hanya pada lapisan pengamanan/identitas klien.

## 2. Batas tanggung jawab

### Android — Exambro APK
- Menampilkan aplikasi CBT-HERO melalui Android WebView.
- Meminta dan mempertahankan immersive fullscreen selama sesi ujian.
- Menangani perubahan fokus/aplikasi, layar terkunci, navigasi keluar, koneksi, dan pemulihan aplikasi sebagai event yang dapat diaudit.
- Membuat pasangan kunci per perangkat melalui Android Keystore; private key tidak diekspor dari perangkat.
- Menandatangani challenge server yang terikat pada peserta, jadwal, Attempt, client UUID, generation, device registration, dan masa berlaku.
- Menolak melanjutkan alur ujian jika server menyatakan perangkat/sesi tidak valid.
- Mendukung Lock Task Mode hanya pada perangkat yang memang disediakan/diizinkan dan diprovisikan sebagai perangkat terkelola.

### Desktop — browser
- Meminta fullscreen melalui interaksi pengguna sesuai aturan browser.
- Mendeteksi `fullscreenchange`; saat keluar, workspace dikunci dan endpoint Fullscreen Guard digunakan.
- Resume hanya setelah server mengonfirmasi pause tersimpan, fullscreen berhasil diminta kembali, dan endpoint resume khusus berhasil.
- Tidak mengklaim browser sebagai kiosk atau perangkat yang tidak dapat dimanipulasi.

### Server
- Menjadi sumber kebenaran untuk waktu, status Attempt, kepemilikan peserta, jadwal, token, dan jawaban.
- Memvalidasi device registration, challenge, signature, masa berlaku, binding konteks, serta pencegahan replay.
- Mencatat event klien dan keputusan validasi untuk monitoring/audit.
- Memastikan aturan platform tidak dapat diubah hanya dengan parameter dari client.

## 3. Model kepercayaan dan tingkat pengamanan

Jangan menyamakan bukti kepemilikan kunci dengan bukti keaslian aplikasi.

| Tingkat | Kemampuan | Makna keamanan |
|---|---|---|
| D1 — Browser desktop | Fullscreen API + server pause/resume | Guard operasional; sinyal fullscreen berasal dari client dan bukan attestation |
| A1 — Android registered key | Kunci unik perangkat dari Android Keystore + challenge-response | Membuktikan pemegang kunci perangkat terdaftar sedang menandatangani challenge; tidak sendirinya membuktikan APK resmi atau perangkat tidak di-root |
| A2 — Android key attestation | Validasi chain attestation Android, security level, challenge, dan kebijakan perangkat oleh server | Bukti perangkat/kunci yang lebih kuat pada perangkat yang mendukung; tetap perlu validasi dan kebijakan kompatibilitas |
| A3 — Managed kiosk | A2 bila tersedia + Android Enterprise/Device Owner + Lock Task Mode | Mode penguncian paling kuat untuk perangkat sekolah yang dikelola |

A1 adalah baseline implementasi pertama. A2 dan A3 menjadi penguatan opsional setelah kompatibilitas perangkat dan kebutuhan operasional ditetapkan. Aplikasi tidak boleh menyatakan A2/A3 aktif bila bukti dan provisioning belum diverifikasi server.

## 4. Device enrollment dan cryptographic protocol

### 4.1 Pembuatan kunci
1. APK membuat pasangan kunci ECDSA P-256 (secp256r1) melalui Android Keystore.
2. Private key ditandai non-exportable dan digunakan untuk signing saja.
3. APK mengirim public key, algoritma, identitas instalasi acak, versi aplikasi, dan metadata platform ke endpoint enrollment.
4. Server mengikat public key ke device registration dan peserta sesuai kebijakan enrollment.
5. Jika tersedia, APK menyertakan Android Key Attestation certificate chain. Server memvalidasi chain dan challenge; metadata yang hanya dikirim sebagai teks tidak dianggap attestation.

Kunci statis atau shared secret yang ditanam di APK dilarang. APK signing certificate/package name saja juga bukan bukti kriptografis yang cukup karena informasi tersebut dapat disalin/dipalsukan oleh klien modifikasi.

### 4.2 Challenge-response
1. Server membuat nonce acak kriptografis minimal 256 bit, sekali pakai, dengan expiry singkat (target awal 120 detik).
2. Challenge terikat pada `participant_id`, `schedule_id`, `attempt_id` bila sudah ada, `client_uuid`, `client_generation`, `device_id`, `key_id`, `audience`, `issued_at`, dan `expires_at`.
3. APK menandatangani representasi payload kanonis yang ditentukan protokol (UTF-8, urutan field tetap, encoding angka/waktu konsisten).
4. Server mengambil public key terdaftar dan memverifikasi signature, nonce belum dipakai, expiry, audience, binding seluruh konteks, status device, status Attempt, dan status jadwal.
5. Nonce dikonsumsi secara atomik pada keberhasilan validasi. Challenge kedaluwarsa, salah konteks, duplikat, atau replay ditolak.
6. Signature tidak pernah menggantikan validasi ownership, status Attempt, jadwal, token, atau otorisasi endpoint.

Protokol wajib mempunyai versi (`protocol_version`) agar format payload dan algoritma dapat ditingkatkan tanpa ambigu.

### 4.3 Revocation dan lifecycle
- Device registration dapat aktif, dicabut, atau diblokir.
- Penggantian perangkat harus melalui alur reset/re-enrollment yang tercatat.
- Satu peserta tidak otomatis boleh memakai banyak perangkat bersamaan; aturan final mengikuti kebijakan sesi CBT-HERO.
- Rotasi kunci membuat key baru dan menonaktifkan key lama setelah proses enrollment berhasil.
- Kehilangan perangkat tidak boleh membuka akses melalui replay challenge lama.

## 5. Kontrak API — rancangan, belum dianggap tersedia

Nama dan bentuk berikut adalah rancangan untuk implementasi setelah audit route/controller/service existing. Jangan membuat client APK bergantung pada endpoint ini sebelum implementasi dan kontrak final disepakati di source.

| Endpoint rancangan | Tujuan |
|---|---|
| `POST /api/exambro/device/enrollment-challenge` | Meminta nonce untuk pendaftaran kunci perangkat |
| `POST /api/exambro/device/enroll` | Mengirim public key dan proof enrollment yang ditandatangani |
| `POST /api/exambro/device/{deviceId}/revoke` | Mencabut device registration melalui otorisasi yang sah |
| `POST /api/exambro/attempt/{attemptId}/challenge` | Meminta challenge yang terikat ke sesi Attempt |
| `POST /api/exambro/attempt/{attemptId}/verify` | Memverifikasi signature challenge dan mengikat status klien Exambro |
| `POST /api/exambro/attempt/{attemptId}/event` | Mencatat event lifecycle/fokus/kiosk yang diizinkan protokol |

Endpoint final harus mengikuti pola route, autentikasi, response envelope, error code, idempotency, dan ownership yang sudah dipakai CBT-HERO. Jangan menambah endpoint yang menduplikasi layanan existing tanpa alasan.

### 5.1 Skema data kandidat
Skema final ditentukan setelah audit migrasi dan relasi existing. Entitas logis yang dibutuhkan:

- `exambro_device`: peserta pemilik, device UUID, public key, algoritma, status, versi aplikasi terakhir, platform/security level, waktu registrasi/revokasi.
- `exambro_challenge`: nonce hash, tujuan, binding participant/schedule/attempt/client/device/key, issued/expiry/consumed timestamp, status.
- `exambro_device_event` atau reuse event log yang sudah sesuai: jenis event, metadata tervalidasi, waktu server, relasi Attempt/device.

Jangan menyimpan private key. Jangan menyimpan nonce mentah jika hash cukup untuk verifikasi lookup. Semua relasi menggunakan foreign key/index sesuai konvensi database proyek. Sebelum migration dibuat, audit apakah tabel event/device yang setara sudah ada agar tidak menciptakan duplikasi.

## 6. Integrasi dengan lifecycle Attempt

- `exam_browser_required = 1`: hanya klien Android Exambro yang lolos verifikasi server boleh memulai/melanjutkan Attempt.
- `exam_browser_required = 0`: desktop browser memakai Fullscreen Guard. Android Exambro tetap dapat digunakan jika diizinkan oleh kebijakan kegiatan.
- Nilai string `exam_browser_proof` tidak boleh lagi dianggap bukti kepercayaan yang cukup.
- START, RESUME, answer sync, heartbeat, token check, dan FINALIZE harus konsisten terhadap status klien/device yang diwajibkan.
- Challenge yang sah harus terikat pada client UUID dan generation; pergantian client tidak boleh mewarisi bukti lama.
- Ketika Exambro keluar/kehilangan kondisi wajib, client mengirim event dan server menerapkan pause/lock melalui aturan yang terdefinisi. Event client yang belum diterima server tidak boleh dipakai untuk mengubah deadline menggunakan timestamp client.
- Server tetap sumber waktu otoritatif. Kebijakan kompensasi gangguan jaringan harus memakai mekanisme penyesuaian waktu resmi dengan audit trail, bukan jam client.

## 7. Android kiosk dan batasan BYOD

- BYOD/ponsel pribadi: immersive fullscreen, deteksi lifecycle, device key, dan server guard; aplikasi biasa tidak dapat menjamin pengguna tidak dapat membuka system UI atau berpindah aplikasi pada semua perangkat.
- Perangkat sekolah terkelola: dapat diprovisikan sebagai Device Owner/dedicated device dan memakai Lock Task Mode. Ini memerlukan provisioning Android Enterprise/ADB/QR sesuai metode deployment, kebijakan launcher, dan uji perangkat nyata.
- Jangan mengaktifkan klaim "kiosk terkunci" hanya berdasarkan immersive flags.
- Jika Lock Task Mode tidak tersedia, Exambro harus melaporkan mode aktual ke server sebagai mode terbatas, bukan menyamar sebagai kiosk.

## 8. Integrasi desktop Fullscreen Guard

Implementasi Fullscreen Guard pada branch ini dipertahankan untuk desktop browser:
- keluar fullscreen mengunci input/workspace;
- pause disimpan server melalui endpoint khusus;
- resume meminta user gesture untuk fullscreen dan memerlukan konfirmasi server;
- timer mengikuti deadline server;
- status jeda dan event ditampilkan di monitoring.

Fullscreen Guard adalah kontrol operasional dan audit trail, bukan bukti anti-cheat yang kuat. Tidak perlu mengubah desktop menjadi APK atau memaksa instalasi Exambro.

## 9. Rencana implementasi berurutan

1. **Audit integrasi existing (sekarang):** routes, START/RESUME, proof lama, client UUID/generation, ownership, participant operations, event log, migration, dan API response.
2. **Server foundation:** migration device/challenge hanya jika belum ada struktur setara; model/service enrollment, challenge, signature verification, nonce consumption, revocation, dan audit event.
3. **Attempt integration:** enforce platform policy pada START/RESUME dan lifecycle lain tanpa memutus browser desktop Fullscreen Guard.
4. **Android APK skeleton:** WebView, enrollment, Android Keystore, canonical signing, challenge-response, immersive mode, lifecycle event, secure storage, dan update/version policy.
5. **Kiosk enhancement:** Device Owner/Lock Task Mode untuk perangkat sekolah terkelola.
6. **Verification:** unit, integration, replay, expiry, wrong binding, revoked key, client-generation mismatch, concurrency, offline, Android version/device matrix, serta regresi desktop.
7. **Acceptance:** hanya setelah keseluruhan implementasi Academic dan Psikologi selesai sesuai urutan proyek dan fase Ujicoba Menyeluruh disetujui.

## 10. Acceptance criteria keamanan

- Tidak ada shared secret/private key bersama yang tertanam di APK atau JavaScript.
- Signature diverifikasi server menggunakan public key perangkat yang terdaftar.
- Nonce satu kali, expiry, audience, dan seluruh binding konteks diverifikasi.
- Replay, nonce kedaluwarsa, signature invalid, device revoked, dan context mismatch ditolak.
- Client biasa tidak dapat memenuhi mode Exambro wajib hanya dengan mengirim string proof.
- Desktop tanpa APK tetap dapat mengerjakan ujian pada kegiatan yang mengizinkan browser, dengan Fullscreen Guard.
- Status mode Android (A1/A2/A3) dan desktop (D1) terlihat jelas pada monitoring.
- Server time tetap otoritatif dan tidak menerima koreksi deadline dari timestamp client.
- Tidak ada klaim functional PASS sebelum pengujian benar-benar dijalankan.

## 11. Status saat dokumen dibuat

Dokumen ini mengunci keputusan arsitektur dan kontrak target. Ini **belum** berarti API Exambro, Android APK, device enrollment, attestation, atau kiosk sudah diimplementasikan. Audit source dan desain migrasi harus diselesaikan sebelum kode protokol ditambahkan.
