# CBT-HERO — PHASE 5B Susulan + Kontrol Operasional Acceptance

Tanggal baseline: 2026-09-28

> **Status pengujian: DEFERRED.**
> Sesuai keputusan proyek, acceptance mulai Phase 4D2 disimpan terlebih dahulu dan
> dijalankan sebagai rangkaian pada akhir pengerjaan Master Ujian Akademis, sebelum
> memulai Master Ujian Psikologi. Dokumen ini adalah daftar uji, bukan klaim PASS.

## Scope

Phase 5B mencakup:

- Susulan #1 ... #N sebagai child langsung Jadwal MAIN;
- target peserta spesifik;
- FIRST_ATTEMPT untuk peserta yang belum pernah START;
- REPLACEMENT untuk peserta dengan Attempt FINISHED;
- pembatalan target sebelum START;
- BUKA/TAHAN pada MAIN maupun Susulan;
- Perpanjang Batas Mulai;
- Tampilkan Nilai Saat Selesai sebelum Attempt pertama START;
- Tambah Waktu pada Attempt ACTIVE;
- idempotency pada pembuatan Susulan.

Preparation/Prepared Assignment tetap Phase 5C.

## Aturan Susulan

- Susulan tidak menggandakan Kegiatan, Bank, bobot, atau komposisi pengambilan soal.
- `jadwal_type_selection` Susulan disalin dari MAIN.
- `parent_jadwal_id` selalu menunjuk MAIN, bukan Susulan sebelumnya.
- Kegiatan induk boleh DRAFT, BERJALAN, atau SELESAI.
- Peserta yang belum mempunyai Attempt root → `FIRST_ATTEMPT`.
- Peserta dengan Attempt terbaru `FINISHED` → `REPLACEMENT` dan menunjuk Attempt tersebut.
- Membuat target replacement **belum** mengubah Attempt lama menjadi SUPERSEDED.
  Perubahan histori dilakukan saat replacement Attempt benar-benar dibuat/START pada Attempt Engine.
- Peserta dengan Attempt ACTIVE tidak dapat dijadikan target Susulan baru.
- Peserta tidak boleh mempunyai dua target Susulan pending pada root Jadwal yang sama.

## Kontrol operasional

### Perpanjang Batas Mulai

Hanya boleh memperpanjang ke waktu yang lebih akhir. Tidak boleh mempersempit jendela
mulai melalui command ini.

### Tampilkan Nilai Saat Selesai

Boleh diubah sebelum Attempt pertama START. Setelah itu server mengembalikan
`RESULT_VISIBILITY_LOCKED`.

### Tambah Waktu

Tambah Waktu bekerja pada Attempt yang masih `ACTIVE`:

- `added_seconds` bertambah;
- `deadline_at` maju sebesar tambahan yang sama;
- Attempt yang sudah selesai tidak diubah;
- backend mendukung semua Attempt aktif atau daftar Attempt tertentu.

## Acceptance tersimpan

| ID | Langkah | Hasil yang diharapkan |
|---|---|---|
| S01 | Buka Jadwal MAIN lalu halaman Susulan. | Konteks Bank, Mapel/Tingkat, dan selection MAIN tampil. |
| S02 | Buat Susulan pertama. | Tersimpan sebagai `jenis_jadwal=SUSULAN`, `parent_jadwal_id=MAIN`. |
| S03 | Buat Susulan kedua. | Menjadi Susulan #2, tetap child langsung MAIN. |
| S04 | Buat Susulan ketika Kegiatan sudah SELESAI. | Tetap diizinkan tanpa reopen Kegiatan. |
| S05 | Pilih peserta yang belum pernah START. | Target disimpan sebagai FIRST_ATTEMPT. |
| S06 | Pilih peserta dengan Attempt FINISHED. | Target disimpan sebagai REPLACEMENT dengan `supersede_attempt_id` Attempt terbaru. |
| S07 | Pilih peserta dengan Attempt ACTIVE. | Tidak dapat dipilih/ditolak server. |
| S08 | Target peserta yang sama pada dua Susulan pending. | Target kedua ditolak. |
| S09 | Bandingkan `jadwal_type_selection` MAIN dan Susulan. | Sama; operator tidak mengatur ulang jumlah soal Susulan. |
| S10 | Ulangi POST create dengan Idempotency-Key dan payload sama. | Tidak membuat Susulan ganda; hasil lama dikembalikan. |
| S11 | Pakai Idempotency-Key yang sama dengan payload berbeda. | Ditolak `IDEMPOTENCY_CONFLICT`. |
| S12 | Edit Susulan sebelum Preparation/START. | Waktu, durasi, akses, dan target dapat diperbarui. |
| S13 | Batalkan satu target sebelum START. | Status target menjadi CANCELED; histori Attempt tidak dihapus. |
| S14 | Batalkan target yang sudah START. | Ditolak DATA_LOCKED. |
| S15 | Ubah BUKA ↔ TAHAN. | Gate akses berubah; tidak mengubah timer Attempt yang sudah aktif. |
| S16 | Perpanjang Batas Mulai ke waktu lebih akhir. | Berhasil dan diaudit. |
| S17 | Coba memperpendek Batas Mulai melalui command extend. | Ditolak 422. |
| S18 | Ubah Tampilkan Nilai sebelum Attempt pertama. | Berhasil. |
| S19 | Ubah Tampilkan Nilai setelah Attempt pertama START. | Ditolak RESULT_VISIBILITY_LOCKED. |
| S20 | Tambah 10 menit ke semua Attempt ACTIVE. | `added_seconds += 600` dan `deadline_at += 600 detik`. |
| S21 | Tambah waktu saat tidak ada Attempt ACTIVE. | Ditolak NO_ACTIVE_ATTEMPT. |
| S22 | Cek Audit Log. | Create/update/cancel/akses/extend/result visibility/tambah waktu tercatat. |

## Status

**DEFERRED — belum dijalankan.**

Acceptance ini akan dijalankan bersama Phase 4D2, 5A, 5C, dan pekerjaan Master
Ujian Akademis berikutnya sebelum Master Ujian Psikologi dimulai.
