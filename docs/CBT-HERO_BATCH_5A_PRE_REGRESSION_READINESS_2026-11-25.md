# CBT-HERO — Batch 5A Pre-Regression Readiness

Tanggal: 2026-11-25

> **Status: IMPLEMENTED / STATIC PASS / FUNCTIONAL REGRESSION DEFERRED**
>
> Batch 5A menutup static readiness dan polish kecil yang tertunda. Tidak ada
> acceptance browser, database, Excel/LibreOffice, mobile-device, concurrency,
> atau load test yang diklaim PASS.

## 1. Baseline dan scope

Baseline sebelum melanjutkan pekerjaan:
- Branch: `main`
- HEAD: `6e3803930b34e1e43e21de84c7a1288ce01afb7d`
- Checkpoint terakhir: Batch 4 Live Scoring, 2026-10-01.

Batch 5A mencakup:
1. inventaris kompatibilitas SQL upgrade terhadap schema baseline;
2. read-only schema preflight;
3. polishing tabel Jadwal Ujian;
4. responsive scroll untuk tabel modal Susulan;
5. hardening edge case parser Excel;
6. penegasan urutan acceptance yang masih DEFERRED.

## 2. SQL upgrade inventory

### Schema baseline

`docs/CBT-HERO_SCHEMA_v1.0.sql` saat ini berisi 53 tabel dan sudah memuat
struktur yang dibutuhkan code-level Master Ujian Akademik terbaru, termasuk:

- Bank composition: `question_count`, `option_count`, `jadwal_type_selection`;
- Susulan: `jadwal_peserta_target`, `jadwal_operations`;
- Preparation: `prepared_assignment`, `prepared_assignment_item`,
  `prepared_assignment_media`;
- Attempt Engine: `participant_operations`, `attempt_active_lock`,
  `attempt_response`;
- Execution: `attempt_control_operations`, `token_control.next_token`;
- Scoring/report: `result_snapshot`, `result_item_snapshot`,
  `official_result_pointer`;
- Live Scoring: `live_scoring_config`;
- final Jadwal order: `jadwal.urutan_ujian` + `idx_jadwal_slot_order`;
- credential revision dan membership source.

### Keputusan untuk database fresh

Jika database dibuat dari schema baseline tersebut:

**Tidak perlu menjalankan file SQL upgrade Phase 2–9 satu per satu.**

File upgrade lama bukan migration chain yang aman dijalankan secara borongan.
Beberapa script menambahkan kolom/tabel yang sudah tersedia di baseline dan akan
gagal dengan duplicate column/index atau hanya mengulang pekerjaan.

### Keputusan untuk database lama

Database lama harus diperiksa dahulu. File upgrade yang masih mungkin relevan
bergantung pada struktur aktual:

| File | Fungsi | Risiko / kondisi |
|---|---|---|
| `tools/phase2a_context_migration.sql` | Mengubah relasi Periode menjadi Tahun/Semester pada Kegiatan | Destruktif: menghapus FK, kolom `periode_id`, dan tabel `periode`; hanya untuk schema lama yang masih memakai Periode |
| `tools/phase2_peserta_completion.sql` | Credential revision + credential operations | Kolom ditambahkan kondisional; tetap periksa schema lama |
| `tools/phase2d_credential_revision.sql` | Credential revision versi lama | ADD COLUMN tanpa guard; jangan jalankan jika kolom sudah ada |
| `tools/phase3b_membership_source.sql` | assignment_source/scope/index | ADD COLUMN/index tanpa guard |
| `docs/CBT-HERO_BANK_COMPOSITION_UPGRADE.sql` | question_count/option_count/jadwal_type_selection | Tidak idempotent; hanya untuk schema komposisi lama |
| `docs/CBT-HERO_MATCHING_OPTION_COUNT_UPGRADE.sql` | Mengisi option_count Matching yang NULL | Hanya untuk schema lama yang sudah punya kolom option_count |
| `docs/CBT-HERO_PHASE_5B_JADWAL_OPERATIONS_UPGRADE.sql` | jadwal_operations | CREATE IF NOT EXISTS |
| `docs/CBT-HERO_PHASE_6_ATTEMPT_ENGINE_UPGRADE.sql` | participant_operations | CREATE IF NOT EXISTS |
| `docs/CBT-HERO_PHASE_7_EXECUTION_UPGRADE.sql` | token_control.next_token + attempt_control_operations | ADD COLUMN tidak ber-guard; hanya bila next_token belum ada |
| `docs/CBT-HERO_PHASE_9_REPORT_LIVE_UPGRADE.sql` | live_scoring_config | Compatibility script; tidak diperlukan jika tabel sudah ada |
| `docs/CBT-HERO_ACADEMIC_FINAL_JADWAL_ORDER_UPGRADE.sql` | urutan_ujian + index | ADD COLUMN/index tidak ber-guard; hanya bila keduanya belum ada |

