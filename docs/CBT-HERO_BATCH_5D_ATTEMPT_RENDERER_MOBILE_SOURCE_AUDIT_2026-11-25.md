# CBT-HERO — Batch 5D Attempt / Renderer / Mobile Source Audit

Tanggal: 2026-11-25

> Source-level audit dan hardening. Acceptance browser/device, PHP lint, PHPUnit, dan integrasi tetap DEFERRED. Dokumen ini tidak menyatakan PASS.

## Scope source

- Participant renderer: `exam-renderer.js`, `question-renderer.js`, `exam-display.js`.
- Local-first answer persistence: `exam-answer-store.js`, `exam-db.js`.
- Navigation, palette, image zoom: `exam-navigation.js`, `exam-display.js`.
- Timer, bootstrap, offline cache: `exam-timer.js`, `exam-bootstrap.js`, `exam-runtime-state.js`.
- Sync/revision/finalize: `exam-sync.js`, `exam-submit.js`.
- Pause/fullscreen/tab lock: `exam-fullscreen-guard.js`, `exam-tab-lock.js`.
- API status: `AttemptRuntimeService.php`.

## Temuan dan perbaikan

### 5D-01 — error handler Fullscreen Guard memanggil elemen DOM sebagai fungsi
Di catch `sendPause()`, `message` adalah HTMLElement tetapi dipanggil seperti fungsi. Ini melempar TypeError sebelum retry timer dijadwalkan, sehingga kegagalan simpan pause (misalnya offline) dapat membuat tombol resume tetap disabled dan tidak ada retry.

Perbaikan: gunakan `message.textContent`; retry dengan pause Idempotency-Key yang sama tetap dijadwalkan.

File: `assets/js/exam-fullscreen-guard.js`  
Commit: `a64f39957994c02836f4ba02d45cb97e9e60e31f`

### 5D-02 — input debounce dapat hidup kembali setelah revision REANSWER/VOID
`applyRevisionChanges()` sebelumnya menghapus answer/sync queue sebelum flush text debounce. `renderIndex()` kemudian flush pending snapshot lama dan dapat membuat kembali jawaban/queue yang seharusnya dihapus.

Perbaikan: flush pending renderer lebih dahulu, baru transaction revision update dan penerapan answer policy.

File: `assets/js/exam-sync.js`  
Commit: `a7811c28ee4678b6b95f317dec386750340ea565`

### 5D-03 — retryable ACK dapat menyebabkan sync loop rapat
Jika batch mengandung ACK transient/unknown atau ACK hilang, mutation tetap berada di queue tetapi client sebelumnya segera menjalankan `queueMicrotask(syncOnce)`. Hal ini dapat mengulang request terus-menerus.

Perbaikan:
- Batch dengan mutation unresolved memakai exponential backoff + jitter.
- Retry timer tidak dibatalkan oleh panggilan `drain()`; `syncOnce()` tidak melewati backoff yang masih aktif.
- Batch yang seluruhnya selesai tetap dapat melanjutkan chunk berikutnya tanpa delay.

File: `assets/js/exam-sync.js`  
Commits: `a26763c3641961956a1855a6e637f7824ec6d9d5`, `36ec4b381e54f1578b1cf42ea63ecad97c23c6c9`

### 5D-04 — cache offline dapat menghidupkan kembali Attempt setelah server menolak akses
Bootstrap sebelumnya fallback ke cache untuk semua HTTP error, termasuk 401/403/404/409 yang merupakan penolakan otoritatif server.

Perbaikan:
- HTTP 4xx otoritatif (kecuali 408/429) hard-lock dan tidak memakai cache.
- Status Attempt terakhir dari status API dipersist di IndexedDB.
- Cache recovery menolak status terakhir non-ACTIVE.

File: `assets/js/exam-bootstrap.js`, `assets/js/exam-sync.js`  
Commits: `94eb46b100e44f27dafa87c2e47fd237ff600f2d`, `f6470ce4ede38b9598a81381a869b04b5f3e2c8d`, `9237186c40f6fa15c650b1dc2a5b8fdea22f3362`

### 5D-05 — pause server tidak tercermin konsisten pada workspace/timer
Status API sudah mengembalikan `paused`, tetapi client hanya memeriksa status ACTIVE/FINISHED. Operator pause dapat tidak mengunci input; timer juga dapat tetap berjalan. Timer bootstrap juga dapat menghitung deadline mentah ketika Attempt sudah paused.

Perbaikan:
- Status API mengembalikan `pause_reason` dari pause event aktif.
- Runtime menyimpan `pauseReason`; sync status mengunci workspace, menampilkan overlay sesuai alasan, dan membuka kembali sesi setelah server resume.
- Server resume memicu pemeriksaan ulang Fullscreen Guard dan sync queue.
- Bootstrap paused langsung membekukan timer pada `remaining_seconds`; status polling paused tidak mengirim event yang membatalkan pause timer.
- UI resume Fullscreen Guard dapat diaktifkan saat status server mengonfirmasi pause.

