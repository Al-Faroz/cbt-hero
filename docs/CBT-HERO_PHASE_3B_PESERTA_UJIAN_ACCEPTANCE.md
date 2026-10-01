# Phase 3B — Peserta Ujian

Basis paket: `f4ebc2a` (Phase 3A). Tidak ada migrasi SQL; `peserta_kegiatan` sudah tersedia pada schema v1.0. Gunakan satu Kegiatan yang belum mempunyai Preparation/Attempt dan beberapa Peserta aktif dari lebih dari satu Rombel.

Keanggotaan menyimpan snapshot NISN, nama, jenis kelamin, dan Rombel saat penugasan. Menjalankan cakupan yang sama lagi melewati Peserta yang sudah menjadi anggota dan melaporkan jumlahnya. Pemilihan individu dan **Pilih Semua** berlaku pada halaman kandidat yang sedang terlihat (maksimal 100). Semua perubahan dibatasi ke Kegiatan yang belum mempunyai dependency Preparation/Attempt/hasil final.

| ID | Langkah | Hasil yang diharapkan |
|---|---|---|
| P01 | Login Admin/Operator, buka Kegiatan Ujian → **Peserta** pada Kegiatan yang belum mempunyai dependency pelaksanaan. | Nama/status Kegiatan tampil, daftar awal kosong, panel Tambahkan Peserta tersedia. |
| P02 | Pilih cakupan **Rombel**, tambahkan satu Rombel aktif. | Peserta aktif pada Rombel itu menjadi anggota; jumlah sesuai. |
| P03 | Ulangi cakupan Rombel yang sama. | Tidak ada anggota ganda; pesan menunjukkan `0 ditambahkan` dan jumlah dilewati. |
| P04 | Pilih cakupan **Tingkat** lain, lalu **Semua Peserta**. | Seluruh Peserta aktif pada Rombel aktif masuk sekali, termasuk yang belum ditugaskan. |
| P05 | Pada Kegiatan lain yang belum mempunyai dependency pelaksanaan, pilih **Individu**, cari/filter kandidat, centang baris atau **Pilih Semua**. | Hanya pilihan halaman aktif yang masuk; kandidat yang sudah ditugaskan hilang dari daftar kandidat. |
| P06 | Cari anggota berdasarkan NISN/nama/Rombel, kemudian hapus satu keanggotaan setelah konfirmasi. | Filter dan pagination benar; penghapusan tidak menghapus data Master Peserta, dan Peserta tersebut kembali menjadi kandidat. |
| P07 | Ubah nama/Rombel Peserta Master yang sudah menjadi anggota, lalu lihat daftar Kegiatan. | Snapshot pada keanggotaan tetap menampilkan identitas/Rombel saat penugasan. |
| P08 | Nonaktifkan satu Peserta Master, coba tambah melalui cakupan. | Peserta nonaktif tidak ditugaskan. Rombel nonaktif juga tidak menjadi sumber penugasan baru. |
| P09 | Periksa desktop dan mobile. | Tabel dapat digulir horizontal; pilihan checkbox dan tombol dapat digunakan. |

Kunci keanggotaan diuji setelah dependency Preparation/Attempt/first START/finalisasi terbentuk. Ruang, Nomor Peserta, dan Kartu menyusul pada step Phase 3 berikutnya. Status Kegiatan bukan gate.
