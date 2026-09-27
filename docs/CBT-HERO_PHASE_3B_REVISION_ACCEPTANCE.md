# Phase 3B — Revisi daftar dan ringkasan Peserta Ujian

## Instalasi pada database Phase 3B yang sudah ada

1. Backup database `cbt_hero`.
2. Sinkron kode dari commit revisi repo.
3. Jalankan `tools/phase3b_membership_source.sql` **sekali** di phpMyAdmin, pada database `cbt_hero`. Database baru yang dibuat dari `docs/CBT-HERO_SCHEMA_v1.0.sql` revisi ini tidak memerlukan SQL migrasi.
4. Refresh halaman Peserta Ujian dengan Ctrl+F5.

Keanggotaan lama berlabel **Asal belum tercatat**. Sistem tidak bisa menyimpulkan selector yang dulu dipakai hanya dari NISN/Rombel. Keanggotaan baru mencatat asal pertama (`Individu`, `Rombel`, `Tingkat`, atau `Semua`). Mengulang penugasan tidak mengubah asal anggota yang sudah ada. Ringkasan menjumlahkan anggota saat ini, bukan jumlah klik penugasan.

## Uji revisi

| ID | Langkah | Hasil yang diharapkan |
|---|---|---|
| R01 | Buka Kegiatan DRAFT berisi anggota lama. | Total tepat; anggota lama muncul pada **Asal belum tercatat** dan tidak dihitung sebagai Individu/Rombel/Tingkat secara spekulatif. |
| R02 | Tambah dua Peserta lewat **Pilih Individu**. | Ringkasan Individu bertambah dua; kolom Asal menampilkan Individu. |
| R03 | Tambah satu Rombel dan satu Tingkat yang mempunyai anggota baru. | Ringkasan menunjukkan nama Rombel dan nomor Tingkat beserta jumlah anggota baru per cakupan. Anggota yang sudah ada tidak pindah kategori. |
| R04 | Jalankan **Semua Peserta**. | Peserta baru dari cakupan ini masuk kategori Semua; jumlah kategori sama dengan Total. |
| R05 | Centang satu anggota, beberapa anggota, lalu **Pilih Semua**. | Checkbox header mengikuti pilihan, dan hanya baris pada halaman aktif yang dipilih. Tombol Hapus Terpilih menampilkan jumlah pilihan. |
| R06 | Klik Hapus Terpilih lalu batalkan; ulangi dan setujui. | Pembatalan tidak mengubah data; persetujuan menghapus semua anggota terpilih secara atomik. Total dan ringkasan langsung berkurang; Master Peserta tetap ada. |
| R07 | Cari anggota atau pindah halaman setelah memilih anggota. | Pilihan dibersihkan agar anggota tersembunyi dari halaman lain tidak ikut terhapus. |
| R08 | Buka halaman Peserta Ujian dari Kegiatan Ujian dan periksa sidebar. | Kegiatan Ujian tetap ditandai aktif; tidak ada menu Peserta Ujian yang berdiri sendiri. |
| R09 | Buka Dashboard, Master Data, Kegiatan, Peserta Ujian, Settings (Admin), dan login Manager sambil melihat Console. | Tidak ada error dari berkas aplikasi `assets/js/` atau kegagalan fungsi halaman. Bila ada pesan dari skrip ekstensi browser, periksa URL sumbernya secara terpisah. |

Pengujian kunci Kegiatan BERJALAN dan relasi Jadwal/Attempt akan ditulis pada acceptance modul yang mengimplementasikan lifecycle dan Jadwal tersebut.

Jika muncul `share-modal.js:1:135`, berkas itu tidak berada pada repo CBT-HERO. Klik nama berkas di Console untuk melihat URL lengkap. Awalan `chrome-extension://` menunjukkan skrip dari ekstensi Chrome; uji ulang dengan ekstensi tersebut dimatikan untuk situs lokal. Debug Toolbar CI4 tetap tersedia pada lingkungan development.
