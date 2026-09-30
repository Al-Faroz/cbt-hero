# CBT-HERO — AUDIT SINKRONISASI DOKUMEN & IMPLEMENTASI

Tanggal audit: 2026-09-29

Dokumen ini mencatat hasil pembacaan ulang `docs/` terhadap implementasi repo
setelah pengujian Akademik end-to-end mulai dijalankan. Tujuannya adalah membedakan:

- requirement yang **sudah berubah dan sudah disinkronkan**;
- requirement yang **ada di dokumen tetapi implementasinya terlewat**;
- modul yang **memang belum dikerjakan**;
- keputusan baru yang **belum dikunci ke schema/API**.

## 1. Perubahan yang sudah disinkronkan

### 1.1 Kegiatan bukan sakelar ON/OFF ujian

Keputusan final operasional:

```text
Kegiatan = container/atribut
Jadwal = source of truth operasional
```

Participant tidak menunggu tombol "Jalankan Kegiatan".

Availability:

```text
Mulai tercapai
+ belum melewati Batas Mulai untuk START baru
+ access_state = BUKA
+ Preparation READY
+ gate participant lain lulus
→ START otomatis tersedia
```

`TAHAN` pada Jadwal adalah kontrol darurat/manual.

Dokumen yang sudah disinkronkan:
- CBT-HERO_DOKUMEN_ACUAN_UTAMA.md
- CBT-HERO_IMPLEMENTATION_SPEC_ROADMAP_FINAL.md
- CBT-HERO_ROUTES_API_FINAL.md
- CBT-HERO_DATABASE_ERD_FINAL.md
- CBT-HERO_UI_STANDARD_FINAL.md

### 1.2 Daftar Ujian participant = agenda hari ini

- hanya Jadwal yang menjadi hak peserta dan tanggal Mulai-nya hari ini;
- sebelum jam Mulai kartu tetap terlihat;
- action utama: **Mulai Ujian**;
- Attempt ACTIVE tetap muncul untuk RESUME setelah pergantian tanggal;
- status Kegiatan bukan gate.

### 1.3 Status Preparation wajib terlihat di daftar Jadwal

Derived UI state:

- DRAFT = Preparation/preflight belum seluruhnya siap;
- READY = target siap dan tinggal menunggu gate waktu/akses.

Operator tidak perlu membuka modal Preparation hanya untuk mengetahui kesiapan.

### 1.4 Dashboard berorientasi Jadwal

Dashboard tidak lagi memakai status Kegiatan DRAFT/BERJALAN sebagai indikator
pelaksanaan. Bagian utama menampilkan Jadwal terbaru + Preparation + state waktu/akses.

### 1.5 Participant submit

Dua browser-confirm lama diganti satu modal final yang menampilkan:

- total soal;
- sudah dijawab;
- belum dijawab;
- ditandai/ragu-ragu.

### 1.6 Renderer Akademik

Keputusan renderer terbaru:

- `&apos;`/entity tampil sebagai karakter normal;
- literal `<br>` menjadi line break;
- tabel rich-content dirender sebagai tabel responsif;
- PG Kompleks diberi hint multi-answer;
- Menjodohkan memakai dua tabel referensi utuh + tabel pasangan/dropdown;
- mobile tetap mempertahankan struktur Menjodohkan; blok referensi boleh horizontal-scroll.

### 1.7 Format angka Indonesia + Manager compact

UI:
- desimal koma;
- ribuan titik;
- input pecahan menerima koma;
- Manager control/header/table dibuat compact untuk memaksimalkan viewport.

### 1.8 Import Peserta staging

Setelah Parse status semantik adalah **PENDING / BELUM DIVALIDASI**, bukan INVALID.
INVALID hanya hasil setelah Validation benar-benar gagal.

---

# 2. Requirement terlewat / implementation gap

## 2.1 Decoupling Kegiatan dari runtime gate

Runtime participant sudah tidak memakai Kegiatan BERJALAN sebagai gate.

Namun implementasi lama masih mempunyai dependency DRAFT pada beberapa operasi
struktural. Yang terkonfirmasi saat audit: `JadwalService` masih memakai status
Kegiatan DRAFT untuk create/edit/delete/structural_editable.

Ini harus direfactor sebelum Akademik PASS supaya lock struktur berbasis dependency
nyata:

- Preparation/dependency;
- first Attempt START;
- Attempt/history;
- finalisasi;

bukan menunggu status Kegiatan yang tidak mempunyai tombol runtime.

