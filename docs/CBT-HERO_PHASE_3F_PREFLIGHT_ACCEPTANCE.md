# Phase 3F — Pemeriksaan Kesiapan Administratif

Tidak ada migrasi SQL. Buka **Master Ujian → Kegiatan Ujian → Kesiapan**, atau **Peserta Ujian → Periksa Kesiapan**. Ini pemeriksaan baca saja. Status administratif Kegiatan bukan indikator kesiapan operasional dan tidak diubah oleh halaman ini.

| ID | Langkah | Hasil yang diharapkan |
| --- | --- | --- |
| P01 | Buka Kegiatan tanpa anggota. | Jumlah anggota 0 dan pemeriksaan administratif belum lengkap. Tidak ada perubahan data. |
| P02 | Tambahkan anggota dengan nomor, ruang aktif, username dan password cetak yang siap; isi identitas kartu. Muat ulang halaman. | Setiap hitungan kekurangan 0 dan pemeriksaan administratif lengkap. |
| P03 | Kosongkan nomor dan lepaskan ruang pada satu anggota yang sama. | “Peserta perlu diperbaiki” naik 1, “Total temuan” naik 2, masing-masing kategori naik 1. Nama/rombel/nomor maksimal 20 contoh tampil; password dan key rahasia tidak ditampilkan. |
| P04 | Pada Kegiatan tersebut, nonaktifkan satu akun Peserta yang sudah menjadi anggota, lalu buka Kesiapan. Sesudahnya aktifkan kembali. | Kategori status akun/keanggotaan bertambah satu, lalu kembali turun setelah akun diaktifkan. |
| P05 | Perbaiki masalah melalui halaman Peserta Ujian/Master Data, lalu muat ulang Kesiapan. | Hitungan mengikuti data terbaru. Tidak ada tombol yang mengubah lifecycle Kegiatan. |
| P06 | Logout lalu buka URL Kesiapan secara langsung. | Akses ditolak oleh filter Manager; respons saat login memakai `Cache-Control: no-store, private`. |

Hitungan credential memeriksa keberadaan username, hash password, password terenkripsi, dan status READY. Dekripsi dan tata letak credential diperiksa saat cetak kartu (K01–K07). Kesiapan operasional Bank/Jadwal/Preparation diperiksa pada modul masing-masing dan tidak bergantung pada status Kegiatan.
