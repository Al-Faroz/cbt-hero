# CBT-HERO — Batch 5B Excel Import Source Audit

Tanggal: 2026-11-25  
Branch: `feat/client-fullscreen-guard-psychology`  
Status: **implementasi hardening parsial; acceptance X01–X21 tetap DEFERRED**.

## Konteks

Dokumen acceptance Phase 4D2 tetap menjadi acuan perilaku Excel. Keputusan 2026-09-28 menunda seluruh acceptance fungsional/browser sampai implementasi Master Ujian Akademik dan Psikologi selesai. Pemeriksaan ini bersifat source-level dan tidak mengubah status pengujian menjadi PASS.

## Temuan yang diperbaiki

### EXCEL-MEDIA-01 — impor gambar berulang ketika deteksi format workbook

Sebelumnya `QuestionDocumentService::xlsx()` memanggil `xlsxSheetValues()` sambil memberikan callback impor gambar hanya untuk membaca marker template. Setelah marker ditemukan, `humanExcel()` membaca sheet lagi dan menjalankan callback yang sama. Akibatnya gambar yang sama dapat dibuat menjadi dua aset media, sedangkan aset dari pembacaan pertama tidak direferensikan oleh payload staging.

### EXCEL-MEDIA-02 — gambar di sheet PETUNJUK / sheet non-soal

Sebelumnya `humanExcel()` memproses drawing sebelum memastikan bahwa sheet mempunyai marker tipe soal yang didukung. Gambar pada sheet petunjuk atau sheet tambahan yang tidak menjadi sumber soal dapat masuk ke penyimpanan media tanpa referensi staging.

### Perubahan

- Pembacaan awal marker workbook/sheet dilakukan tanpa memproses drawing.
- Hanya sheet dengan marker `CBT-HERO-EXCEL-V2` dan tipe soal yang dikenali yang dibaca ulang dengan callback impor gambar.
- Gambar pada sheet petunjuk dan sheet non-soal tidak dibuat menjadi aset media.
- Setiap gambar pada sheet soal hanya diproses pada tahap parsing payload, bukan pada tahap deteksi format.

File:
- `app/Services/QuestionDocumentService.php`

Commit:
- `239ab403e7e79aa0a0752ddb83d032afe05c4be2`
- `78674a77b34dd974b65e2c905c50b7479b6acef9`

## Batasan status

- Belum menjalankan PHP lint, PHPUnit, atau fixture XLSX aktual.
- X01–X21 tetap DEFERRED; perubahan ini bukan klaim acceptance PASS.
- Perlu pemeriksaan lanjutan terhadap struktur drawing Excel yang tidak didukung, formula/sel di luar rentang, dan konsistensi parser dengan validasi komposisi sebelum Batch 5B dinyatakan selesai.
- Tidak ada perubahan skema database pada bagian ini.

## Langkah berikutnya

1. Audit semua cabang parser Excel dan validator terhadap X01–X21, termasuk kasus workbook rusak atau struktur drawing yang tidak didukung.
2. Cocokkan parser dengan `QuestionValidationService`, `AdvancedQuestionService`, dan renderer agar payload hasil impor tidak berubah makna saat commit.
3. Periksa alur cleanup media saat parse gagal, staging gagal, item dikeluarkan, dan job dibatalkan.
4. Lanjutkan ke hardening source-level yang terukur; acceptance fungsional tetap dilakukan pada Ujicoba Menyeluruh setelah implementasi Akademik dan Psikologi selesai.


## Temuan lanjutan yang masih terbuka

### EXCEL-MEDIA-03 — siklus hidup media staging belum memiliki kepemilikan eksplisit

Parser membuat record `media_assets` berstatus ACTIVE sebelum staging job selesai digunakan. Ketika baris dikeluarkan dari staging atau job ditinggalkan sebelum commit, belum terlihat mekanisme yang mengikat aset hasil impor ke pemilik/job dan membersihkannya dengan aman.

Pembersihan berdasarkan token saja belum aman: payload hasil perbaikan operator dapat mereferensikan aset yang sudah ada, dan satu aset mungkin dipakai lebih dari satu baris atau revisi. Skema saat ini belum menunjukkan relasi eksplisit `import_job_media` / `import_staging_media` yang membuktikan bahwa aset dimiliki oleh satu job impor.

**Keputusan:** jangan menghapus aset secara heuristik. Sebelum cleanup otomatis dibuat, audit skema `media_assets`, tambahkan kepemilikan staging yang eksplisit atau mekanisme referensi yang setara, lalu pastikan rollback/commit/exclude/job-retention tidak menghapus media yang telah dipakai soal lain. Ini dicatat sebagai gap hardening, bukan disamarkan sebagai selesai.


