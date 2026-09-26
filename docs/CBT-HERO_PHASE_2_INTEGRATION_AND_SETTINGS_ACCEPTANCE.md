# Gerbang integrasi Master Data dan Settings akademik

Basis kode: `d53d4ca` (Phase 2E). Jalankan pada instalasi PHP/MySQL setelah migrasi Phase 2D diterapkan; gunakan akun Admin dan Operator serta data uji yang boleh dihapus. Periksa Console/Network browser bila sebuah langkah gagal.

## A. Integrasi Master Data

| ID | Langkah | Hasil wajib |
|---|---|---|
| I01 | Login Admin lalu Operator; buka Rombel, Peserta, Import Peserta, dan Mata Pelajaran. | Keempat halaman dan API daftar tersedia bagi kedua role, tanpa error 403/500. |
| I02 | Buat Rombel aktif `7-A`, Peserta di `7-A`, lalu coba hapus Rombel itu. | Peserta menampilkan `7-A`; penghapusan Rombel ditolak. |
| I03 | Nonaktifkan Rombel `7-A`, buka modal Tambah Peserta. | `7-A` tidak dapat dipilih untuk Peserta baru; Peserta lama tetap menampilkan Rombel yang benar. Aktifkan kembali sesudahnya. |
| I04 | Buat Username dan Password Peserta; login Peserta di browser lain; ubah Username/Password dari Manager. | Password cetak terbaru sesuai; sesi Peserta lama ditolak pada request protected selanjutnya. |
| I05 | Pilih beberapa Peserta termasuk yang sudah punya Username; jalankan Generate Username kosong, kemudian Reset Password massal. | Username yang sudah ada tidak tertimpa pada aksi pertama; jumlah aksi sesuai; password terbaru dapat dilihat per akun. |
| I06 | Unduh template, isi baris valid, NISN duplikat, dan Rombel salah; upload → parse → validasi → exclude/perbaiki → validasi ulang → commit. | Hanya baris valid yang masuk sekali; referensi Rombel benar. Refresh lalu buka kembali job untuk memastikan status COMMITTED. |
| I07 | Buat Mapel, ubah urutan/status, cari/filter, lalu hapus Mapel yang belum dipakai. | Urutan dan status tetap setelah refresh; Mapel terhapus. Penolakan hapus Mapel yang dipakai Bank Soal diuji saat Bank Soal tersedia. |
| I08 | Periksa UI pada desktop dan mobile, termasuk tabel Peserta, modal, dan tabel staging. | Kontrol dapat digunakan; tidak ada teks sisa sebelum checkbox/tombol; tabel dapat digulir. |

Pagination lintas halaman diuji menggunakan volume data nyata saat melebihi ukuran halaman. Kunci data pada Kegiatan BERJALAN dan relasi Bank Soal diuji saat modul pemakai tersedia. Catat hasil I01–I08 sebelum menyatakan Phase 2 terintegrasi.

## B. Settings akademik (dependency Phase 3)

| ID | Langkah | Hasil wajib |
|---|---|---|
| S01 | Login Admin, buka Sistem → Pengaturan. | Form default Tahun Pelajaran dan Semester terbuka; nilai kosong pada instalasi baru. |
| S02 | Simpan `2026/2027` dan `GANJIL`; refresh halaman. | Keduanya tetap tersimpan dan tercatat di audit log tanpa secret setting. |
| S03 | Coba `2026/2029`, tahun tanpa `/`, dan Semester kosong. | Server menolak tanpa mengubah nilai sebelumnya. |
| S04 | Login Operator, buka URL halaman dan API Settings langsung. | Keduanya 403; Operator tetap dapat memakai Master Data. |
| S05 | Periksa `sys_settings` setelah menyimpan dan nilai `credential_encryption_key` yang sudah ada. | Hanya dua key `default_tahun_pelajaran` / `default_semester` berubah; key rahasia tidak tampil pada API. |

Nilai disimpan sebagai default. Phase 3 harus menyalinnya ke `kegiatan.tahun_pelajaran` dan `kegiatan.semester` saat Kegiatan dibuat, sehingga riwayat Kegiatan tidak berubah ketika Settings diperbarui.
