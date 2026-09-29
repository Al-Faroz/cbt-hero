# CBT-HERO — PHASE 7 Pelaksanaan + Token + Monitoring + Kontrol Acceptance

Tanggal baseline: 2026-09-29

> **Status pengujian: DEFERRED.**
> Acceptance Phase 7 disimpan dan dijalankan bersama regression Master Ujian
> Akademis sebelum Master Ujian Psikologi. DEFERRED bukan PASS.

## Scope

Phase 7 mencakup:

- Token global ON/OFF;
- token sekarang + token berikutnya;
- generate/rotate manual;
- auto-rotate;
- Monitoring Ujian server-side;
- summary Total/Belum/Sedang/Selesai/Tidak Terdeteksi;
- detail Attempt;
- Reset Akses individual/bulk;
- Tambah Waktu individual/bulk;
- Paksa Selesai individual/bulk;
- adaptive monitoring refresh;
- audit + idempotency command kritis.

Live Scoring tetap dikerjakan pada phase hasil/live scoring.

## Token

- Token bukan milik Kegiatan/Jadwal/Mapel/Ruang.
- Token ON wajib pada START dan RE-ENTRY/RESUME.
- Token OFF tidak diminta.
- Rotasi tidak mengganggu participant yang sudah berada di workspace.
- Auto-rotate menggunakan `current_token`, `next_token`, dan `next_rotate_at`.
- START/RESUME melakukan lazy rotation bila waktunya sudah lewat walaupun halaman Manager Token tidak sedang terbuka.

## Monitoring

Monitoring memakai Jadwal sebagai context utama dan server-side pagination.

Connection telemetry:

- ONLINE: sync/activity server masih baru;
- TIDAK_TERDETEKSI: Attempt ACTIVE tetapi sync/activity melewati ambang monitoring;
- RESET_AKSES: Attempt ACTIVE sedang pause authoritative akibat Reset Akses;
- SELESAI: Attempt sudah FINISHED/SUPERSEDED.

`Tidak Terdeteksi` adalah telemetry dan dapat menjadi subset dari peserta `Sedang`.

## Reset Akses

Transaction:

```text
lock Attempt ACTIVE
→ pause_started_at = now
→ client_uuid = NULL
→ client_generation += 1
→ create attempt_pause_event
→ preserve attempt_active_lock
→ preserve jawaban
→ preserve prepared assignment
→ audit
```

Saat peserta login/RESUME kembali, AttemptResumeService menutup pause event dan
menggeser deadline sebesar durasi pause sehingga sisa waktu berlanjut.

## Tambah Waktu

Command hanya menerima Attempt ACTIVE.

```text
added_seconds += X
deadline_at += X
insert attempt_time_adjustment
audit
```

Retry dengan Idempotency-Key yang sama tidak menambah waktu dua kali.

## Paksa Selesai

Paksa Selesai membuat snapshot hasil dari jawaban yang sudah diterima server,
melepas active lock, dan menetapkan `finish_reason=FORCE_FINISH`.

Jika server mendeteksi indikasi pending sync (misalnya last sync stale / activity
lebih baru dari sync), command meminta konfirmasi risiko. Server tidak mengklaim
dapat mengambil jawaban yang hanya masih tersimpan di IndexedDB perangkat.

## Acceptance tersimpan

| ID | Langkah | Hasil yang diharapkan |
|---|---|---|
| E01 | Buka menu Token. | Status, token sekarang, token berikutnya, rotasi, dan auto-rotate tampil. |
| E02 | Token OFF lalu participant START. | START tidak meminta validasi token. |
| E03 | Token ON lalu START tanpa/salah token. | Ditolak TOKEN_INVALID. |
| E04 | START dengan token benar. | Berhasil. |
| E05 | Rotate Token saat peserta sudah di workspace. | Workspace aktif tidak terputus; re-entry berikutnya memakai token baru. |
| E06 | Auto-rotate melewati next_rotate_at tanpa halaman Manager terbuka. | START/RESUME melakukan lazy rotate dan memakai token baru. |
| E07 | Buka Monitoring dan pilih Jadwal. | Summary + tabel peserta tampil server-side. |
| E08 | Participant belum START. | Status BELUM. |
| E09 | Participant ACTIVE dan sync baru. | Status SEDANG + Online. |
| E10 | Participant ACTIVE tanpa sync yang cukup lama. | SEDANG + Tidak terdeteksi. |
| E11 | Participant FINISHED. | Status SELESAI. |
| E12 | Reset Akses Attempt ACTIVE. | Generation naik, client lama tidak valid, timer pause authoritative, jawaban tidak hilang. |
| E13 | Participant login ulang setelah Reset Akses. | RESUME memakai generation baru dan deadline digeser sebesar durasi pause. |
| E14 | Retry Reset Akses dengan Idempotency-Key sama. | Tidak membuat pause event ganda. |
| E15 | Tambah Waktu 10 menit. | added_seconds +600, deadline +600, log time adjustment tercatat. |
| E16 | Retry Tambah Waktu dengan key sama. | Tidak menambah waktu dua kali. |
| E17 | Bulk Tambah Waktu beberapa Attempt ACTIVE. | Seluruh target berubah atomik. |
| E18 | Paksa Selesai Attempt dengan sync normal. | FINISHED/FORCE_FINISH, active lock dilepas, snapshot hasil dibuat. |
| E19 | Paksa Selesai Attempt dengan pending-sync risk tanpa konfirmasi. | Ditolak PENDING_SYNC_RISK. |
| E20 | Konfirmasi risiko lalu Paksa Selesai. | Command dijalankan; hanya jawaban server yang masuk snapshot. |
| E21 | Bulk Reset/Time/Finish >100 target. | Ditolak validation; command dibatasi 100 Attempt/request. |
| E22 | Detail Attempt. | Identitas, timer, sync, jumlah response, pause/time adjustment/client event tampil tanpa answer key. |
| E23 | Biarkan Monitoring terbuka. | Refresh tidak overlap; interval melambat saat tab background. |
| E24 | Cek Audit Log. | Token dan seluruh command kritis tercatat. |

## Status

**DEFERRED — belum dijalankan.**
