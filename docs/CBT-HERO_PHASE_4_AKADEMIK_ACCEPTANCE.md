# Phase 4 — Bank Soal Akademik: uji terpadu

Jalankan setelah menarik perubahan Phase 4. Gunakan Kegiatan **AKADEMIK berstatus DRAFT**, Mapel aktif, dan akun Manager dengan izin `master.exam.manage`. Semua kasus berikut menggunakan halaman yang sudah tersedia; tidak perlu menunggu lifecycle Jadwal/Attempt.

## Persiapan

1. Buka **Master Ujian → Bank Soal**, buat Bank untuk Kegiatan DRAFT.
2. Buka **Komposisi**. Aktifkan keenam tipe bila hendak menguji semuanya. Isi `question_count = 1` masing-masing, `option_count = 4` untuk ketiga tipe pilihan, dan bobot yang berjumlah tepat 100%, misalnya `20, 20, 15, 15, 15, 15`. Pada database lama, jalankan `CBT-HERO_BANK_COMPOSITION_UPGRADE.sql` sebelum menguji.
3. Untuk Menjodohkan pilih mode **Per pasangan**. Simpan. Bank tetap DRAFT sampai semua syarat READY terpenuhi.

| ID | Langkah | Hasil yang diharapkan |
| --- | --- | --- |
| A01 | Pada **Soal PG**, tambah satu pertanyaan dengan 2–6 opsi, satu kunci, poin positif. | Soal tersimpan dan pratinjau hanya menandai satu kunci. |
| A02 | Edit PG yang sama, ubah teks/kunci, simpan. | Nomor revisi naik; daftar dan pratinjau menampilkan revisi baru. |
| A03 | Pada **Tipe Lain**, pilih PG Kompleks; centang minimal satu opsi benar dan sisakan satu salah. | Simpan berhasil, pratinjau menandai semua opsi benar. |
| A04 | Buat PG Bertingkat dengan minimal satu opsi bernilai positif dan nilai tiap opsi tidak melebihi poin maksimal. | Simpan berhasil; pratinjau menampilkan nilai setiap opsi. |
| A05 | Buat Menjodohkan dengan dua pasangan unik. | Mode penilaian mengikuti Komposisi; pratinjau pasangan dan simpan berhasil. |
| A06 | Buat Isian Singkat mode TEXT dengan dua jawaban berbeda, lalu satu soal mode NUMERIC dengan angka harapan dan toleransi. | Jawaban dan angka tersimpan; input duplikat atau toleransi negatif ditolak. |
| A07 | Buat Uraian beserta rubrik. | Pertanyaan tersimpan; rubrik muncul pada pratinjau Manager. |
| A08 | Coba menyimpan soal tanpa pertanyaan, tanpa kunci PG, opsi kosong, atau poin negatif. | API menolak dengan pesan validasi; tidak menambah soal. |
| A09 | Sisipkan `**tebal**`, `*miring*`, `$x^2$`, teks Arab, serta tabel dengan baris `| Kolom A | Kolom B |` pada pertanyaan. | Pratinjau dan halaman cetak menampilkan format, rumus, arah teks, dan tabel. Teks `<script>` tampil sebagai teks, tidak dieksekusi. |
| A10 | Unggah gambar JPG/PNG/WebP atau audio MP3/OGG/M4A dari editor, sisipkan kode `[[media:ID]]`. Tambahkan tautan video YouTube/Vimeo HTTPS bila perlu. | Media tampak/diputar pada pratinjau; referensi media yang tidak aktif/tidak ditemukan menolak penyimpanan. |
| A11 | Dari **Impor**, unduh template Excel dan Word. Isi satu soal per baris sesuai Panduan; unggah. | Job berisi staging dan status valid/invalid per baris; Bank belum bertambah sebelum commit. |
| A12 | Pada staging, buka **Pratinjau** dan periksa kunci. Perbaiki satu baris invalid lewat JSON, keluarkan baris lain, lalu **Validasi ulang**. | Jumlah valid/invalid diperbarui; baris yang dikeluarkan tidak ikut commit. |
| A13 | Commit staging yang seluruh baris aktifnya valid; buka ulang riwayat job yang sama. | Soal masuk Bank sekali saja dan job berstatus COMMITTED; panggilan commit ulang tidak menggandakan soal. |
| A14 | Dalam template Word, sisipkan gambar JPG/PNG/WebP ke sel pertanyaan lalu unggah. | Gambar menjadi referensi media pada staging dan terlihat saat pratinjau; ikut soal setelah commit. |
| A15 | Dengan jumlah soal belum cukup atau bobot belum 100%, klik **Validasi / Status** di daftar Bank. | READY ditolak dengan rincian ketersediaan per tipe. |
| A16 | Setelah jumlah soal aktif tiap tipe **tepat sama** dengan `question_count`, jumlah pilihan setiap soal sesuai `option_count`, dan bobot 100%, klik **Validasi / Status**, ubah ke READY. | Bank berstatus READY dan editor/komposisi terkunci. Bila belum dipakai Jadwal dan Kegiatan masih DRAFT, Bank dapat dikembalikan ke DRAFT. |
| A17 | Klik **Cetak PDF**. Lihat semua soal, centang/lepaskan **Sertakan kunci dan rubrik**, lalu gunakan dialog **Cetak / Simpan PDF**. | Tampilan cetak sesuai pratinjau, dan kunci/rubrik hanya muncul bila dipilih. |
| A18 | Dengan Bank READY, coba POST/PUT/DELETE soal melalui API Manager. | Server menolak perubahan dengan status terkunci, bukan sekadar menyembunyikan tombol. |
| A19 | Akses API peserta yang sudah ada (`/api/ujian` dan konfirmasi ujian). | Tidak ada kunci jawaban, rubrik, atau daftar jawaban diterima pada respons peserta. |

## Batas data yang perlu diperhatikan

- Template Word/Excel menggunakan kolom `options_json`, `pairs_json`, dan `accepted_values_json` untuk struktur jawaban. Panduan serta contoh sintaks tersedia pada kedua template.
- Word `.docx` mendukung teks, tebal/miring, tabel dalam sel, dan gambar JPG/PNG/WebP pada sel pertanyaan. Formula ditulis dalam sintaks KaTeX `$...$`. File `.doc` lama tidak diterima.
- Cetak PDF menggunakan dialog cetak browser, sehingga operator memilih **Save as PDF** pada tujuan cetak.
- Pengujian runtime PHP/database dilakukan setelah perubahan ditarik ke lingkungan lokal yang memiliki PHP dan MySQL.
