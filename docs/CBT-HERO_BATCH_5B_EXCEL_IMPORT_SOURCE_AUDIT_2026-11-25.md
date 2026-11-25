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
