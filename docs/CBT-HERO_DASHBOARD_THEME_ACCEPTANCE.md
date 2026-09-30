# Dashboard dan Tema Manager — Pemeriksaan

Perubahan ini dapat diperiksa **setelah R01–R07 Phase 3B selesai** agar hasil pengujian tidak tercampur. Tidak perlu mengubah data untuk mengecek tema. Dashboard membaca jumlah dari database saat halaman dibuka; ia tidak menampilkan kata sandi atau key rahasia.

## Acuan visual

- Atlassian Design, *Data visualization color*: warna kategori tetap disertai label atau penanda; tema terang dan gelap memiliki token berbeda. https://atlassian.design/foundations/color/data-visualization-color
- Atlassian Design, *Color*: kontras teks kecil sekurangnya 4,5:1, elemen visual penting sekurangnya 3:1. https://atlassian.design/foundations/color
- IBM Carbon, *Color*: permukaan gelap berlapis dan token warna yang berubah sesuai tema. https://carbondesignsystem.com/elements/color/overview/

Dashboard memakai aksen warna yang masing-masing tetap memiliki judul dan ikon. Status operasional Jadwal/Preparation selalu disertai teks. Satu keluarga font Ubuntu disimpan di `assets/fonts/ubuntu/` untuk halaman aplikasi; berkas `UFL.txt` memuat lisensinya.

| ID | Langkah | Harapan |
| --- | --- | --- |
| UI01 | Buka `manager/dashboard`, bandingkan angka peserta aktif, rombel aktif, mata pelajaran aktif, dan total kegiatan dengan data Master. | Angka sesuai data, bukan indikator teknis placeholder. |
| UI02 | Buka dashboard ketika belum ada kegiatan. | Panel menyatakan belum ada kegiatan dan memberi tautan ke Kegiatan Ujian. |
| UI03 | Buka dashboard setelah membuat Jadwal dan menjalankan Preparation; kemudian muat ulang. | Daftar Jadwal terbaru tampil dengan status Preparation DRAFT/READY dan status waktu/akses yang sesuai. |
| UI04 | Klik tombol **Tema gelap** di header lalu kunjungi Peserta, Kegiatan Ujian, dan modal formulir. | Latar, panel, tabel, formulir, menu, dan modal tetap terbaca; tombol berubah menjadi **Tema terang**. |
| UI05 | Muat ulang halaman dan berpindah ke halaman Manager lain, lalu klik **Tema terang**. | Pilihan tema bertahan selama navigasi dan setelah muat ulang. |
| UI06 | Periksa Dashboard pada lebar ponsel dan desktop; buka beberapa halaman Manager. | Kartu turun menjadi kolom sesuai lebar, tombol tema dapat dipakai, satu keluarga font Ubuntu digunakan pada teks dan kontrol. |
| UI07 | Buka DevTools Console saat mengganti tema dan berpindah halaman Manager. | Tidak ada error JavaScript dari aplikasi. |
| UI08 | Bandingkan jarak pada Dashboard, Kegiatan Ujian, Peserta Ujian, dan halaman dengan tabel pada desktop serta ponsel. | Ruang antar-panel lebih rapat; teks, tombol, tabel, dan formulir tetap terbaca tanpa elemen saling bertumpuk. |

Catatan: tema hanya diterapkan di area Manager saat ini. Login dan halaman peserta menggunakan font Ubuntu yang sama. Tema awal mengikuti preferensi terang/gelap perangkat sampai tombol tema dipakai.
