# CBT-HERO — PHASE 4D2 Excel Template Acceptance

> **Status pengujian: DEFERRED (keputusan 2026-09-28).** Acceptance disimpan dan akan dijalankan pada akhir pengerjaan Master Ujian Akademis sebelum Master Ujian Psikologi. DEFERRED bukan PASS.

Tanggal baseline: 2026-09-28

## Tujuan

Template Excel harus dapat dipakai guru/operator tanpa mengisi JSON. Struktur workbook
dibuat otomatis dari Komposisi Bank dan hasil impor masuk ke staging yang sama dengan Word.

## Struktur workbook

- Sheet pertama: **PETUNJUK**.
- Sheet berikutnya hanya tipe soal yang aktif pada Komposisi.
- Satu baris pada sheet tipe = satu soal.
- Jumlah baris sama dengan `question_count`.
- Jumlah pilihan/pasangan sama dengan `option_count`.
- Tidak ada kolom `options_json`, `pairs_json`, atau `accepted_values_json`.

## Aturan media

- Gambar: tempel/Insert gambar lalu letakkan pada area sel konten yang dituju.
- Satu sel boleh mempunyai teks + gambar atau beberapa gambar.
- Audio: ketik `Audio: <link Google Drive>` langsung di sel konten.
- Video: ketik `Video: <link Google Drive>` langsung di sel konten.
- Formula Excel (`=...`) tidak digunakan sebagai rumus soal dan harus ditolak.
- Untuk Equation Word native, gunakan template Word; pada Excel rumus dapat berupa teks sederhana atau gambar.

## Acceptance

| ID | Langkah | Hasil yang diharapkan |
|---|---|---|
| X01 | Unduh template Excel dari halaman Impor Bank. | Nama file mengikuti Bank dan workbook dapat dibuka normal di Excel/LibreOffice. |
| X02 | Periksa sheet. | Ada PETUNJUK dan hanya sheet tipe aktif. |
| X03 | Cocokkan jumlah baris/pilihan/pasangan dengan Komposisi. | Struktur sama persis dengan Komposisi. |
| X04 | Isi PG dan tulis satu huruf pada Jawaban Benar. | Staging VALID bila seluruh field benar. |
| X05 | Isi PG Kompleks dan tulis `A,C` pada Pilihan Benar. | A dan C ditandai sebagai jawaban benar. |
| X06 | Isi PG Bertingkat dengan poin tiap pilihan. | Poin maksimum dihitung dari nilai pilihan tertinggi. |
| X07 | Isi Menjodohkan. | Jumlah pasangan dan mode penilaian mengikuti Komposisi; poin maksimum dibaca dari baris. |
| X08 | Isi Isian TEKS dengan beberapa jawaban dipisah baris baru. | Semua jawaban diterima masuk staging. |
| X09 | Isi Isian ANGKA dengan target dan toleransi. | Nilai target/toleransi masuk staging dengan benar. |
| X10 | Isi Uraian + Pedoman Penilaian. | Rubrik dan poin maksimum masuk staging. |
| X11 | Tempel gambar pada Soal dan salah satu Pilihan. | Kedua gambar tampil pada field yang tepat di Pratinjau. |
| X12 | Tempel gambar pada kiri/kanan Menjodohkan dan Rubrik Uraian. | Gambar tetap terikat pada sel yang dituju. |
| X13 | Ketik Audio/Video Google Drive langsung dalam sel. | Player tampil pada Pratinjau setelah staging. |
| X14 | Ketik Arab dan aksara Jawa. | Teks tetap utuh setelah upload/commit. |
| X15 | Isi formula Excel `=SUM(...)`. | Upload ditolak; formula Excel tidak dieksekusi sebagai isi soal. |
| X16 | Biarkan beberapa baris template kosong. | Baris kosong dilewati dan tidak menghasilkan soal invalid. |
| X17 | Commit staging valid. | Soal muncul pada Daftar Soal dan dapat Edit/Tinjau. |

## PASS

Phase 4D2 PASS bila X01–X17 berhasil tanpa perubahan manual pada struktur workbook.
