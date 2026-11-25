# CBT-HERO — Audit Integrasi Exambro ke Attempt Core

Tanggal: 25 November 2026  
Branch: `feat/client-fullscreen-guard-psychology`  
Status: **SOURCE REVIEW PARSIAL — BELUM TESTED**

## 1. Ruang lingkup yang diperiksa

Audit dilakukan pada source branch `feat/client-fullscreen-guard-psychology`:

- `app/Config/Routes.php`
- `app/Controllers/Api/Participant/AttemptController.php`
- `app/Services/AttemptStartService.php`
- `app/Services/AttemptResumeService.php`
- `app/Services/AttemptOwnershipService.php`
- `app/Services/ParticipantOperationService.php`
- `app/Services/AttemptFullscreenGuardService.php`

Ini merupakan pemeriksaan source, bukan PHP lint, PHPUnit, integrasi DB, atau uji perangkat.

## 2. Temuan integrasi existing

### 2.1 Route dan autentikasi
- API peserta berada di group `participant-auth:api`.
- Start Akademik: `POST /api/ujian/{jadwalId}/start`.
- Resume: `POST /api/attempt/{attemptId}/resume`.
- Desktop Fullscreen Guard: `POST /api/attempt/{attemptId}/fullscreen-pause` dan `fullscreen-resume`.
- API Attempt lain (status, revisions, bootstrap, sync, event, finalize) berada pada group yang sama.
- Controller mengambil peserta dari `participant_auth` session dan client identity dari header `X-CBT-Client-Id` serta `X-CBT-Client-Generation`.

Implikasi: API Exambro harus menggunakan pola auth peserta dan ownership yang sudah ada; jangan membuka endpoint device/attempt sebagai endpoint publik tanpa autentikasi dan otorisasi eksplisit.

### 2.2 Pemeriksaan proof lama
- `AttemptStartService::start()` membaca `exam_browser_proof` dari payload, memasukkan hash proof ke idempotency payload hash, lalu untuk `exam_browser_required = 1` hanya memeriksa proof bukan string kosong.
- `AttemptResumeService::resume()` melakukan pemeriksaan setara: proof non-empty untuk kegiatan required.
- Belum ada signature verification atau pengikatan proof terhadap device public key, nonce sekali pakai, audience, expiry, attempt, client UUID, maupun generation di dua service tersebut.
- Karena itu, proof lama belum dapat diperlakukan sebagai trust assertion. Tidak boleh membuat APK sekadar mengirim string konstan untuk melewati pemeriksaan.

### 2.3 START akademik dan psikologi
- `AttemptStartService::start()` secara eksplisit menolak jadwal yang bukan `AKADEMIK`, yang tidak memiliki `bank_soal_id`, atau yang memiliki `psych_instrument_id`.
- Dengan demikian, service ini bukan lifecycle start universal untuk Akademik dan Psikologi.
- Sebelum enforcement Exambro dibuat, jalur START/RESUME Psikologi dan service khususnya harus ditemukan dan diaudit. Enforcement hanya di service Akademik akan meninggalkan celah pada kegiatan Psikologi.

### 2.4 Ownership dan waktu
- `AttemptOwnershipService::owned()` mengambil relasi jadwal/kegiatan dan memuat `exam_browser_required`, `psych_instrument_id`, client UUID/generation, serta data Attempt.
- `validateClient()` memeriksa format UUID, generation yang sama, dan kecocokan UUID dengan Attempt.
- `now()` dan `timezone()` benar-benar tersedia pada service yang diperiksa; penggunaan keduanya pada Fullscreen Guard bukan pemanggilan method yang tidak ada.
- Waktu server menggunakan timezone aplikasi. Deadline dan pause/resume harus tetap dihitung server-side.

### 2.5 Participant operation / idempotency
- `ParticipantOperationService::begin()` mencari `idempotency_key` dengan `FOR UPDATE`.
- Key yang sama hanya diterima jika peserta, action, jadwal (ketika diberikan), dan payload hash cocok.
- Baris yang sudah ada tetapi belum selesai (`IN_PROGRESS`) dikembalikan sebagai existing row; perilaku setiap caller terhadap status ini perlu ditinjau ketika concurrency/retry diuji.
- Integrasi enrollment/challenge harus menggunakan idempotency yang sesuai dan unique constraint database; jangan menganggap operasi IN_PROGRESS otomatis selesai.

### 2.6 Desktop Fullscreen Guard
- `AttemptFullscreenGuardService::pause()` menolak kegiatan dengan `exam_browser_required = 1`; ini konsisten dengan pemisahan Android Exambro dan desktop browser.
- Event yang disimpan menyebut sumber `BROWSER_FULLSCREEN_API` dan mode `BROWSER`.
- Pause memvalidasi ownership, client UUID/generation, status Attempt, access state, deadline server, dan tidak menimpa pause lain.
- Resume memerlukan client yang sama, pause terbuka dengan reason `FULLSCREEN_EXIT`, serta menghitung durasi dari waktu server.
- Jalur ini dipertahankan khusus untuk browser desktop; jangan memakai endpoint ini sebagai bukti autentikasi APK Android.

