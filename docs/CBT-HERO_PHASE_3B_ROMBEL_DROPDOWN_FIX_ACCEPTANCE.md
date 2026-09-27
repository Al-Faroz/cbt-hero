# Revisi Phase 3B — Dropdown Rombel pada Kegiatan berikutnya

Uji pada dua Kegiatan berstatus DRAFT dan minimal dua Rombel aktif di Master Data. Tidak ada migrasi SQL.

| ID | Langkah | Hasil yang diharapkan |
| --- | --- | --- |
| RB01 | Buka Peserta Ujian pada Kegiatan pertama; pilih Cakupan → Rombel. | Dropdown memuat seluruh Rombel aktif dari Master Data, termasuk yang belum menjadi anggota Kegiatan ini. |
| RB02 | Kembali ke daftar Kegiatan, buka Kegiatan kedua yang belum memiliki anggota; pilih Cakupan → Rombel. | Dropdown tetap terisi, tidak bergantung pada keanggotaan Kegiatan sebelumnya. Pilih Rombel dan Tambahkan; hanya anggota Kegiatan kedua yang bertambah. |
| RB03 | Pilih Cakupan → Pilih Individu, lalu periksa filter Rombel pada daftar kandidat. | Filter memuat Rombel aktif serta pilihan Semua Rombel aktif. |
| RB04 | Nonaktifkan satu Rombel yang tidak dipakai dan muat ulang halaman Peserta Ujian. | Rombel tersebut tidak muncul dalam dropdown penugasan. Opsi Rombel anggota yang sudah tersimpan untuk pengaturan Ruang/Nomor tetap mengikuti anggota Kegiatan, bukan daftar Rombel aktif. |

Jika tidak ada Rombel aktif, dropdown penugasan dinonaktifkan dan pesan jelas ditampilkan. Respons daftar anggota Kegiatan kini membawa `assign_rombel_options`; informasi ini hanya berupa ID dan nama Rombel aktif, tanpa credential Peserta.