### EXCEL-STRUCTURE-01 — sheet tambahan / tidak dikenal sebelumnya diabaikan

Parser sebelumnya hanya memproses sheet dengan marker tipe soal yang dikenal dan melewati sheet lain. Workbook yang ditambah sheet non-template atau menggandakan sheet tipe soal berpotensi tetap diterima sementara isinya tidak ikut staging.

Perbaikan source-level:
- Memastikan sheet pertama bernama `PETUNJUK` dan memiliki marker workbook resmi.
- Menolak gambar pada sheet `PETUNJUK`; gambar hanya diperbolehkan pada sheet soal aktif.
- Menolak sheet tambahan atau sheet soal tanpa marker resmi.
- Menolak duplikasi sheet untuk tipe soal yang sama.
- Deteksi marker tetap dilakukan tanpa mengimpor gambar; gambar baru diproses setelah sheet soal dikenali.

File: `app/Services/QuestionDocumentService.php`  
Commit: `2685bee0e8912fcf076b2eb3c5f7e052dfe8a9bb`

Catatan: validasi ini menjaga struktur workbook yang dikenali. Kecocokan tipe/opsi dengan Komposisi tetap diperiksa pada tahap validasi staging. Acceptance X01–X21 tetap DEFERRED.


## Temuan lanjutan: media cleanup dan staging

### EXCEL-MEDIA-04 — cleanup harus menghormati referensi aktif

Audit menemukan `QuestionMediaService::discardImport()` sebelumnya langsung menghapus row `media_assets` dan file lokal berdasarkan ID, tanpa memeriksa apakah aset telah dipakai oleh revisi soal atau masih direferensikan staging lain.

Perbaikan:
- Cleanup sekarang hanya untuk aset lokal dengan path valid.
- Pengecekan dilakukan terhadap `soal_revision_media` dan token media pada seluruh `import_staging_items.payload_json`.
- Sebelum penghapusan dilakukan transaksi dan pemeriksaan ulang setelah row aset dikunci.
- Jika masih ada referensi atau terjadi kegagalan, cleanup dilewati; file tidak dihapus.

File: `app/Services/QuestionMediaService.php`  
Commit: `824ec46cad8c97634a56d64f6b5fd1cc06bb66e0`

**Batasan kepemilikan tetap ada:** skema belum membedakan aset yang dibuat oleh import job dari aset lokal yang diunggah operator. Karena itu cleanup saat operator mengubah/mengeluarkan staging row tidak boleh dilakukan hanya berdasarkan token lama; aset bisa merupakan upload manual atau dipakai ulang. Percobaan menambahkan cleanup otomatis saat FIX dibatalkan agar tidak menghapus aset tanpa bukti ownership. Relasi ownership job-media tetap menjadi pekerjaan desain terpisah.

### EXCEL-IMPORT-01 — parameter Idempotency-Key pada commit

`QuestionImportService::commit()` memvalidasi format `Idempotency-Key`, tetapi tidak menyimpan key tersebut. Duplikasi commit saat job sudah COMMITTED tetap tidak membuat soal ganda karena status job dikunci dan diperiksa, tetapi kontrak idempotensi per-key tidak diterapkan. Ini perlu diselaraskan dengan kontrak API: bila idempotensi memang berbasis job-state, dokumentasikan demikian dan jangan mengiklankan key sebagai deduplikasi; bila berbasis key, simpan key + hasil secara atomik pada schema upgrade. Belum diubah pada batch ini agar tidak menambah migrasi tanpa audit kontrak.

## Status audit saat ini

- Parser workbook: hardening struktur sheet dan pembatasan media sudah diterapkan.
- Media cleanup pada jalur gagal: sekarang memeriksa referensi aktif sebelum menghapus.
- Media ownership per import job: belum tersedia; cleanup otomatis untuk FIX/EXCLUDE/job retention ditahan.
- Commit idempotency key: format diperiksa, key belum dipersistenkan.
- X01–X21 tetap DEFERRED; tidak ada PHP lint, PHPUnit, fixture XLSX, atau browser test yang diklaim PASS.


### EXCEL-MODE-01 — marker mode Menjodohkan tidak boleh fallback diam-diam

Parser sebelumnya mengubah semua nilai marker selain `ALL_OR_NOTHING` menjadi `PARTIAL`. Marker rusak/tidak dikenal dapat lolos jika Komposisi Bank kebetulan menggunakan PARTIAL.

Perbaikan: marker sekarang wajib tepat `PARTIAL` atau `ALL_OR_NOTHING`; nilai lain ditolak saat parsing.  
File: `app/Services/QuestionDocumentService.php`  
Commit: `08c31ed2f9f5755b77d0b6e56f97cd427e8d49ec`

Acceptance X01–X21 tetap DEFERRED.