## 3. Temuan yang masih harus ditutup sebelum implementasi API Exambro

- [ ] Audit jalur START/RESUME Psikologi, termasuk controller, service, route, dan field proof/platform.
- [ ] Audit seluruh schema/migration terkait `kegiatan.exam_browser_required`, `attempt.client_uuid`, `attempt.client_generation`, `participant_operations`, dan event log.
- [ ] Audit answer sync, heartbeat/status, bootstrap, token, dan finalize untuk menentukan titik enforcement yang tidak dapat dilewati.
- [ ] Audit cara client UUID/generation dibuat dan di-reset agar device registration dapat terikat konsisten.
- [ ] Audit kebijakan satu peserta/satu Attempt aktif dan apakah satu device boleh terdaftar untuk beberapa peserta.
- [ ] Tetapkan apakah pendaftaran perangkat mandiri, operator-approved, atau melalui kode enrollment.
- [ ] Finalisasi skema device/challenge dengan foreign key/index berdasarkan migration aktual.
- [ ] Buat threat-model test matrix untuk replay, nonce expiry, signature invalid, revocation, context mismatch, concurrency, dan offline.

## 4. Keputusan implementasi setelah audit

1. Pertahankan desktop Fullscreen Guard tanpa APK.
2. Implementasikan Exambro Android sebagai first-party client dengan Android Keystore per-device key.
3. Jangan lagi menganggap `exam_browser_proof` non-empty sebagai validasi yang cukup.
4. Enforcement required harus mencakup seluruh lifecycle Akademik dan Psikologi, bukan hanya StartService Akademik.
5. Jangan membuat migration/API sampai pemeriksaan schema dan jalur Psikologi selesai agar tidak menduplikasi struktur atau menciptakan bypass.
6. Semua perubahan dilakukan dalam batch terpisah dengan test source dan catatan hasil; functional acceptance tetap menunggu fase pengujian menyeluruh proyek.

## 5. Status

Source audit membuktikan bahwa method `now()`/`timezone()` yang dipakai Fullscreen Guard tersedia, dan memperlihatkan bahwa proof lama hanya string non-empty pada jalur Akademik yang diperiksa. Jalur Psikologi dan audit schema/lifecycle penuh masih terbuka. Tidak ada klaim PASS.


## 6. Cross-check dokumen ERD dan kontrak API normatif

Pemeriksaan dokumen turunan menghasilkan temuan tambahan:

- ERD sudah memiliki `kegiatan.exam_browser_required` sebagai sakelar kebijakan kegiatan.
- Tabel `attempt` sudah memiliki `client_uuid` dan `client_generation`; keduanya dapat menjadi bagian binding sesi, tetapi bukan pengganti device registration.
- ERD sudah mendefinisikan `attempt_pause_event` dan `attempt_client_event`. Event lifecycle Attempt dapat menggunakan tabel yang ada jika kolom/retensi/index mencukupi.
- ERD belum mendefinisikan entitas khusus device registration atau challenge nonce. Sebelum migration, pastikan belum ada implementasi tambahan di source/migration aktual.
- Kontrak API normatif saat ini masih memakai field `exam_browser_proof` pada START/RESUME. Kontrak tersebut harus direvisi saat protokol Android final diimplementasikan; jangan mengubah request client lebih dulu sebelum service server dan migration tersedia.
- ERD menyatakan START wajib atomic dan Attempt memakai satu active lock per peserta. Enrollment dan challenge tidak boleh mengubah aturan satu peserta/maksimum satu Attempt ACTIVE.
- ERD menyatakan network putus/browser ditutup tidak otomatis membuat pause. Fullscreen Guard adalah keputusan tambahan yang spesifik untuk event keluar fullscreen yang diterima server; offline lifecycle tidak boleh diberi kompensasi dari timestamp client.

### Dampak pada rancangan data

Kandidat minimum yang masih perlu diverifikasi terhadap seluruh migration aktual:
1. `exambro_device` untuk public key dan status perangkat.
2. `exambro_challenge` untuk nonce hash, binding, expiry, dan consumed state.
3. Reuse `attempt_client_event` untuk event Exambro yang terkait Attempt, dengan metadata yang tervalidasi dan dibatasi ukuran/jenisnya.

Tabel ketiga tidak perlu dibuat bila `attempt_client_event` sudah memenuhi kebutuhan audit. Tidak ada private key atau shared secret di database server.
