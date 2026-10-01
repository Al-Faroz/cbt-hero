# CBT-HERO — PHASE 9B–9E Laporan, Export, dan Public Live Scoring Acceptance

Tanggal baseline: 2026-10-01

> **Status pengujian: DEFERRED.**
> Implementasi dilanjutkan sekarang; pengujian fungsional/regression dilakukan pada checkpoint akhir Akademik sebelum Master Ujian Psikologi.

## Scope

Phase ini melengkapi bagian Akademik setelah 9A Hasil Ujian:

- Rekap Nilai — matrix Peserta × Mata Pelajaran berbasis official result;
- Analisis Soal — item-aware dari official result snapshot;
- Export Excel;
- PDF melalui halaman cetak / Save as PDF browser;
- Manager Live Scoring;
- Public Live Scoring fullscreen;
- competition rank dan siklus fetch → scroll penuh → fetch berikutnya.

Database baseline terbaru sudah memiliki `live_scoring_config`. Untuk database lama tersedia:

`docs/CBT-HERO_PHASE_9_REPORT_LIVE_UPGRADE.sql`

## Rekap Nilai

| ID | Langkah | Hasil yang diharapkan |
|---|---|---|
| P9R01 | Buka **Hasil & Laporan → Rekap Nilai**, pilih Kegiatan. | Matrix menampilkan peserta sebagai baris dan mapel/jadwal resmi sebagai kolom. |
| P9R02 | Participant mempunyai Attempt lama lalu hasil replacement Susulan menjadi official. | Hanya snapshot yang ditunjuk `official_result_pointer` masuk matrix. |
| P9R03 | Filter Rombel. | Hanya peserta rombel tersebut tampil. |
| P9R04 | Hasil masih belum final/scoring proses. | Nilai final kosong/— dan UI dapat menandai proses; tidak mengarang nilai. |
| P9R05 | Klik Excel. | XLSX mengikuti Kegiatan/Rombel aktif dan memakai angka nilai numerik. |
| P9R06 | Klik PDF. | Halaman cetak mengikuti filter aktif dan dapat disimpan sebagai PDF dari browser. |
| P9R07 | Kegiatan mempunyai dua root Jadwal untuk Mata Pelajaran bernama sama. | Kolom Rekap dibedakan dengan suffix ID Jadwal pada UI/export sehingga tidak ambigu. |
| P9R08 | Hasil aktif masih BELUM FINAL. | Cell nilai akhir tetap kosong/PROSES dan tidak ikut rata-rata maupun export angka FINAL. |

## Analisis Soal

| ID | Langkah | Hasil yang diharapkan |
|---|---|---|
| P9A01 | Pilih Jadwal MAIN yang mempunyai official result. | Daftar item menampilkan peserta, rata-rata %, indeks skor %, nilai penuh %, skor nol %, dan jumlah VOID. |
| P9A02 | Filter salah satu dari 6 tipe soal. | Hanya tipe tersebut tampil. |
| P9A03 | Buka Detail satu item. | Detail memakai response dari Attempt yang ditunjuk official pointer, bukan Attempt superseded. |
| P9A04 | Ada item VOID. | VOID tidak menambah denominator analisis aktif. |
| P9A05 | Export Excel/PDF. | Data export konsisten dengan filter Jadwal + tipe soal. |
| P9A06 | Uraian belum dinilai / response NEEDS_REVIEW masih ada pada hasil aktif. | Item tersebut masuk hitungan Belum Dinilai tetapi tidak dianggap skor 0 dan tidak masuk denominator rata-rata/indeks/full-score/zero-score. |
| P9A07 | Buka detail question_id yang tidak terdapat pada hasil aktif Jadwal. | API menolak NOT_FOUND, bukan mengembalikan detail kosong. |

Catatan: agregat dihitung langsung dari snapshot resmi sehingga V1 tidak membutuhkan tabel cache agregat/stale flag. Setelah rescore/live edit menghasilkan official snapshot baru, pembacaan berikutnya otomatis memakai pointer terbaru.

## Hasil Ujian Export

| ID | Langkah | Hasil yang diharapkan |
|---|---|---|
| P9E01 | Atur filter pada Hasil Ujian lalu export Excel. | Export mengikuti filter, bukan hanya halaman pagination yang sedang terlihat. |
| P9E02 | Export PDF. | Halaman cetak berisi dataset hasil aktif sesuai filter dan dikirim dengan Cache-Control no-store/private. |
| P9E03 | Dataset Hasil melebihi safety limit export. | Export ditolak dengan EXPORT_TOO_LARGE; tidak membuat file yang terpotong diam-diam. |

## Manager Live Scoring

| ID | Langkah | Hasil yang diharapkan |
|---|---|---|
| P9L01 | Buka **Pelaksanaan Ujian → Live Scoring**. | Pilihan Jadwal akademik tersedia; instrumen psikologis tidak tersedia. |
| P9L02 | Pilih Jadwal lalu START. | State ON, URL random tersedia, layar publik dapat dibuka. |
| P9L03 | STOP. | Public endpoint/token menolak akses baru dan layar aktif berhenti pada fetch berikutnya. |
| P9L04 | Ganti URL. | Token version naik; URL lama langsung tidak berlaku. |
| P9L05 | START kembali tanpa regenerate. | URL yang masih aktif dapat dipakai kembali. |

## Public Live Scoring

Kolom harus tepat:

`No | No Peserta | Nama | Skor | Status`

| ID | Langkah | Hasil yang diharapkan |
|---|---|---|
| P9L06 | Peserta mulai mengerjakan. | Status MENGERJAKAN tampil. |
| P9L07 | Peserta selesai. | Status berubah menjadi SELESAI. |
| P9L08 | Ada jawaban belum diisi. | Nilainya sementara 0 untuk item tersebut. |
| P9L09 | Dataset mempunyai nilai sama. | Competition rank: skor sama mendapat rank sama; urutan sekunder stabil berdasarkan No Peserta; tidak ada tie-break waktu. |
| P9L10 | Audit formula skor. | Hanya PG, PG Kompleks, PG Bertingkat, dan Menjodohkan yang dihitung; denominator sama dengan kelompok Klik pada Halaman Selesai. |
| P9L11 | Dataset lebih tinggi daripada viewport. | Snapshot tidak diganti selama scroll; data discroll sampai baris terakhir baru fetch berikutnya. |
| P9L12 | Dataset muat satu layar. | Sistem menunggu satu siklus wajar sebelum fetch baru; tidak polling agresif. |
| P9L13 | Audit payload public. | Tidak ada peserta_id, attempt_id, jawaban, kunci, rombel, atau data internal lain; hanya konteks display dan lima field tabel. |
| P9L14 | ±2.000 peserta. | Satu snapshot menggunakan query agregat, bukan query per peserta/N+1. |

## Gate

Status Phase 9B–9E saat implementasi: **IMPLEMENTED / ACCEPTANCE DEFERRED**.

Phase 9 baru menjadi **PASS** setelah Phase 9A dan seluruh acceptance di dokumen ini diuji bersama regression Akademik end-to-end. Master Ujian Psikologi tetap belum boleh dimulai sebelum itu.