File:
- `app/Services/AttemptRuntimeService.php`
- `assets/js/exam-runtime-state.js`
- `assets/js/exam-sync.js`
- `assets/js/exam-timer.js`
- `assets/js/exam-fullscreen-guard.js`

Commits:
- `f59867076c62df8f10d8b8aa96047677ceeb2e78`
- `1a0a1574cc6c3daec8bec5d1eecd4fd852b2d169`
- `a07c6a734f4249f9931de114cd9bbd1379152d8e`
- `2631efa3d3c906fbb3da6cdd9516a56bba6dea42`
- `3d82e60544d92bbd63d475c9e638550468c2c067`
- `9486f830d2ce8fbfc568db46d03141a2b9ea1732`
- `f92b7b23f3c172ed8d97ed39b4975a49a18286f4`

Acceptance E33–E38 ditambahkan pada `CBT-HERO_PHASE_6_ATTEMPT_ENGINE_ACCEPTANCE.md`.

## Acceptance yang masih DEFERRED

- E01–E38: seluruh Attempt Engine, local-first, sync, timeout, pause/resume, finalisasi, cache, dan security.
- PRM01–PRM20: enam tipe soal, rich content, zoom, mobile viewport, offline, palette, dan jawaban.
- Belum menjalankan PHP lint, PHPUnit, browser automation, Android device/browser tests, IndexedDB fault/reload tests, atau DB concurrency.
- Belum ada klaim PASS.

## Langkah source audit berikutnya

1. Audit renderer dan Question Runtime Item contract untuk seluruh enam tipe soal serta live revision.
2. Audit Answer Sync server-side terhadap ACK transient/permanent, pause, deadline, and revision policy.
3. Audit offline re-entry, timeout pending, and finalize idempotency end-to-end.
4. Sinkronkan master docs dan acceptance sebelum Batch 5D ditutup.


### 5D-06 — pause/resume server tidak tersinkron ke workspace dan timer
Attempt status API sudah menyertakan `paused`, tetapi client sebelumnya hanya memeriksa `status=ACTIVE/FINISHED`. Operator pause dapat tidak mengunci input dan timer dapat terus berjalan. Timer bootstrap juga menghitung `deadline_at` mentah pada Attempt paused.

Perbaikan:
- Status API menambahkan `pause_reason` dari event pause terbuka.
- Runtime menyimpan alasan pause; sync status mengunci workspace dan menampilkan overlay sesuai OPERATOR/FULLSCREEN_EXIT.
- Server resume membuka kembali sesi, memperbarui deadline, memicu Fullscreen Guard untuk memeriksa fullscreen, dan meminta sync queue dilanjutkan.
- Timer paused menggunakan `remaining_seconds` dari server; status poll paused tidak mengirim event deadline yang membatalkan pause.
- Pause yang sudah ada sebelum workspace dibuka juga dikunci, termasuk client dengan Exambro-required ON.

File: `AttemptRuntimeService.php`, `exam-runtime-state.js`, `exam-sync.js`, `exam-timer.js`, `exam-fullscreen-guard.js`.

Commits:
- `f59867076c62df8f10d8b8aa96047677ceeb2e78`
- `1a0a1574cc6c3daec8bec5d1eecd4fd852b2d169`
- `a07c6a734f4249f9931de114cd9bbd1379152d8e`
- `2631efa3d3c906fbb3da6cdd9516a56bba6dea42`
- `3d82e60544d92bbd63d475c9e638550468c2c067`
- `9486f830d2ce8fbfc568db46d03141a2b9ea1732`
- `f92b7b23f3c172ed8d97ed39b4975a49a18286f4`

### 5D-07 — status polling mengabaikan penolakan otoritatif client/session
Status polling sebelumnya mengabaikan seluruh HTTP error. Jika server mengembalikan CLIENT_MISMATCH/CLIENT_GENERATION_MISMATCH/ATTEMPT_NOT_ACTIVE, workspace masih dapat menerima input lokal dan sync queue akan mencoba ulang.

Perbaikan:
- HTTP 4xx otoritatif selain 408/429 mem-hard-lock workspace.
- Cache status ditandai BLOCKED sehingga tidak dapat dipulihkan sebagai sesi ACTIVE offline.
- Attempt runtime ditandai BLOCKED agar Answer Sync tidak meneruskan request.
- Attempt non-ACTIVE dari Manager menampilkan overlay selesai dan tautan kembali ke daftar ujian.

File: `assets/js/exam-sync.js`  
Commits: `3dd57f0bccbe6516d127e1b564f34f9709a94448`, `89d2253f9eb39ed53d6b0dcab89ebc63d048e252`.