Status 2026-09-30: **DIKERJAKAN**. Ditambahkan `ExecutionDependencyService`; Jadwal,
Kegiatan, membership, Ruang, Nomor Peserta, Peserta master, dan Bank mulai dipindah
ke dependency nyata. Tetap harus diverifikasi pada regression.

## 2.2 No Urut Ujian pada slot waktu yang sama — IMPLEMENTED

Requirement baru dari pengujian lapangan belum ada pada schema/API lama.

Konsep yang sedang diusulkan:

- default `urutan_ujian = 1`;
- jika semua Jadwal pada slot sama bernilai 1 → peserta bebas memilih;
- jika ada 1,2,3 → peserta harus menyelesaikan seluruh Jadwal urutan lebih kecil
  yang memang menjadi hak peserta sebelum urutan berikutnya aktif;
- rule harus participant-aware;
- TAHAN tetap override manual.

Implementasi 2026-09-30:
- kolom `jadwal.urutan_ujian` default 1 pada baseline schema;
- upgrade SQL `CBT-HERO_ACADEMIC_FINAL_JADWAL_ORDER_UPGRADE.sql`;
- form dan kolom daftar Jadwal;
- kartu participant menampilkan Urutan;
- discovery participant memakai state `ORDER_LOCKED`;
- START backend mengulang gate secara authoritative;
- Susulan mewarisi urutan MAIN;
- acceptance J17–J20 ditambahkan.

Status: **IMPLEMENTED, BELUM PASS**.

## 2.3 Participant A-/A/A+ — IMPLEMENTED

Acuan Utama dan Roadmap UI Polish mensyaratkan kontrol ukuran teks.
Implementasi 2026-09-30 menambahkan A−/A/A+ pada workspace dan menyimpan pilihan
di localStorage.

Status: **IMPLEMENTED, BELUM PASS MOBILE/REGRESSION**.

## 2.4 Image zoom/Panzoom — IMPLEMENTED

Acuan Exam Shell mensyaratkan image zoom. Implementasi 2026-09-30 memuat Panzoom
lokal dan modal zoom gambar dengan +/−/reset serta pan/wheel.

Status: **IMPLEMENTED, BELUM PASS MOBILE/REGRESSION**.

## 2.5 Audit mobile seluruh tipe soal — code hardening IMPLEMENTED

Scope tetap:
- PG;
- PG Kompleks;
- PG Bertingkat;
- Matching;
- Isian;
- Uraian;
- gambar;
- rumus;
- tabel;
- audio;
- video.

Audit kode 2026-09-30 menemukan dan memperbaiki beberapa gap penting:
- debounce Isian/Uraian sebelumnya dapat dibatalkan saat peserta cepat pindah soal;
- write jawaban cepat sekarang diserialisasi agar revision lokal tidak berlomba;
- pending text di-flush sebelum navigasi, submit, page hide, dan timeout;
- flush dari input yang sudah terjadi sebelum timeout tetap dapat dipersist walau
  workspace sudah input-locked;
- line break rich-content opsi/Matching dipertahankan;
- long content dan tabel dicegah membuat halaman utama overflow;
- cell tabel memakai direction auto;
- audio/video Google Drive dipisahkan sizing-nya agar audio tetap compact dan video
  responsif;
- banner offline mobile tidak lagi menutup fixed navigation;
- touch/input size Matching dan Isian/Uraian diperkuat.

Acceptance khusus dibuat pada:
`docs/CBT-HERO_PARTICIPANT_RENDERER_MOBILE_ACCEPTANCE.md` dengan PRM01–PRM20.

Status: **IMPLEMENTED / BELUM PASS DEVICE REGRESSION**.

## 2.6 Exam Browser proof konkret

Gate `exam_browser_required` sudah dirancang pada Attempt Engine, tetapi integrasi
proof dengan aplikasi Exam Browser nyata masih tercatat sebagai pekerjaan yang harus
dikunci.

Status: **BELUM FINAL**.

## 2.7 Dashboard API pada ROUTES_API tidak sesuai implementasi

Dokumen Routes masih menyebut kontrak seperti:
- `GET /manager/api/dashboard/summary`
- `GET /manager/api/dashboard/active-schedules`
- `GET /manager/api/dashboard/preflight-warnings`

Dashboard implementasi saat ini server-rendered dan endpoint tersebut belum tersedia.

Keputusan perlu dibuat:
- endpoint memang diperlukan → implementasikan; atau
- tidak diperlukan → hapus dari kontrak Routes.

Status: **DOC/API DRIFT**.

---

# 3. Akademik yang belum selesai

## 3.1 Phase 9A — Hasil Ujian

Implementasi: **SUDAH ADA**.

Acceptance: **DEFERRED**, belum PASS.

