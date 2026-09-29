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
- submit dua konfirmasi;
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
| E09 | Audit bootstrap package. | Tidak ada is_correct, point_value PG Bertingkat, accepted answer, rubric rahasia, atau pasangan benar Matching. |
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
| E22 | Klik Selesai. | Konfirmasi dua tahap → drain queue → FINALIZE SUBMIT. |
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

## Catatan Exam Browser

Phase 6 sudah menyediakan gate `exam_browser_proof` pada START/RESUME ketika
`exam_browser_required=1`. Integrasi proof konkret dengan aplikasi Exam Browser
yang dipilih harus dikunci bersama konfigurasi Pelaksanaan/Exam Browser pada
phase operasional; browser biasa tanpa proof ditolak.

## Status

**DEFERRED — belum dijalankan.**