Acceptance E36–E40 ditambahkan pada Phase 6; masih DEFERRED.


### 5D-08 — retry Fullscreen Resume memakai key baru
Service Fullscreen Guard sudah mendukung replay melalui `participant_operations`, tetapi client sebelumnya membuat Idempotency-Key baru pada setiap klik. Jika server berhasil resume tetapi response hilang, retry baru akan mendapat FULLSCREEN_PAUSE_NOT_FOUND.

Perbaikan: client menyimpan `resumeKey` selama satu operasi resume; key baru dibuat hanya untuk siklus pause/resume berikutnya. Key di-reset setelah sukses dan ketika pause fullscreen baru dimulai.

File: `assets/js/exam-fullscreen-guard.js`  
Commit: `edd543fc643e005018c2f80431ad277224076bf7`  
Acceptance E41 ditambahkan; belum diuji melalui retry API/browser.


### 5D-09 — replay Fullscreen Pause dapat melaporkan state lama
Jika pause request berhasil tetapi response hilang, lalu Manager sudah resume sebelum retry pause tiba, service sebelumnya mengembalikan `paused=true` hanya berdasarkan operasi lama meskipun Attempt sudah tidak paused.

Perbaikan:
- Replay pause membaca state Attempt terkini.
- Jika pause sudah diakhiri, response replay mengembalikan `paused=false` dan deadline terkini.
- Client menutup overlay lama dan memicu pemeriksaan ulang fullscreen; jika browser masih keluar fullscreen, pause cycle baru dimulai dengan key baru.

File:
- `app/Services/AttemptFullscreenGuardService.php`
- `assets/js/exam-fullscreen-guard.js`

Commits:
- `1d68428cbb0833d879a0d0f4d08472bea3cbdaa5`
- `b4b8104a0e12a511be87f2c60fd60083ef66610e`

Acceptance E42 ditambahkan; belum diuji dengan concurrency/retry fixture.


### 5D-10 — timer tetap berjalan setelah status terminal dari server
Status non-ACTIVE sebelumnya hanya mengunci input. Countdown lokal masih dapat mencapai nol dan mengirim TIMEOUT kedua setelah Manager mengakhiri Attempt.

Perbaikan: status FINISHED/SUPERSEDED maupun error ATTEMPT_NOT_ACTIVE/NOT_FOUND mengunci timer dengan pause reason terminal. Client mismatch pada Attempt yang masih ACTIVE tetap tidak menghentikan waktu authority.

File: `assets/js/exam-sync.js`  
Commits: `0f6b9d182b8225021507254f8ac8dec31924b484`, `626e829f942dd7210a684e438f4258ceb50434a1`.

Acceptance E43 ditambahkan; belum diuji dengan Manager force-finish/status polling.


### 5D-11 — TIMEOUT_PENDING tidak retry jika server gagal saat client sudah online
Timeout offline disimpan sebagai `TIMEOUT_PENDING` dan dicoba ulang saat event online/bootstrap. Namun jika finalisasi sudah dicoba online lalu gagal sementara (misalnya 5xx), tidak ada event baru yang menjamin retry; peserta dapat tertahan dalam state pending.

Perbaikan:
- Drain yang belum selesai menjadwalkan retry timeout finalization.
- Error finalization timeout menjadwalkan exponential backoff + jitter hingga 30 detik.
- Idempotency-Key `finish_key` dipertahankan pada semua retry.
- Event online mempercepat retry dengan membatalkan timer backoff yang sedang menunggu.
- Sukses finalisasi membersihkan timer retry dan cache lokal.

File: `assets/js/exam-submit.js`  
Commit: `e89d6ab174adcf3474ca553efd97e842b8f21dda`  
Acceptance E44 ditambahkan; belum diuji melalui fault injection.


Routes/API contract juga disinkronkan: status participant sekarang mendokumentasikan `remaining_seconds` dan `pause_reason` untuk pause/resume server.

Commit: `3e3b478545c18ee2605fa5d8fa00ee63ff94edf1`.



### 5D-12 — pause state hasil polling belum disimpan untuk offline reload
Status polling sudah mengunci workspace ketika server pause, tetapi hanya menyimpan status ACTIVE/deadline/sync revision. Jika browser refresh saat offline, cache dapat memulihkan `paused=false` dari bootstrap lama dan membuka input.

Perbaikan:
- IndexedDB runtime_state menyimpan `server_paused`, `server_pause_reason`, dan `server_remaining_seconds` setiap status poll.
- Bootstrap cache memulihkan nilai terakhir tersebut; status resume terbaru juga menghapus pause cache.
- Bootstrap network menyimpan state pause yang diterima dari server.

