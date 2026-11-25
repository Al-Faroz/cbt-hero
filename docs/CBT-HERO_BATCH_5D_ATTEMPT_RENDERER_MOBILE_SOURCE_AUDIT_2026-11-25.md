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
