# Phase 3A — Kegiatan Ujian

Basis paket: commit `f248d0e`. Tidak ada migrasi SQL; tabel `kegiatan` telah tersedia pada schema v1.0. Settings akademik sudah diisi dan Phase 2 terintegrasi.

Step ini membuat wadah Kegiatan dalam status DRAFT. Kegiatan menyimpan Tahun Pelajaran dan Semester saat disimpan; perubahan Settings berikutnya tidak mengubah row lama. Transisi BERJALAN/SELESAI akan dipasang bersama preflight, Jadwal, dan Preparation agar status tidak dapat berubah tanpa prasyarat. Penambahan Peserta Ujian, Ruang, Nomor Peserta, dan Kartu dikerjakan pada step Phase 3 berikutnya.

| ID | Uji melalui UI | Hasil yang diharapkan |
|---|---|---|
| K01 | Login Admin dan Operator, buka **Master Ujian → Kegiatan Ujian**. | Daftar dan tombol Tambah tampil untuk kedua role. |
| K02 | Klik Tambah. | Tahun Pelajaran/Semester terisi dari Settings (`2026/2027`, `GANJIL`); Jenis dapat Akademik/Psikologis. |
| K03 | Buat Kegiatan Akademik dengan nama, keterangan, dan Exam Browser wajib. | Row DRAFT tampil dengan nilai yang sama setelah refresh. |
| K04 | Buat Kegiatan Psikologis dengan Semester GENAP; edit nama/keterangan; batalkan edit berikutnya. | Nilai yang disimpan tetap benar; pembatalan tidak mengubah row. |
| K05 | Isi Tahun Pelajaran `2026/2029` atau kosongkan Nama; simpan. | Ditolak tanpa menciptakan row baru. |
| K06 | Cari nama, filter Jenis dan Status, lalu Reset. | Daftar mengikuti filter dan kembali lengkap. |
| K07 | Ubah Settings akademik ke tahun berikutnya, lalu buka Tambah Kegiatan. | Default baru tampil; dua Kegiatan lama tetap memakai Tahun/Semester semula. Kembalikan Settings jika pengujian selesai. |
| K08 | Hapus Kegiatan DRAFT kosong, batalkan pada konfirmasi pertama lalu setujui berikutnya. | Pembatalan mempertahankan row, persetujuan menghapusnya. |
| K09 | Buka halaman pada desktop dan mobile. | Modal dan filter dapat digunakan, tabel dapat digulir pada layar sempit. |

Penolakan hapus Kegiatan yang telah memiliki Peserta/Bank/Jadwal dan kunci edit ketika BERJALAN diuji bersama modul yang membuat relasi dan transisi tersebut. Jangan mengubah status secara manual di database untuk mencoba uji itu.
