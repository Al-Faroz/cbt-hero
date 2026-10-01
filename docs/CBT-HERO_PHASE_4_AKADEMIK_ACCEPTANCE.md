# Phase 4 — Bank Soal Akademik: uji terpadu

Jalankan setelah menarik perubahan Phase 4. Gunakan Kegiatan **AKADEMIK**, Mapel aktif, dan akun Manager dengan izin `master.exam.manage`. Status administratif Kegiatan bukan gate editor Bank. Semua kasus berikut menggunakan halaman yang sudah tersedia; tidak perlu menunggu lifecycle Jadwal/Attempt.

## Persiapan

1. Buka **Master Ujian → Bank Soal**, buat Bank DRAFT pada Kegiatan Akademik.
2. Buka **Komposisi**. Aktifkan keenam tipe bila hendak menguji semuanya. Isi `question_count = 1` masing-masing, `option_count = 4` untuk PG/PG Kompleks/PG Bertingkat dan Menjodohkan (pada Menjodohkan berarti 4 pasangan), lalu isi bobot yang berjumlah tepat 100%, misalnya `20, 20, 15, 15, 15, 15`. Pada database lama, jalankan `CBT-HERO_BANK_COMPOSITION_UPGRADE.sql`; bila upgrade lama sudah pernah dijalankan sebelum revisi Matching, jalankan juga `CBT-HERO_MATCHING_OPTION_COUNT_UPGRADE.sql`.
3. Untuk Menjodohkan pilih mode **Per pasangan**. Simpan. Bank tetap DRAFT sampai semua syarat READY terpenuhi.

