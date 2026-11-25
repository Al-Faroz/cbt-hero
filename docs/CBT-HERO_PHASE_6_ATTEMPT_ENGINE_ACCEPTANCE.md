# CBT-HERO — PHASE 6 Attempt Engine + IndexedDB + Sync + Timer Acceptance

Tanggal baseline: 2026-09-29

> **Status pengujian: DEFERRED.**
> Pengujian dijalankan pada checkpoint akhir Master Ujian Akademis sebelum
> Master Ujian Psikologi. DEFERRED bukan PASS.

## Scope

Phase 6 akademik mencakup:

- START Attempt dari Prepared Assignment READY;
- RESUME/re-entry;
- satu peserta maksimum satu Attempt ACTIVE;
- satu Attempt satu client generation;
- bootstrap package tanpa answer key;
- IndexedDB/Dexie local-first;
- Answer Sync batch kecil dan revision-safe;
- timer authority dari `deadline_at`;
- recovery refresh/offline;
- duplicate-tab guard;
- media manifest/prefetch gambar kritis;
- submit melalui satu modal konfirmasi final yang menampilkan ringkasan jawaban;
- timeout offline → TIMEOUT_PENDING → reconnect → sync → finalize;
- finalize idempotent;
- provisional/complete academic result snapshot.

## SQL

Jalankan setelah upgrade Phase 5B:

```text
docs/CBT-HERO_PHASE_6_ATTEMPT_ENGINE_UPGRADE.sql
```

Tabel `participant_operations` menyimpan idempotency command START, RESUME, dan
FINALIZE. Mutation jawaban tetap memakai `mutation_id + client_revision`.

## Answer Contract Client

| Tipe | Payload |
|---|---|
| PG | `{"selected":"A"}` |
| PG Kompleks | `{"selected":["A","C"]}` |
| PG Bertingkat | `{"selected":"B"}` |
| Menjodohkan | `{"pairs":{"L1":"R3","L2":"R1"}}` |
| Isian Singkat | `{"value":"jawaban"}` |
| Uraian | `{"text":"jawaban panjang"}` |

Server menghitung skor sendiri. Client tidak pernah mengirim skor/kunci.

Khusus Menjodohkan, `left_key/right_key` internal tidak dikirim ke participant.
Preparation membuat opaque presentation key acak untuk sisi kiri/kanan dan Answer
Sync menerjemahkannya kembali di server sebelum validasi/scoring. Pergantian kontrak
ini ikut fingerprint Preparation sehingga assignment lama terdeteksi STALE.

## Acceptance tersimpan

