# CBT-HERO — PHASE 5C Preparation / Prepared Assignment Acceptance

Tanggal baseline: 2026-09-28

> **Status pengujian: DEFERRED.**
> Acceptance dijalankan bersama rangkaian Master Ujian Akademis sebelum Master
> Ujian Psikologi. DEFERRED bukan PASS.

## Tujuan

Preparation memindahkan pekerjaan berat dari saat peserta START ke tahap persiapan
operator. START nantinya hanya memakai Prepared Assignment yang sudah READY.

Preparation akademik menyelesaikan:

- resolve target peserta;
- validasi Bank READY;
- pembacaan `selection_count` per tipe dari Jadwal;
- pemilihan soal;
- pengacakan soal sesuai Komposisi Bank;
- urutan opsi stabil sesuai Komposisi Bank;
- urutan kanan Menjodohkan yang stabil bila pengacakan aktif;
- pin `soal_revision_id`;
- media manifest;
- fingerprint assignment;
- selective rebuild untuk assignment terdampak;
- chunk maksimal 100 target per request agar dapat dilanjutkan/resume.

## Target

### Jadwal MAIN

Target adalah seluruh `peserta_kegiatan` ACTIVE pada Kegiatan dengan peserta ACTIVE.

### Jadwal SUSULAN

Target hanya baris `jadwal_peserta_target.status=TARGETED`.

## Fingerprint

Fingerprint Preparation berasal dari:

- Jadwal;
- Bank dan `bank_soal.version_no`;
- fingerprint Bank READY;
- `jadwal_type_selection`;
- konfigurasi bobot/pengacakan Bank;
- identitas `peserta_kegiatan`;
- mode target Susulan / Attempt replacement bila ada.

Jika sumber yang memengaruhi assignment berubah sebelum START, assignment lama
terbaca sebagai STALE dan hanya target tersebut yang perlu dibangun ulang.

## Pengacakan

Tidak memakai `ORDER BY RAND()` saat peserta START.

Urutan dipilih secara deterministik dari fingerprint participant:

- `shuffle_questions=0` → mengikuti `sort_order` Bank;
- `shuffle_questions=1` → urutan peserta dapat berbeda tetapi stabil setelah prepared;
- `shuffle_options=1` → urutan opsi PG/PG Kompleks/PG Bertingkat stabil per participant;
- Menjodohkan menyimpan urutan kiri/kanan tampilan tanpa menyimpan jawaban benar
  ke payload participant.

Isian Singkat dan Uraian tidak diacak oleh konfigurasi Bank V1.

## Media manifest

Semua media yang terhubung ke revisi soal terpilih disalin sebagai referensi ke
`prepared_assignment_media`. Gambar ditandai critical/prefetch lebih awal;
audio/video Google Drive tetap berupa referensi asset yang sama.

## Preflight dasar

Status Preparation juga memeriksa target mempunyai:

- Nomor Peserta;
- username;
- password hash;
- `credential_status=READY`.

Prepared Assignment dapat selesai dibuat walaupun identitas login belum lengkap,
tetapi `can_start=false` sampai preflight peserta lulus.

## API

```text
GET  /manager/api/jadwal/{id}/preparation
POST /manager/api/jadwal/{id}/prepare
POST /manager/api/jadwal/{id}/prepare/rebuild

GET  /manager/api/jadwal/{id}/prepared-assignments
GET  /manager/api/prepared-assignments/{assignmentId}
```

POST Preparation memakai `Idempotency-Key`. Satu request memproses maksimal 100
target. UI mengulang request secara berurutan sampai `has_more=false`. Jika proses
terputus, Preparation dapat dijalankan kembali dan assignment READY yang fingerprint-
nya masih sesuai tidak dibuat ulang.

## Acceptance tersimpan

| ID | Langkah | Hasil yang diharapkan |
|---|---|---|
| P01 | Buka Preparation Jadwal MAIN. | Total target sama dengan peserta aktif Kegiatan. |
| P02 | Klik Siapkan Semua pada 1500 target. | Diproses bertahap maksimal 100 per request sampai selesai, tanpa START participant. |
| P03 | Bank memiliki 40 PG, Jadwal memilih 20. | Setiap assignment mempunyai tepat 20 PG. |
| P04 | Bank multi-tipe. | Jumlah tiap tipe persis mengikuti `jadwal_type_selection`. |
| P05 | `shuffle_questions=0`. | Soal terpilih mengikuti urutan Bank dan stabil. |
| P06 | `shuffle_questions=1`. | Peserta dapat memperoleh urutan/selection berbeda; assignment yang sama tetap stabil. |
| P07 | `shuffle_options=1` pada PG. | `option_order_json` tersimpan dan stabil per assignment. |
| P08 | Menjodohkan dengan pengacakan. | `mapping_json` menyimpan urutan tampilan kiri/kanan tanpa menaruh kunci jawaban pada payload assignment. |
| P09 | Edit soal setelah Bank dipakai Jadwal. | Diblok oleh lifecycle Bank/Jadwal; revision pinned tidak berubah diam-diam. |
| P10 | Ubah struktur Jadwal sebelum START setelah preparation. | Status assignment lama menjadi STALE berdasarkan fingerprint. |
| P11 | Jalankan Bangun Ulang Terdampak. | Hanya STALE/FAILED yang dibuat ulang; READY yang valid tidak diubah. |
| P12 | Periksa `soal_revision_id`. | Setiap item menunjuk revisi soal yang dipakai saat Preparation. |
| P13 | Soal mempunyai gambar/audio/video. | Manifest media berisi asset terkait; gambar critical, media tidak diduplikasi. |
| P14 | Ulangi POST dengan Idempotency-Key + payload sama. | Chunk tidak dibuat ganda. |
| P15 | Idempotency-Key sama dengan payload berbeda. | Ditolak `IDEMPOTENCY_CONFLICT`. |
| P16 | Putus proses setelah beberapa chunk lalu jalankan lagi. | READY valid dilewati; proses melanjutkan target yang belum siap. |
| P17 | Peserta belum mempunyai kredensial READY/Nomor Peserta. | Assignment boleh READY tetapi preflight gagal dan `can_start=false`. |
| P18 | Semua assignment READY dan preflight peserta lulus. | `can_start=true`. |
| P19 | Preparation Susulan. | Hanya target TARGETED pada Susulan yang dibuatkan assignment. |
| P20 | Replacement Susulan. | Fingerprint menyertakan mode replacement dan assignment baru terpisah dari histori lama. |
| P21 | Buka detail Prepared Assignment. | Manager melihat item/revisi/media untuk troubleshooting tanpa API participant membuka answer key. |
| P22 | Audit Log. | Prepare/Rebuild tercatat dengan jumlah target yang diproses. |

## Status

**DEFERRED — belum dijalankan.**