| ID | Langkah | Hasil yang diharapkan |
| --- | --- | --- |
| A01 | Buka **Daftar Soal → tab PG**, klik **Tambah Soal**, isi pertanyaan dengan Rich Content Editor, pilihan sesuai Komposisi, satu kunci, dan poin positif. | Soal tersimpan dan pratinjau hanya menandai satu kunci. |
| A02 | Edit PG yang sama, ubah teks/kunci, simpan. | Nomor revisi naik; daftar dan pratinjau menampilkan revisi baru. |
| A03 | Pada **Daftar Soal → tab PG Kompleks**, centang minimal satu opsi benar dan sisakan satu salah. | Simpan berhasil, pratinjau menandai semua opsi benar. |
| A04 | Buat PG Bertingkat dengan minimal satu opsi bernilai positif dan nilai tiap opsi tidak melebihi poin maksimal. | Simpan berhasil; pratinjau menampilkan nilai setiap opsi. |
| A05 | Atur Menjodohkan 4 pasangan pada Komposisi lalu buat soal dengan tepat empat pasangan unik. | Jumlah pasangan dan mode penilaian mengikuti Komposisi; pratinjau dan simpan berhasil. |
| A06 | Buat Isian Singkat mode TEXT dengan dua jawaban berbeda, lalu satu soal mode NUMERIC dengan angka harapan dan toleransi. | Jawaban dan angka tersimpan; input duplikat atau toleransi negatif ditolak. |
| A07 | Buat Uraian beserta rubrik. Pada template Word, biarkan Poin maksimum default 1 atau ubah sesuai skala rubrik. | Pertanyaan tersimpan; rubrik muncul pada pratinjau Manager dan max point mengikuti nilai template. |
| A08 | Coba menyimpan soal tanpa pertanyaan, tanpa kunci PG, opsi kosong, atau poin negatif. | API menolak dengan pesan validasi; tidak menambah soal. |
| A09 | Gunakan toolbar **B**, **I**, **fx Rumus**, **Gambar**, dan **Tabel** pada Rich Content Editor. Ketik Arab dan aksara Jawa; coba pula shortcut `$x^2$` / `$$...$$`. | Pratinjau menampilkan format, rumus inline/blok, gambar, tabel, Unicode, dan arah Arab dengan benar. HTML/script clipboard yang tidak diizinkan tidak dieksekusi. |
| A10 | Unggah gambar JPG/PNG/WebP dari editor. Untuk audio/video, ketik langsung `Audio: <link Google Drive>` atau `Video: <link Google Drive>` pada pertanyaan/opsi/pasangan; file Drive harus berakses **Siapa saja yang memiliki link / Viewer**. Coba pula URL YouTube/Vimeo atau Google Drive yang formatnya tidak valid. | Gambar tampil normal; audio/video Google Drive menjadi player inline pada Pratinjau. URL selain file Google Drive ditolak. Tidak ada upload audio/video lokal. |
| A11 | Dari **Impor**, unduh **Template Word sesuai Komposisi**. Pastikan jumlah blok/row mengikuti `question_count` dan jumlah pilihan/pasangan mengikuti `option_count`; isi lalu unggah DOCX yang sama. | Parser Word V2 membuat staging tanpa JSON; status valid/invalid muncul per soal dan Bank belum bertambah sebelum commit. |
| A12 | Pada staging, buka **Pratinjau** dan periksa kunci. Perbaiki satu baris invalid lewat JSON, keluarkan baris lain, lalu **Validasi ulang**. | Jumlah valid/invalid diperbarui; baris yang dikeluarkan tidak ikut commit. |
| A13 | Commit staging yang seluruh baris aktifnya valid; buka ulang riwayat job yang sama. | Soal masuk Bank sekali saja dan job berstatus COMMITTED; panggilan commit ulang tidak menggandakan soal. |
| A14 | Dalam template Word, sisipkan gambar JPG/PNG/WebP ke sel pertanyaan lalu unggah. | Gambar menjadi referensi media pada staging dan terlihat saat pratinjau; ikut soal setelah commit. |
| A15 | Dengan jumlah soal belum cukup atau bobot belum 100%, klik **Validasi / Status** di daftar Bank. | READY ditolak dengan rincian ketersediaan per tipe. |
| A16 | Setelah jumlah soal aktif tiap tipe **tepat sama** dengan `question_count`, jumlah pilihan/pasangan setiap soal sesuai `option_count`, dan bobot 100%, klik **Validasi / READY** lalu jadikan READY. | Modal menampilkan ringkasan per tipe dan hasil validasi. Bank menjadi READY dan editor/komposisi terkunci. **Cetak PDF**, **Detail READY**, dan **Kembali ke DRAFT** tetap tersedia. Bila belum dipakai Jadwal/dependency pelaksanaan, Kembali ke DRAFT membuka editor/import lagi tanpa bergantung pada status administratif Kegiatan. |
| A17 | Klik **Cetak PDF** saat DRAFT maupun READY. Lihat semua soal, centang/lepaskan **Sertakan kunci dan rubrik**, lalu gunakan dialog **Cetak / Simpan PDF**. | Tampilan cetak sesuai pratinjau; kunci/rubrik hanya muncul bila dipilih; angka poin ditampilkan ringkas tanpa nol desimal semu (contoh `4`, bukan `4.000`). |
| A18 | Dengan Bank READY, coba POST/PUT/DELETE soal melalui API Manager. | Server menolak perubahan dengan status terkunci, bukan sekadar menyembunyikan tombol. |
| A19 | Akses API peserta yang sudah ada (`/api/ujian` dan konfirmasi ujian). | Tidak ada kunci jawaban, rubrik, atau daftar jawaban diterima pada respons peserta. |
| A20 | Pada salah satu tab Daftar Soal, pilih beberapa checkbox dan **Hapus Terpilih**. | Konfirmasi menampilkan jumlah soal; seluruh soal terpilih terhapus permanen dalam satu aksi dan selection dibersihkan. |
| A21 | Klik **Informasi** pada setiap tab. | Modal menjelaskan aturan tipe aktif, Rich Content, gambar per-field, Equation, Unicode, serta Audio/Video Google Drive. |
| A22 | Klik tombol gambar pada salah satu opsi/pasangan/rubrik, bukan hanya pada Pertanyaan. | Gambar masuk ke field yang dipilih, tampil di Preview, tersimpan, dan muncul kembali saat Edit/Tinjau. |
| A23 | Unduh **Template Excel sesuai Komposisi**. | Workbook berisi PETUNJUK dan hanya sheet tipe aktif; jumlah baris serta pilihan/pasangan mengikuti Komposisi. |
| A24 | Isi Excel untuk beberapa tipe tanpa JSON, termasuk PG Kompleks `A,C`, PG Bertingkat, Menjodohkan, Isian TEKS/ANGKA, dan Uraian. | Upload menghasilkan staging per soal dengan tipe, jawaban, poin, dan mode yang sesuai. |
| A25 | Tempel gambar pada sel Soal, Pilihan, pasangan Menjodohkan, atau Rubrik di Excel lalu upload. | Gambar dipetakan ke konten sel yang sesuai dan tampil pada Pratinjau staging. |
| A26 | Ketik `Audio: <link Google Drive>` / `Video: <link Google Drive>` langsung dalam sel Excel. | Staging dan Pratinjau memakai player yang sama dengan Word/manual. |
| A27 | Masukkan formula Excel seperti `=SUM(A1:A2)` pada area template lalu upload. | Import ditolak dengan pesan bahwa formula Excel tidak boleh digunakan pada template soal. |

## Batas data yang perlu diperhatikan

- Template Word V2 dan Excel V2 sama-sama dibuat dinamis dari Komposisi Bank dan tidak memakai kolom JSON. Excel berisi sheet PETUNJUK + sheet per tipe aktif; satu baris mewakili satu soal.
- Word `.docx` mendukung teks, tebal/miring, tabel dalam sel, gambar JPG/PNG/WebP, Unicode termasuk Arab/Jawa, dan **Insert → Equation** Word/OMML pada sel isi. Equation yang didukung dikonversi ke format rumus internal; shortcut KaTeX `$...$` / `$$...$$` tetap boleh tetapi bukan kewajiban pengguna. Audio/video tidak di-embed ke dokumen: tulis langsung `Audio: <link Google Drive>` atau `Video: <link Google Drive>`. Formatting bold/italic pada field kontrol dinormalisasi sebelum validasi. File `.doc` lama tidak diterima.
- Cetak PDF menggunakan dialog cetak browser, sehingga operator memilih **Save as PDF** pada tujuan cetak.
- Pengujian runtime PHP/database dilakukan setelah perubahan ditarik ke lingkungan lokal yang memiliki PHP dan MySQL.