| ID | Langkah | Hasil yang diharapkan |
|---|---|---|
| E01 | Peserta login dan buka Konfirmasi ujian yang Prepared READY. | Tombol Mulai aktif sesuai window/access/token. |
| E02 | Double click/retry START. | Hanya satu Attempt dibuat; idempotency/retry mengembalikan Attempt yang sama. |
| E03 | Peserta sudah punya Attempt ACTIVE lalu START ujian lain. | Ditolak ACTIVE_ATTEMPT_EXISTS. |
| E04 | START ketika Prepared Assignment belum READY/stale. | Ditolak NOT_PREPARED; tidak ada randomisasi fallback. |
| E05 | START akademik Bank tingkat 9 oleh peserta tingkat lain. | Ditolak LEVEL_MISMATCH. |
| E06 | Token ON dengan token salah. | START/RESUME ditolak TOKEN_INVALID. |
| E07 | Exam Browser required tanpa proof. | Ditolak EXAM_BROWSER_REQUIRED. |
| E08 | Bootstrap setelah START. | Package berisi soal assignment, snapshot peserta, timer, answers ACK, media manifest. |
| E09 | Audit bootstrap package. | Tidak ada is_correct, point_value PG Bertingkat, accepted answer, rubric rahasia, atau pasangan benar Matching. Key internal pasangan Matching tidak dikirim; client memakai opaque presentation key. |
| E10 | PG/PGK/PGB dikerjakan. | Payload lokal sesuai tipe dan server melakukan validasi sendiri. |
| E11 | Menjodohkan dengan gambar/rumus pada sisi kanan. | Daftar pasangan kanan tetap dapat dilihat; satu pilihan kanan tidak dapat dipakai dua kali. |
| E12 | Isian teks/numeric dan Uraian dikerjakan. | Nilai disimpan lokal dan tersinkron sesuai matcher/server contract. |
| E13 | Matikan jaringan, jawab beberapa soal, pindah nomor. | UI tetap bekerja; jawaban tersimpan IndexedDB; tidak hilang saat navigasi. |
| E14 | Refresh saat offline dengan cache tersedia. | Workspace pulih dari IndexedDB tanpa kehilangan jawaban lokal. |
| E15 | Sambungkan jaringan kembali. | Queue terkirim batch kecil; retry tidak storm; status kembali Tersinkron. |
| E16 | Kirim mutation duplicate. | Server ACK aman tanpa write/score ganda. |
| E17 | Kirim client_revision lebih lama. | Di-ignore sebagai STALE_REVISION tanpa merusak jawaban terbaru. |
| E18 | Buka Attempt sama di tab kedua. | Tab kedua diblok lokal; tab utama tetap berjalan. |
| E19 | Tutup browser 5 menit lalu buka kembali. | Timer berkurang 5 menit; browser close tidak pause. |
| E20 | Putus jaringan sampai timer 0. | Input lock, TIMEOUT_PENDING lokal, jawaban pending sebelum timeout tetap tersimpan. |
| E21 | Koneksi kembali setelah E20. | Pending valid disync lalu FINALIZE TIMEOUT. |
| E22 | Klik Selesai. | Modal menampilkan total/dijawab/belum dijawab/ditandai → konfirmasi final → drain queue → FINALIZE SUBMIT. |
| E23 | Finalize request diulang dengan Idempotency-Key sama. | State FINISHED sama dikembalikan; snapshot tidak digandakan destruktif. |
| E24 | Setelah FINISHED. | `attempt_active_lock` dilepas dan peserta dapat membuka ujian lain. |
| E25 | Refresh workspace setelah FINISHED. | Dialihkan ke halaman selesai. |
| E26 | Result visibility OFF. | Halaman selesai tidak menampilkan skor. |
| E27 | Result visibility ON dan seluruh skor auto selesai. | Nilai final dapat tampil. |
| E28 | Ada Uraian berjawab belum dikoreksi. | scoring_status IN_PROCESS, final_score belum final. |
| E29 | Media gambar pada soal/opsi. | Gambar termuat dari manifest/route Attempt; audio/video GDrive tetap streaming online. |
| E30 | Coba memakai client_uuid lain tanpa Reset Akses. | Ditolak CLIENT_MISMATCH. |
| E31 | Setelah Reset Akses (Phase 7) generation berubah dan pause server dibuat. | Client lama invalid; resume generation baru melanjutkan Attempt yang sama dan deadline bergeser sebesar pause. |
| E32 | Pindah device/cache hilang tetapi server ACK sudah ada. | Bootstrap membangun ulang jawaban yang sudah ACK; jawaban local-only device lama tidak dapat dipulihkan. |
| E33 | Server mengembalikan ACK retryable (misalnya Attempt sedang dijeda) atau tidak mengembalikan ACK untuk mutation dalam batch. | Client memakai exponential backoff dan tidak mengulang request rapat, termasuk ketika drain dipanggil saat submit. Setelah kondisi pulih, queue dapat disinkronkan. |
| E34 | Server sudah mengembalikan status Attempt non-ACTIVE, lalu browser di-refresh ketika jaringan putus. | Cache lokal tidak menghidupkan kembali workspace; participant dikunci karena status server terakhir sudah non-ACTIVE. |
| E35 | Ada input teks pending debounce, lalu server menerapkan revision REANSWER/VOID pada item itu. | Client flush snapshot input lebih dulu, baru menerapkan policy revision; jawaban lama tidak muncul kembali setelah dihapus. |
| E36 | Pengawas pause Attempt saat workspace peserta terbuka, lalu resume dari Manager. | Status poll mengunci input dan membekukan timer saat pause; setelah server resume, client membuka kembali sesi dan sinkronisasi berlanjut. |
| E37 | Pause Fullscreen Guard gagal tersimpan karena jaringan putus, lalu koneksi kembali. | Pesan error tidak memutus retry; pause dikirim ulang dengan Idempotency-Key yang sama sampai terkonfirmasi. |
| E38 | Attempt sudah paused sebelum workspace dibuka (termasuk Exambro-required). | Timer langsung menampilkan remaining_seconds yang dibekukan; input tetap terkunci sampai server resume atau Fullscreen Guard memvalidasi resume. |
| E39 | Client menerima 401/403/404/409 otoritatif dari status API (misalnya CLIENT_MISMATCH atau CLIENT_GENERATION_MISMATCH). | Workspace hard-lock, tombol resume fullscreen tidak ditawarkan, cache ditandai BLOCKED, dan answer sync berhenti. |
| E40 | Attempt diakhiri Manager saat workspace peserta masih terbuka. | Poll status mengunci input dan menampilkan overlay selesai dengan tautan kembali ke daftar ujian. |
| E41 | Server berhasil memproses Fullscreen Resume tetapi response hilang; peserta mencoba lagi. | Client mengirim Idempotency-Key resume yang sama dan menerima replay sukses tanpa menambah paused_seconds/deadline dua kali. |
| E42 | Fullscreen Pause berhasil, lalu Manager sudah resume sebelum retry pause dengan key lama tiba. | Replay pause melaporkan state server terkini (paused=false); client tidak menampilkan tombol resume palsu dan mengunci ulang jika browser masih keluar fullscreen. |
| E43 | Server menyatakan Attempt FINISHED/SUPERSEDED atau status API mengembalikan ATTEMPT_NOT_ACTIVE. | Timer berhenti, input tetap terkunci, dan workspace menampilkan status terminal tanpa mengirim TIMEOUT kedua. |
| E44 | Timeout finalization gagal sementara ketika koneksi sudah online (misalnya 5xx setelah sync drain). | State TIMEOUT_PENDING tetap tersimpan dan client mencoba ulang finalisasi dengan exponential backoff; Idempotency-Key tetap sama sampai sukses. |
| E45 | Server pause/resume sudah terdeteksi status polling, lalu browser refresh saat offline. | Cache memulihkan server_paused, pause_reason, dan remaining_seconds terakhir; timer/input tetap mengikuti status pause/resume terakhir yang diketahui. |
| E46 | Bootstrap menerima penolakan otoritatif (Attempt nonaktif/client mismatch), lalu browser refresh offline. | Status BLOCKED sudah dipersist sehingga cache lama tidak dapat menghidupkan kembali workspace. |
| E47 | Manager mengubah akses Jadwal menjadi TAHAN setelah Attempt dibuat; peserta refresh workspace. | Bootstrap menolak re-entry dengan SCHEDULE_HELD; peserta yang sudah berada di workspace tanpa refresh tetap berjalan, sesuai policy TAHAN. |
| E48 | Paket ujian berisi gambar lokal critical; peserta membuka paket online lalu reload offline. | Blob gambar tersimpan di IndexedDB dan dirender dari object URL; cache blob dibersihkan saat Attempt selesai. |
| E49 | Live revision menambahkan/mengganti gambar critical. | Media baru ikut diprefetch/cache sebelum renderer menerapkan perubahan; tidak mengirim PACKAGE_READY duplikat. |

## Catatan Exam Browser

Phase 6 sudah menyediakan gate `exam_browser_proof` pada START/RESUME ketika
`exam_browser_required=1`. Integrasi proof konkret dengan aplikasi Exam Browser
yang dipilih harus dikunci bersama konfigurasi Pelaksanaan/Exam Browser pada
phase operasional; browser biasa tanpa proof ditolak.

## Status

**DEFERRED — belum dijalankan.**