Perbedaan legacy `credential_revision` INT/BIGINT juga harus dipertimbangkan;
keberadaan kolom saja belum cukup untuk menyimpulkan script mana yang sesuai.

### Read-only preflight

Ditambahkan:

`tools/academic_schema_preflight.sql`

Script hanya membaca `information_schema` untuk memeriksa tabel, kolom, dan
index kunci. Script tidak menjalankan ALTER/CREATE/DROP/INSERT/UPDATE/DELETE.

**Belum ada SQL upgrade yang dijalankan pada database pengguna.** Hasil preflight
pada database lokal diperlukan sebelum menentukan migration yang benar.

## 3. Polishing tabel Jadwal Ujian

Temuan deferred pada `CBT-HERO_UI_STANDARD_FINAL.md` ditangani pada source-level:

- Kegiatan dan identitas Bank/Mapel digabung menjadi satu kolom konteks;
- Urutan dan Durasi digabung menjadi satu kolom;
- Soal Diambil menampilkan ringkasan total + jumlah tipe; rincian per tipe dibuka
  melalui disclosure;
- Preparation tetap aksi langsung;
- Buka/Tahan tetap aksi langsung jika editable;
- Susulan, Perpanjang Batas Mulai, Tambah Waktu, Nilai Selesai ON/OFF, Edit,
  dan Hapus dikelompokkan pada disclosure Aksi;
- disclosure Aksi berada dalam alur row, sehingga tidak terpotong overlay dropdown
  oleh overflow wrapper;
- lebar kolom diatur agar desktop lebih padat; viewport lebih sempit tetap memakai
  horizontal scroll lokal.

Tidak ada perubahan API/backend.

Acceptance baru: J21 pada `CBT-HERO_PHASE_5A_JADWAL_ACCEPTANCE.md`.

Status: **IMPLEMENTED / VISUAL REGRESSION DEFERRED**.

## 4. Tabel modal Susulan

Dua tabel yang berada di luar cakupan selector global section card sekarang
memakai wrapper khusus:

- daftar kandidat peserta pada modal Tambah/Edit Susulan;
- daftar target pada modal Target Susulan.

Wrapper:
- memiliki horizontal scroll lokal;
- memiliki minimum width yang sesuai pada mobile;
- dapat difokuskan keyboard;
- tidak seharusnya memperlebar halaman/modal.

Acceptance baru: S23–S24 pada
`CBT-HERO_PHASE_5B_SUSULAN_OPERASIONAL_ACCEPTANCE.md`.

Status: **IMPLEMENTED / VISUAL REGRESSION DEFERRED**.

## 5. Excel template/parser hardening

Static review mengonfirmasi implementasi dynamic Excel:

- workbook dibuat dari Komposisi Bank;
- sheet PETUNJUK + sheet hanya untuk tipe aktif;
- enam tipe soal didukung;
- upload XLSX masuk staging yang sama dengan Word;
- formula Excel ditolak;
- gambar dipetakan dari drawing anchor ke sel;
- Audio/Video Google Drive dibaca sebagai directive teks;
- batas upload 5 MB, maksimal 200 soal, dan expanded ZIP size dibatasi.

Gap parser yang ditemukan dan diperbaiki:

