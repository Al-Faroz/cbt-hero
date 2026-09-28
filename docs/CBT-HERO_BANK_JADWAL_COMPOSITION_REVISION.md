# Revisi Komposisi Bank dan Pengambilan Soal Jadwal

## Keputusan

- Bank berisi seluruh soal yang disusun. `question_count` per tipe pada Komposisi
  adalah target jumlah soal aktif Bank, bukan jumlah yang dipilih peserta.
- `option_count` menentukan jumlah pilihan untuk PG, PG Kompleks, dan PG Bertingkat,
  serta jumlah pasangan untuk Menjodohkan. Batasnya PG 2–6, PG Kompleks/PG
  Bertingkat 2–8, dan Menjodohkan 2–12.
- Jadwal akademik menentukan `selection_count` per tipe. Nilainya positif,
  tidak melebihi soal aktif di Bank, dan menjadi jumlah yang diambil untuk
  setiap peserta saat preparation. Susulan memakai komposisi Jadwal utama.
- Bobot dan aturan penilaian tipe tetap berada di Bank. Semua tipe dengan bobot
  aktif perlu dipilih pada Jadwal supaya perhitungan nilai tidak ambigu.

Contoh: Bank memiliki 30 PG dan 10 PG Kompleks. Jadwal memilih 15 PG dan
5 PG Kompleks. Unduhan template Bank kelak membuat 30 blok PG dan 10 blok
PG Kompleks, dengan banyak pilihan menurut `option_count` masing-masing.

## Ruang Lingkup Revisi Ini

Halaman Komposisi, kontrak API Bank, skema dan pemeriksaan READY memakai
`question_count`/`option_count`. Editor manual dan validasi impor memeriksa
jumlah pilihan terhadap Komposisi. Migrasi SQL untuk database yang sudah ada
terdapat pada `CBT-HERO_BANK_COMPOSITION_UPGRADE.sql`.

Generator Word dinamis dan parser Word manusiawi `CBT-HERO-WORD-V2` sudah
diimplementasikan pada Phase 4D1. Template Word mengikuti `question_count` dan
`option_count` Komposisi serta dapat round-trip melalui staging tanpa kolom JSON.
Template/parser Excel masih memakai format teknis 13 kolom dan dikerjakan pada
Phase 4D2. Modul Jadwal belum diimplementasikan; tabel `jadwal_type_selection`
sudah disiapkan agar pengambilan soal per tipe tetap mengikuti keputusan ini.

## Pemeriksaan Revisi Komposisi

| ID | Langkah | Hasil |
| --- | --- | --- |
| K01 | Di Bank DRAFT, isi PG 4 soal dan 4 pilihan, PG Kompleks 2 soal dan 5 pilihan, serta Menjodohkan 1 soal dan 6 pasangan. Simpan dan muat ulang. | Nilai tetap tersimpan; label UI menyebut jumlah soal Bank dan jumlah pilihan/pasangan. |
| K02 | Isi PG 7 pilihan, Menjodohkan 13 pasangan, atau jumlah soal Bank 0 melalui API. | Ditolak 422; konfigurasi sebelumnya tidak berubah. |
| K03 | Buat empat PG dengan empat opsi dan dua PG Kompleks dengan lima opsi; bobot tepat 100%. Validasi READY. | READY lolos bila persyaratan lain lengkap. |
| K04 | Coba READY saat salah satu tipe memiliki jumlah soal lebih sedikit atau lebih banyak daripada `question_count`, atau jumlah pilihan/pasangan tidak sesuai `option_count`. | READY ditolak dengan rincian tipe. |
| K05 | Ubah jumlah pilihan Komposisi saat soal sudah ada; coba edit soal mengikuti jumlah baru. | Editor mengikuti Komposisi; soal lama yang belum disesuaikan mencegah READY. |

Pada tahap Jadwal, tambahkan pemeriksaan 40 soal Bank → 20 soal Jadwal serta
penolakan `selection_count` yang melampaui jumlah tersedia. Pada tahap template,
uji jumlah blok/baris yang diunduh dan round trip impor sebelum menyatakan fitur
siap dipakai operator.

Catatan UI lanjutan: audit dan rapikan tampilan DataTables pada mobile mengikuti
`CBT-HERO_UI_STANDARD_FINAL.md`. Pekerjaan responsive DataTables tidak termasuk
revisi komposisi ini dan dikerjakan pada fase polish UI.
