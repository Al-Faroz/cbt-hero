# CBT-HERO — PHASE 9A Hasil Ujian Acceptance

Tanggal baseline: 2026-09-29

> **Status pengujian: DEFERRED.**
> Acceptance dijalankan pada checkpoint akhir Master Ujian Akademis sebelum Master Ujian Psikologi.

## Cakupan

Phase 9A mengimplementasikan halaman **Hasil Ujian** dan API hasil resmi akademik.

Prinsip utama:

- sumber daftar hasil adalah `official_result_pointer`;
- Attempt historis/superseded tidak muncul sebagai hasil resmi;
- snapshot tidak dimutasi dari halaman laporan;
- filter kegiatan, jadwal, mapel, rombel, status final, dan pencarian peserta tersedia;
- detail hasil membaca `result_item_snapshot` dari snapshot resmi;
- halaman Manager mengikuti tabel responsif tanpa horizontal page overflow.

## Route

```text
GET /manager/hasil/ujian
GET /manager/api/results
GET /manager/api/results/options
GET /manager/api/results/{resultSnapshotId}
```

## Acceptance tersimpan

| ID | Langkah | Hasil yang diharapkan |
|---|---|---|
| R01 | Buka Hasil Ujian tanpa filter. | Hanya hasil yang mempunyai official pointer tampil. |
| R02 | Participant punya Attempt lama lalu replacement Susulan. | Hanya snapshot yang ditunjuk official pointer tampil. |
| R03 | Filter Kegiatan. | Hanya hasil kegiatan tersebut tampil. |
| R04 | Filter Jadwal MAIN/root. | Hanya hasil official pada root Jadwal tersebut tampil. |
| R05 | Filter Mata Pelajaran. | Hasil mapel lain tidak tampil. |
| R06 | Filter Rombel. | Hasil rombel lain tidak tampil. |
| R07 | Filter FINAL/BELUM FINAL. | Status sesuai `result_snapshot.is_final`. |
| R08 | Cari No Peserta/Nama/Rombel. | Query mengembalikan peserta yang cocok. |
| R09 | Buka Detail. | Header snapshot dan item hasil tampil tanpa mengubah histori. |
| R10 | Snapshot ID yang bukan official pointer. | API detail menolak dengan NOT_FOUND. |
| R11 | Dataset > 25 hasil. | Pagination berjalan dan tidak memuat seluruh dataset sekaligus. |
| R12 | Mobile 360x800. | Tabel tetap usable melalui horizontal table scroll, bukan page overflow. |

## Status

**DEFERRED — implementasi Phase 9A selesai, acceptance belum dijalankan.**
