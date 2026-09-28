# CBT-HERO — PHASE 5A Jadwal Ujian Utama Acceptance

> **Status pengujian: DEFERRED (keputusan 2026-09-28).** Acceptance disimpan dan akan dijalankan pada akhir pengerjaan Master Ujian Akademis sebelum Master Ujian Psikologi. DEFERRED bukan PASS.

Tanggal baseline: 2026-09-28

## Scope

Phase 5A membangun Jadwal Ujian **MAIN akademik**. Susulan, Preparation/Prepared
Assignment, selective rebuild, dan Extend/Tambah Waktu dikerjakan pada subphase
berikutnya.

Jadwal MAIN memuat:

- Kegiatan Akademik;
- Bank Soal READY dari Kegiatan yang sama;
- Mulai;
- Batas Mulai;
- Durasi individual;
- akses BUKA/TAHAN;
- Tampilkan Nilai Saat Selesai;
- `selection_count` per tipe soal aktif pada Bank.

`selection_count` adalah jumlah soal yang diberikan kepada setiap peserta pada saat
Preparation. Bobot dan aturan penilaian tetap berasal dari Bank.

## Aturan utama

- Struktur Jadwal utama hanya dapat dibuat/diubah saat Kegiatan masih DRAFT.
- Bank harus READY dan berasal dari Kegiatan yang sama.
- Setiap tipe aktif pada Komposisi Bank wajib mempunyai `selection_count` minimal 1.
- `selection_count` tidak boleh melebihi jumlah soal ACTIVE tipe tersebut.
- Batas Mulai harus setelah Mulai.
- Durasi harus lebih dari 0 detik.
- BUKA/TAHAN adalah gate akses, bukan pause timer.
- Setelah struktur terkunci, perubahan waktu operasional dilakukan melalui command
  khusus pada subphase berikutnya, bukan PUT struktural.

## Acceptance

| ID | Langkah | Hasil yang diharapkan |
|---|---|---|
| J01 | Buka menu **Jadwal Ujian**. | Halaman daftar tampil dengan filter, pagination, dan tombol Tambah Jadwal. |
| J02 | Klik Tambah Jadwal. | Hanya Kegiatan Akademik DRAFT yang dapat dipilih; Bank hanya READY dari Kegiatan yang dipilih. |
| J03 | Pilih Bank dengan contoh 40 PG. | Bagian Jumlah Soal menampilkan PG tersedia 40 dan input jumlah yang diambil. |
| J04 | Isi PG 20 dari 40 lalu simpan. | Jadwal tersimpan; daftar menampilkan `20 Pilihan Ganda`; `jadwal_type_selection.selection_count=20`. |
| J05 | Bank mempunyai beberapa tipe aktif. | Semua tipe aktif tampil dan semuanya wajib mempunyai jumlah positif. |
| J06 | Isi salah satu jumlah 0/kosong. | Server menolak 422; data lama tidak berubah. |
| J07 | Isi jumlah lebih besar daripada soal ACTIVE, misalnya 41 dari 40. | Server menolak 422 dengan pesan jumlah melebihi soal tersedia. |
| J08 | Pilih Bank yang bukan READY atau Bank dari Kegiatan lain melalui request manual. | Server menolak; Jadwal tidak dibuat. |
| J09 | Isi Batas Mulai sama/sebelum Mulai. | Server menolak 422. |
| J10 | Simpan durasi 90 menit. | Database menyimpan `durasi_seconds=5400`; UI menampilkan 90 menit. |
| J11 | Edit Jadwal selama Kegiatan DRAFT. | Waktu, durasi, Bank, akses, nilai, dan selection dapat diperbarui secara atomik. |
| J12 | Klik Tahan, lalu Buka. | `access_state` berubah TAHAN/BUKA dan perubahan tercatat audit. |
| J13 | Hapus Jadwal yang belum dipakai. | Jadwal dan `jadwal_type_selection` terhapus; Bank tetap utuh. |
| J14 | Coba hapus Jadwal yang telah mempunyai child/Preparation/Attempt. | Server menolak dependency delete. |
| J15 | Ubah Kegiatan menjadi non-DRAFT atau tandai Jadwal telah START, lalu PUT struktural. | Server menolak dengan DATA_LOCKED. |
| J16 | Cek Bank READY yang sudah dipakai Jadwal lalu coba kembalikan Bank ke DRAFT. | Bank tetap terkunci oleh dependency Jadwal. |

## PASS

Phase 5A PASS bila J01–J16 sesuai. Setelah PASS lanjut ke Preparation/Prepared
Assignment sebelum Attempt Engine; START tidak boleh melakukan randomisasi berat.
