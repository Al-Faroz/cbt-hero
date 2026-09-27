# Phase 4C1 — Editor Manual Pilihan Ganda

Gunakan Bank Akademik DRAFT pada Kegiatan DRAFT. Aktifkan tipe **Pilihan Ganda** melalui Komposisi, lalu buka **Bank Soal → Soal PG**. Tabel `soal`, `soal_revision`, dan `soal_opsi` sudah ada pada skema awal; tidak ada migrasi SQL.

Editor awal ini menerima **teks biasa** dengan baris baru; tanda `<`/`>` di-escape sehingga tidak menjadi HTML aktif. Gambar, tabel kaya, formula, audio, dan video menunggu sanitizer/media pada tahap berikutnya. Hanya tipe PG dalam Bank ini yang tampil di daftar. Kunci jawaban hanya dikirim lewat API Manager berizin Master Ujian.

| ID | Langkah | Hasil yang diharapkan |
| --- | --- | --- |
| Q01 | Buat soal dengan teks, empat opsi A–D, satu kunci, dan poin 1. Simpan, buka ulang. | Soal terdaftar dengan revisi 1, opsi dan kunci sesuai, pratinjau cocok dengan isian. Status Bank tetap DRAFT. |
| Q02 | Edit teks/opsi/kunci/poin, simpan kembali. | Revisi menjadi 2; `stable_key` soal tidak berubah. Revisi 1 dan opsi lamanya tetap tercatat dalam DB, sementara daftar dan pratinjau menggunakan revisi 2. |
| Q03 | Masukkan teks `<script>alert(1)</script>` pada pertanyaan/opsi di Bank uji, lalu buka pratinjau. | Teks tampil apa adanya; skrip tidak berjalan. Uji ini jangan menggunakan data Bank produksi. |
| Q04 | Coba soal tanpa kunci, kurang dari dua opsi, opsi kosong, atau poin 0 melalui form/API. | Ditolak tanpa membuat revisi baru. |
| Q05 | Buka satu soal pada dua tab. Simpan tab pertama; coba simpan tab kedua dengan revisi lama. | Tab kedua ditolak 409 dan diminta memuat ulang. Revisi dari tab pertama tetap aktif. |
| Q06 | Gunakan URL API Bank A untuk membaca/mengedit ID soal dari Bank B. | Ditolak 404; soal Bank B tidak berubah. |
| Q07 | Hapus soal PG pada Bank uji yang belum dipakai Jadwal. | Soal hilang dari daftar; Bank/Komposisi tetap ada. Hapus dilakukan permanen bersama riwayat revisi soal tersebut, sehingga hanya uji dengan soal sementara. |
| Q08 | Logout lalu akses halaman/API Soal PG secara langsung. | Filter Manager menolak akses. |

Jika tipe PG belum aktif, tombol Tambah Soal tidak tersedia. Penguncian saat Kegiatan BERJALAN dan perlindungan terhadap Jadwal yang sudah memakai Bank dilakukan di server, tetapi skenario transisi lifecycle/Jadwal belum dapat dijalankan pada tahap ini.
