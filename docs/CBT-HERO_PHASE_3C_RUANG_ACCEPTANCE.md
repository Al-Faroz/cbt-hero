# Phase 3C — Ruang Ujian dan Penempatan Peserta

Tahap ini memakai tabel `ruang` dan kolom `peserta_kegiatan.ruang_id` yang sudah ada di `CBT-HERO_SCHEMA_v1.0.sql`. Pada database Phase 3B yang mengikuti skema tersebut **tidak ada SQL migrasi tambahan**. Buat satu Kegiatan yang belum mempunyai Preparation/Attempt dengan beberapa anggota dari sedikitnya dua Rombel agar cakupan mudah diperiksa. Ruang reusable, jadi penempatan satu Kegiatan tidak mengubah anggota Kegiatan lain.

| ID | Langkah | Hasil yang diharapkan |
| --- | --- | --- |
| C01 | Buka **Master Ujian → Ruang**, tambah `LAB-1` / `Laboratorium 1` dan `LAB-2` / `Laboratorium 2`. | Kedua Ruang muncul sebagai Aktif; kode/nama tampil sesuai input. |
| C02 | Coba simpan kode `LAB-1` lagi atau nama kosong. Cari `LAB`, filter status, lalu pindah halaman bila jumlah data cukup. | Duplikat/invalid ditolak; daftar/filter/pagination sesuai hasil. |
| C03 | Edit nama `LAB-2`, nonaktifkan lalu aktifkan lagi. | Perubahan tersimpan; hanya Ruang aktif muncul sebagai pilihan baru pada penempatan anggota. |
| C04 | Dari **Kegiatan Ujian → Peserta Ujian** pada Kegiatan yang belum mempunyai dependency pelaksanaan, pilih Ruang pada satu baris anggota. | Kolom Ruang menampilkan kode dan nama, tetap ada setelah refresh; anggota lain tidak berubah. |
| C05 | Centang dua anggota pada halaman yang sama, pilih cakupan **Anggota terpilih**, arahkan ke `LAB-1`. | Kedua anggota berubah; pilihan checkbox hanya untuk halaman aktif dan kosong setelah daftar dimuat ulang. |
| C06 | Pilih cakupan **Rombel**, lalu **Tingkat**, kemudian **Semua anggota Kegiatan**, dengan Ruang yang berbeda. | Anggota yang sesuai cakupan berubah. Semua berlaku untuk seluruh anggota Kegiatan, termasuk yang berada pada halaman lain. Konfirmasi menyebut cakupannya. |
| C07 | Pilih **Tanpa Ruang** untuk satu anggota atau cakupan tertentu. | Ruang pada cakupan tersebut kosong kembali; identitas anggota dan keanggotaannya tetap ada. |
| C08 | Coba nonaktifkan dan hapus Ruang yang masih dipakai anggota Kegiatan; kemudian hapus Ruang kosong. | Nonaktif dan hapus Ruang yang dipakai ditolak; Ruang kosong dapat dihapus. |

Perlindungan penempatan memakai dependency Preparation/Attempt/first START/finalisasi (HTTP 423), bukan status Kegiatan. Nomor Peserta dan Kartu berada pada tahap berikutnya.