## 3.2 Rekap Nilai

Requirement:
- matrix Peserta × Mapel;
- official result only;
- export.

Controller/Service/View belum ada.

Status: **BELUM DIKERJAKAN**.

## 3.3 Analisis Soal

Requirement:
- type-aware;
- aggregate;
- stale/rebuild setelah scoring/live edit.

Controller/Service/View belum ada.

Status: **BELUM DIKERJAKAN**.

## 3.4 Export Excel/PDF hasil

Belum ada ReportExportService/flow final sesuai roadmap Phase 9.

Status: **BELUM DIKERJAKAN**.

## 3.5 Public Live Scoring

Sidebar masih unavailable.

Requirement tetap:
- satu Jadwal per display;
- URL public random;
- 5 kolom exact;
- click score;
- competition rank;
- full scroll cycle baru refresh.

Status: **BELUM DIKERJAKAN**.

## 3.6 Participant finish page

Sudah mulai diimplementasikan:
- Nilai Pilihan Ganda;
- Nilai Isian & Uraian / DALAM PROSES.

Tetap harus diuji bersama scoring/result visibility.

Status: **IMPLEMENTED, BELUM PASS**.

---

# 4. System yang belum lengkap

Acuan Utama mensyaratkan Settings minimal:
- nama instansi;
- logo;
- alamat;
- URL CBT;
- default Tahun Pelajaran;
- default Semester;
- timezone;
- identitas kartu;
- footer/copyright;
- instruksi default peserta;
- retensi log;
- encryption key secret.

Implementasi saat audit baru jelas mencakup:
- default Tahun Pelajaran/Semester;
- identitas kartu/logo/URL.

Masih belum lengkap:
- timezone UI;
- footer/copyright;
- instruksi default participant;
- log retention UI;
- system log viewer;
- backup DB;
- restore DB;
- backup media;
- restore media;
- pengosongan data.

Status: **PHASE SYSTEM BELUM SELESAI**.

---

# 5. Psikologis

Instrumen/Hasil Psikologis masih unavailable.

Sesuai keputusan proyek:
> Psikologis **tidak dikerjakan sebelum Akademik FIX & PASS**.

Status: **SENGAJA DITUNDA**.

---

# 6. Performance & production hardening

Belum dilakukan final:
- regression terpadu seluruh Akademik;
- 500/1000/1500/2000 concurrent;
- stress 2500;
- query EXPLAIN final;
- adaptive load protection final;
- backup/restore regression;
- security regression penuh;
- cross-viewport final;
- accessibility/focus pass.

Status: **BELUM DIKERJAKAN / CHECKPOINT AKHIR**.

---

# 7. Acceptance yang masih DEFERRED

Dokumen `CBT-HERO_MASTER_UJIAN_AKADEMIK_DEFERRED_ACCEPTANCE.md` tetap benar:
DEFERRED **bukan PASS**.

Minimal yang harus dijalankan sebelum Psikologis:
- Phase 4D2;
- Phase 5A/5B/5C;
- Phase 6;
- Phase 7;
- Phase 8;
- Phase 9 Akademik;
- regression Import Peserta → Ujian → Hasil.

---

# 8. Dokumen yang perlu dianggap historical acceptance

Acceptance per-phase menyimpan baseline saat fase dibuat. Bila UX berubah tanpa
mengubah engine (contoh double-confirm submit menjadi satu modal summary), dokumen
acceptance harus disinkronkan pada baris terkait tetapi histori fase tetap dipertahankan.

`CBT-HERO_TEST_ACCEPTANCE_FINAL.md` saat ini lebih banyak memuat acceptance awal
Phase 1/2. Untuk acceptance Akademik terbaru, gunakan dokumen Phase 4–9 + Deferred
Acceptance sebagai sumber utama sampai file Test Acceptance Final dikonsolidasikan.

---

# 9. Urutan pengerjaan yang direkomendasikan setelah audit

```text
A. Selesaikan Participant Renderer + Mobile
B. Verifikasi regression dependency-lock baru
C. Verifikasi regression No Urut Ujian
D. Selesaikan Phase 9:
   Hasil → Rekap → Analisis → Export → Live Scoring
E. Rapikan Dashboard/Monitoring/Manager final
F. Jalankan regression Akademik terpadu
G. Perbaiki semua temuan sampai PASS
H. Baru buka Phase Psikologis
```

---

# 10. Catatan penting

Tidak ada requirement baru yang boleh dianggap selesai hanya karena dokumen sudah
ditulis. Status `IMPLEMENTED`, `DEFERRED`, dan `PASS` harus tetap dibedakan.
