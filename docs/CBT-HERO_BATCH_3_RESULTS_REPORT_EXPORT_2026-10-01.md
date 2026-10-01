# CBT-HERO — Batch 3 Hasil / Report / Export

Tanggal: 2026-10-01

> **Status: IMPLEMENTED / STATIC PASS / FUNCTIONAL REGRESSION DEFERRED**
>
> Batch ini membahas Hasil Ujian, Rekap Nilai, Analisis Soal, serta export
> Excel/PDF akademik. Functional regression tetap dilakukan pada checkpoint akhir
> Master Ujian Akademik.

## 1. Invariant sumber laporan

Seluruh laporan akademik tetap memakai:

```text
official_result_pointer
        ↓
result_snapshot
        ↓
result_item_snapshot
```

Attempt historis/SUPERSEDED tidak dibaca sebagai hasil utama.

Istilah UI diperjelas:
- **hasil aktif/terpilih** = snapshot yang sedang ditunjuk official_result_pointer;
- **FINAL** = snapshot dengan `result_snapshot.is_final = 1`;
- hasil aktif belum tentu FINAL.

## 2. Hasil Ujian

`HasilUjianService`:
- list hanya berasal dari official pointer;
- filter Kegiatan/root Jadwal/Mapel/Rombel/scoring/final/search tetap authoritative;
- detail hanya dapat dibuka jika snapshot masih ditunjuk official pointer;
- scoring revision item dibaca dari payload snapshot, fallback baseline hanya untuk snapshot lama.

### Perbaikan boolean FINAL

Bug ditemukan pada Detail Hasil:
- list sudah cast `is_final` ke boolean;
- endpoint detail belum;
- string DB `"0"` dapat dianggap truthy oleh JavaScript.

Perbaikan:
- `result_snapshot_id`, `attempt_id`, `root_jadwal_id`, `jadwal_id` dinormalisasi integer;
- `is_final` dinormalisasi boolean sebelum JSON response.

## 3. Pending item bukan skor nol

Snapshot provisional dapat mempunyai:
- `PENDING`;
- `PENDING_MANUAL`;
- `NEEDS_REVIEW`.

Raw score sementara dapat bernilai 0 secara internal, tetapi angka tersebut bukan
nilai final item.

Perbaikan Hasil Detail:
- `scoring_state` diekspos dari payload immutable `result_item_snapshot`;
- item pending tampil `—`;
- status tampil **BELUM DINILAI**;
- VOID tetap tampil VOID.

Dengan demikian Operator tidak membaca Uraian belum dikoreksi sebagai skor 0.

## 4. Rekap Nilai

Rekap tetap berbasis official pointer.

Aturan:
- hanya `is_final = 1` yang menghasilkan `final_score` pada matrix;
- hasil BELUM FINAL tampil kosong/PROSES;
- hasil BELUM FINAL tidak ikut rata-rata;
- export Rekap hanya menulis angka untuk hasil FINAL.

Hardening:
- opsi Rombel dibatasi ke `result_type = ACADEMIC`;
- hasil psikologis masa depan tidak dapat mencemari filter Rekap Akademik;
- bila terdapat dua root Jadwal dengan nama Mapel sama, label UI/export memakai
  suffix `#root_jadwal_id`.

## 5. Analisis Soal

Bug ditemukan pada denominator Analisis.

Sebelumnya:
- item `PENDING_MANUAL` / `NEEDS_REVIEW` mempunyai raw score sementara 0;
- agregat SQL memasukkannya ke scored_count;
- average / difficulty / zero-score rate dapat turun secara palsu.

Sekarang item scorable adalah:

```text
voided = 0
AND scoring_state NOT IN
(PENDING, PENDING_MANUAL, NEEDS_REVIEW)
```

Item pending:
- tetap dihitung pada `participant_count`;
- masuk `pending_count`;
- tidak masuk denominator average;
- tidak masuk raw/max total;
- tidak masuk full-score rate;
- tidak masuk zero-score rate.

UI dan export menampilkan kolom **Belum Dinilai**.

Jika metric belum mempunyai denominator valid, UI menampilkan `—`, bukan `—%`.

## 6. Detail Analisis

Hardening:
- question_id yang tidak terdapat pada hasil aktif root Jadwal ditolak NOT_FOUND;
- detail response tetap berasal dari Attempt yang ditunjuk official pointer;
- scoring revision diambil dari payload snapshot;
- item pending menampilkan skor `—`, bukan 0;
- question_html ditampilkan sebagai teks yang dapat dibaca, bukan tag HTML mentah.

