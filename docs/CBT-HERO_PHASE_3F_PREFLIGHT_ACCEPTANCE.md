# Phase 3F — Pemeriksaan Kesiapan Administratif

Tidak ada migrasi SQL. Buka **Master Ujian → Kegiatan Ujian → Kesiapan**, atau **Peserta Ujian → Periksa Kesiapan**. Ini pemeriksaan baca saja. Status BERJALAN/SELESAI belum dapat diubah karena lifecycle dan preflight Bank Soal/Jadwal belum tersedia; jangan menjalankan skenario transisi status pada tahap ini.

| ID | Langkah | Hasil yang diharapkan |
| --- | --- | --- |
| P01 | Buka Kegiatan DRAFT tanpa anggota. | Jumlah anggota 0 dan status administratif belum lengkap. Tidak ada perubahan data. |
| P02 | Tambahkan anggota dengan nomor, ruang aktif, username dan password cetak yang siap; isi identitas kartu. Muat ulang halaman. | Setiap hitungan kekurangan 0 dan pemeriksaan administratif lengkap. |
| P03 | Kosongkan nomor sebagian anggota, lepaskan ruang, atau buat anggota yang belum punya credential. | Hitungan kategori terkait naik. Nama/rombel/nomor maksimal 20 contoh tampil; password dan key rahasia tidak ditampilkan. Satu orang dapat dihitung di lebih dari satu kategori. |
| P04 | Pada Kegiatan DRAFT, nonaktifkan satu akun Peserta yang sudah menjadi anggota, lalu buka Kesiapan. Sesudahnya aktifkan kembali. | Kategori status akun/keanggotaan bertambah satu, lalu kembali turun setelah akun diaktifkan. |
| P05 | Perbaiki masalah melalui halaman Peserta Ujian/Master Data, lalu muat ulang Kesiapan. | Hitungan mengikuti data terbaru. Tidak ada tombol yang mengubah lifecycle Kegiatan. |
| P06 | Logout lalu buka URL Kesiapan secara langsung. | Akses ditolak oleh filter Manager; respons saat login memakai `Cache-Control: no-store, private`. |

Hitungan credential memeriksa keberadaan username, hash password, password terenkripsi, dan status READY. Dekripsi dan tata letak credential diperiksa saat cetak kartu (K01–K07). Pemeriksaan Bank Soal/Jadwal menjadi tahap integrasi setelah kedua modul dibangun.