File: `assets/js/exam-sync.js`, `assets/js/exam-bootstrap.js`  
Commits: `b5e25bf9c1d90fb39b700d0f729018f2a4f2ee4e`, `dc916b194ca4776aa2f0d8459da7f7d0dc6a3d1c`.  
Acceptance E45 ditambahkan; belum diuji dengan IndexedDB reload/offline fixture.


### 5D-13 — bootstrap 4xx belum menandai cache sebagai BLOCKED
Status polling sudah mempersist BLOCKED pada penolakan client, tetapi bootstrap 4xx hanya menampilkan hard-lock. Jika peserta kemudian offline dan reload, runtime_state lama masih ACTIVE dan cache dapat dipulihkan.

Perbaikan: bootstrap authoritative 4xx juga menyimpan `server_attempt_status=BLOCKED`; hanya bootstrap network yang berhasil dapat mengembalikannya ke ACTIVE.

File: `assets/js/exam-bootstrap.js`  
Commit: `8f4a01e4e6b5ba20a70b45e1c8151802ac0bf5ad`.  
Acceptance E46 ditambahkan; belum diuji melalui reload sequence.


### 5D-14 — workspace bootstrap melewati gate TAHAN
`AttemptResumeService` sudah memeriksa `access_state=BUKA`, tetapi reload workspace memanggil `AttemptBootstrapService` langsung tanpa memeriksa akses Jadwal. Peserta yang refresh setelah Manager menahan Jadwal dapat memuat ulang soal tanpa RESUME.

Perbaikan: bootstrap menolak `access_state != BUKA` dengan `423 SCHEDULE_HELD`. Workspace yang sudah aktif dan tidak refresh tetap berjalan; status polling tidak mem-pause Attempt karena TAHAN bukan pause.

File: `app/Services/AttemptBootstrapService.php`  
Commit: `11d5493d41f785c1b13ca505008d29fdaaddb734`.  
Acceptance E47 ditambahkan; belum diuji dengan perubahan access state saat sesi aktif.


Routes/API contract Phase 6 juga disinkronkan untuk guard `SCHEDULE_HELD` pada bootstrap re-entry dan membedakannya dari sesi yang sudah aktif.

Commit: `0ad99e6b645f00a709f8e77ecd2d4a6510e68a26`.


### 5D-15 — critical image hanya dipre-fetch ke HTTP cache, bukan penyimpanan offline yang durable
`exam-media.js` sebelumnya membuat `Image()` untuk critical image dan menunggu load, tetapi tidak menyimpan blob di IndexedDB. Browser cache dapat dieviction sehingga reload offline tidak menjamin gambar tersedia.

Perbaikan:
- Dexie v2 menambahkan `media_cache` keyed per Attempt + media asset.
- Critical local image di-fetch dengan same-origin credentials dan disimpan sebagai Blob.
- Bootstrap network/cache menghidupkan object URL dari Blob sebelum event bootstrap-ready/render.
- Live revision juga memprefetch/cache media baru; event PACKAGE_READY tidak dikirim ulang.
- Cleanup finalisasi menghapus blob media untuk Attempt tersebut.
- Media external Google Drive tetap berupa referensi; tidak diklaim offline-cached.

File:
- `assets/js/exam-db.js`
- `assets/js/exam-media.js`
- `assets/js/exam-bootstrap.js`
- `assets/js/exam-sync.js`
- `assets/js/exam-submit.js`
- `app/Services/QuestionRuntimeItemService.php`

Commits:
- `ad25c427dda857688156ed7484d9bfd3b24f6283`
- `3f5adf790ea6950d3ee1f49f2bec5f02050ec025`
- `81e6629698cf1da4d42394713413c37eb220258a`
- `298e664bfcea43bba20f04dec1e0d699768d802e`
- `7fddb5a5b450f055a68c676baa35db520e877668`
- `0628fbf51d4b09f8ef50784b5d86272bae7c6966`
- `3753c7aa8e8a33f7ec0c14d501a19ffa745ff295`
- `ae7fd517929fe96f6bd62c42a6916f486967bc23`

Acceptance E48–E49 ditambahkan; belum diuji dengan IndexedDB quota/reload/offline/device fixtures.


Critical image prefetch kini dibatasi 8 detik per asset menggunakan AbortController agar koneksi media yang macet tidak menahan bootstrap tanpa batas.

Commit: `8aec7317e22596e4d53fbba390cd19802e28c78b`. Acceptance E50 ditambahkan; belum diuji dengan network throttling.


Prefetch gambar critical kini dibatasi maksimal empat fetch paralel per participant; object URL lama tidak dicabut saat live revision agar gambar pada item yang masih tampil tidak rusak. Commits: `6759fddeaa3a50d7dee9a9921214dba61d6f8735`, `755f26309e75daf948db36090ed227ed6f65a44d`. Acceptance E51 ditambahkan; belum diuji pada beban banyak participant.