## 7. Snapshot revision consistency

Hasil Detail dan Analisis menggunakan:
- `result_item_snapshot.payload_json.scoring_revision_id`;
- fallback `prepared_assignment_item.soal_revision_id` hanya untuk snapshot lama.

Dengan demikian Live Edit sebelum FINAL tidak membuat report memakai revision
terbaru secara membabi buta.

## 8. Export Hasil

Sebelumnya export Hasil:
- membaca service list per 100 row;
- menggabungkan banyak page;
- mempunyai hard stop 500 page;
- secara teori dataset dapat berubah di tengah export atau terpotong diam-diam.

Sekarang:
- `HasilUjianService::exportRows()` mengambil dataset melalui satu query terurut;
- safety limit = 50.000 row;
- bila melebihi limit, response `EXPORT_TOO_LARGE`;
- tidak ada silent truncation.

Target CBT-HERO sekitar 2.000 peserta jauh di bawah safety limit tersebut.

## 9. Excel

XLSX:
- mengikuti filter aktif;
- angka nilai ditulis sebagai numeric cell, bukan string;
- decimal internal OpenXML tetap canonical;
- Excel/locale pengguna dapat menampilkan decimal comma;
- header dan matrix mengikuti dataset service yang sama dengan UI.

Tidak ada SQL/migrasi baru.

## 10. PDF / Print

PDF V1 tetap mengikuti keputusan:
- server menghasilkan halaman print;
- browser menjalankan Cetak / Save as PDF.

Hardening privacy:
- `Cache-Control: no-store, private`;
- `Pragma: no-cache`;
- `X-Content-Type-Options: nosniff`.

Semua value tabel print tetap di-escape.

## 11. Asset deployment

Repo mempunyai copy:

```text
assets/js/manager-results.js
public/assets/js/manager-results.js
```

Karena Batch 3 mengubah result UI, copy public disinkronkan kembali agar deployment
CI4 standar tidak menjalankan script lama.

## 12. Wording FINAL vs active

Wording Manager/report diperjelas:
- Hasil Ujian = hasil aktif yang ditunjuk sistem;
- FINAL/BELUM FINAL adalah status terpisah;
- Rekap hanya memakai nilai FINAL;
- Analisis pending tidak dianggap nol.

Ini mencegah istilah "official pointer" dibaca sebagai "sudah final".

## 13. Acceptance ditambah

### Phase 9A
- R13 detail BELUM FINAL tidak salah menjadi FINAL;
- R14 pending item tampil BELUM DINILAI;
- R15 export Hasil memakai single-query dataset.

### Phase 9 Report/Export
- P9R07 duplicate Mapel label tidak ambigu;
- P9R08 BELUM FINAL tidak ikut rata-rata;
- P9A06 pending item keluar dari denominator;
- P9A07 question detail di luar hasil Jadwal → NOT_FOUND;
- P9E02 printable report no-store/private;
- P9E03 dataset terlalu besar ditolak, bukan dipotong.

## 14. Static validation

JavaScript syntax:
- `assets/js/manager-results.js` → OK;
- `public/assets/js/manager-results.js` → OK;
- `assets/js/manager-rekap-nilai.js` → OK;
- `assets/js/manager-analisis-soal.js` → OK.

PHP brace balance:
- HasilUjianService → 0;
- RekapNilaiService → 0;
- AnalisisSoalService → 0;
- ReportExportService → 0;
- ReportExportController → 0.

Static sweep memastikan semua report akademik utama masih berangkat dari
`official_result_pointer`.

## 15. Yang belum diklaim PASS

Masih DEFERRED:
- actual PHP/MySQL execution;
- dataset nyata > pagination;
- Excel dibuka di Microsoft Excel/LibreOffice;
- Save as PDF browser nyata;
- replacement Susulan end-to-end;
- concurrent scoring saat export;
- pending Uraian nyata;
- mobile report regression;
- full regression Phase 9.

Status Batch 3:

**IMPLEMENTED / STATIC PASS / FUNCTIONAL REGRESSION DEFERRED**

## 16. Batch berikutnya

Setelah checkpoint ini:

**Batch 4 — Live Scoring**

Fokus:
- manager START/STOP/regenerate URL;
- public token boundary;
- lima kolom public;
- click-only score;
- competition rank;
- scroll-cycle snapshot;
- performance / N+1;
- privacy payload.
