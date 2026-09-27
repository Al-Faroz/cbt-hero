# Phase 4A — Wadah Bank Soal Akademik

Tabel `bank_soal` sudah termasuk dalam skema awal; tidak ada SQL migrasi. Bank baru selalu DRAFT. Tahap ini hanya wadah Bank, belum mencakup komposisi tipe soal, editor, impor, atau status READY.

| ID | Langkah | Hasil yang diharapkan |
| --- | --- | --- |
| B01 | Siapkan dua Kegiatan Akademik DRAFT dan satu Kegiatan Psikologis, serta Mapel aktif. Dari Kegiatan Akademik klik **Bank Soal**, buat Bank dengan Mapel, tingkat 7, dan nama. | Halaman tersaring sesuai Kegiatan. Bank tersimpan sebagai DRAFT pada Kegiatan/Mapel/tingkat yang dipilih. Kegiatan Psikologis tidak dapat dipilih. |
| B02 | Buka Bank Soal dari sidebar, ubah filter Kegiatan, cari nama Bank/Mapel, dan ganti ukuran halaman. | Daftar mengikuti filter tanpa memindahkan Bank antar-Kegiatan. |
| B03 | Edit nama Bank, Mapel, atau tingkat saat Bank masih kosong dan Kegiatan DRAFT. | Perubahan tersimpan. Tidak ada tombol yang memindahkan Bank ke Kegiatan lain atau mengubah status menjadi READY. |
| B04 | Coba isi nama kosong, tingkat selain 7–9, Mapel nonaktif, atau Kegiatan Psikologis melalui API. | Server menolak 422; data lama tetap tersimpan. |
| B05 | Hapus Bank DRAFT yang belum memiliki soal/jadwal. | Bank terhapus. Master Mapel dan Kegiatan tidak ikut terhapus. |
| B06 | Coba hapus Mapel yang digunakan Bank. | Ditolak oleh aturan dependensi Master Data Mapel. |
| B07 | Logout, lalu akses halaman/API Bank Soal secara langsung. | Filter Manager menolak akses. |
| B08 | Saat Bank masih ada, edit Kegiatan pemiliknya dan coba ganti jenis Akademik menjadi Psikologis. | Server menolak 409 dan jenis Kegiatan tetap Akademik. |

Jangan uji perubahan ketika Kegiatan BERJALAN pada tahap ini: transisi lifecycle belum tersedia. Ketentuan server telah menolak mutasi Bank di luar DRAFT dan akan diuji saat lifecycle tersedia.