1. PG Kompleks sebelumnya mengabaikan huruf jawaban yang tidak valid. Sekarang huruf
   di luar jumlah opsi, token multi-huruf, dan duplikasi ditolak.
2. Mode Isian Singkat yang tidak dikenal sebelumnya otomatis dianggap TEXT. Sekarang
   hanya TEKS/TEXT/T dan ANGKA/NUMERIC/N yang diterima.
3. Isi sel non-kosong di luar area template yang didukung sekarang ditolak, bukan
   diabaikan diam-diam.
4. Drawing/image anchor di luar area template sekarang ditolak sebelum dipetakan.

Acceptance tambahan:
- X18: huruf PG Kompleks invalid;
- X19: mode Isian invalid;
- X20: cell/image anchor di luar batas;
- X21: beberapa gambar + teks pada sel yang sama.

Dokumen: `CBT-HERO_PHASE_4D2_EXCEL_TEMPLATE_ACCEPTANCE.md`.

Status: **IMPLEMENTED / STATIC REVIEW COMPLETE / EXCEL REGRESSION DEFERRED**.

## 6. Static validation

- `assets/js/manager-jadwal.js`: JavaScript syntax OK;
- `assets/js/manager-susulan.js`: JavaScript syntax OK;
- `assets/css/manager-ui-standard.css`: brace balance 0;
- `QuestionDocumentService.php`: brace balance 0;
- view Jadwal/Susulan: brace balance 0;
- Jadwal view header = 9 kolom, JS empty row colspan = 9;
- old selection label wrapping dan overlay dropdown tidak lagi dipakai;
- read-only preflight: hanya statement SELECT setelah komentar diabaikan.

Catatan keterbatasan:
- GitHub repository access tidak menjalankan PHP lint/runtime atau browser;
- test suite repo saat ini belum memiliki domain PHPUnit tests untuk Academic flow;
- tidak ditemukan GitHub Actions workflow pada repository tree.

## 7. Yang tetap DEFERRED

Belum diuji:
- SQL preflight pada database lokal pengguna;
- migration/upgrade legacy jika memang diperlukan;
- X01–X21 dengan Excel/LibreOffice;
- J01–J21 dan S01–S24 melalui browser;
- Preparation chunk/resume pada data nyata;
- participant START/RESUME, answer sync, offline/reconnect, timer, submit;
- PRM01–PRM20 pada desktop/mobile nyata;
- Monitoring/token/reset/time/force finish;
- scoring/manual override/Live Edit/VOID/Rescore/finalisasi;
- hasil/reports/export/PDF/Live Scoring;
- end-to-end ±1.500 peserta;
- load 500/1.000/1.500/2.000, stress 2.500, stretch 3.000.

## 8. Urutan kerja setelah Batch 5A

Berdasarkan dokumen deferred acceptance (keputusan 2026-09-28), pengujian
terpadu dimulai dari Phase 4D2, bukan mengulang Phase 2–3 yang sebelumnya telah
melewati acceptance:

1. **Batch 5B:** Phase 4D2 Excel Template (X01–X21).
2. **Batch 5C:** Phase 5A Jadwal, Phase 5B Susulan/operasional, Phase 5C Preparation.
3. **Batch 5D:** Phase 6 Attempt Engine + Participant Renderer/Mobile PRM01–PRM20.
4. **Batch 5E:** Phase 7 Execution + Phase 8 Scoring/Finalisasi.
5. **Batch 5F:** Phase 9 Hasil/Report/Export/Live Scoring.
6. **Batch 5G:** End-to-end Akademik + Phase 12 performance/load regression.

Master Ujian Psikologi tetap terkunci sampai seluruh acceptance/regression
Master Ujian Akademik berstatus PASS.

## 9. Next action

1. Pull branch `main`.
2. Jalankan `tools/academic_schema_preflight.sql` pada database `cbt_hero`
   (read-only) dan kirim hasilnya.
3. Setelah schema cocok, mulai Batch 5B — X01–X21.

Status Batch 5A:

**IMPLEMENTED / STATIC PASS / FUNCTIONAL REGRESSION DEFERRED**
